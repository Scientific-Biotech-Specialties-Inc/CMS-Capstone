<script setup lang="ts">
import {
  Undo2,
  Redo2,
  RotateCw,
  SeparatorHorizontal,
  PanelTop,
} from 'lucide-vue-next'
import type { EditorToolbarState, PaperSize, PageOrientation } from '@/composables/useQuillEditor'
import type { HeaderFooterConfig } from '@/composables/useCreateContractDraft'
import type { MarginPreset, PageMargins } from '@/types/contract-margins'

const props = defineProps<{
  state: EditorToolbarState
  headerFooter: HeaderFooterConfig
  margins: PageMargins
  currentPreset?: MarginPreset
}>()

const emit = defineEmits<{
  'undo': []
  'redo': []
  'set-paper-size': [size: PaperSize]
  'set-orientation': [orientation: PageOrientation]
  'set-margin-preset': [preset: MarginPreset]
  'insert-page-break': []
  'toggle-header-footer': []
  'update:headerFooter': [config: HeaderFooterConfig]
}>()

function onPresetChange(e: Event) {
  const preset = (e.target as HTMLSelectElement).value as MarginPreset
  emit('set-margin-preset', preset)
}
</script>

<template>
  <div class="editor-toolbar-wrapper print:hidden w-full bg-white border-b border-black/10 px-4 py-1.5 flex items-center justify-between flex-wrap gap-x-3 gap-y-1.5 text-xs select-none sticky top-0 z-20 shadow-xs">
    <!-- Quill Rich Text Formats (Quill binds only to this container) -->
    <div
      id="quill-toolbar-container"
      class="ql-toolbar ql-snow !border-none !p-0 flex items-center flex-wrap gap-x-2 gap-y-1 text-xs"
    >
      <!-- History Controls -->
      <span class="ql-formats !mr-1.5 flex items-center">
        <button type="button" @click="emit('undo')" class="!w-7 !h-7 !float-none !inline-flex !items-center !justify-center text-black/60 hover:text-brand-navy hover:bg-black/5 rounded transition cursor-pointer" title="Undo (Ctrl+Z)">
          <Undo2 class="w-3.5 h-3.5" />
        </button>
        <button type="button" @click="emit('redo')" class="!w-7 !h-7 !float-none !inline-flex !items-center !justify-center text-black/60 hover:text-brand-navy hover:bg-black/5 rounded transition cursor-pointer" title="Redo (Ctrl+Y)">
          <Redo2 class="w-3.5 h-3.5" />
        </button>
      </span>

      <!-- Typography Dropdowns -->
      <span class="ql-formats">
        <select class="ql-header" title="Heading Level">
          <option selected>Normal</option>
          <option value="1">Heading 1</option>
          <option value="2">Heading 2</option>
          <option value="3">Heading 3</option>
        </select>
        <select class="ql-font" title="Font Family">
          <option selected>Poppins</option>
          <option value="serif">Serif</option>
          <option value="monospace">Monospace</option>
        </select>
        <select class="ql-size" title="Font Size">
          <option value="10pt">10pt</option>
          <option value="11pt" selected>11pt</option>
          <option value="12pt">12pt</option>
          <option value="14pt">14pt</option>
          <option value="16pt">16pt</option>
          <option value="18pt">18pt</option>
        </select>
      </span>

      <!-- Text Styles -->
      <span class="ql-formats">
        <button class="ql-bold" title="Bold (Ctrl+B)"></button>
        <button class="ql-italic" title="Italic (Ctrl+I)"></button>
        <button class="ql-underline" title="Underline (Ctrl+U)"></button>
        <button class="ql-strike" title="Strikethrough"></button>
      </span>

      <!-- Colors -->
      <span class="ql-formats">
        <select class="ql-color" title="Text Color"></select>
        <select class="ql-background" title="Highlight Color"></select>
      </span>

      <!-- Paragraph Formats -->
      <span class="ql-formats">
        <button class="ql-script" value="sub" title="Subscript"></button>
        <button class="ql-script" value="super" title="Superscript"></button>
        <select class="ql-align" title="Text Alignment"></select>
        <button class="ql-list" value="ordered" title="Numbered List"></button>
        <button class="ql-list" value="bullet" title="Bulleted List"></button>
        <button class="ql-blockquote" title="Quote Block"></button>
      </span>

      <!-- Media & Clean -->
      <span class="ql-formats">
        <button class="ql-link" title="Insert Link"></button>
        <button class="ql-clean" title="Clear Formatting"></button>
      </span>
    </div>

    <!-- Contract Document Layout Controls (Separated from Quill to avoid picker hijacking) -->
    <div class="contract-page-controls flex items-center gap-1.5 border-l border-black/15 pl-2.5">
      <!-- Paper Size Dropdown -->
      <div class="flex items-center gap-1">
        <label for="paper-size-select" class="text-[11px] font-semibold text-black/60 hidden xl:inline">Paper:</label>
        <select
          id="paper-size-select"
          :value="props.state.paperSize"
          @change="(e) => emit('set-paper-size', (e.target as HTMLSelectElement).value as PaperSize)"
          class="h-7 text-xs border border-black/15 rounded px-2 bg-white text-black/80 font-medium outline-none hover:border-brand-blue focus:border-brand-blue cursor-pointer transition shadow-2xs"
          title="Paper Size"
        >
          <option value="letter">Letter (8.5 × 11")</option>
          <option value="a4">A4 (8.27 × 11.69")</option>
          <option value="legal">Legal (8.5 × 14")</option>
        </select>
      </div>

      <!-- Orientation Toggle Button -->
      <button
        type="button"
        @click="emit('set-orientation', props.state.orientation === 'portrait' ? 'landscape' : 'portrait')"
        class="h-7 inline-flex items-center gap-1 px-2 rounded border border-black/15 hover:border-brand-blue bg-white text-black/75 hover:text-brand-navy text-xs font-medium transition cursor-pointer shadow-2xs"
        :title="`Orientation: ${props.state.orientation}`"
      >
        <RotateCw class="w-3.5 h-3.5 text-brand-navy" />
        <span class="capitalize">{{ props.state.orientation }}</span>
      </button>

      <!-- Margin Preset Selector -->
      <select
        :value="props.currentPreset || 'custom'"
        @change="onPresetChange"
        class="h-7 text-xs border border-black/15 rounded px-2 bg-white text-black/80 font-medium outline-none hover:border-brand-blue focus:border-brand-blue cursor-pointer transition shadow-2xs"
        title="Page Margins (or drag guides on sheet)"
      >
        <option value="normal">Margins: Normal (1.0")</option>
        <option value="narrow">Margins: Narrow (0.5")</option>
        <option value="moderate">Margins: Moderate (0.75")</option>
        <option value="wide">Margins: Wide (1.25")</option>
        <option value="custom" disabled>Margins: Custom (Dragged)</option>
      </select>

      <!-- Page Break Button -->
      <button
        type="button"
        @click="emit('insert-page-break')"
        class="h-7 inline-flex items-center gap-1 px-2 rounded border border-black/15 hover:border-brand-blue bg-white text-black/75 hover:text-brand-navy text-xs font-medium transition cursor-pointer shadow-2xs"
        title="Insert Page Break"
      >
        <SeparatorHorizontal class="w-3.5 h-3.5 text-brand-navy" />
        <span>Page Break</span>
      </button>

      <!-- Header & Footer Button -->
      <button
        type="button"
        @click="emit('toggle-header-footer')"
        class="h-7 inline-flex items-center gap-1 px-2 rounded border border-black/15 hover:border-brand-blue bg-white text-black/75 hover:text-brand-navy text-xs font-medium transition cursor-pointer shadow-2xs"
        title="Edit Header & Footer"
      >
        <PanelTop class="w-3.5 h-3.5 text-brand-navy" />
        <span>Header &amp; Footer</span>
      </button>
    </div>
  </div>
</template>

<style>
/* Quill Snow Custom Font Size Picker Labels */
.ql-snow .ql-picker.ql-size .ql-picker-label[data-value="10pt"]::before,
.ql-snow .ql-picker.ql-size .ql-picker-item[data-value="10pt"]::before {
  content: '10pt';
}
.ql-snow .ql-picker.ql-size .ql-picker-label[data-value="11pt"]::before,
.ql-snow .ql-picker.ql-size .ql-picker-item[data-value="11pt"]::before {
  content: '11pt';
}
.ql-snow .ql-picker.ql-size .ql-picker-label[data-value="12pt"]::before,
.ql-snow .ql-picker.ql-size .ql-picker-item[data-value="12pt"]::before {
  content: '12pt';
}
.ql-snow .ql-picker.ql-size .ql-picker-label[data-value="14pt"]::before,
.ql-snow .ql-picker.ql-size .ql-picker-item[data-value="14pt"]::before {
  content: '14pt';
}
.ql-snow .ql-picker.ql-size .ql-picker-label[data-value="16pt"]::before,
.ql-snow .ql-picker.ql-size .ql-picker-item[data-value="16pt"]::before {
  content: '16pt';
}
.ql-snow .ql-picker.ql-size .ql-picker-label[data-value="18pt"]::before,
.ql-snow .ql-picker.ql-size .ql-picker-item[data-value="18pt"]::before {
  content: '18pt';
}
.ql-snow .ql-picker.ql-size .ql-picker-label:not([data-value])::before,
.ql-snow .ql-picker.ql-size .ql-picker-item:not([data-value])::before {
  content: '11pt';
}
</style>
