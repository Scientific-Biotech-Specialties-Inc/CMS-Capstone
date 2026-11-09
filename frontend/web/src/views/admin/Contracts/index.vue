<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Upload, Plus } from 'lucide-vue-next'
import { exportToExcel } from '@/utils/excelExport'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import ContractsTable       from './ContractsTable.vue'
import ConfirmationDialog   from '@/components/shared/ConfirmationDialog.vue'
import { contractLifecycle, remainingDays } from '@/types/contract'
import type { ContractWithDays, FilterTab, StatusFilter } from '@/types/contract'
import { sortContractsForAll } from '@/utils/contractSort'

const router = useRouter()
const { success, error } = useToast()
const { state: authState, hasPermission } = useAuth()
const { state: cacheState, fetchContracts: fetchContractsCached } = useApiCache()

const contracts = computed(() => cacheState.contracts || [])
const loading   = computed(() => cacheState.contractsLoading)

const activeFilter   = ref<FilterTab>('all')
const searchQuery    = ref('')
const categoryFilter  = ref('')
const regionFilter    = ref('')
const statusFilter    = ref<StatusFilter>('')
const startDateFilter = ref('')
const endDateFilter   = ref('')
const currentPage    = ref(1)
const itemsPerPage   = 15

const showDeleteConfirm = ref(false)
const contractIdToDelete = ref<string | null>(null)

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
  { label: 'Total Contracts', value: statCards.value.total,    valueClass: 'text-black', change: '+2.1%', positive: true },
  { label: 'Active',          value: statCards.value.active,   valueClass: 'text-black', change: '+4.0%', positive: true },
  { label: 'Inactive',        value: statCards.value.inactive, valueClass: 'text-black', change: '+0.5%', positive: true },
  { label: 'Expiring Soon',   value: statCards.value.expiring, valueClass: 'text-black', change: '+5.2%', positive: true },
  { label: 'Expired',         value: statCards.value.expired,  valueClass: 'text-black', change: '-1.3%', positive: false },
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
    await fetchContractsCached(undefined, true)
  } catch (err) {
    console.error('Failed to fetch contracts:', err)
    error('Fetch failed', 'Could not load contracts from server.')
  }
}

onMounted(() => {
  fetchContracts()
})

watch([activeFilter, categoryFilter, regionFilter, statusFilter, startDateFilter, endDateFilter, searchQuery], () => {
  currentPage.value = 1
})

function openDetail(c: ContractWithDays) {
  router.push(`/admin/contracts/${c.id}`)
}

function openEdit(c: ContractWithDays) {
  router.push(`/admin/contracts/${c.id}?edit=1`)
}

const deletingContract = ref(false)

function triggerDelete(id: string) {
  contractIdToDelete.value = id
  showDeleteConfirm.value = true
}

async function confirmDelete() {
  if (!contractIdToDelete.value || !authState.user) return
  const id = contractIdToDelete.value
  const contract = contracts.value.find(c => c.id === id)
  if (!contract) return

  const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string
  deletingContract.value = true

  try {
    const res = await fetch(`${apiBase}/contracts/${id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${authState.token || ''}`,
        'Accept': 'application/json'
      }
    })
    const json = await res.json()
    if (res.ok) {
      showDeleteConfirm.value = false
      contractIdToDelete.value = null
      await fetchContracts()
      success('Contract removed', `${contract.businessPartner}'s contract has been deleted.`)
    } else {
      error('Delete failed', json.message || 'Could not delete contract.')
    }
  } catch (err) {
    console.error('Failed to delete contract:', err)
    error('Delete failed', 'Connection to contract service failed.')
  } finally {
    deletingContract.value = false
  }
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
    'Remaining Days': c.days, 'Approval Status': c.approvalStatus,
    'Workflow Status': c.workflowStatus ?? '', 'Sales Rep': c.createdBy,
  }))
  await exportToExcel('sbsi-contracts.xlsx', 'Contracts', rows)
  success('Export complete', `${filtered.value.length} contracts exported.`)
}
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Header -->
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-xl font-semibold text-black">Contracts</h1>
        <p class="text-sm text-black/40 mt-0.5">Manage and monitor all contract agreements.</p>
      </div>
      <div class="flex items-center gap-2">
        <Button @click="exportXLSX" variant="outline" class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
          <Upload class="w-4 h-4" /> Export XLSX
        </Button>
        <Button v-if="hasPermission('cms.contracts.create')" @click="router.push('/admin/contracts/create')" class="group h-9 w-9 p-0 bg-brand-navy hover:bg-brand-dark text-white rounded-lg shadow-sm transition-all duration-200 hover:scale-105 active:scale-95 flex items-center justify-center">
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
    <ContractsTable
      :loading="loading"
      :paginated="paginated"
      :filtered="filtered"
      :total-items="totalItems"
      :active-filter="activeFilter"
      :search-query="searchQuery"
      :category-filter="categoryFilter"
      :region-filter="regionFilter"
      :status-filter="statusFilter"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      :start-date-filter="startDateFilter"
      :end-date-filter="endDateFilter"
      @open-detail="openDetail"
      @open-edit="openEdit"
      @delete="triggerDelete"
      @update:active-filter="activeFilter = $event"
      @update:search-query="searchQuery = $event"
      @update:category-filter="categoryFilter = $event"
      @update:region-filter="regionFilter = $event"
      @update:status-filter="statusFilter = $event"
      @update:current-page="currentPage = $event"
      @update:startDateFilter="startDateFilter = $event"
      @update:endDateFilter="endDateFilter = $event"
    />

    <ConfirmationDialog
      v-model:open="showDeleteConfirm"
      title="Delete Contract"
      description="Are you sure you want to permanently delete this contract? This action cannot be undone and the contract data will be removed from the system."
      confirm-label="Delete"
      variant="destructive"
      :loading="deletingContract"
      @confirm="confirmDelete"
    />

    <ConfirmationDialog
      v-model:open="showExportConfirm"
      title="Export Contracts"
      description="Are you sure you want to export the contracts to an Excel (.xlsx) file?"
      confirm-label="Export"
      variant="default"
      @confirm="executeExport"
    />
  </div>
</template>
