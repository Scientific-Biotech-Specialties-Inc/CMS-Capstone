<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Building2, Truck, Search, LayoutGrid, List, Upload, Plus, Sparkles } from 'lucide-vue-next'
import { exportToExcel } from '@/utils/excelExport'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useVendorService } from '@/composables/useVendorService'
import { useAuth } from '@/composables/useAuth'
import { usePartners } from '@/composables/usePartners'
import PartnersGrid  from '@/views/admin/Partners/PartnersGrid.vue'
import PartnersTable from '@/views/admin/Partners/PartnersTable.vue'
import DeleteConfirmDialog from '@/views/admin/Partners/DeleteConfirmDialog.vue'
import type { Partner, TabKey } from '@/types/partner'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const router = useRouter()
const { success, error } = useToast()
const { hasPermission } = useAuth()
const { fetchPartners, fetchSuppliers } = useVendorService()
const { deletePartner, fetchIndustries } = usePartners()

const showAiSuggestionButton = computed(() => hasPermission('cms.ai.vendor_suggestions'))

const businessPartners = ref<Partner[]>([])
const suppliersData    = ref<Partner[]>([])
const loading          = ref(false)

async function loadData() {
  loading.value = true
  try {
    const [p, s] = await Promise.all([fetchPartners(), fetchSuppliers()])
    businessPartners.value = p
    suppliersData.value    = s
  } catch {
    error('Load failed', 'Could not load partners and suppliers from server.')
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

const activeTab      = ref<TabKey>('partners')
const viewMode       = ref<'card' | 'table'>('card')
const search         = ref('')
const regionFilter   = ref('All')
const statusFilter   = ref('All')
const industryFilter = ref('All')
const regions        = ['All', 'Luzon', 'Visayas', 'Mindanao']
const statusOptions  = ['All', 'Active', 'Inactive', 'Suspended'] as const

const partnersCount = computed(() => businessPartners.value.length)
const suppliersCount = computed(() => suppliersData.value.length)

const source = computed(() => activeTab.value === 'partners' ? businessPartners.value : suppliersData.value)

const industryOptions = ref<string[]>([])

// Full industry list comes from the API (not from whatever pages happened to
// load), so a new industry is never hidden by the current region filter.
const industries = computed(() => {
  const vals = source.value.map(p => p.industry).filter(Boolean)
  return ['All', ...[...new Set([...industryOptions.value, ...vals])].sort()]
})

async function loadIndustries(tab: TabKey) {
  const values = await fetchIndustries(tab)
  if (tab !== activeTab.value) return
  industryOptions.value = values
  if (industryFilter.value !== 'All' && !values.includes(industryFilter.value)) {
    industryFilter.value = 'All'
  }
}

const filtered = computed(() =>
  source.value.filter(p => {
    const q = search.value.toLowerCase()
    return (
      (!q || p.name.toLowerCase().includes(q) || p.industry.toLowerCase().includes(q)) &&
      (regionFilter.value   === 'All' || p.region   === regionFilter.value) &&
      (statusFilter.value   === 'All' || p.status   === statusFilter.value) &&
      (industryFilter.value === 'All' || p.industry === industryFilter.value)
    )
  })
)

const currentPage  = ref(1)
const itemsPerPage = 15
watch([search, regionFilter, statusFilter, industryFilter], () => { currentPage.value = 1 })
watch(activeTab, () => {
  search.value         = ''
  regionFilter.value   = 'All'
  statusFilter.value   = 'All'
  industryFilter.value = 'All'
  currentPage.value    = 1
  void loadIndustries(activeTab.value)
})
onMounted(() => void loadIndustries(activeTab.value))
const paginated = computed(() => filtered.value.slice((currentPage.value - 1) * itemsPerPage, currentPage.value * itemsPerPage))

const selectedIds     = ref<(string | number)[]>([])
const allPageSelected = computed(() =>
  source.value.length > 0 && source.value.every(p => selectedIds.value.includes(p.id))
)
function toggleSelectAll() {
  const ids = source.value.map(p => String(p.id))
  if (allPageSelected.value) selectedIds.value = selectedIds.value.filter(id => !ids.includes(String(id)))
  else ids.forEach(id => { if (!selectedIds.value.includes(id)) selectedIds.value.push(id) })
}
function toggleRow(id: string | number) {
  const i = selectedIds.value.indexOf(id)
  i >= 0 ? selectedIds.value.splice(i, 1) : selectedIds.value.push(id)
}

function getPartnerCode(p: Partner): string {
  if (activeTab.value === 'partners') {
    const raw = p.bpCode || String(p.id)
    if (raw.startsWith('BP-')) return raw.replace(/^(BP-)+/i, 'BP-')
    return `BP-${String(p.db_id || p.id).padStart(4, '0')}`
  }
  const raw = String(p.id)
  if (raw.startsWith('SP-')) return raw.replace(/^(SP-)+/i, 'SP-')
  return `SP-${String(p.db_id || p.id).padStart(4, '0')}`
}

function openDetail(p: Partner) {
  router.push(`/sales/partners/${getPartnerCode(p)}`)
}

function openEdit(p: Partner) {
  router.push(`/sales/partners/${getPartnerCode(p)}/edit`)
}

const showDelete   = ref(false)
const deleteTarget = ref<Partner | null>(null)
function openDelete(p: Partner) { deleteTarget.value = p; showDelete.value = true }
async function confirmDelete() {
  if (!deleteTarget.value) return
  const target = deleteTarget.value
  showDelete.value  = false
  deleteTarget.value = null
  try {
    if (target.db_id) {
      await deletePartner(activeTab.value, target.db_id)
      await loadData()
    }
    selectedIds.value = selectedIds.value.filter(id => id !== target.id)
    success('Entry deleted', `${target.name} has been removed.`)
  } catch {
    error('Delete failed', `Could not delete ${target.name}. Please try again.`)
  }
}

const showExportConfirm = ref(false)

function exportXLSX() {
  showExportConfirm.value = true
}

async function executeExport() {
  showExportConfirm.value = false
  const type = activeTab.value === 'partners' ? 'Business Partner' : 'Supplier'
  const rows = filtered.value.map(p => ({
    'ID':             p.id,
    'Name':           p.name,
    'Type':           type,
    'Industry':       p.industry,
    'Region':         p.region ?? '',
    'Status':         p.status,
    'Contact Person': p.contactPerson,
    'Phone':          p.phone,
    'Email':          p.email,
    'Address':        p.address,
    ...(activeTab.value === 'partners' ? { 'BP Code': p.bpCode ?? '' } : { 'TIN': p.tinNumber ?? '' }),
  }))
  await exportToExcel(`sbsi-${activeTab.value}.xlsx`, type === 'Business Partner' ? 'Partners' : 'Suppliers', rows)
  success('Export complete', `${filtered.value.length} records exported.`)
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-xl font-semibold text-black">Partners & Suppliers</h1>
        <p class="text-sm text-black/40 mt-0.5">View business relationships.</p>
      </div>
      <div class="flex items-center gap-2">
        <Button @click="exportXLSX" variant="outline" class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
          <Upload class="w-4 h-4" /> Export XLSX
        </Button>
        <Button v-if="showAiSuggestionButton" @click="router.push('/sales/vendor-suggestions')" class="h-9 gap-2 text-sm bg-black/4 hover:bg-black/10 text-black border border-black/5 rounded-lg shadow-sm font-medium transition-colors">
          <Sparkles class="w-4 h-4 text-black/60" /> AI Suggestions
        </Button>
        <Button id="add-partner-btn" v-if="hasPermission('cms.partners.create')" @click="router.push('/sales/partners/create?type=' + activeTab)" class="h-9 w-9 p-0 bg-brand-navy hover:bg-brand-dark text-white rounded-lg shadow-sm">
          <Plus class="w-5 h-5" />
        </Button>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1">
        <button @click="activeTab = 'partners'"
          class="flex items-center gap-2 px-4 py-1.5 text-sm rounded transition-all font-medium"
          :class="activeTab === 'partners' ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
          <Building2 class="w-3.5 h-3.5" /> Business Partners ({{ partnersCount }})
        </button>
        <button @click="activeTab = 'suppliers'"
          class="flex items-center gap-2 px-4 py-1.5 text-sm rounded transition-all font-medium"
          :class="activeTab === 'suppliers' ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
          <Truck class="w-3.5 h-3.5" /> Suppliers ({{ suppliersCount }})
        </button>
      </div>
      <div class="ml-auto flex items-center gap-0.5 bg-black/4 rounded-lg p-1">
        <button @click="viewMode = 'card'" class="p-2 rounded-md transition-all"
          :class="viewMode === 'card' ? 'bg-white shadow-sm text-black' : 'text-black/35 hover:text-black/60'">
          <LayoutGrid class="w-4 h-4" />
        </button>
        <button @click="viewMode = 'table'" class="p-2 rounded-md transition-all"
          :class="viewMode === 'table' ? 'bg-white shadow-sm text-black' : 'text-black/35 hover:text-black/60'">
          <List class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Status filter pills -->
    <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1 w-fit">
      <button v-for="s in statusOptions" :key="s" @click="statusFilter = s"
        class="px-3 py-1.5 text-sm rounded transition-all font-medium"
        :class="statusFilter === s ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
        {{ s }}
      </button>
    </div>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <Search class="w-3.5 h-3.5 text-black/30 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input v-model="search" type="text" :placeholder="`Search ${activeTab === 'partners' ? 'partners' : 'suppliers'}...`"
          class="w-full h-9 rounded-lg border border-black/10 bg-white pl-8.5 pr-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none transition" />
      </div>
      <select v-model="regionFilter"
        class="h-9 rounded-lg border border-black/10 bg-white px-3 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors">
        <option v-for="r in regions" :key="r" :value="r">{{ r === 'All' ? 'All Regions' : r }}</option>
      </select>
      <select v-model="industryFilter"
        class="h-9 rounded-lg border border-black/10 bg-white px-3 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors">
        <option v-for="ind in industries" :key="ind" :value="ind">{{ ind === 'All' ? 'All Industries' : ind }}</option>
      </select>
    </div>

    <PartnersGrid v-if="viewMode === 'card'" :partners="filtered" :active-tab="activeTab" :loading="loading"
      :can-edit="hasPermission('cms.partners.edit')" :can-delete="hasPermission('cms.partners.delete')"
      @open-detail="openDetail" @open-edit="openEdit" @open-delete="openDelete" />
    <PartnersTable v-else
      :paginated="paginated" :filtered="filtered" :active-tab="activeTab"
      :selected-ids="selectedIds" :all-page-selected="allPageSelected"
      :current-page="currentPage" :items-per-page="itemsPerPage"
      :can-edit="hasPermission('cms.partners.edit')" :can-delete="hasPermission('cms.partners.delete')" :loading="loading"
      @open-detail="openDetail" @open-edit="openEdit"
      @toggle-row="toggleRow" @toggle-select-all="toggleSelectAll" @open-delete="openDelete"
      @update:current-page="currentPage = $event"
    />

  </div>

  <DeleteConfirmDialog v-model:open="showDelete" :partner="deleteTarget" :active-tab="activeTab" @confirm="confirmDelete" />

  <ConfirmationDialog
    v-model:open="showExportConfirm"
    title="Export Partners & Suppliers"
    description="Are you sure you want to export the partners & suppliers list to an Excel (.xlsx) file?"
    confirm-label="Export"
    variant="default"
    @confirm="executeExport"
  />
</template>
