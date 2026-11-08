<script setup lang="ts">
import { ref, onUnmounted } from 'vue'
import { UploadCloud, FileType2, X, AlertCircle } from 'lucide-vue-next'
import type { UploadedDoc } from '@/types/contract'
import { useDocumentUpload } from '@/composables/useDocumentUpload'

export type { UploadedDoc }

const props = defineProps<{ 
  modelValue: UploadedDoc[]
  onPreview?: (doc: UploadedDoc) => void
  hideSuccessBadge?: boolean
}>()
const emit  = defineEmits<{ 'update:modelValue': [v: UploadedDoc[]] }>()

const { detectType, validate: validateDoc, uploadFile: uploadDoc } = useDocumentUpload()
const fileInput = ref<HTMLInputElement | null>(null)
const dragOver  = ref(false)
const fileError = ref('')

function validate(f: File): string {
  return validateDoc(f, props.modelValue)
}

async function uploadFile(doc: UploadedDoc, index: number) {
  await uploadDoc(doc, (patch) => {
    const updatedValue = [...props.modelValue]
    if (!updatedValue[index]) return
    updatedValue[index] = { ...updatedValue[index], ...patch }
    emit('update:modelValue', updatedValue)
  })
}

function selectFile(f: File) {
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
  const newList = [...props.modelValue, newDoc]
  emit('update:modelValue', newList)

  const index = newList.length - 1
  uploadFile(newDoc, index)
}

function onFileInput(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  Array.from(files).forEach(selectFile)
  if (fileInput.value) fileInput.value.value = ''
}

let dragCounter = 0
function onDragEnter() {
  dragCounter++
  dragOver.value = true
}

function onDragLeave() {
  dragCounter--
  if (dragCounter <= 0) {
    dragCounter = 0
    dragOver.value = false
  }
}

function onDrop(e: DragEvent) {
  dragCounter = 0
  dragOver.value = false
  const files = e.dataTransfer?.files
  if (!files) return
  Array.from(files).forEach(selectFile)
}

function onZoneClick(e: MouseEvent) {
  if ((e.target as HTMLElement).closest('.file-card-item')) return
  fileInput.value?.click()
}

function removeDoc(index: number) {
  const doc = props.modelValue[index]
  if (doc?.previewUrl) URL.revokeObjectURL(doc.previewUrl)
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}

onUnmounted(() => {
  props.modelValue.forEach(d => { if (d.previewUrl) URL.revokeObjectURL(d.previewUrl) })
})

const fileSizeMB = (bytes: number) => (bytes / 1024 / 1024).toFixed(2)
</script>

