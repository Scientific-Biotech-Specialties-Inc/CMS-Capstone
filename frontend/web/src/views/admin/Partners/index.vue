<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { Building2, Truck, Search, LayoutGrid, List, Plus, Upload, Sparkles } from 'lucide-vue-next'
import { exportToExcel } from '@/utils/excelExport'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { usePartners } from '@/composables/usePartners'
import PartnersGrid       from './PartnersGrid.vue'
import PartnersTable      from './PartnersTable.vue'
import DeleteConfirmDialog from './DeleteConfirmDialog.vue'
import TablePagination     from '@/components/shared/TablePagination.vue'
import type { Partner, TabKey } from '@/types/partner'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const router = useRouter()
const { success, error } = useToast()
const { hasPermission } = useAuth()

const showAiSuggestionButton = computed(() => hasPermission('cms.ai.vendor_suggestions'))

const {
  partners,
  loading,
  totalItems,
  fetchPartners,
  fetchIndustries,
  deletePartner
} = usePartners()



const activeTab      = ref<TabKey>('partners')
const viewMode       = ref<'card' | 'table'>('card')
const search         = ref('')
const regionFilter   = ref('All')
const statusFilter   = ref('All')
const industryFilter = ref('All')
const regions        = ['All', 'Luzon', 'Visayas', 'Mindanao']
const statusOptions  = ['All', 'Active', 'Inactive', 'Suspended'] as const

const partnersCount = ref(0)
const suppliersCount = ref(0)



const industryOptions = ref<string[]>([])

// The dropdown must list every industry in the database, regardless of the
// region/status/search currently applied — never derive it from `partners`
// alone (those rows are already filtered server-side). The values from the
// loaded page are only merged in as an additive safety net.
const industries = computed(() => {
  const pageValues = partners.value.map(p => p.industry).filter(Boolean)
  return ['All', ...[...new Set([...industryOptions.value, ...pageValues])].sort()]
})

async function loadIndustries(tab: TabKey) {
  const values = await fetchIndustries(tab)
  // Ignore responses for a tab the user has already switched away from.
  if (tab !== activeTab.value) return
  industryOptions.value = values
  if (industryFilter.value !== 'All' && !values.includes(industryFilter.value)) {
    industryFilter.value = 'All'
  }
}

// Status, industry and region are filtered by the API so they apply to the
// whole dataset, not just the rows of the current page.
const filtered = computed(() => partners.value)

const currentPage  = ref(1)
const itemsPerPage = 15

watch([search, regionFilter, statusFilter, industryFilter], () => { currentPage.value = 1 })
watch(activeTab, () => {
  search.value         = ''
  regionFilter.value   = 'All'
  statusFilter.value   = 'All'
  industryFilter.value = 'All'
  currentPage.value    = 1
  loadIndustries(activeTab.value)
})

watch(
  [activeTab, currentPage, search, regionFilter, statusFilter, industryFilter],
  async ([tab, page, q, region, status, industry]) => {
    await fetchPartners(tab, page, itemsPerPage, q, region, status, industry)
  }
)

watch([activeTab, totalItems], () => {
  if (activeTab.value === 'partners') {
    partnersCount.value = totalItems.value
  } else {
    suppliersCount.value = totalItems.value
  }
}, { immediate: true })

onMounted(async () => {
  try {
    await fetchPartners('suppliers', 1, 1)
    suppliersCount.value = totalItems.value
    await fetchPartners('partners', 1, itemsPerPage)
    partnersCount.value = totalItems.value
    await loadIndustries('partners')
  } catch (err) {
    console.error(err)
  }
})

// A partner/supplier created in another tab (or just before returning to this
// page) must appear in the Industry filter without a full reload.
function refreshIndustries() {
  void loadIndustries(activeTab.value)
}
onMounted(() => window.addEventListener('focus', refreshIndustries))
onBeforeUnmount(() => window.removeEventListener('focus', refreshIndustries))

const selectedIds     = ref<(string | number)[]>([])
const allPageSelected = computed(() =>
  partners.value.length > 0 && partners.value.every(p => selectedIds.value.includes(p.id))
)
function toggleSelectAll() {
  const ids = partners.value.map(p => String(p.id))
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
  router.push(`/admin/partners/${getPartnerCode(p)}`)
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
      await fetchPartners(
        activeTab.value,
        currentPage.value,
        itemsPerPage,
        search.value,
        regionFilter.value,
        statusFilter.value,
        industryFilter.value
      )
      await loadIndustries(activeTab.value)
    }
    selectedIds.value = selectedIds.value.filter(id => id !== target.id)
    success('Entry deleted', `${target.name} has been removed.`)
  } catch {
    error('Delete failed', `Could not delete ${target.name}. Please try again.`)
  }
}

function openEdit(p: Partner) {
  router.push(`/admin/partners/${getPartnerCode(p)}/edit`)
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
        <p class="text-sm text-black/40 mt-0.5">Manage business relationships.</p>
      </div>
      <div class="flex items-center gap-2">
        <Button @click="exportXLSX" variant="outline" class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
          <Upload class="w-4 h-4" /> Export XLSX
        </Button>
        <Button v-if="showAiSuggestionButton" @click="router.push('/admin/vendor-suggestions')" variant="outline"
          class="h-9 gap-2 text-sm font-medium border-brand-blue/30 text-brand-blue hover:bg-brand-blue/5">
          <Sparkles class="w-4 h-4" /> AI Suggestion
        </Button>
        <Button id="add-partner-btn" v-if="hasPermission('cms.partners.create')" @click="router.push('/admin/partners/create?type=' + activeTab)" class="h-9 w-9 p-0 bg-brand-navy hover:bg-brand-dark text-white rounded-lg shadow-sm">
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

    <template v-if="viewMode === 'card'">
      <PartnersGrid :partners="filtered" :active-tab="activeTab" :loading="loading"
        :can-delete="hasPermission('cms.partners.delete')"
        @open-detail="openDetail" @open-delete="openDelete" />

      <div v-if="!loading && totalItems > 0" class="flex justify-center pt-1">
        <TablePagination :current-page="currentPage" :total-items="totalItems"
          :items-per-page="itemsPerPage" :current-page-items-count="partners.length"
          @update:current-page="currentPage = $event" />
      </div>
    </template>
    <PartnersTable v-else
      :paginated="filtered" :filtered="filtered" :active-tab="activeTab"
      :total-items="totalItems"
      :selected-ids="selectedIds" :all-page-selected="allPageSelected"
      :current-page="currentPage" :items-per-page="itemsPerPage"
      :can-edit="hasPermission('cms.partners.edit')" :can-delete="hasPermission('cms.partners.delete')" :loading="loading"
      @open-detail="openDetail" @open-edit="openEdit" @open-delete="openDelete"
      @toggle-row="toggleRow" @toggle-select-all="toggleSelectAll"
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
