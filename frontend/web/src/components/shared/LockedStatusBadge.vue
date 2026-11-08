<script setup lang="ts">
import { Lock } from 'lucide-vue-next'

// US-023: shown on the contract table/detail header while a High/Critical-risk
// contract's mandatory approval is still pending (i.e. Active status is
// locked until an approver records a decision with rationale).
withDefaults(
  defineProps<{
    size?: 'sm' | 'md'
  }>(),
  {
    size: 'sm',
  }
)
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 font-medium rounded-full whitespace-nowrap border bg-red-50 text-red-600 border-red-200"
    :class="size === 'sm' ? 'text-[11px] px-2 py-0.5' : 'text-xs px-2.5 py-1'"
    title="Active status is locked pending a recorded high-risk approval"
    aria-label="Locked: pending high-risk approval"
  >
    <Lock :class="size === 'sm' ? 'w-3 h-3' : 'w-3.5 h-3.5'" class="shrink-0" />
    <span>Approval Required</span>
  </span>
</template>
