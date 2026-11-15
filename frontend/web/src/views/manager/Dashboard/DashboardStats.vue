<script setup lang="ts">
import { computed } from 'vue'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'

const props = defineProps<{
  contracts: (Contract & { days: number })[]
}>()

const stats = computed(() => {
  const approvedContracts = props.contracts.filter(c => c.approvalStatus === 'Approved')
  const total = approvedContracts.length
  const active = approvedContracts.filter(c => contractLifecycle(c) === 'active').length
  const expiring = approvedContracts.filter(c => contractLifecycle(c) === 'expiring').length
  const expired = approvedContracts.filter(c => contractLifecycle(c) === 'expired').length

  return [
    { label: 'Total Contracts', value: String(total), change: '+12.5%', positive: true,  sub: 'All time' },
    { label: 'Active',          value: String(active), change: '+8.3%',  positive: true,  sub: 'Currently running' },
    { label: 'Expiring Soon',    value: String(expiring), change: '+22.2%', positive: false, sub: 'Within 30 days' },
    { label: 'Expired',          value: String(expired), change: '-5.0%',  positive: true,  sub: 'Past end date' },
  ]
})
</script>

<template>
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
    <div
      v-for="s in stats"
      :key="s.label"
      class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm"
    >
      <p class="text-xs font-medium text-black/40 mb-3 uppercase tracking-wide">{{ s.label }}</p>
      <div class="flex items-end justify-between gap-2">
        <span class="text-3xl font-semibold text-black tabular-nums">{{ s.value }}</span>
        <span
          class="text-xs font-medium px-2 py-0.5 rounded-md mb-0.5 shrink-0"
          :class="s.positive ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'"
        >
          {{ s.change }}
        </span>
      </div>
      <p class="text-[10px] text-black/30 mt-1.5">{{ s.sub }}</p>
    </div>
  </div>
</template>
