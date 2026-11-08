<script setup lang="ts">
import { computed } from 'vue'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'

const props = defineProps<{
  contracts: (Contract & { days: number })[]
  employeesCount: number
}>()

const statCards = computed(() => {
  const total = props.contracts.length
  const expiring = props.contracts.filter(c => contractLifecycle(c) === 'expiring').length
  const expired = props.contracts.filter(c => contractLifecycle(c) === 'expired').length
  const totalEmployees = props.employeesCount

  return [
    { label: 'Total Contracts',  value: String(total), change: '+2.1%', positive: true  },
    { label: 'Expiring Soon',    value: String(expiring), change: '+5.2%', positive: true  },
    { label: 'Expired',          value: String(expired), change: '-1.3%', positive: false },
    { label: 'Total Employees',  value: String(totalEmployees), change: '+4.0%', positive: true  },
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
        <span
          class="text-xs font-medium px-2 py-0.5 rounded-md mb-0.5 shrink-0"
          :class="card.positive ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'"
        >
          {{ card.change }}
        </span>
      </div>
    </div>
  </div>
</template>
