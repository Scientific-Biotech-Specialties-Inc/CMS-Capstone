<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileText, UploadCloud, X, AlertCircle, CheckCircle2, Loader2 } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [v: boolean] }>()

const MAX_BYTES = 10 * 1024 * 1024 // 10 MB

type Stage = 'idle' | 'selected' | 'extracting' | 'done'

const stage     = ref<Stage>('idle')
const file      = ref<File | null>(null)
const fileError = ref('')
const dragOver  = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const fileSizeMB = computed(() =>
  file.value ? (file.value.size / 1024 / 1024).toFixed(2) : '0'
)

function validate(f: File): string {
  const isPdf = f.type === 'application/pdf' || f.name.toLowerCase().endsWith('.pdf')
  if (!isPdf)          return 'Only PDF files are accepted.'
  if (f.size > MAX_BYTES) return `File exceeds the 10 MB limit (${(f.size / 1024 / 1024).toFixed(2)} MB).`
  return ''
}

function selectFile(f: File) {
  const err = validate(f)
  if (err) {
    fileError.value = err
    file.value      = null
    stage.value     = 'idle'
    return
  }
  fileError.value = ''
  file.value      = f
  stage.value     = 'selected'
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
  file.value      = null
  fileError.value = ''
  stage.value     = 'idle'
  if (fileInput.value) fileInput.value.value = ''
}

function handleClose() {
  clearFile()
  stage.value = 'idle'
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose">
    <DialogContent class="max-w-md p-0 gap-0 overflow-hidden" @pointer-down-outside="handleClose">

      <!-- Header -->
      <DialogHeader class="px-5 pt-5 pb-4 border-b border-black/6">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
            <UploadCloud class="w-4.5 h-4.5 text-brand-navy" />
          </div>
          <div>
            <DialogTitle class="text-sm font-bold text-black leading-snug">Autofill with OCR</DialogTitle>
            <DialogDescription class="text-xs text-black/40 mt-0.5">Upload a contract PDF to extract its data automatically.</DialogDescription>
          </div>
        </div>
      </DialogHeader>

      <!-- Body -->
      <div class="px-5 py-5 space-y-4">

        <!-- Drop zone -->
        <div v-if="stage === 'idle' || stage === 'selected'"
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
            accept=".pdf,application/pdf"
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
            <p class="text-xs text-black/35 mt-1">PDF only · Max 10 MB</p>
          </div>
        </div>

        <!-- File error -->
        <div v-if="fileError" class="flex items-start gap-2.5 rounded-lg bg-red-50 border border-red-200 px-3.5 py-3">
          <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
          <p class="text-xs font-medium text-red-600">{{ fileError }}</p>
        </div>

        <!-- Selected file card -->
        <div v-if="file && stage !== 'idle'"
          class="flex items-center gap-3 rounded-lg border border-black/8 bg-white px-3.5 py-3 shadow-sm">
          <div class="w-9 h-9 rounded-lg bg-red-50 border border-red-100 flex items-center justify-center shrink-0">
            <FileText class="w-4.5 h-4.5 text-red-500" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-black truncate">{{ file.name }}</p>
            <p class="text-xs text-black/40 mt-0.5">{{ fileSizeMB }} MB · PDF Document</p>
          </div>
          <button v-if="stage === 'selected'"
            @click.stop="clearFile"
            class="w-7 h-7 rounded-md flex items-center justify-center text-black/30 hover:text-black hover:bg-black/5 transition shrink-0">
            <X class="w-3.5 h-3.5" />
          </button>
          <CheckCircle2 v-if="stage === 'done'" class="w-4.5 h-4.5 text-emerald-500 shrink-0" />
        </div>

        <!-- Extracting state -->
        <div v-if="stage === 'extracting'"
          class="flex items-center gap-3 rounded-lg bg-brand-navy/4 border border-brand-navy/10 px-3.5 py-3">
          <Loader2 class="w-4 h-4 text-brand-navy animate-spin shrink-0" />
          <p class="text-xs font-medium text-brand-navy">Extracting contract data from PDF…</p>
        </div>

      </div>

      <!-- Footer -->
      <div class="px-5 pb-5 flex items-center justify-end gap-3">
        <Button variant="outline" @click="handleClose"
          class="h-9 px-4 text-sm border-black/15 text-black/60 hover:text-black">
          Cancel
        </Button>
        <Button
          :disabled="stage !== 'selected'"
          class="h-9 px-4 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
          Extract & Autofill
        </Button>
      </div>

    </DialogContent>
  </Dialog>
</template>
