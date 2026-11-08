<script setup lang="ts">
import { ref } from 'vue'
import {
  Upload,
  Trash2,
  Calendar,
  PanelTop,
} from 'lucide-vue-next'
import type { HeaderFooterConfig } from '@/composables/useCreateContractDraft'

const props = defineProps<{
  headerFooter: HeaderFooterConfig
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  'update:headerFooter': [config: HeaderFooterConfig]
}>()

const logoFileInput = ref<HTMLInputElement | null>(null)

function handleLogoUpload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    if (typeof reader.result === 'string') {
      emit('update:headerFooter', { ...props.headerFooter, headerLogoUrl: reader.result })
      if (logoFileInput.value) logoFileInput.value.value = ''
    }
  }
  reader.readAsDataURL(file)
}

function removeLogo() {
  emit('update:headerFooter', { ...props.headerFooter, headerLogoUrl: '' })
}

function insertCurrentDate() {
  const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
  emit('update:headerFooter', { ...props.headerFooter, headerRight: today })
}
</script>

<template>
  <div v-if="modelValue" class="header-footer-floating-pill fixed top-28 left-1/2 -translate-x-1/2 bg-brand-navy text-white px-4 py-2 rounded-full shadow-2xl flex items-center gap-3 text-xs z-50 print:hidden animate-in fade-in slide-in-from-top-2 duration-150">
    <span class="font-semibold text-xs flex items-center gap-1.5 text-blue-200">
      <PanelTop class="w-3.5 h-3.5 text-brand-blue" />
      Header &amp; Footer Edit Mode
    </span>
    <span class="w-px h-3.5 bg-white/20"></span>
    <button type="button" @click="logoFileInput?.click()" class="hover:text-blue-300 text-xs flex items-center gap-1 font-medium transition cursor-pointer">
      <Upload class="w-3.5 h-3.5" />
      {{ headerFooter.headerLogoUrl ? 'Change Logo' : 'Add Logo' }}
    </button>
    <input ref="logoFileInput" type="file" accept="image/*" class="hidden" @change="handleLogoUpload" />
    <button v-if="headerFooter.headerLogoUrl" type="button" @click="removeLogo" class="hover:text-red-300 text-xs flex items-center gap-1 font-medium transition cursor-pointer">
      <Trash2 class="w-3.5 h-3.5" />
      Remove Logo
    </button>
    <span class="w-px h-3.5 bg-white/20"></span>
    <button type="button" @click="insertCurrentDate" class="hover:text-blue-300 text-xs flex items-center gap-1 font-medium transition cursor-pointer">
      <Calendar class="w-3.5 h-3.5" />
      Insert Date
    </button>
    <span class="w-px h-3.5 bg-white/20"></span>
    <button type="button" @click="emit('update:modelValue', false)" class="bg-brand-blue hover:bg-brand-blue/80 text-white text-xs px-3 py-1 rounded-full font-semibold transition cursor-pointer shadow-xs">
      Done (Esc)
    </button>
  </div>
</template>