<template>
  <div class="space-y-3">

    <!-- File input (hidden) -->
    <input
      ref="fileInput"
      type="file"
      multiple
      accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
      class="hidden"
      @change="onFileInput"
    />

    <!-- Empty state: Full-width broken line dropzone -->
    <div
      v-if="modelValue.length === 0"
      @click="fileInput?.click()"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
      class="w-full min-h-[190px] rounded-2xl border-2 border-dashed transition-all duration-300 select-none cursor-pointer flex flex-col items-center justify-center p-6 group relative overflow-hidden"
      :class="dragOver
        ? 'border-brand-blue bg-brand-blue/[0.04] shadow-md shadow-brand-blue/5'
        : 'border-slate-300 bg-white hover:border-brand-blue hover:bg-brand-blue/[0.02]'"
    >
      <!-- Center Icon Badge (centered by default, moves up slightly on hover) -->
      <div
        class="w-12 h-12 rounded-full bg-white border border-black/8 shadow-sm flex items-center justify-center transition-all duration-300 group-hover:-translate-y-3 group-hover:scale-105 group-hover:border-brand-blue/30 group-hover:shadow-md"
        :class="{ '-translate-y-3 scale-105 border-brand-blue/30 shadow-md': dragOver }"
      >
        <UploadCloud class="w-5 h-5 text-brand-blue transition-transform duration-300 group-hover:-translate-y-0.5" />
      </div>

      <!-- Text in the middle: appears when hovered or dragged over -->
      <div
        class="absolute bottom-6 inset-x-4 text-center transition-all duration-300 transform pointer-events-none group-hover:pointer-events-auto"
        :class="dragOver
          ? 'opacity-100 translate-y-0'
          : 'opacity-0 translate-y-2 group-hover:opacity-100 group-hover:translate-y-0'"
      >
        <p class="text-sm font-semibold text-brand-blue leading-snug">
          Click to upload
        </p>
        <p class="text-xs text-slate-600 font-medium mt-0.5 leading-snug">
          or drag &amp; drop
        </p>
        <p class="text-[10px] text-slate-400 mt-1.5 font-medium">
          PDF or DOCX · Max 10 MB per file
        </p>
      </div>
    </div>

    <!-- Files-present state: Files live inside the full-width broken line -->
    <div
      v-else
      @click="onZoneClick"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
      class="w-full min-h-[220px] rounded-2xl border-2 border-dashed transition-all duration-300 select-none p-4 relative group/zone cursor-pointer"
      :class="dragOver
        ? 'border-brand-blue bg-brand-blue/[0.04]'
        : 'border-slate-300/80 bg-white hover:border-brand-blue/70 hover:bg-brand-blue/[0.01]'"
    >
      <!-- Uploaded Files Grid -->
      <div class="flex flex-wrap items-center gap-3.5 relative z-10">

        <!-- Existing file cards -->
        <div v-for="(doc, i) in modelValue" :key="i"
          class="file-card-item relative w-36 rounded-lg border border-black/8 bg-white shadow-sm overflow-hidden flex flex-col group/card hover:shadow-md transition-shadow cursor-default">

          <!-- PDF thumbnail -->
          <div v-if="doc.type === 'pdf'"
            class="flex flex-col items-center justify-center gap-2 h-44 bg-red-50/50">
            <FileType2 class="w-9 h-9 text-red-500" />
            <span class="text-[10px] font-bold text-red-600 uppercase tracking-widest">PDF</span>
          </div>

          <!-- DOCX card body -->
          <div v-else
            class="flex flex-col items-center justify-center gap-2 h-44 bg-blue-50/50">
            <FileType2 class="w-9 h-9 text-blue-500" />
            <span class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">DOCX</span>
          </div>

          <!-- Filename chip -->
          <div class="px-2 py-1.5 border-t border-black/6 bg-white z-10">
            <p class="text-[10px] font-medium text-black truncate leading-snug">{{ doc.name }}</p>
            <p class="text-[9px] text-black/35 mt-0.5">{{ fileSizeMB(doc.size) }} MB</p>
          </div>

          <!-- Remove button -->
          <button @click.stop="removeDoc(i)"
            class="absolute top-1.5 right-1.5 z-30 w-5 h-5 rounded-full bg-black/50 hover:bg-black/70 flex items-center justify-center text-white transition">
            <X class="w-3 h-3" />
          </button>

          <!-- Status Overlay -->
          <div v-if="doc.uploadStatus && doc.uploadStatus !== 'success'"
            class="absolute inset-0 h-44 z-20 flex flex-col items-center justify-center gap-2 px-2.5 text-center transition-all duration-300"
            :class="{
              'bg-black/70 text-white': doc.uploadStatus === 'uploading',
              'bg-[#FFFbeb] border border-[#fef3c7] text-[#92400e]': doc.uploadStatus === 'scanning',
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
          <div v-if="doc.uploadStatus === 'success' && !hideSuccessBadge"
            class="absolute top-1.5 left-1.5 z-20 flex items-center gap-1 rounded bg-brand-blue px-1.5 py-0.5 text-[8px] font-bold text-white uppercase tracking-wider shadow-sm select-none pointer-events-none"
            :title="doc.scanWarning || 'Malware scan completed successfully.'">
            <span>{{ doc.scanWarning ? 'Scan Skipped' : 'Malware Free' }}</span>
          </div>

          <!-- Preview Hover Overlay (only for successful uploads if onPreview exists) -->
          <div v-if="doc.uploadStatus === 'success' && onPreview"
            @click.stop="onPreview(doc)"
            class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-black/0 hover:bg-black/40 opacity-0 hover:opacity-100 transition-all duration-200 cursor-pointer text-white">
            <div class="p-2 rounded-full bg-white/20 backdrop-blur-sm mb-1 transform translate-y-2 group-hover/card:translate-y-0 transition-all duration-300">
              <svg class="w-5 h-5 text-white drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider drop-shadow-md transform translate-y-2 group-hover/card:translate-y-0 transition-all duration-300 delay-75">Preview</span>
          </div>

        </div>

        <!-- "Add more" tile alongside existing cards -->
        <div
          @click.stop="fileInput?.click()"
          class="relative w-36 h-[218px] rounded-lg border-2 border-dashed border-slate-300 hover:border-brand-blue bg-slate-50/50 hover:bg-brand-blue/[0.04] transition-all duration-200 cursor-pointer flex flex-col items-center justify-center p-3 text-center group/add z-20"
        >
          <div class="w-10 h-10 rounded-full bg-white border border-black/8 shadow-sm flex items-center justify-center mb-2 group-hover/add:scale-105 group-hover/add:border-brand-blue/30 transition-all">
            <UploadCloud class="w-4.5 h-4.5 text-brand-blue" />
          </div>
          <p class="text-xs font-semibold text-brand-blue">Add more</p>
          <p class="text-[10px] text-slate-500 mt-0.5">or drop files</p>
        </div>

      </div>

      <!-- Slight visible blue container covering the whole broken line when uploading again -->
      <div
        v-if="dragOver"
        class="absolute inset-0 rounded-2xl bg-brand-blue/10 backdrop-blur-[1px] border-2 border-dashed border-brand-blue z-30 flex flex-col items-center justify-center p-6 text-center transition-all duration-200 pointer-events-none"
      >
        <div class="w-12 h-12 rounded-full bg-white border border-brand-blue/30 shadow-md flex items-center justify-center mb-2.5 scale-105">
          <UploadCloud class="w-5 h-5 text-brand-blue" />
        </div>
        <p class="text-sm font-bold text-brand-blue leading-snug">
          Drop files here to upload
        </p>
        <p class="text-xs text-slate-600 font-medium mt-0.5">
          Release to add to your attached documents
        </p>
      </div>

    </div>

    <!-- Validation error -->
    <div v-if="fileError"
      class="flex items-start gap-2.5 rounded-lg bg-red-50 border border-red-200 px-3.5 py-2.5">
      <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
      <p class="text-xs font-medium text-red-600">{{ fileError }}</p>
    </div>

  </div>
</template>

