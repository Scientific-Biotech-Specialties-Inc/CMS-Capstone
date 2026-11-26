<script setup lang="ts">
import { computed } from 'vue'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'
import { TIME_FILTER_LABELS, type TimeFilterOption } from '@/composables/useTimeFilter'
import { computeContractStatDeltas } from '@/utils/statDelta'
import StatDeltaPill from '@/components/shared/StatDeltaPill.vue'

const props = defineProps<{
  contracts: (Contract & { days: number })[]
  allContracts?: (Contract & { days: number })[]
  timeFilter?: TimeFilterOption
  selectedYear?: number | null
}>()

const periodCaption = computed(() => {
  if (props.selectedYear !== null && props.selectedYear !== undefined) {
    return `Year ${props.selectedYear}`
  }
  return TIME_FILTER_LABELS[props.timeFilter ?? 'all']
})

const stats = computed(() => {
  const approvedContracts = props.contracts.filter(c => c.approvalStatus === 'Approved')
  const total = approvedContracts.length
  const active = approvedContracts.filter(c => contractLifecycle(c) === 'active').length
  const expiring = approvedContracts.filter(c => contractLifecycle(c) === 'expiring').length
  const expired = approvedContracts.filter(c => contractLifecycle(c) === 'expired').length

  const deltas = computeContractStatDeltas(props.allContracts ?? props.contracts, {
    filter: props.timeFilter ?? 'all',
    selectedYear: props.selectedYear ?? null,
    approvedOnly: true,
  })

  return [
    { label: 'Total Contracts', value: String(total), delta: deltas.total, sub: periodCaption.value },
    { label: 'Active',          value: String(active), delta: deltas.active, sub: 'Currently running' },
    { label: 'Expiring Soon',   value: String(expiring), delta: deltas.expiring, sub: 'Within 30 days' },
    { label: 'Expired',         value: String(expired), delta: deltas.expired, sub: 'Past end date' },
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
        <StatDeltaPill :delta="s.delta" />
      </div>
      <p class="text-[10px] text-black/30 mt-1.5">{{ s.sub }}</p>
    </div>
  </div>
</template>
