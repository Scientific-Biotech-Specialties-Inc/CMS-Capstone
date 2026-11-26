<script setup lang="ts">
import { computed } from 'vue'
import type { StatDelta } from '@/utils/statDelta'

const props = defineProps<{
  delta: StatDelta
}>()

const toneClass = computed(() => {
  if (props.delta.tone === 'positive') return 'bg-emerald-50 text-emerald-600'
  if (props.delta.tone === 'negative') return 'bg-red-50 text-red-500'
  return 'bg-slate-100 text-slate-500'
})
</script>

<template>
  <span
    class="text-xs font-medium px-2 py-0.5 rounded-md mb-0.5 shrink-0 tabular-nums"
    :class="toneClass"
    :title="delta.tooltip"
  >
    {{ delta.change }}
  </span>
</template>
