<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Trash2, FileText, AlertCircle, ArrowDown } from 'lucide-vue-next'
import type { PaperSize, PageOrientation, PageSheet } from '@/composables/useQuillEditor'
import type { HeaderFooterConfig } from '@/composables/useCreateContractDraft'
import type { PageMargins } from '@/types/contract-margins'

const props = defineProps<{
  page: PageSheet
  pageIndex: number
  totalPages: number
  paperSize?: PaperSize
  orientation?: PageOrientation
  headerFooter: HeaderFooterConfig
  margins: PageMargins
  dimensions: { width: number; height: number }
  printableHeight: number
  isEditingHeaderFooter: boolean
  isActive: boolean
  isOverflowing?: boolean
}>()

const emit = defineEmits<{
  mount: [pageId: string, element: HTMLElement, initialHtml: string]
  unmount: [pageId: string]
  activate: [pageId: string]
  'remove-page': [pageId: string]
  'split-overflow': [pageId: string]
  'open-header-footer': []
  'update:headerFooter': [config: HeaderFooterConfig]
}>()

const editorContainerRef = ref<HTMLElement | null>(null)

onMounted(() => {
  if (editorContainerRef.value) {
    emit('mount', props.page.id, editorContainerRef.value, props.page.html)
  }
})

onUnmounted(() => {
  emit('unmount', props.page.id)
})
</script>

