<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Contract } from '@/types/contract'
import TimeFilterBar from '@/components/shared/TimeFilterBar.vue'
import YearFilterSelect from '@/components/shared/YearFilterSelect.vue'
import { useTimeFilter, isWithinRange, dateRangeFor } from '@/composables/useTimeFilter'
import DashboardStats from './DashboardStats.vue'
import ContractTrendChart from './ContractTrendChart.vue'
import ContractStatusChart from './ContractStatusChart.vue'
import ContractsByRegionChart from './ContractsByRegionChart.vue'
import BusinessPartnersOverviewChart from './BusinessPartnersOverviewChart.vue'
import RecentContractsTable from './RecentContractsTable.vue'
import ExpiringContractsList from './ExpiringContractsList.vue'

interface PartnerItem {
  id: string
  name: string
  region: string
  status: string
  type: 'Partner' | 'Supplier'
  createdAt: string | null
}

const props = defineProps<{
  contracts: (Contract & { days: number })[]
  partners: PartnerItem[]
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
</script>

<template>
  <div class="space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-3">
      <p class="text-sm text-black/40">Your contract overview.</p>
      <div class="flex items-center gap-2.5">
        <TimeFilterBar :model-value="filter" @update:model-value="handleFilterChange" />
        <YearFilterSelect :model-value="selectedYear" :available-years="availableYears" @update:model-value="handleYearChange" />
      </div>
    </div>

    <DashboardStats :contracts="scopedContracts" />

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <div class="xl:col-span-3"><ContractTrendChart :contracts="contracts" :time-filter="filter" :year="selectedYear" /></div>
      <div class="xl:col-span-2"><ContractStatusChart :contracts="scopedContracts" /></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <div class="xl:col-span-3"><RecentContractsTable :contracts="scopedContracts" /></div>
      <div class="xl:col-span-2"><ExpiringContractsList :contracts="contracts" /></div>
    </div>

    <ContractsByRegionChart :contracts="scopedContracts" />

    <!-- Business Partners & Suppliers: status breakdown + new additions over time -->
    <BusinessPartnersOverviewChart :partners="partners" :time-filter="filter" />

  </div>
</template>
