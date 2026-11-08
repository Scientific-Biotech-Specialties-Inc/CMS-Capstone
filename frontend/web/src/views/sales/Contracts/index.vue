<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Upload, Plus } from 'lucide-vue-next'
import { exportToExcel } from '@/utils/excelExport'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import SalesContractsTable  from './SalesContractsTable.vue'
import { contractLifecycle, remainingDays } from '@/types/contract'
import type { ContractWithDays, StatusFilter, FilterTab } from '@/types/contract'
import { sortContractsForAll } from '@/utils/contractSort'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const router = useRouter()
const { success, error } = useToast()
const { state: authState, hasPermission } = useAuth()

import { useApiCache } from '@/composables/useApiCache'

const { state: cacheState, fetchContracts: fetchContractsCached, deleteContractFromCache, updateContractInCache } = useApiCache()

const contracts = computed(() => cacheState.contracts || [])
const loading   = computed(() => cacheState.contractsLoading)

const activeFilter   = ref<FilterTab>('all')
const statusFilter   = ref<StatusFilter>('')
const searchQuery    = ref('')
const categoryFilter  = ref('')
const regionFilter    = ref('')
const startDateFilter = ref('')
const endDateFilter   = ref('')
const currentPage    = ref(1)
const itemsPerPage   = 15

const withDays = computed(() =>
  contracts.value.map(c => ({ ...c, days: remainingDays(c.endDate) }))
)

const statCards = computed(() => {
  return {
    total:    cacheState.contractsStats?.total ?? withDays.value.length,
    active:   cacheState.contractsStats?.active ?? withDays.value.filter(c => contractLifecycle(c) === 'active').length,
    inactive: cacheState.contractsStats?.inactive ?? withDays.value.filter(c => contractLifecycle(c) === 'inactive').length,
    expiring: cacheState.contractsStats?.expiring ?? withDays.value.filter(c => contractLifecycle(c) === 'expiring').length,
    expired:  cacheState.contractsStats?.expired ?? withDays.value.filter(c => contractLifecycle(c) === 'expired').length,
  }
})

const statCardList = computed(() => [
  { label: 'My Contracts',  value: statCards.value.total,    valueClass: 'text-black', change: '+2.1%', positive: true },
  { label: 'Active',        value: statCards.value.active,   valueClass: 'text-black', change: '+4.0%', positive: true },
  { label: 'Inactive',      value: statCards.value.inactive, valueClass: 'text-black', change: '+0.5%', positive: true },
  { label: 'Expiring Soon', value: statCards.value.expiring, valueClass: 'text-black', change: '+5.2%', positive: true },
  { label: 'Expired',       value: statCards.value.expired,  valueClass: 'text-black', change: '-1.3%', positive: false },
])

const approvalStatuses = new Set(['Pending', 'Approved', 'Rejected'])

const filtered = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const cat = categoryFilter.value
  const reg = regionFilter.value
  const sf = statusFilter.value
  const startD = startDateFilter.value
  const endD = endDateFilter.value

  const list = withDays.value.filter(c => {
    const bySearch = !q
      || c.id.toLowerCase().includes(q)
      || (c.contractCode && c.contractCode.toLowerCase().includes(q))
      || c.businessPartner.toLowerCase().includes(q)
      || c.category.toLowerCase().includes(q)
      || c.itemCode.toLowerCase().includes(q)

    const byTab = activeFilter.value === 'all' || contractLifecycle(c) === activeFilter.value

    const byCategory = !cat || c.category === cat
    const byRegion = !reg || c.region === reg
    const byStatus = !sf
      ? true
      : approvalStatuses.has(sf)
        ? c.approvalStatus === sf
        : c.workflowStatus === sf

    const byStartDate = !startD || (c.startDate && c.startDate >= startD)
    const byEndDate = !endD || (c.endDate && c.endDate <= endD)

    return bySearch && byTab && byCategory && byRegion && byStatus && byStartDate && byEndDate
  })

  return sortContractsForAll(list)
})

const totalItems = computed(() => filtered.value.length)

const paginated = computed(() =>
  filtered.value.slice((currentPage.value - 1) * itemsPerPage, currentPage.value * itemsPerPage)
)

async function fetchContracts() {
  if (!authState.user) return
  try {
    const userId = authState.user?.id
    await fetchContractsCached(userId, true)
  } catch {
    error('Network error', 'Could not reach the server.')
  }
}

onMounted(fetchContracts)

watch([activeFilter, statusFilter, categoryFilter, regionFilter, startDateFilter, endDateFilter, searchQuery], () => {
  currentPage.value = 1
})

function openDetail(c: ContractWithDays) {
  router.push(`/sales/contracts/${c.id}`)
}

const showExportConfirm = ref(false)

function exportXLSX() {
  showExportConfirm.value = true
}

async function executeExport() {
  showExportConfirm.value = false
  const rows = filtered.value.map(c => ({
    'Contract ID': c.id, 'Business Partner': c.businessPartner, 'Category': c.category,
    'Item Code': c.itemCode, 'Description': c.description, 'Serial No': c.serialNo,
    'Region': c.region, 'Start Date': c.startDate, 'End Date': c.endDate,
    'Remaining Days': c.days, 'Approval Status': c.approvalStatus, 'Workflow Status': c.workflowStatus ?? '',
  }))
  await exportToExcel('my-contracts.xlsx', 'My Contracts', rows)
  success('Export complete', `${filtered.value.length} contracts exported.`)
}

