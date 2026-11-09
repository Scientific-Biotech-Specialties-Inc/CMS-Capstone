<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FileX, FileType2, UploadCloud, X, AlertCircle, Download } from 'lucide-vue-next'
import type { UploadedDoc } from '@/types/contract'
import { useAuth } from '@/composables/useAuth'

const props = defineProps<{
  docs:      UploadedDoc[]
  isEditing: boolean
  snapshotVersion?: number | null
}>()

const emit = defineEmits<{ 'update:docs': [v: UploadedDoc[]] }>()

const { state: authState } = useAuth()
const route = useRoute()
const router = useRouter()
const contractId = route.params.id as string

const fileInput = ref<HTMLInputElement | null>(null)
const dragOver  = ref(false)
const fileError = ref('')
const previousDrafts = computed(() => props.docs.filter(doc => doc.category === 'draft' && !doc.isCurrentDraft))
const currentDocuments = computed(() => props.docs.filter(doc => doc.category !== 'draft' || doc.isCurrentDraft))

function viewDocument(doc: UploadedDoc) {
  if (doc.uploadStatus && doc.uploadStatus !== 'success') return

  if (doc.id) {
    let basePath = ''
    if (route.path.startsWith('/admin')) basePath = '/admin'
    else if (route.path.startsWith('/manager')) basePath = '/manager'
    else basePath = '/sales'

    const query = props.snapshotVersion ? `?snapshotVersion=${props.snapshotVersion}` : ''
    router.push(`${basePath}/contracts/${contractId}/documents/${doc.id}${query}`)
  }
}

const MAX_BYTES = 10 * 1024 * 1024

function detectType(f: File): 'pdf' | 'docx' | null {
  const name = f.name.toLowerCase()
  if (f.type === 'application/pdf' || name.endsWith('.pdf')) return 'pdf'
  if (
    f.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
    name.endsWith('.docx')
  ) return 'docx'
  return null
}

function validate(f: File): string {
  if (!detectType(f))     return 'Only PDF or DOCX files are accepted.'
  if (f.size > MAX_BYTES) return `File exceeds the 10 MB limit (${(f.size / 1024 / 1024).toFixed(2)} MB).`
  if (props.docs.some(d => d.name === f.name && d.size === f.size)) return 'This file has already been added.'
  return ''
}

async function uploadFile(doc: UploadedDoc, index: number) {
  if (!doc.file) return

  const updatedValue = [...props.docs]
  updatedValue[index] = {
    ...doc,
    uploadStatus: 'uploading'
  }
  emit('update:docs', updatedValue)

  const scanTimer = setTimeout(() => {
    if (props.docs[index] && props.docs[index].uploadStatus === 'uploading') {
      const scanningValue = [...props.docs]
      scanningValue[index] = {
        ...scanningValue[index],
        uploadStatus: 'scanning'
      }
      emit('update:docs', scanningValue)
    }
  }, 450)

  try {
    const formData = new FormData()
    formData.append('file', doc.file)
    if (contractId) {
      formData.append('contract_id', contractId)
    }

    const res = await fetch(`${import.meta.env.VITE_CONTRACT_API_URL}/documents/upload`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
      body: formData,
    })

    clearTimeout(scanTimer)
    const data = await res.json()

    const finalValue = [...props.docs]
    if (res.ok) {
      finalValue[index] = {
        ...finalValue[index],
        id: data.data.document_id,
        uploadStatus: 'success',
        scanWarning: data.scan_warning
      }
    } else {
      const msg = data.errors?.file?.[0] || data.message || 'Upload failed.'
      finalValue[index] = {
        ...finalValue[index],
        uploadStatus: 'error',
        errorMessage: msg
      }
    }
    emit('update:docs', finalValue)
  } catch (err) {
    clearTimeout(scanTimer)
    const finalValue = [...props.docs]
    finalValue[index] = {
      ...finalValue[index],
      uploadStatus: 'error',
      errorMessage: 'Network error. Failed to scan file.'
    }
    emit('update:docs', finalValue)
  }
}

function addFile(f: File) {
  const err = validate(f)
  if (err) { fileError.value = err; return }
  fileError.value = ''
  const type       = detectType(f)!
  const previewUrl = type === 'pdf' ? URL.createObjectURL(f) : ''
  const newDoc: UploadedDoc = {
    file: f,
    name: f.name,
    size: f.size,
    type,
    previewUrl,
    uploadStatus: 'uploading'
  }
  const newList = [...props.docs, newDoc]
  emit('update:docs', newList)

  const index = newList.length - 1
  uploadFile(newDoc, index)
}