<template>
  <div
    class="quill-page-sheet relative bg-white flex flex-col cursor-text select-text rounded-[2px]"
    :class="isActive ? 'ring-2 ring-brand-blue/70 shadow-xl print:ring-0 print:shadow-none' : 'hover:shadow-lg shadow-md ring-1 ring-black/10 print:ring-0 print:shadow-none'"
    :style="{ width: `${dimensions.width}px`, minHeight: `${dimensions.height}px` }"
    @click="emit('activate', page.id)"
  >
    <!-- Overflow Warning Bar -->
    <div v-if="isOverflowing" class="bg-amber-500/10 border-b border-amber-500/30 px-6 py-1.5 flex items-center justify-between text-xs text-amber-900 print:hidden animate-in fade-in">
      <div class="flex items-center gap-1.5 font-medium">
        <AlertCircle class="w-3.5 h-3.5 text-amber-600 shrink-0" />
        <span>Content exceeds paper boundaries (Page {{ pageIndex + 1 }})</span>
      </div>
      <button type="button" @click.stop="emit('split-overflow', page.id)" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-600 hover:bg-amber-700 text-white font-medium text-[11px] transition cursor-pointer">
        <ArrowDown class="w-3 h-3" />
        <span>Push to Next Page</span>
      </button>
    </div>

    <!-- Sheet Header -->
    <div
      class="header-footer-zone group/header py-3.5 border-b flex justify-between items-center text-xs font-poppins select-none transition-all cursor-pointer rounded-t-[2px]"
      :class="isEditingHeaderFooter ? 'border-dashed border-2 border-brand-blue/60 bg-brand-blue/[0.03]' : 'border-black/[0.08] hover:bg-black/[0.015]'"
      :style="{ paddingLeft: `${props.margins.left}px`, paddingRight: `${props.margins.right}px` }"
      @dblclick.stop="emit('open-header-footer')"
      title="Double-click to edit Header"
    >
      <div class="flex items-center gap-3 max-w-[55%]">
        <img v-if="headerFooter.headerLogoUrl" :src="headerFooter.headerLogoUrl" class="h-7 max-w-[120px] object-contain rounded" alt="Logo" />
        <span v-if="!isEditingHeaderFooter" class="text-[11px] font-bold text-brand-navy uppercase tracking-wider truncate">
          {{ headerFooter.headerLeft || 'CONFIDENTIAL CONTRACT AGREEMENT' }}
        </span>
        <input v-else :value="headerFooter.headerLeft" @input="(e) => emit('update:headerFooter', { ...headerFooter, headerLeft: (e.target as HTMLInputElement).value })" placeholder="Header Title" class="bg-white border border-brand-blue/40 rounded px-2 py-0.5 text-[11px] font-bold text-brand-navy outline-none focus:ring-1 focus:ring-brand-blue w-full" />
      </div>

      <div class="flex items-center gap-2">
        <span class="text-[9px] uppercase tracking-wider font-semibold px-2 py-0.5 rounded border border-brand-blue/20 bg-brand-blue/5 text-brand-navy print:hidden">
          Page {{ pageIndex + 1 }}
        </span>
        <span v-if="!isEditingHeaderFooter" class="text-[11px] font-medium text-black/55 tracking-wide text-right">
          {{ headerFooter.headerRight || 'SBSI CRMS' }}
        </span>
        <input v-else :value="headerFooter.headerRight" @input="(e) => emit('update:headerFooter', { ...headerFooter, headerRight: (e.target as HTMLInputElement).value })" placeholder="Header Reference" class="bg-white border border-brand-blue/40 rounded px-2 py-0.5 text-[11px] font-medium text-black/70 outline-none focus:ring-1 focus:ring-brand-blue text-right w-40" />
        <button v-if="totalPages > 1" type="button" @click.stop="emit('remove-page', page.id)" class="opacity-0 group-hover/header:opacity-100 p-1 text-black/40 hover:text-red-600 transition rounded hover:bg-red-50 ml-1 print:hidden cursor-pointer" title="Delete page">
          <Trash2 class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Printable Quill Canvas Body with Dynamic Adjustable Margins -->
    <div
      class="quill-printable-body text-black/90 font-poppins outline-none focus:outline-none flex-1 flex flex-col"
      :style="{
        paddingLeft: `${props.margins.left}px`,
        paddingRight: `${props.margins.right}px`,
        paddingTop: `${props.margins.top}px`,
        paddingBottom: `${props.margins.bottom}px`,
      }"
    >
      <div ref="editorContainerRef" class="quill-page-instance flex-1 outline-none focus:outline-none" :style="{ minHeight: `${printableHeight}px` }" />
    </div>

    <!-- Sheet Footer -->
    <div
      class="header-footer-zone group/footer py-4 border-t flex justify-between items-center text-xs font-poppins select-none transition-all cursor-pointer rounded-b-[2px]"
      :class="isEditingHeaderFooter ? 'border-dashed border-2 border-brand-blue/60 bg-brand-blue/[0.03]' : 'border-black/[0.08] hover:bg-black/[0.015]'"
      :style="{ paddingLeft: `${props.margins.left}px`, paddingRight: `${props.margins.right}px` }"
      @dblclick.stop="emit('open-header-footer')"
      title="Double-click to edit Footer"
    >
      <div class="flex items-center gap-2 max-w-[65%]">
        <span v-if="!isEditingHeaderFooter" class="text-[11px] text-black/55 font-medium truncate">
          {{ headerFooter.footerLeft || 'Authorized Legal Draft · Strictly Confidential' }}
        </span>
        <input v-else :value="headerFooter.footerLeft" @input="(e) => emit('update:headerFooter', { ...headerFooter, footerLeft: (e.target as HTMLInputElement).value })" placeholder="Footer Disclaimer" class="bg-white border border-brand-blue/40 rounded px-2 py-0.5 text-[11px] font-medium text-black/80 outline-none focus:ring-1 focus:ring-brand-blue w-80" />
      </div>

      <div v-if="headerFooter.showPageNumber" class="text-[11px] font-medium text-black/60 shrink-0 flex items-center gap-1.5">
        <FileText class="w-3.5 h-3.5 text-brand-navy/50" />
        <span>Page {{ pageIndex + 1 }} of {{ totalPages }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.ql-container.ql-snow) {
  border: none !important;
}

:deep(.ql-editor) {
  border: none !important;
  outline: none !important;
  padding: 0 !important;
}

:deep(.ql-editor:focus) {
  outline: none !important;
}
</style>
