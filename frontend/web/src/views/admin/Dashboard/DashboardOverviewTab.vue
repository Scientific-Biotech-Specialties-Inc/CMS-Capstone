<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Contract } from '@/types/contract'
import TimeFilterBar from '@/components/shared/TimeFilterBar.vue'
import YearFilterSelect from '@/components/shared/YearFilterSelect.vue'
import { useTimeFilter, isWithinRange, dateRangeFor } from '@/composables/useTimeFilter'
import DashboardStats from './DashboardStats.vue'
import ContractTrendChart from './ContractTrendChart.vue'
import ContractStatusChart from './ContractStatusChart.vue'
import RenewalPipelineChart from './RenewalPipelineChart.vue'
import UserPartnerAnalyticsChart from './UserPartnerAnalyticsChart.vue'
import ContractsByRegionChart from './ContractsByRegionChart.vue'
import BusinessPartnersOverviewChart from './BusinessPartnersOverviewChart.vue'
import RecentContractsTable from './RecentContractsTable.vue'
import AuditLogList from './AuditLogList.vue'
import UsersList from './UsersList.vue'

type Role   = 'Admin' | 'Manager' | 'Sales'
type Status = 'Active' | 'Inactive'

interface DashUser { id: string; name: string; email: string; role: Role; status: Status }
interface PartnerItem { id: string; name: string; region: string; status: string; type: 'Partner' | 'Supplier'; createdAt: string | null }
interface AuditLog { action: string; user: string; timestamp: string; type: 'create' | 'update' | 'approve' | 'delete' }

const props = defineProps<{
  contracts: (Contract & { days: number })[]
  users: DashUser[]
  partners: PartnerItem[]
  auditLogs: AuditLog[]
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

// Time-filtered contract subset feeding the KPI/status/region charts on this
// tab. The trend chart below reads the same filter/year state directly.
const scopedContracts = computed(() => {
  const range = dateRangeFor(filter.value)
  return props.contracts.filter(c => isWithinRange(c.startDate, range))
})
</script>

<template>
  <div class="space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-3">
      <p class="text-sm text-black/40">System-wide contracts, vendors, and workflow analytics.</p>
      <div class="flex items-center gap-2.5">
        <TimeFilterBar :model-value="filter" @update:model-value="handleFilterChange" />
        <YearFilterSelect :model-value="selectedYear" :available-years="availableYears" @update:model-value="handleYearChange" />
      </div>
    </div>

    <!-- Stats Summary cards (scoped to selected time window) -->
    <DashboardStats :contracts="scopedContracts" :employees-count="users.length" />

    <!-- Monthly Trend chart + Status donut chart -->
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <div class="xl:col-span-3">
        <ContractTrendChart :contracts="contracts" :time-filter="filter" :year="selectedYear" />
      </div>
      <div class="xl:col-span-2">
        <ContractStatusChart :contracts="scopedContracts" />
      </div>
    </div>

    <!-- Renewal Pipeline (Full Width Row) -->
    <RenewalPipelineChart :contracts="scopedContracts" />

    <!-- User Roles + Business Partners / Suppliers Regional Analytics -->
    <UserPartnerAnalyticsChart :users="users" :partners="partners" />

    <!-- Business Partners & Suppliers: status breakdown + new additions over time -->
    <BusinessPartnersOverviewChart :partners="partners" :time-filter="filter" />

    <!-- Grouped Category and Region Distribution chart -->
    <ContractsByRegionChart :contracts="scopedContracts" />

    <!-- Recent Contracts + Audit Logs -->
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <div class="xl:col-span-3">
        <RecentContractsTable :contracts="scopedContracts" />
      </div>
      <div class="xl:col-span-2">
        <AuditLogList :logs="auditLogs" />
      </div>
    </div>

    <!-- Total User list -->
    <UsersList :users="users" />

  </div>
</template>