function onFileInput(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  Array.from(files).forEach(addFile)
  if (fileInput.value) fileInput.value.value = ''
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  const files = e.dataTransfer?.files
  if (!files) return
  Array.from(files).forEach(addFile)
}

function removeDoc(index: number) {
  const doc = props.docs[index]
  if (doc?.previewUrl) URL.revokeObjectURL(doc.previewUrl)
  emit('update:docs', props.docs.filter((_, i) => i !== index))
}

function fmtSize(bytes: number) {
  return (bytes / 1_048_576).toFixed(2) + ' MB'
}
</script>

<template>
  <div class="bg-white rounded-xl border border-black/[0.08] p-4 sm:p-6 shadow-sm">

    <!-- Section header -->
    <div class="flex items-center gap-3 mb-6">
      <h3 class="text-[10px] font-bold text-brand-navy/60 uppercase tracking-widest">Documents</h3>
      <span class="px-2 py-0.5 bg-brand-blue/10 text-[10px] font-bold rounded-full text-brand-blue tabular-nums">{{ docs.length }}</span>
    </div>

    <!-- Empty state (view mode) -->
    <div v-if="!isEditing && docs.length === 0"
      class="flex flex-col items-center gap-2 py-10 text-black/25">
      <FileX class="w-8 h-8" />
      <p class="text-sm font-medium">No documents attached</p>
    </div>

    <!-- View mode document cards -->
    <div v-else-if="!isEditing" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="doc in currentDocuments" :key="doc.id" class="flex min-w-0 flex-col gap-2">
        <p v-if="doc.isPrimary && doc.signerName" class="text-sm text-slate-700">
          <strong>Signed by:</strong> {{ doc.signerName }}
        </p>
        <button type="button" @click="viewDocument(doc)"
          class="group border border-black/10 rounded-lg p-4 flex w-full items-center gap-4 text-left hover:border-brand-blue transition-all cursor-pointer bg-white shadow-sm">
        <div class="w-12 h-12 bg-black/[0.04] flex items-center justify-center rounded-lg text-black/40 group-hover:bg-brand-blue group-hover:text-white transition-colors shrink-0">
          <FileType2 class="w-6 h-6" />
        </div>
        <div class="flex-1 overflow-hidden min-w-0">
          <p class="text-[15px] text-black font-semibold truncate">{{ doc.name }}</p>
          <p class="text-xs text-black/50">{{ fmtSize(doc.size) }} • {{ doc.type.toUpperCase() }}</p>
          <p v-if="doc.isPrimary" class="mt-2 text-xs font-semibold text-emerald-700">Primary Signed PDF</p>
          <p v-else-if="doc.isCurrentDraft" class="mt-2 text-xs font-semibold text-brand-navy">Current Draft</p>
        </div>
        <Download class="w-5 h-5 text-black/30 group-hover:text-brand-navy shrink-0" />
        </button>
      </div>
    </div>

    <!-- Edit mode: Cards + upload zone -->
    <div v-else class="flex flex-col gap-3">
      <div class="flex flex-wrap gap-3">
        <!-- Document cards -->
        <div v-for="(doc, i) in docs" :key="doc.name + doc.size"
          class="relative w-36 rounded-lg border border-black/8 overflow-hidden shadow-sm bg-white flex flex-col group">

          <!-- Remove button -->
          <button v-if="!doc.category" type="button" :aria-label="`Remove ${doc.name}`" @click="removeDoc(i)"
            class="absolute top-1.5 right-1.5 z-30 w-5 h-5 rounded-full bg-black/50 hover:bg-red-500 text-white flex items-center justify-center transition">
            <X class="w-3 h-3" />
          </button>

          <!-- PDF preview -->
          <template v-if="doc.type === 'pdf'">
            <div @click="viewDocument(doc)" class="w-full h-44 bg-red-50 flex flex-col items-center justify-center gap-2 cursor-pointer hover:bg-red-100 transition-all border-b border-black/5">
              <FileType2 class="w-10 h-10 text-red-400" />
              <span class="text-[10px] font-bold text-red-500 uppercase tracking-wider">PDF</span>
            </div>
          </template>

          <!-- DOCX placeholder -->
          <template v-else>
            <div @click="viewDocument(doc)" class="w-full h-44 bg-blue-50 flex flex-col items-center justify-center gap-2 cursor-pointer hover:bg-blue-100 transition-all">
              <FileType2 class="w-10 h-10 text-blue-400" />
              <span class="text-[10px] font-bold text-blue-400 uppercase tracking-wider">DOCX</span>
            </div>
          </template>

          <!-- Filename strip -->
          <div class="px-2 py-1.5 border-t border-black/6 bg-white z-10">
            <p class="text-[10px] font-medium text-black/60 truncate" :title="doc.name">{{ doc.name }}</p>
            <p class="text-[9px] text-black/30 tabular-nums">{{ fmtSize(doc.size) }}</p>
          </div>

          <!-- Status Overlay -->
          <div v-if="doc.uploadStatus && doc.uploadStatus !== 'success'"
            class="absolute inset-0 h-44 z-20 flex flex-col items-center justify-center gap-2 px-2.5 text-center transition-all duration-300"
            :class="{
              'bg-black/70 text-white': doc.uploadStatus === 'uploading',
              'bg-[#fffbeb] border border-[#fef3c7] text-[#92400e]': doc.uploadStatus === 'scanning',
              'bg-red-500/90 text-white': doc.uploadStatus === 'error'
            }">
            <template v-if="doc.uploadStatus === 'uploading'">
              <div class="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent"></div>
              <span class="text-[10px] font-semibold">Uploading...</span>
            </template>
            
            <template v-else-if="doc.uploadStatus === 'scanning'">
              <div class="animate-spin rounded-full h-5 w-5 border-2 border-[#d97706] border-t-transparent"></div>
              <span class="text-[10px] font-semibold text-[#b45309]">Scanning file...</span>
            </template>

            <template v-else-if="doc.uploadStatus === 'error'">
              <AlertCircle class="w-5 h-5 text-white animate-pulse" />
              <span class="text-[9px] font-bold uppercase tracking-wide">Blocked</span>
              <span class="text-[8px] leading-tight max-h-16 overflow-y-auto px-1">{{ doc.errorMessage || 'Malware detected!' }}</span>
            </template>
          </div>

          <!-- Success overlay/badge -->
          <div v-if="doc.uploadStatus === 'success'"
            class="absolute top-1.5 left-1.5 z-20 flex items-center gap-1 rounded bg-brand-blue px-1.5 py-0.5 text-[8px] font-bold text-white uppercase tracking-wider shadow-sm select-none"
            :title="doc.scanWarning || 'Malware scan completed successfully.'">
            <span>{{ doc.scanWarning ? 'Scan Skipped' : 'Malware Free' }}</span>
          </div>

        </div>

        <!-- Upload drop zone -->
        <div class="w-36 rounded-lg border-2 border-dashed flex flex-col items-center justify-center gap-2 cursor-pointer transition-colors select-none"
          style="min-height: 11rem;"
          :class="dragOver ? 'border-brand-blue bg-brand-blue/5' : 'border-black/15 hover:border-brand-blue/50 hover:bg-black/2'"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
          @click="fileInput?.click()">
          <UploadCloud class="w-6 h-6 text-black/25" />
          <span class="text-[10px] font-semibold text-black/35 text-center px-2 leading-tight">Add files</span>
          <input ref="fileInput" type="file" accept=".pdf,.docx" multiple class="hidden" @change="onFileInput" />
        </div>
      </div>

      <!-- File error -->
      <div v-if="fileError" class="mt-1 flex items-center gap-2 text-red-500">
        <AlertCircle class="w-3.5 h-3.5 shrink-0" />
        <p class="text-xs">{{ fileError }}</p>
      </div>
    </div>

    <details v-if="!isEditing && previousDrafts.length" class="mt-4 border-t border-slate-200 pt-4">
      <summary class="cursor-pointer text-sm font-semibold text-brand-navy">Previous Drafts</summary>
      <ul class="mt-2 space-y-2">
        <li v-for="doc in previousDrafts" :key="doc.id">
          <button type="button" class="text-sm text-brand-navy underline" @click="viewDocument(doc)">{{ doc.name }}</button>
        </li>
      </ul>
    </details>
  </div>
</template>
