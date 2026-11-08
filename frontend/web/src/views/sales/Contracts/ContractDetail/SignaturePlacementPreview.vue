<script setup lang="ts">
import { ref, watch } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { SIGNATURE_PAGE_WIDTH, type SignaturePlacement } from './signDraftPdf'

const props = defineProps<{ source: string; signature: string; disabled: boolean }>()
const emit = defineEmits<{ change: [placement: SignaturePlacement | null] }>()
const page = ref(1)
const pageCount = ref(0)
const frame = ref<HTMLElement | null>(null)
const overlay = ref<HTMLButtonElement | null>(null)
const ready = ref(false)
const failure = ref(false)
const position = ref({ x: 0.65, y: 0.7 })
let drag: { pointerId: number; x: number; y: number } | null = null

watch([page, () => props.signature], () => emit('change', null))
watch(page, () => { ready.value = false })

function place(x: number, y: number) {
  if (!frame.value || !overlay.value || !ready.value || props.disabled) return
  const bounds = frame.value.getBoundingClientRect()
  const signature = overlay.value.getBoundingClientRect()
  if (signature.height > bounds.height) return
  position.value = {
    x: Math.max(0, Math.min(x, 1 - SIGNATURE_PAGE_WIDTH)),
    y: Math.max(0, Math.min(y, 1 - signature.height / bounds.height)),
  }
  emit('change', { page: page.value, ...position.value })
}

function dragStart(event: PointerEvent) {
  if (!overlay.value || props.disabled) return
  const bounds = overlay.value.getBoundingClientRect()
  overlay.value.setPointerCapture(event.pointerId)
  drag = { pointerId: event.pointerId, x: event.clientX - bounds.left, y: event.clientY - bounds.top }
}

function move(event: PointerEvent) {
  if (!drag || drag.pointerId !== event.pointerId || !frame.value) return
  const bounds = frame.value.getBoundingClientRect()
  place((event.clientX - bounds.left - drag.x) / bounds.width, (event.clientY - bounds.top - drag.y) / bounds.height)
}

function placeAtPointer(event: MouseEvent) {
  if (!frame.value || !overlay.value) return
  const bounds = frame.value.getBoundingClientRect()
  const signature = overlay.value.getBoundingClientRect()
  place((event.clientX - bounds.left - signature.width / 2) / bounds.width, (event.clientY - bounds.top - signature.height / 2) / bounds.height)
}
</script>

<template>
  <section class="space-y-2" aria-labelledby="position-signature-title">
    <h3 id="position-signature-title" class="text-sm font-semibold text-slate-800">Position Signature</h3>
    <p id="position-signature-help" class="text-sm text-slate-600">Click the signature area or drag the signature into place. You can also move it with the arrow keys.</p>
    <p v-if="failure" role="alert" class="text-sm text-red-700">The PDF preview could not be opened. Try a different signing method.</p>
    <template v-else>
      <div class="flex items-center justify-between gap-2">
        <Button type="button" variant="outline" size="sm" :disabled="disabled || page <= 1" @click="page--"><ChevronLeft class="size-4" /> Previous</Button>
        <p class="text-sm text-slate-600" aria-live="polite">Page {{ page }} of {{ pageCount || '…' }}</p>
        <Button type="button" variant="outline" size="sm" :disabled="disabled || page >= pageCount" @click="page++">Next <ChevronRight class="size-4" /></Button>
      </div>
      <div class="max-h-[45dvh] overflow-auto rounded-lg border border-slate-200 bg-slate-50">
        <div ref="frame" class="relative mx-auto w-full max-w-xl" @click="placeAtPointer">
          <div v-if="!ready" role="status" aria-label="Rendering PDF" class="h-48 animate-pulse bg-slate-100" />
          <VuePdfEmbed :source="source" :page="page" @loaded="pageCount = $event.numPages" @rendered="ready = true"
            @loading-failed="failure = true" @rendering-failed="failure = true" />
          <button v-if="ready" ref="overlay" type="button" :disabled="disabled" aria-label="Move signature" aria-describedby="position-signature-help"
            class="absolute touch-none cursor-move rounded bg-brand-blue/10 ring-1 ring-brand-blue focus-visible:outline-2 focus-visible:outline-brand-navy"
            :style="{ left: `${position.x * 100}%`, top: `${position.y * 100}%`, width: `${SIGNATURE_PAGE_WIDTH * 100}%` }"
            @click.stop="place(position.x, position.y)" @pointerdown="dragStart" @pointermove="move" @lostpointercapture="drag = null"
            @keydown.left.prevent="place(position.x - 0.01, position.y)" @keydown.right.prevent="place(position.x + 0.01, position.y)"
            @keydown.up.prevent="place(position.x, position.y - 0.01)" @keydown.down.prevent="place(position.x, position.y + 0.01)">
            <img :src="signature" alt="" draggable="false" class="block w-full select-none" />
          </button>
        </div>
      </div>
    </template>
  </section>
</template>
