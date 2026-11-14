<script setup lang="ts">
import { computed } from 'vue'
import { Receipt } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  sourceSystem?: string | null
  sourceReferenceId?: string | null
  size?: 'sm' | 'md'
}>(), {
  sourceSystem: null,
  sourceReferenceId: null,
  size: 'sm',
})

const label = computed(() => {
  if (!props.sourceSystem) return ''
  return props.sourceSystem === 'SERMS' ? 'From SERMS' : `From ${props.sourceSystem}`
})

const sizeClasses = computed(() =>
  props.size === 'md'
    ? 'px-3 py-1 text-xs gap-1.5'
    : 'px-2 py-0.5 text-[10px] gap-1'
)
</script>

<template>
  <span
    v-if="sourceSystem"
    class="inline-flex items-center font-semibold rounded-full border bg-blue-50 text-[#252578] border-[#2e85d8]/30 whitespace-nowrap shrink-0"
    :class="sizeClasses"
    :title="sourceReferenceId ? `Origin: ${sourceSystem} (${sourceReferenceId})` : `Origin: ${sourceSystem}`"
  >
    <Receipt :class="size === 'md' ? 'w-3.5 h-3.5 text-[#2e85d8]' : 'w-2.5 h-2.5 text-[#2e85d8]'" />
    {{ label }}
  </span>
</template>