const showDeleteConfirm = ref(false)
const contractToDelete = ref<string | null>(null)

function openDelete(id: string) {
  contractToDelete.value = id
  showDeleteConfirm.value = true
}

async function executeDelete() {
  if (!contractToDelete.value) return
  showDeleteConfirm.value = false
  try {
    const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string
    const res = await fetch(`${apiBase}/contracts/${contractToDelete.value}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      }
    })
    if (!res.ok) throw new Error()
    deleteContractFromCache(contractToDelete.value)
    success('Contract deleted', 'The contract has been successfully deleted.')
    await fetchContracts()
  } catch (e) {
    error('Delete failed', 'Could not delete the contract.')
  }
}

const showApproveConfirm = ref(false)
const contractToApprove = ref<string | null>(null)

function openApprove(id: string) {
  contractToApprove.value = id
  showApproveConfirm.value = true
}

async function executeApprove() {
  if (!contractToApprove.value) return
  showApproveConfirm.value = false
  try {
    const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string
    const res = await fetch(`${apiBase}/contracts/${contractToApprove.value}/status`, {
      method: 'PATCH',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
      body: JSON.stringify({ approval_status: 'Approved', workflow_status: 'SBSI Review' })
    })
    if (!res.ok) throw new Error()
    const { data: approved } = await res.json()
    updateContractInCache(contractToApprove.value, { approvalStatus: approved.approval_status, workflowStatus: approved.workflow_status, lifecycleStatus: approved.lifecycle_status })
    success('Contract approved', 'The contract has been approved.')
    await fetchContracts()
  } catch (e) {
    error('Approve failed', 'Could not approve the contract.')
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Header -->
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-xl font-semibold text-black">My Contracts</h1>
        <p class="text-sm text-black/40 mt-0.5">View all contracts assigned to you.</p>
      </div>
      <div class="flex items-center gap-2">
        <Button @click="exportXLSX" variant="outline"
          class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
          <Upload class="w-4 h-4" /> Export XLSX
        </Button>
        <Button v-if="hasPermission('cms.contracts.create')" @click="router.push('/sales/contracts/create')" class="group h-9 w-9 p-0 bg-brand-navy hover:bg-brand-dark text-white rounded-lg shadow-sm transition-all duration-200 hover:scale-105 active:scale-95">
          <Plus class="w-5 h-5 transition-transform duration-300 group-hover:rotate-90" />
        </Button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <template v-if="loading && contracts.length === 0">
        <div v-for="i in 5" :key="i"
          class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
          <div class="h-3.5 w-24 bg-black/5 animate-pulse rounded mb-4"></div>
          <div class="flex items-end justify-between gap-2">
            <div class="h-8 w-12 bg-black/5 animate-pulse rounded"></div>
            <div class="h-5 w-10 bg-black/5 animate-pulse rounded mb-0.5"></div>
          </div>
        </div>
      </template>
      <template v-else>
        <div
          v-for="card in statCardList"
          :key="card.label"
          class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm"
        >
          <p class="text-xs font-medium text-black/40 uppercase tracking-wide mb-3">{{ card.label }}</p>
          <div class="flex items-end justify-between gap-2">
            <span class="text-3xl font-semibold tabular-nums" :class="card.valueClass">{{ card.value }}</span>
            <span class="text-xs font-medium px-2 py-0.5 rounded-md mb-0.5 shrink-0"
              :class="card.positive ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'">
              {{ card.change }}
            </span>
          </div>
        </div>
      </template>
    </div>

    <!-- Table -->
    <SalesContractsTable
      :loading="loading"
      :paginated="paginated"
      :filtered="filtered"
      :total-items="totalItems"
      :active-filter="activeFilter"
      :status-filter="statusFilter"
      :category-filter="categoryFilter"
      :region-filter="regionFilter"
      :search-query="searchQuery"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      :start-date-filter="startDateFilter"
      :end-date-filter="endDateFilter"
      @open-detail="openDetail"
      @approve="openApprove"
      @delete="openDelete"
      @update:active-filter="activeFilter = $event"
      @update:status-filter="statusFilter = $event"
      @update:category-filter="categoryFilter = $event"
      @update:region-filter="regionFilter = $event"
      @update:search-query="searchQuery = $event"
      @update:current-page="currentPage = $event"
      @update:startDateFilter="startDateFilter = $event"
      @update:endDateFilter="endDateFilter = $event"
    />

  </div>

  <ConfirmationDialog
    v-model:open="showDeleteConfirm"
    title="Delete Contract"
    description="Are you sure you want to delete this contract? This action cannot be undone."
    confirm-label="Delete"
    variant="destructive"
    @confirm="executeDelete"
  />

  <ConfirmationDialog
    v-model:open="showApproveConfirm"
    title="Approve Contract"
    description="Are you sure you want to approve this contract? This will change the status to Approved and advance the workflow."
    confirm-label="Approve"
    variant="default"
    @confirm="executeApprove"
  />

  <ConfirmationDialog
    v-model:open="showExportConfirm"
    title="Export Contracts"
    description="Are you sure you want to export the contracts to an Excel (.xlsx) file?"
    confirm-label="Export"
    variant="default"
    @confirm="executeExport"
  />
</template>
