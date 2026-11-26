<script setup lang="ts">
import { computed } from 'vue'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'
import type { TimeFilterOption } from '@/composables/useTimeFilter'
import { computeContractStatDeltas, computeUserStatDeltas } from '@/utils/statDelta'
import StatDeltaPill from '@/components/shared/StatDeltaPill.vue'

const props = defineProps<{
  contracts: (Contract & { days: number })[]
  allContracts?: (Contract & { days: number })[]
  users?: { role?: string; createdAt?: string | null }[]
  employeesCount: number
  timeFilter?: TimeFilterOption
  selectedYear?: number | null
}>()

const statCards = computed(() => {
  const total = props.contracts.length
  const expiring = props.contracts.filter(c => contractLifecycle(c) === 'expiring').length
  const expired = props.contracts.filter(c => contractLifecycle(c) === 'expired').length
  const totalEmployees = props.employeesCount

  const contractDeltas = computeContractStatDeltas(props.allContracts ?? props.contracts, {
    filter: props.timeFilter ?? 'all',
    selectedYear: props.selectedYear ?? null,
  })
  const userDeltas = computeUserStatDeltas(props.users ?? [], [], {
    filter: props.timeFilter ?? 'all',
    selectedYear: props.selectedYear ?? null,
  })

  return [
    { label: 'Total Contracts', value: String(total), delta: contractDeltas.total },
    { label: 'Expiring Soon', value: String(expiring), delta: contractDeltas.expiring },
    { label: 'Expired', value: String(expired), delta: contractDeltas.expired },
    { label: 'Total Employees', value: String(totalEmployees), delta: userDeltas.total },
  ]
})
</script>

<template>
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
    <div
      v-for="card in statCards"
      :key="card.label"
      class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm"
    >
      <p class="text-xs font-medium text-black/40 mb-3 uppercase tracking-wide">{{ card.label }}</p>
      <div class="flex items-end justify-between gap-2">
        <span class="text-3xl font-semibold text-black tabular-nums">{{ card.value }}</span>
        <StatDeltaPill :delta="card.delta" />
      </div>
    </div>
  </div>
</template>
