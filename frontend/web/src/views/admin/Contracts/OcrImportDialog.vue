<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { FileText, FileImage, UploadCloud, X, AlertCircle, Loader2 } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { useOcr } from '@/composables/useOcr'
import { useToast } from '@/composables/useToast'
import type { OcrExtractedData } from '@/types/ocr'

const props = defineProps<{
  open: boolean
  candidatePartners: string[]
}>()

const emit = defineEmits<{
  'update:open': [v: boolean]
  'success': [data: OcrExtractedData, file: File]
}>()

const { error: ocrError, loading: ocrLoading, extractFromFile } = useOcr()
const { success: showSuccessToast, error: showErrorToast } = useToast()

const MAX_BYTES = 10 * 1024 * 1024 // 10 MB

type Stage = 'idle' | 'selected' | 'scanning' | 'done'

const stage = ref<Stage>('idle')
const file = ref<File | null>(null)
const localError = ref('')
const dragOver = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

// Step-by-step progress message helper for better UX during OCR
const progressMessage = ref('Reading file...')
let progressTimer: number | null = null

const fileSizeMB = computed(() =>
  file.value ? (file.value.size / 1024 / 1024).toFixed(2) : '0'
)

const fileExtension = computed(() => {
  if (!file.value) return ''
  return file.value.name.split('.').pop()?.toLowerCase() || ''
})

const isImage = computed(() => {
  return ['png', 'jpg', 'jpeg', 'tiff', 'bmp', 'gif'].includes(fileExtension.value)
})

function detectType(f: File): 'pdf' | 'docx' | 'image' | null {
  const name = f.name.toLowerCase()
  if (f.type === 'application/pdf' || name.endsWith('.pdf')) return 'pdf'
  if (
    f.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
    name.endsWith('.docx')
  ) return 'docx'
  if (f.type.startsWith('image/') || /\.(png|jpe?g|tiff|bmp|gif)$/.test(name)) return 'image'
  return null
}

function validate(f: File): string {
  if (!detectType(f)) return 'Only PDF, DOCX, or Image files (PNG, JPG) are accepted.'
  if (f.size > MAX_BYTES) return `File exceeds the 10 MB limit (${(f.size / 1024 / 1024).toFixed(2)} MB).`
  return ''
}

function selectFile(f: File) {
  const err = validate(f)
  if (err) {
    localError.value = err
    file.value = null
    stage.value = 'idle'
    return
  }
  localError.value = ''
  file.value = f
  stage.value = 'selected'
}

function onFileInput(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (f) selectFile(f)
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  const f = e.dataTransfer?.files?.[0]
  if (f) selectFile(f)
}

function clearFile() {
  file.value = null
  localError.value = ''
  stage.value = 'idle'
  stopProgressSimulation()
  if (fileInput.value) fileInput.value.value = ''
}

function handleClose() {
  if (ocrLoading.value) return // prevent close while scanning
  clearFile()
  emit('update:open', false)
}

function startProgressSimulation() {
  progressMessage.value = 'Reading document bytes...'
  let count = 0
  
  progressTimer = window.setInterval(() => {
    count++
    if (count === 1) {
      progressMessage.value = 'Running Tesseract OCR text extraction...'
    } else if (count === 3) {
      progressMessage.value = 'Analyzing layout & segmenting sections...'
    } else if (count === 5) {
      progressMessage.value = 'Asking Gemini AI to structure required fields...'
    } else if (count === 8) {
      progressMessage.value = 'Disambiguating company names and dates...'
    } else if (count === 12) {
      progressMessage.value = 'Finalizing response payload...'
    }
  }, 1800)
}

function stopProgressSimulation() {
  if (progressTimer) {
    clearInterval(progressTimer)
    progressTimer = null
  }
}

onUnmounted(() => {
  stopProgressSimulation()
})

