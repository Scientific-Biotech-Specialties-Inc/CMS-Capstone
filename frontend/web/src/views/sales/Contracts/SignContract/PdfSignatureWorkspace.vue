<script setup lang="ts">
import { ref, watch } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed'
import { ChevronLeft, ChevronRight, ZoomIn, ZoomOut, AlertCircle, CheckCircle2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { SIGNATURE_MIN_WIDTH, SIGNATURE_MAX_WIDTH, type SignaturePlacement } from '../ContractDetail/signDraftPdf'

const props = defineProps<{
  pdfSource: string
  signature: string | null
  signatureWidth: number
  placement: SignaturePlacement | null
  disabled: boolean
  attemptedSubmit: boolean
}>()

const emit = defineEmits<{
  'update:placement': [placement: SignaturePlacement | null]
  'update:signatureWidth': [width: number]
}>()

const page = ref(1)
const pageCount = ref(0)
const frame = ref<HTMLElement | null>(null)
const overlay = ref<HTMLButtonElement | null>(null)
const ready = ref(false)
const failure = ref(false)
const zoom = ref(100)

const position = ref({ x: 0.60, y: 0.70 })
let drag: { pointerId: number; x: number; y: number } | null = null
let resizing: { pointerId: number; startX: number; startWidth: number } | null = null

watch(page, () => { ready.value = false })

function clampAndEmit(x: number, y: number, width: number) {
  if (!frame.value || !overlay.value || !ready.value || props.disabled) return
  const bounds = frame.value.getBoundingClientRect()
  const sigRect = overlay.value.getBoundingClientRect()
  const heightRatio = sigRect.height > 0 ? sigRect.height / bounds.height : 0.08

  const clampedX = Math.max(0, Math.min(x, 1 - width))
  const clampedY = Math.max(0, Math.min(y, 1 - heightRatio))
  position.value = { x: clampedX, y: clampedY }
  emit('update:placement', { page: page.value, x: clampedX, y: clampedY, width })
}

function placeAtPointer(event: MouseEvent) {
  if (!props.signature || !frame.value || !overlay.value || props.disabled) return
  const bounds = frame.value.getBoundingClientRect()
  const sigWidth = bounds.width * props.signatureWidth
  const targetX = (event.clientX - bounds.left - sigWidth / 2) / bounds.width
  const targetY = (event.clientY - bounds.top - 20) / bounds.height
  clampAndEmit(targetX, targetY, props.signatureWidth)
}

function dragStart(event: PointerEvent) {
  if (!overlay.value || props.disabled) return
  const bounds = overlay.value.getBoundingClientRect()
  overlay.value.setPointerCapture(event.pointerId)
  drag = { pointerId: event.pointerId, x: event.clientX - bounds.left, y: event.clientY - bounds.top }
}

function onPointerMove(event: PointerEvent) {
  if (!frame.value) return
  const bounds = frame.value.getBoundingClientRect()
  if (drag && drag.pointerId === event.pointerId) {
    const nx = (event.clientX - bounds.left - drag.x) / bounds.width
    const ny = (event.clientY - bounds.top - drag.y) / bounds.height
    clampAndEmit(nx, ny, props.signatureWidth)
  } else if (resizing && resizing.pointerId === event.pointerId) {
    const deltaX = (event.clientX - resizing.startX) / bounds.width
    const nextWidth = Math.max(SIGNATURE_MIN_WIDTH, Math.min(SIGNATURE_MAX_WIDTH, resizing.startWidth + deltaX))
    emit('update:signatureWidth', Number(nextWidth.toFixed(3)))
    clampAndEmit(position.value.x, position.value.y, nextWidth)
  }
}

function startResize(event: PointerEvent) {
  event.stopPropagation()
  if (props.disabled) return
  (event.target as HTMLElement).setPointerCapture(event.pointerId)
  resizing = { pointerId: event.pointerId, startX: event.clientX, startWidth: props.signatureWidth }
}
</script>

<template>
  <div class="flex min-h-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <!-- Top toolbar: Page switcher, zoom, placement indicator -->
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
      <div class="flex items-center gap-2">
        <Button type="button" variant="outline" size="sm" :disabled="disabled || page <= 1" @click="page--">
          <ChevronLeft class="size-4" />
        </Button>
        <span class="text-xs font-semibold text-slate-700 min-w-24 text-center">
          Page {{ page }} of {{ pageCount || '…' }}
        </span>
        <Button type="button" variant="outline" size="sm" :disabled="disabled || page >= pageCount" @click="page++">
          <ChevronRight class="size-4" />
        </Button>
      </div>

      <!-- Placement badge -->
      <div class="flex items-center gap-2">
        <span v-if="placement" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
          <CheckCircle2 class="size-3.5" /> Placed on Page {{ placement.page }}
        </span>
        <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium" :class="attemptedSubmit ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-slate-100 text-slate-600'">
          <AlertCircle class="size-3.5" /> Click document to place
        </span>

        <!-- Zoom controls -->
        <div class="flex items-center gap-1 border-l border-slate-200 pl-2">
          <Button type="button" variant="ghost" size="sm" :disabled="zoom <= 75" @click="zoom = Math.max(75, zoom - 15)">
            <ZoomOut class="size-3.5" />
          </Button>
          <span class="text-[11px] text-slate-500 font-mono w-10 text-center">{{ zoom }}%</span>
          <Button type="button" variant="ghost" size="sm" :disabled="zoom >= 150" @click="zoom = Math.min(150, zoom + 15)">
            <ZoomIn class="size-3.5" />
          </Button>
        </div>
      </div>
    </div>

    <!-- PDF Canvas Viewport -->
    <div class="relative min-h-0 max-h-[68dvh] overflow-auto rounded-xl border border-slate-200 bg-slate-100/80 p-2 sm:p-4 lg:max-h-none lg:flex-1">
      <div v-if="!ready && !failure" class="h-96 flex flex-col items-center justify-center gap-2 text-slate-400">
        <div class="size-8 rounded-full border-2 border-brand-navy border-t-transparent animate-spin" />
        <span class="text-xs">Rendering contract PDF…</span>
      </div>

      <div
        ref="frame"
        class="relative mx-auto transition-transform origin-top select-none shadow-md"
        :style="{ width: `${zoom}%`, maxWidth: '100%' }"
        @click="placeAtPointer"
      >
        <VuePdfEmbed
          :source="pdfSource"
          :page="page"
          @loaded="pageCount = $event.numPages"
          @rendered="ready = true"
          @loading-failed="failure = true"
          @rendering-failed="failure = true"
        />

        <!-- Placed Signature Box (Interactive) -->
        <button
          v-if="ready && signature && (!placement || placement.page === page)"
          ref="overlay"
          type="button"
          :disabled="disabled"
          class="absolute touch-none cursor-move rounded border-2 border-brand-blue bg-brand-blue/15 shadow-lg group focus-visible:outline-2 focus-visible:outline-brand-navy"
          :style="{
            left: `${position.x * 100}%`,
            top: `${position.y * 100}%`,
            width: `${signatureWidth * 100}%`
          }"
          @click.stop
          @pointerdown="dragStart"
          @pointermove="onPointerMove"
          @lostpointercapture="drag = null"
          @keydown.left.prevent="clampAndEmit(position.x - 0.01, position.y, signatureWidth)"
          @keydown.right.prevent="clampAndEmit(position.x + 0.01, position.y, signatureWidth)"
          @keydown.up.prevent="clampAndEmit(position.x, position.y - 0.01, signatureWidth)"
          @keydown.down.prevent="clampAndEmit(position.x, position.y + 0.01, signatureWidth)"
        >
          <img :src="signature" alt="Signature preview" draggable="false" class="block w-full select-none p-0.5" />
          <span class="absolute -top-5 left-0 px-1.5 py-0.5 bg-brand-navy text-[10px] text-white font-mono rounded opacity-90">
            {{ Math.round(signatureWidth * 100) }}% size
          </span>
          <!-- Corner Resize Handle (Bottom-Right) -->
          <span
            class="absolute -bottom-1.5 -right-1.5 size-3.5 bg-brand-navy rounded-sm border-2 border-white cursor-se-resize shadow touch-none"
            title="Drag to resize signature"
            @pointerdown="startResize"
            @pointermove="onPointerMove"
            @lostpointercapture="resizing = null"
          />
        </button>
      </div>
    </div>
  </div>
</template>

