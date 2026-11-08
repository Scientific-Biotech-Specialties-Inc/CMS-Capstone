<script setup lang="ts">
import { ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import type { WorkflowRun } from '@/types/contractWorkflow'

defineProps<{
  run: WorkflowRun
  orientation?: 'horizontal' | 'vertical'
}>()

const expanded = ref(false)
</script>

<template>
  <div class="border border-black/8 rounded-lg bg-black/[0.015] overflow-hidden">
    <button
      @click="expanded = !expanded"
      class="w-full flex items-center justify-between gap-2 px-4 py-2.5 text-left"
      :aria-expanded="expanded"
    >
      <span class="text-xs font-semibold text-black/50">Previous attempt #{{ run.runNumber }}</span>
      <ChevronDown class="w-3.5 h-3.5 text-black/35 transition-transform" :class="expanded ? 'rotate-180' : ''" />
    </button>

    <ol v-if="expanded && orientation === 'vertical'" class="wf-timeline relative list-none space-y-6 py-2 pl-8 pr-2">
      <slot />
      <!-- Spine rails LAST in DOM so stagger indices align with steps. -->
      <div class="absolute bottom-4 left-[6px] top-4 w-0.5 bg-black/[0.08]" aria-hidden="true" />
      <div class="wf-spine-fill absolute bottom-4 left-[6px] top-4 w-0.5 origin-top bg-brand-blue" aria-hidden="true" />
    </ol>

    <ol v-if="expanded && orientation !== 'vertical'" class="list-none px-4 pb-5 pt-2 flex items-start overflow-x-auto">
      <slot />
    </ol>
  </div>
</template>

<style scoped src="./vertical-timeline.css"></style>