async function handleExtract() {
  if (!file.value) return
  const scannedFile = file.value
  stage.value = 'scanning'
  startProgressSimulation()

  try {
    const result = await extractFromFile(scannedFile, props.candidatePartners)
    stopProgressSimulation()

    if (result) {
      stage.value = 'done'
      showSuccessToast('OCR scan completed', `Successfully extracted data with a confidence score of ${result.confidence_score}%.`)
      
      // Short delay before closing and populating form to show success state
      setTimeout(() => {
        emit('success', result, scannedFile)
        handleClose()
      }, 800)
    } else {
      stage.value = 'selected'
      showErrorToast('OCR extraction failed', ocrError.value || 'Could not extract data.')
    }
  } catch {
    stage.value = 'selected'
    stopProgressSimulation()
    showErrorToast('Network error', 'Failed to reach the OCR service.')
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose">
    <DialogContent class="max-w-md p-0 gap-0 overflow-hidden font-poppins" @pointer-down-outside="handleClose">

      <!-- Header -->
      <DialogHeader class="px-5 pt-5 pb-4 border-b border-black/6">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
            <UploadCloud class="w-4.5 h-4.5 text-brand-navy" />
          </div>
          <div>
            <DialogTitle class="text-sm font-bold text-black leading-snug">Autofill with OCR</DialogTitle>
            <DialogDescription class="text-xs text-black/40 mt-0.5">Upload a contract PDF, DOCX, or Image (PNG/JPG) to extract fields.</DialogDescription>
          </div>
        </div>
      </DialogHeader>

      <!-- Body -->
      <div class="px-5 py-5 space-y-4">

        <!-- Drop zone -->
        <div v-if="stage === 'idle'"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
          @click="fileInput?.click()"
          class="relative flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-10 cursor-pointer transition-colors select-none"
          :class="dragOver
            ? 'border-brand-blue bg-brand-blue/4'
            : 'border-black/12 bg-black/1.5 hover:border-brand-blue/50 hover:bg-brand-blue/2'">

          <input
            ref="fileInput"
            type="file"
            accept=".pdf,.docx,.png,.jpg,.jpeg,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/*"
            class="hidden"
            @change="onFileInput"
          />

          <div class="w-12 h-12 rounded-full bg-white border border-black/8 shadow-sm flex items-center justify-center">
            <UploadCloud class="w-5 h-5 text-black/35" />
          </div>

          <div class="text-center">
            <p class="text-sm font-medium text-black/70">
              <span class="text-brand-blue font-semibold">Click to upload</span> or drag & drop
            </p>
            <p class="text-xs text-black/35 mt-1">PDF, DOCX, PNG, or JPG · Max 10 MB</p>
          </div>
        </div>

        <!-- File validation error -->
        <div v-if="localError" class="flex items-start gap-2.5 rounded-lg bg-red-50 border border-red-200 px-3.5 py-3">
          <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
          <p class="text-xs font-medium text-red-600">{{ localError }}</p>
        </div>

        <!-- Selected file card -->
        <div v-if="file && stage !== 'idle'"
          class="flex items-center gap-3 rounded-lg border border-black/8 bg-white px-3.5 py-3 shadow-sm">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 border"
            :class="isImage ? 'bg-blue-50 border-blue-100' : 'bg-red-50 border-red-100'">
            <FileImage v-if="isImage" class="w-4.5 h-4.5 text-blue-500" />
            <FileText v-else class="w-4.5 h-4.5 text-red-500" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-black truncate">{{ file.name }}</p>
            <p class="text-xs text-black/40 mt-0.5">{{ fileSizeMB }} MB · {{ fileExtension.toUpperCase() }} File</p>
          </div>
          <button v-if="stage === 'selected'"
            @click.stop="clearFile"
            class="w-7 h-7 rounded-md flex items-center justify-center text-black/30 hover:text-black hover:bg-black/5 transition shrink-0">
            <X class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Extracting/Scanning state -->
        <div v-if="stage === 'scanning'"
          class="flex flex-col gap-2 rounded-lg bg-brand-navy/4 border border-brand-navy/10 px-4 py-3.5">
          <div class="flex items-center gap-3">
            <Loader2 class="w-4 h-4 text-brand-navy animate-spin shrink-0" />
            <p class="text-xs font-semibold text-brand-navy">AI OCR Pipeline Active</p>
          </div>
          <p class="text-[11px] text-black/50 ml-7 animate-pulse">{{ progressMessage }}</p>
        </div>

        <!-- API Errors -->
        <div v-if="ocrError && stage !== 'scanning'" class="flex items-start gap-2.5 rounded-lg bg-red-50 border border-red-200 px-3.5 py-3">
          <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
          <p class="text-xs font-medium text-red-600">{{ ocrError }}</p>
        </div>

      </div>

      <!-- Footer -->
      <div class="px-5 pb-5 flex items-center justify-end gap-3 border-t border-black/5 pt-4">
        <Button variant="outline" @click="handleClose" :disabled="stage === 'scanning'"
          class="h-9 px-4 text-sm border-black/15 text-black/60 hover:text-black">
          Cancel
        </Button>
        <Button
          @click="handleExtract"
          :disabled="stage !== 'selected'"
          class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
          {{ stage === 'scanning' ? 'Extracting…' : 'Extract & Autofill' }}
        </Button>
      </div>

    </DialogContent>
  </Dialog>
</template>
