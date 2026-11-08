<script setup lang="ts">
import { ref, watch } from 'vue'
import { RotateCcw } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

defineProps<{ disabled: boolean }>()
const emit = defineEmits<{ change: [signature: string | null] }>()
const mode = ref<'draw' | 'upload'>('draw')
const canvas = ref<HTMLCanvasElement | null>(null)
const drawing = ref(false)
const image = ref<string | null>(null)
const error = ref('')
let uploadAttempt = 0

function clear() {
  uploadAttempt++
  const pad = canvas.value
  if (pad) pad.getContext('2d')?.clearRect(0, 0, pad.width, pad.height)
  drawing.value = false
  image.value = null
  error.value = ''
  emit('change', null)
}

watch(mode, clear)

function draw(event: PointerEvent, start = false) {
  const pad = canvas.value
  const context = pad?.getContext('2d')
  if (!pad || !context || (!start && !drawing.value)) return
  const rect = pad.getBoundingClientRect()
  const x = (event.clientX - rect.left) * pad.width / rect.width
  const y = (event.clientY - rect.top) * pad.height / rect.height
  if (start) {
    pad.setPointerCapture(event.pointerId)
    context.beginPath()
    context.moveTo(x, y)
    drawing.value = true
  } else {
    context.strokeStyle = '#252578'
    context.lineWidth = 4
    context.lineCap = 'round'
    context.lineJoin = 'round'
    context.lineTo(x, y)
    context.stroke()
  }
}

function finish() {
  if (!drawing.value || !canvas.value) return
  drawing.value = false
  const pad = canvas.value
  const pixels = pad.getContext('2d')?.getImageData(0, 0, pad.width, pad.height).data
  if (!pixels) return
  let left = pad.width, top = pad.height, right = -1, bottom = -1
  for (let y = 0; y < pad.height; y++) {
    for (let x = 0; x < pad.width; x++) {
      if (!pixels[(y * pad.width + x) * 4 + 3]) continue
      left = Math.min(left, x)
      top = Math.min(top, y)
      right = Math.max(right, x)
      bottom = Math.max(bottom, y)
    }
  }
  if (right < left) return
  const crop = document.createElement('canvas')
  crop.width = right - left + 1
  crop.height = bottom - top + 1
  crop.getContext('2d')?.drawImage(pad, left, top, crop.width, crop.height, 0, 0, crop.width, crop.height)
  emit('change', crop.toDataURL('image/png'))
}

async function upload(file?: File) {
  clear()
  const attempt = uploadAttempt
  if (!file) return
  if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 2 * 1024 * 1024) {
    error.value = 'Choose a PNG or JPG of 2 MB or less.'
    return
  }
  try {
    const bitmap = await createImageBitmap(file)
    const preview = document.createElement('canvas')
    const scale = Math.min(1, 900 / bitmap.width, 300 / bitmap.height)
    preview.width = Math.max(1, Math.round(bitmap.width * scale))
    preview.height = Math.max(1, Math.round(bitmap.height * scale))
    preview.getContext('2d')?.drawImage(bitmap, 0, 0, preview.width, preview.height)
    bitmap.close()
    if (attempt !== uploadAttempt || mode.value !== 'upload') return
    image.value = preview.toDataURL('image/png')
    emit('change', image.value)
  } catch {
    error.value = 'The signature image could not be opened.'
  }
}
</script>

<template>
  <fieldset class="space-y-2" :disabled="disabled">
    <legend class="mb-2 text-sm font-semibold text-slate-800">Signature</legend>
    <div class="flex flex-wrap gap-4">
      <label class="flex items-center gap-2 text-sm"><input v-model="mode" type="radio" value="draw" class="accent-brand-navy" /> Draw Signature</label>
      <label class="flex items-center gap-2 text-sm"><input v-model="mode" type="radio" value="upload" class="accent-brand-navy" /> Upload Signature Image</label>
    </div>
    <div v-if="mode === 'draw'" class="space-y-2">
      <canvas ref="canvas" width="900" height="240" role="img" aria-label="Draw your signature with a mouse or touch"
        class="block h-32 w-full touch-none rounded-lg border border-slate-300 bg-white"
        @pointerdown="!disabled && draw($event, true)" @pointermove="!disabled && draw($event)" @pointerup="finish" @lostpointercapture="finish" />
      <Button type="button" size="sm" variant="outline" @click="clear"><RotateCcw class="size-4" /> Clear Signature</Button>
    </div>
    <label v-else class="block rounded-lg border border-slate-200 p-4 text-sm text-slate-700">
      <span class="block font-semibold">Signature Image</span>
      <span class="block">PNG or JPG, up to 2 MB</span>
      <input type="file" accept="image/png,image/jpeg" class="mt-2 block max-w-full" @change="upload(($event.target as HTMLInputElement).files?.[0])" />
      <img v-if="image" :src="image" alt="Uploaded signature preview" class="mt-2 max-h-24 max-w-full object-contain" />
    </label>
    <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
  </fieldset>
</template>
