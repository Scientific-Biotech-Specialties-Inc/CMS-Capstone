<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { Plus } from 'lucide-vue-next'
import type { PaperSize, PageOrientation, PageSheet } from '@/composables/useQuillEditor'
import type { HeaderFooterConfig } from '@/composables/useCreateContractDraft'
import type { PageMargins } from '@/types/contract-margins'
import QuillPageSheet from './QuillPageSheet.vue'
import SheetMarginGuides from './SheetMarginGuides.vue'
import HeaderFooterFloatingBar from './HeaderFooterFloatingBar.vue'

const props = withDefaults(
  defineProps<{
    zoom?: number
    paperSize?: PaperSize
    orientation?: PageOrientation
    headerFooter: HeaderFooterConfig
    margins: PageMargins
    pages: PageSheet[]
    activePageId: string
    overflowingPageIds?: string[]
    isEditingHeaderFooter?: boolean
  }>(),
  {
    zoom: 1,
    paperSize: 'letter',
    orientation: 'portrait',
    overflowingPageIds: () => [],
    isEditingHeaderFooter: false,
  }
)

const emit = defineEmits<{
  'mount-page': [pageId: string, element: HTMLElement, initialHtml: string]
  'unmount-page': [pageId: string]
  'set-active-page': [pageId: string]
  'add-page': [afterPageId?: string]
  'remove-page': [pageId: string]
  'split-overflow': [pageId: string, maxPrintableHeight: number]
  'update:headerFooter': [config: HeaderFooterConfig]
  'update:margins': [margins: PageMargins]
  'update:isEditingHeaderFooter': [value: boolean]
}>()

const isEditing = computed({
  get: () => props.isEditingHeaderFooter ?? false,
  set: (val: boolean) => emit('update:isEditingHeaderFooter', val),
})

// Standard paper dimensions at 96 DPI
const dimensions = computed(() => {
  const size = props.paperSize || 'letter'
  const isLandscape = props.orientation === 'landscape'

  let w = 816
  let h = 1056

  if (size === 'a4') {
    w = 794
    h = 1123
  } else if (size === 'legal') {
    w = 816
    h = 1344
  }

  return {
    width: isLandscape ? h : w,
    height: isLandscape ? w : h,
  }
})

// Printable body height per page sheet (excluding header, footer and user margins)
const printableHeight = computed(() => {
  const verticalMargins = (props.margins?.top || 48) + (props.margins?.bottom || 48)
  return Math.max(300, dimensions.value.height - 100 - verticalMargins)
})

function handleGlobalKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && isEditing.value) isEditing.value = false
}

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))
</script>

<template>
  <div
    class="w-full flex-1 overflow-y-auto bg-[#ECEEF1] py-10 px-4 flex flex-col items-center select-text print:bg-white print:p-0 print:overflow-visible print:h-auto"
    @dblclick="(e) => { if (!(e.target as HTMLElement).closest('.header-footer-zone') && isEditing) isEditing = false }"
  >
    <div
      class="relative flex flex-col items-center transition-all origin-top mb-16 print:mb-0 print:transform-none print:!w-full"
      :style="{ width: `${dimensions.width}px`, transform: props.zoom && props.zoom !== 1 ? `scale(${props.zoom})` : 'none' }"
    >
      <!-- Floating Header & Footer Edit Pill -->
      <HeaderFooterFloatingBar
        :model-value="isEditing"
        :header-footer="props.headerFooter"
        @update:model-value="(val) => isEditing = val"
        @update:header-footer="(cfg) => emit('update:headerFooter', cfg)"
      />

      <!-- Discrete Physical Paper Sheets Loop -->
      <template v-for="(page, pageIndex) in props.pages" :key="page.id">
        <div class="relative group/sheet-wrap print:!w-full" :style="{ width: `${dimensions.width}px` }">
          <!-- Interactive Draggable Margin Handles (Shown for active page) -->
          <SheetMarginGuides
            v-if="props.activePageId === page.id"
            :margins="props.margins"
            :width="dimensions.width"
            :height="dimensions.height"
            @update:margins="(m) => emit('update:margins', m)"
          />

          <QuillPageSheet
            :page="page"
            :page-index="pageIndex"
            :total-pages="props.pages.length"
            :paper-size="props.paperSize"
            :orientation="props.orientation"
            :header-footer="props.headerFooter"
            :margins="props.margins"
            :dimensions="dimensions"
            :printable-height="printableHeight"
            :is-editing-header-footer="isEditing"
            :is-active="props.activePageId === page.id"
            :is-overflowing="props.overflowingPageIds.includes(page.id)"
            @mount="(id, el, html) => emit('mount-page', id, el, html)"
            @unmount="(id) => emit('unmount-page', id)"
            @activate="(id) => emit('set-active-page', id)"
            @remove-page="(id) => emit('remove-page', id)"
            @split-overflow="(id) => emit('split-overflow', id, printableHeight)"
            @open-header-footer="isEditing = true"
            @update:header-footer="(cfg) => emit('update:headerFooter', cfg)"
          />
        </div>

        <!-- Desk Gap Separator with Add Page Action -->
        <div class="w-full flex items-center justify-center my-6 group/gap print:hidden" :style="{ width: `${dimensions.width}px` }">
          <div class="h-px bg-black/10 flex-1 transition-colors group-hover/gap:bg-brand-blue/30" />
          <button
            type="button"
            @click="emit('add-page', page.id)"
            class="mx-3 px-3 py-1 rounded-full bg-white hover:bg-brand-navy hover:text-white border border-black/15 shadow-xs text-[11px] font-medium text-black/60 flex items-center gap-1.5 transition cursor-pointer"
          >
            <Plus class="w-3 h-3" />
            <span>Add Page Below</span>
          </button>
          <div class="h-px bg-black/10 flex-1 transition-colors group-hover/gap:bg-brand-blue/30" />
        </div>
      </template>
    </div>
  </div>
</template>
