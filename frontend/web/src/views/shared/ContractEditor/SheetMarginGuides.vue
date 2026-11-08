<script setup lang="ts">
import { ref } from 'vue'
import type { PageMargins } from '@/types/contract-margins'

const props = defineProps<{
  margins: PageMargins
  width: number
  height: number
}>()

const emit = defineEmits<{
  'update:margins': [margins: PageMargins]
}>()

const activeGuide = ref<'left' | 'right' | 'top' | 'bottom' | null>(null)
const startPos = ref(0)
const initialVal = ref(0)

const pxToInch = (px: number) => (px / 72).toFixed(2)

function onPointerDown(guide: 'left' | 'right' | 'top' | 'bottom', e: PointerEvent) {
  activeGuide.value = guide
  const target = e.currentTarget as HTMLElement
  target.setPointerCapture(e.pointerId)

  if (guide === 'left' || guide === 'right') {
    startPos.value = e.clientX
    initialVal.value = props.margins[guide]
  } else {
    startPos.value = e.clientY
    initialVal.value = props.margins[guide]
  }
}

function onPointerMove(e: PointerEvent) {
  if (!activeGuide.value) return

  const guide = activeGuide.value
  let newVal = initialVal.value

  if (guide === 'left') {
    const delta = e.clientX - startPos.value
    newVal = Math.max(24, Math.min(Math.round(props.width / 2 - 80), initialVal.value + delta))
    emit('update:margins', { ...props.margins, left: newVal })
  } else if (guide === 'right') {
    const delta = startPos.value - e.clientX
    newVal = Math.max(24, Math.min(Math.round(props.width / 2 - 80), initialVal.value + delta))
    emit('update:margins', { ...props.margins, right: newVal })
  } else if (guide === 'top') {
    const delta = e.clientY - startPos.value
    newVal = Math.max(20, Math.min(180, initialVal.value + delta))
    emit('update:margins', { ...props.margins, top: newVal })
  } else if (guide === 'bottom') {
    const delta = startPos.value - e.clientY
    newVal = Math.max(20, Math.min(180, initialVal.value + delta))
    emit('update:margins', { ...props.margins, bottom: newVal })
  }
}

function onPointerUp(e: PointerEvent) {
  if (!activeGuide.value) return
  try {
    const target = e.currentTarget as HTMLElement
    target.releasePointerCapture(e.pointerId)
  } catch {
    // Ignore if pointer capture was already released
  }
  activeGuide.value = null
}
</script>

<template>
  <div class="sheet-margin-guides absolute inset-0 pointer-events-none z-20 print:hidden select-none">
    <!-- Left Margin Guide Handle -->
    <div
      class="pointer-events-auto absolute top-0 bottom-0 w-3 -ml-1.5 cursor-ew-resize flex items-center justify-center group/left transition-colors"
      :style="{ left: `${props.margins.left}px` }"
      @pointerdown="(e) => onPointerDown('left', e)"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      title="Drag to adjust Left Margin"
    >
      <div
        class="w-px h-full transition-colors"
        :class="activeGuide === 'left' ? 'bg-brand-blue ring-1 ring-brand-blue/30' : 'bg-transparent group-hover/left:bg-brand-blue/60 group-hover/left:border-r group-hover/left:border-dashed group-hover/left:border-brand-blue'"
      />
      <!-- Handle Pill Indicator -->
      <div
        class="absolute top-12 -left-2 bg-brand-navy text-white text-[9px] px-1.5 py-0.5 rounded shadow-md opacity-0 group-hover/left:opacity-100 transition-opacity whitespace-nowrap font-mono pointer-events-none"
        :class="{ '!opacity-100 ring-2 ring-brand-blue': activeGuide === 'left' }"
      >
        L: {{ pxToInch(props.margins.left) }}"
      </div>
    </div>

    <!-- Right Margin Guide Handle -->
    <div
      class="pointer-events-auto absolute top-0 bottom-0 w-3 -mr-1.5 cursor-ew-resize flex items-center justify-center group/right transition-colors"
      :style="{ right: `${props.margins.right}px` }"
      @pointerdown="(e) => onPointerDown('right', e)"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      title="Drag to adjust Right Margin"
    >
      <div
        class="w-px h-full transition-colors"
        :class="activeGuide === 'right' ? 'bg-brand-blue ring-1 ring-brand-blue/30' : 'bg-transparent group-hover/right:bg-brand-blue/60 group-hover/right:border-r group-hover/right:border-dashed group-hover/right:border-brand-blue'"
      />
      <!-- Handle Pill Indicator -->
      <div
        class="absolute top-12 -right-2 bg-brand-navy text-white text-[9px] px-1.5 py-0.5 rounded shadow-md opacity-0 group-hover/right:opacity-100 transition-opacity whitespace-nowrap font-mono pointer-events-none"
        :class="{ '!opacity-100 ring-2 ring-brand-blue': activeGuide === 'right' }"
      >
        R: {{ pxToInch(props.margins.right) }}"
      </div>
    </div>

    <!-- Top Margin Guide Handle -->
    <div
      class="pointer-events-auto absolute left-0 right-0 h-3 -mt-1.5 cursor-ns-resize flex items-center justify-center group/top transition-colors"
      :style="{ top: `${props.margins.top + 46}px` }"
      @pointerdown="(e) => onPointerDown('top', e)"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      title="Drag to adjust Top Margin"
    >
      <div
        class="h-px w-full transition-colors"
        :class="activeGuide === 'top' ? 'bg-brand-blue ring-1 ring-brand-blue/30' : 'bg-transparent group-hover/top:bg-brand-blue/60 group-hover/top:border-b group-hover/top:border-dashed group-hover/top:border-brand-blue'"
      />
      <div
        class="absolute -top-4 left-16 bg-brand-navy text-white text-[9px] px-1.5 py-0.5 rounded shadow-md opacity-0 group-hover/top:opacity-100 transition-opacity whitespace-nowrap font-mono pointer-events-none"
        :class="{ '!opacity-100 ring-2 ring-brand-blue': activeGuide === 'top' }"
      >
        Top: {{ pxToInch(props.margins.top) }}"
      </div>
    </div>

    <!-- Bottom Margin Guide Handle -->
    <div
      class="pointer-events-auto absolute left-0 right-0 h-3 -mb-1.5 cursor-ns-resize flex items-center justify-center group/bottom transition-colors"
      :style="{ bottom: `${props.margins.bottom + 46}px` }"
      @pointerdown="(e) => onPointerDown('bottom', e)"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      title="Drag to adjust Bottom Margin"
    >
      <div
        class="h-px w-full transition-colors"
        :class="activeGuide === 'bottom' ? 'bg-brand-blue ring-1 ring-brand-blue/30' : 'bg-transparent group-hover/bottom:bg-brand-blue/60 group-hover/bottom:border-b group-hover/bottom:border-dashed group-hover/bottom:border-brand-blue'"
      />
      <div
        class="absolute -bottom-4 left-16 bg-brand-navy text-white text-[9px] px-1.5 py-0.5 rounded shadow-md opacity-0 group-hover/bottom:opacity-100 transition-opacity whitespace-nowrap font-mono pointer-events-none"
        :class="{ '!opacity-100 ring-2 ring-brand-blue': activeGuide === 'bottom' }"
      >
        Bottom: {{ pxToInch(props.margins.bottom) }}"
      </div>
    </div>
  </div>
</template>
