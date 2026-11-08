<script setup lang="ts">
import { computed, ref } from 'vue'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'
import type { ContractRequest } from '@/types/contractRequest'
import TimeFilterBar from '@/components/shared/TimeFilterBar.vue'
import YearFilterSelect from '@/components/shared/YearFilterSelect.vue'
import { useTimeFilter, isWithinRange, dateRangeFor } from '@/composables/useTimeFilter'
import RecentRequestsTable from './RecentRequestsTable.vue'
import ContractStatusPanel from './ContractStatusPanel.vue'
import SalesTrendChart from './SalesTrendChart.vue'
import SalesStatusChart from './SalesStatusChart.vue'
import SalesCategoryChart from './SalesCategoryChart.vue'

const props = defineProps<{
  contracts: (Contract & { days: number })[]
  requests: ContractRequest[]
}>()

const { filter, setFilter } = useTimeFilter('month')
const selectedYear = ref<number | null>(null)

function handleFilterChange(option: typeof filter.value) {
  selectedYear.value = null
  setFilter(option)
}

function handleYearChange(year: number | null) {
  selectedYear.value = year
}

const availableYears = computed(() => {
  const years = new Set<number>()
  props.contracts.forEach(c => {
    if (!c.startDate) return
    years.add(new Date(c.startDate).getFullYear())
  })
  return Array.from(years).sort((a, b) => b - a)
})

const scopedContracts = computed(() => {
  const range = dateRangeFor(filter.value)
  return props.contracts.filter(c => isWithinRange(c.startDate, range))
})

const scopedRequests = computed(() => {
  const range = dateRangeFor(filter.value)
  return props.requests.filter(r => isWithinRange(r.requestDate, range))
})

const recentRequests = computed(() => props.requests.slice(0, 6))

const statCards = computed(() => [
  { label: 'My Contracts',    value: scopedContracts.value.filter(c => c.approvalStatus === 'Approved').length },
  { label: 'Pending',         value: scopedRequests.value.filter(r => r.status === 'Pending' || r.status === 'Under Review').length },
  { label: 'Expiring Soon',   value: scopedContracts.value.filter(c => contractLifecycle(c) === 'expiring').length },
  { label: 'Approved',        value: scopedRequests.value.filter(r => r.status === 'Approved').length },
])
</script>

<template>
  <div class="space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-3">
      <p class="text-sm text-black/40">An overview of your own contracts and requests.</p>
      <div class="flex items-center gap-2.5">
        <TimeFilterBar :model-value="filter" @update:model-value="handleFilterChange" />
        <YearFilterSelect :model-value="selectedYear" :available-years="availableYears" @update:model-value="handleYearChange" />
      </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
      <div v-for="card in statCards" :key="card.label"
        class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
        <p class="text-xs font-medium text-black/40 uppercase tracking-wide mb-3">{{ card.label }}</p>
        <p class="text-3xl font-semibold tabular-nums text-black">{{ card.value }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <div class="xl:col-span-3">
        <SalesTrendChart :contracts="contracts" :time-filter="filter" :year="selectedYear" />
      </div>
      <div class="xl:col-span-2">
        <SalesStatusChart :requests="scopedRequests" />
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <div class="xl:col-span-3">
        <SalesCategoryChart :contracts="scopedContracts" />
      </div>
      <div class="xl:col-span-2">
        <ContractStatusPanel :contracts="contracts" />
      </div>
    </div>

    <RecentRequestsTable :requests="recentRequests" />

  </div>
</template>
