<script setup lang="ts">
import { BRAND_PALETTE } from '@/constants/theme'
import { ref, watch, computed } from 'vue'
import { Search, MoreHorizontal, Eye, Pencil, Trash2, Filter, X, Flag } from 'lucide-vue-next'
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/popover'
import { useAuth } from '@/composables/useAuth'
import { Button } from '@/components/ui/button'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select'
import TablePagination from '@/components/shared/TablePagination.vue'
import { approvalStatusBadge, workflowStatusBadge, fmtDate, contractLifecycle, formatRemainingTime } from '@/types/contract'
import type { ContractWithDays, FilterTab, StatusFilter } from '@/types/contract'
import { contractGroup, CONTRACT_GROUPS, groupCounts } from '@/utils/contractSort'
import type { ContractGroupKey } from '@/utils/contractSort'
import ContractLifecycleBadge from '@/components/shared/ContractLifecycleBadge.vue'
import { useRiskAssessment } from '@/composables/useRiskAssessment'
import type { RiskLevel } from '@/types/riskAssessment'
import { severityIconColor, severityLabel } from '@/types/riskAssessment'

const { fetchBulkLevels } = useRiskAssessment()
const { hasPermission } = useAuth()

const aiRiskAssessmentVisible = computed(() => hasPermission('cms.ai.risk_assessment'))
const riskLevels = ref<Record<string, { riskLevel: RiskLevel, findingsCount: number, status: string }>>({})
const loadingRisk = ref(false)


const props = defineProps<{
  paginated:      ContractWithDays[]
  filtered:       ContractWithDays[]
  activeFilter:   FilterTab
  searchQuery:    string
  categoryFilter: string
  regionFilter:   string
  statusFilter:   StatusFilter
  currentPage:    number
  startDateFilter?: string
  endDateFilter?:   string
  loading?:       boolean
  totalItems?:    number
  itemsPerPage?:  number
}>()

const emit = defineEmits<{
  openDetail:              [c: ContractWithDays]
  openEdit:                [c: ContractWithDays]
  delete:                  [id: string]
  'update:activeFilter':   [v: FilterTab]
  'update:searchQuery':    [v: string]
  'update:categoryFilter': [v: string]
  'update:regionFilter':   [v: string]
  'update:statusFilter':   [v: StatusFilter]
  'update:currentPage':    [v: number]
  'update:startDateFilter': [v: string]
  'update:endDateFilter':  [v: string]
  openActivity: [id: string]
}>()

watch(() => props.paginated, async (list) => {
  if (!aiRiskAssessmentVisible.value) return
  const ids = list.map(c => c.id)
  if (!ids.length) {
    riskLevels.value = {}
    return
  }
  loadingRisk.value = true
  const res = await fetchBulkLevels(ids)
  if (res) {
    riskLevels.value = res
  }
  loadingRisk.value = false
}, { immediate: true })

// ── Grouped sections (contract list ordering) ──────────────────────────
const groupMetaByKey = computed(() =>
  Object.fromEntries(CONTRACT_GROUPS.map(g => [g.key, g])) as Record<ContractGroupKey, typeof CONTRACT_GROUPS[number]>
)
const counts = computed(() => groupCounts(props.filtered))
// Only show section headers when the current view actually spans >1 section,
// so single-lifecycle tabs stay clean.
const showGroupHeaders = computed(() => CONTRACT_GROUPS.filter(g => counts.value[g.key] > 0).length > 1)

function rowGroup(c: ContractWithDays): ContractGroupKey {
  return contractGroup(c)
}

// Headers repeat at the top of every page so context is never lost when paging.
function isGroupStart(index: number): boolean {
  if (index === 0) return true
  return contractGroup(props.paginated[index - 1]) !== contractGroup(props.paginated[index])
}

const showFilterPopover = ref(false)

const draftCategory = ref(props.categoryFilter || '')
const draftRegion = ref(props.regionFilter || '')
const draftStatus = ref(props.statusFilter || '')
const draftStartDate = ref(props.startDateFilter || '')
const draftEndDate = ref(props.endDateFilter || '')

watch(showFilterPopover, (open) => {
  if (open) {
    draftCategory.value = props.categoryFilter || ''
    draftRegion.value = props.regionFilter || ''
    draftStatus.value = props.statusFilter || ''
    draftStartDate.value = props.startDateFilter || ''
    draftEndDate.value = props.endDateFilter || ''
  }
})

function applyFilters() {
  emit('update:categoryFilter', draftCategory.value)
  emit('update:regionFilter', draftRegion.value)
  emit('update:statusFilter', draftStatus.value as StatusFilter)
  emit('update:startDateFilter', draftStartDate.value)
  emit('update:endDateFilter', draftEndDate.value)
  showFilterPopover.value = false
}

function clearAll() {
  draftCategory.value = ''
  draftRegion.value = ''
  draftStatus.value = ''
  draftStartDate.value = ''
  draftEndDate.value = ''

  emit('update:categoryFilter', '')
  emit('update:regionFilter', '')
  emit('update:statusFilter', '')
  emit('update:startDateFilter', '')
  emit('update:endDateFilter', '')
  showFilterPopover.value = false
}

const activeFilterChips = computed(() => {
  const chips = []
  if (props.categoryFilter) {
    chips.push({ key: 'category', label: `Category: ${props.categoryFilter}` })
  }
  if (props.regionFilter) {
    chips.push({ key: 'region', label: `Region: ${props.regionFilter}` })
  }
  if (props.statusFilter) {
    const label = statusOptions.find(o => o.value === props.statusFilter)?.label || props.statusFilter
    chips.push({ key: 'status', label: `Status: ${label}` })
  }
  if (props.startDateFilter) {
    chips.push({ key: 'startDate', label: `From: ${props.startDateFilter}` })
  }
  if (props.endDateFilter) {
    chips.push({ key: 'endDate', label: `To: ${props.endDateFilter}` })
  }
  return chips
})

function removeChip(key: string) {
  if (key === 'category') emit('update:categoryFilter', '')
  if (key === 'region') emit('update:regionFilter', '')
  if (key === 'status') emit('update:statusFilter', '')
  if (key === 'startDate') emit('update:startDateFilter', '')
  if (key === 'endDate') emit('update:endDateFilter', '')
}

const filterTabs: { label: string; value: FilterTab }[] = [
  { label: 'All',           value: 'all'      },
  { label: 'Active',        value: 'active'   },
  { label: 'Inactive',      value: 'inactive' },
  { label: 'Pending Execution', value: 'pending_execution' },
  { label: 'Expiring Soon', value: 'expiring' },
  { label: 'Expired',       value: 'expired'  },
]

const categories = [
  'Service Agreement',
  'Supply Contract',
  'Equipment Maintenance',
  'Equipment Lease',
  'Partnership Agreement'
]

const statusOptions: { label: string; value: StatusFilter }[] = [
  { label: 'Pending',       value: 'Pending'      },
  { label: 'Approved',      value: 'Approved'     },
  { label: 'Rejected',      value: 'Rejected'     },
  { label: 'For Signing', value: 'For Signing' },
  { label: 'Signed', value: 'Signed' },
  { label: 'Notarized PDF', value: 'Notarized PDF' },
  { label: 'SBSI Review',   value: 'SBSI Review'  },
  { label: 'Client Review', value: 'Client Review'},
]


const palette = BRAND_PALETTE
function initials(name: string) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}
function avatarColor(name: string) {
  let h = 0
  for (const c of name) h = (h * 31 + c.charCodeAt(0)) & 0xffff
  return palette[h % palette.length]
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h2 class="text-sm font-semibold text-black">
        All Contracts <span class="text-black/30 font-normal">({{ totalItems !== undefined ? totalItems : filtered.length }})</span>
      </h2>
    </div>

    <!-- Filters + search -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 px-6 py-4 border-b border-black/5 bg-black/[0.005]">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Tabs -->
        <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1">
          <button v-for="tab in filterTabs" :key="tab.value"
            @click="emit('update:activeFilter', tab.value)"
            class="flex items-center gap-1.5 px-4 py-1.5 text-sm rounded transition-all font-medium"
            :class="activeFilter === tab.value
              ? 'bg-white text-black shadow-sm'
              : 'text-black/40 hover:text-black/60'">
            {{ tab.label }}
          </button>
        </div>

        <!-- Filters Popover Trigger -->
        <Popover v-model:open="showFilterPopover">
          <PopoverTrigger as-child>
            <Button variant="outline" class="h-9 gap-2 text-sm border-black/10 bg-white text-black/70 hover:text-black font-semibold">
              <Filter class="w-4 h-4 text-black/40" />
              Filters
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-80 p-5 bg-white border border-black/10 shadow-lg rounded-lg space-y-4" align="start">
            <h3 class="text-sm font-semibold text-black">Filters</h3>
            
            <!-- Category -->
            <div class="space-y-1">
              <label class="text-[10px] font-semibold text-black/40 uppercase tracking-wider block">Category</label>
              <Select :model-value="draftCategory || '__all__'" @update:model-value="(v) => draftCategory = (v === '__all__' ? '' : v as string)">
                <SelectTrigger class="w-full h-8 rounded-md border-black/10 bg-white text-xs text-black/70 focus:ring-brand-blue/15">
                  <SelectValue placeholder="All Categories" />
                </SelectTrigger>
                <SelectContent class="bg-white border border-black/10 shadow-lg">
                  <SelectItem value="__all__">All Categories</SelectItem>
                  <SelectItem v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Region -->
            <div class="space-y-1">
              <label class="text-[10px] font-semibold text-black/40 uppercase tracking-wider block">Region</label>
              <Select :model-value="draftRegion || '__all__'" @update:model-value="(v) => draftRegion = (v === '__all__' ? '' : v as string)">
                <SelectTrigger class="w-full h-8 rounded-md border-black/10 bg-white text-xs text-black/70 focus:ring-brand-blue/15">
                  <SelectValue placeholder="All Regions" />
                </SelectTrigger>
                <SelectContent class="bg-white border border-black/10 shadow-lg">
                  <SelectItem value="__all__">All Regions</SelectItem>
                  <SelectItem value="Luzon">Luzon</SelectItem>
                  <SelectItem value="Visayas">Visayas</SelectItem>
                  <SelectItem value="Mindanao">Mindanao</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Status -->
            <div class="space-y-1">
              <label class="text-[10px] font-semibold text-black/40 uppercase tracking-wider block">Status</label>
              <Select :model-value="draftStatus || '__all__'" @update:model-value="(v) => draftStatus = (v === '__all__' ? '' : (v as StatusFilter))">
                <SelectTrigger class="w-full h-8 rounded-md border-black/10 bg-white text-xs text-black/70 focus:ring-brand-blue/15">
                  <SelectValue placeholder="All Status" />
                </SelectTrigger>
                <SelectContent class="bg-white border border-black/10 shadow-lg">
                  <SelectItem value="__all__">All Status</SelectItem>
                  <SelectItem v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Date Range -->
            <div class="space-y-2 pt-1 border-t border-black/5">
              <label class="text-[10px] font-semibold text-black/40 uppercase tracking-wider block">Date Range</label>
              <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-[11px] font-medium text-black/65">Start Date</span>
                  <input type="date" v-model="draftStartDate"
                    class="h-7 w-36 rounded-md border border-black/10 bg-white px-2 text-xs focus:border-brand-blue focus:outline-none transition-colors" />
                </div>
                <div class="flex items-center justify-between gap-2">
                  <span class="text-[11px] font-medium text-black/65">End Date</span>
                  <input type="date" v-model="draftEndDate"
                    class="h-7 w-36 rounded-md border border-black/10 bg-white px-2 text-xs focus:border-brand-blue focus:outline-none transition-colors" />
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="pt-3 border-t border-black/5 flex items-center justify-end gap-2">
              <Button variant="outline" size="sm" @click="clearAll"
                class="flex-1 h-8 text-xs border-black/15 text-black/65 hover:text-black hover:bg-black/4">
                Clear All
              </Button>
              <Button size="sm" @click="applyFilters"
                class="flex-1 h-8 text-xs bg-brand-navy hover:bg-brand-dark text-white font-medium shadow-sm">
                Apply Filters
              </Button>
            </div>
          </PopoverContent>
        </Popover>
      </div>

      <!-- Search query input -->
      <div class="relative w-full lg:w-64">
        <Search class="w-3.5 h-3.5 text-black/30 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input :value="searchQuery"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value.trim())"
          type="text" placeholder="Search contracts..."
          maxlength="100"
          class="w-full h-9 rounded-lg border border-black/10 bg-white pl-8.5 pr-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/15 transition" />
      </div>
    </div>

    <!-- Active Filter Chips -->
    <div v-if="activeFilterChips.length > 0" class="flex flex-wrap items-center gap-2 px-6 py-2.5 border-b border-black/5 bg-black/[0.005]">
      <span class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Active Filters:</span>
      <div v-for="chip in activeFilterChips" :key="chip.key"
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-black/5 border border-black/10 text-xs text-black/70 font-semibold">
        {{ chip.label }}
        <button @click="removeChip(chip.key)" class="text-black/40 hover:text-black shrink-0 transition-colors">
          <X class="w-3 h-3" />
        </button>
      </div>
      <button @click="clearAll" class="text-xs text-red-600 hover:text-red-700 font-semibold ml-2">
        Clear All
      </button>
    </div>

    <!-- Table -->
    <Table>
      <TableHeader class="bg-black/1.8">
        <TableRow class="border-b border-black/4 hover:bg-transparent">
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3 pl-6 w-56">Contract</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Category</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Region</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">End Date</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Remaining Time</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Contract State</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Sales Rep</TableHead>
          <TableHead class="w-12 py-3" />
        </TableRow>
      </TableHeader>

      <TableBody>
        <template v-if="loading">
          <TableRow v-for="i in itemsPerPage" :key="i" class="border-b border-black/4 last:border-0">
            <!-- Contract ID + Partner -->
            <TableCell class="py-4 pl-6">
              <div class="h-4 w-32 bg-black/5 animate-pulse rounded mb-1.5"></div>
              <div class="h-3 w-16 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- Category -->
            <TableCell class="py-4">
              <div class="h-4 w-24 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- Region -->
            <TableCell class="py-4">
              <div class="h-4 w-16 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- End Date -->
            <TableCell class="py-4">
              <div class="h-4 w-20 bg-black/5 animate-pulse rounded mb-1.5"></div>
              <div class="h-3 w-28 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- Remaining Time -->
            <TableCell class="py-4">
              <div class="h-4 w-24 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- Contract State -->
            <TableCell class="py-4">
              <div class="h-5 w-24 bg-black/5 animate-pulse rounded-full"></div>
            </TableCell>
            <TableCell class="py-4">
              <div class="flex flex-col gap-1">
                <div class="h-5 w-20 bg-black/5 animate-pulse rounded-full"></div>
                <div class="h-5 w-20 bg-black/5 animate-pulse rounded-full"></div>
              </div>
            </TableCell>

            <!-- Sales Rep -->
            <TableCell class="py-4">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-black/5 animate-pulse shrink-0"></div>
                <div class="h-3 w-24 bg-black/5 animate-pulse rounded"></div>
              </div>
            </TableCell>

            <!-- Actions -->
            <TableCell class="py-4 pr-4">
              <div class="h-8 w-8 bg-black/5 animate-pulse rounded ml-auto"></div>
            </TableCell>
          </TableRow>
        </template>
        <template v-else>
          <template v-for="(c, i) in paginated" :key="c.id">
            <TableRow v-if="showGroupHeaders && isGroupStart(i)"
              class="border-b border-black/4 bg-black/[0.02] hover:bg-black/[0.02]">
              <TableCell :colspan="9" class="py-2 pl-6">
                <div class="flex items-center gap-2">
                  <span class="w-1 h-3.5 rounded-full" :style="{ backgroundColor: groupMetaByKey[rowGroup(c)].hex }" />
                  <span class="text-[11px] font-semibold uppercase tracking-wider text-black/45">
                    {{ groupMetaByKey[rowGroup(c)].label }}
                  </span>
                  <span class="text-[10px] font-semibold text-black/35 bg-black/5 rounded px-1.5 py-0.5">
                    {{ counts[rowGroup(c)] }}
                  </span>
                </div>
              </TableCell>
            </TableRow>
            <TableRow
              class="border-b border-black/4 last:border-0 transition-colors hover:bg-black/1.2 cursor-pointer"
              @click="emit('openDetail', c)">

            <!-- Contract ID + Partner -->
            <TableCell class="py-4 pl-6">
              <div class="flex items-center gap-2">
                <p class="text-sm font-medium text-black leading-snug truncate max-w-[220px]"
                  :title="c.businessPartner">{{ c.businessPartner }}</p>
                <Flag
                  v-if="aiRiskAssessmentVisible && riskLevels[c.id]?.status === 'completed' && riskLevels[c.id]?.riskLevel"
                  class="w-3.5 h-3.5 shrink-0 fill-current"
                  :class="severityIconColor[riskLevels[c.id].riskLevel]"
                  :title="`AI Risk: ${severityLabel[riskLevels[c.id].riskLevel]}${riskLevels[c.id].findingsCount ? ` (${riskLevels[c.id].findingsCount} flagged clause${riskLevels[c.id].findingsCount === 1 ? '' : 's'})` : ''}`"
                />
              </div>
              <p class="text-xs font-mono text-black/40 mt-0.5">{{ c.contractCode || c.id }}</p>
            </TableCell>

            <!-- Category -->
            <TableCell class="py-4 text-sm text-black/60">{{ c.category }}</TableCell>

            <!-- Region -->
            <TableCell class="py-4 text-sm text-black/60">{{ c.region }}</TableCell>

            <!-- End Date -->
            <TableCell class="py-4">
              <p class="text-sm font-medium text-black">{{ fmtDate(c.endDate) }}</p>
              <p class="text-xs text-black/35 mt-0.5">from {{ fmtDate(c.startDate) }}</p>
            </TableCell>

            <!-- Remaining Time -->
            <TableCell class="py-4 text-sm text-black/60">{{ formatRemainingTime(c.endDate) }}</TableCell>

            <!-- Contract State -->
            <TableCell class="py-4">
              <ContractLifecycleBadge :status="contractLifecycle(c)" />
            </TableCell>

            <!-- Status -->
            <TableCell class="py-4" @click.stop>
              <div class="flex flex-col gap-1">
                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full border whitespace-nowrap w-fit"
                  :class="approvalStatusBadge[c.approvalStatus]">
                  {{ c.approvalStatus }}
                </span>
                <span v-if="c.workflowStatus"
                  class="text-xs font-medium px-2.5 py-0.5 rounded-full border whitespace-nowrap w-fit"
                  :class="workflowStatusBadge[c.workflowStatus]">
                  {{ c.workflowStatus }}
                </span>
              </div>
            </TableCell>

            <!-- Sales Rep -->
            <TableCell class="py-4" @click.stop>
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0 select-none"
                  :style="{ backgroundColor: avatarColor(c.createdBy) }">
                  {{ initials(c.createdBy) }}
                </div>
                <span class="text-xs font-medium text-black/70 leading-snug">{{ c.createdBy }}</span>
              </div>
            </TableCell>

            <!-- Actions -->
            <TableCell class="py-4 pr-4" @click.stop>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon"
                    class="h-8 w-8 text-black/60 hover:text-black hover:bg-black/5 data-[state=open]:bg-black/5 data-[state=open]:text-black">
                    <MoreHorizontal class="w-4 h-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                  <DropdownMenuLabel class="text-xs font-semibold text-black/38 pb-1">Actions</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem @click="emit('openDetail', c)" class="gap-2.5 text-sm cursor-pointer">
                    <Eye class="w-3.5 h-3.5 text-black/40" /> View details
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="hasPermission('cms.contracts.edit')" @click="emit('openEdit', c)" class="gap-2.5 text-sm cursor-pointer">
                    <Pencil class="w-3.5 h-3.5 text-black/40" /> Edit contract
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="hasPermission('cms.contracts.delete')" />
                  <DropdownMenuItem v-if="hasPermission('cms.contracts.delete')" @click="emit('delete', c.id)"
                    class="gap-2.5 text-sm cursor-pointer text-red-600 focus:text-red-600 focus:bg-red-50">
                    <Trash2 class="w-3.5 h-3.5" /> Delete
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>
          </template>

          <TableRow v-if="paginated.length === 0">
            <TableCell colspan="9" class="text-center py-16">
              <p class="text-sm font-semibold text-black/28">No contracts found</p>
              <p class="text-xs text-black/20 mt-1">Try a different filter or search term</p>
            </TableCell>
          </TableRow>
        </template>
      </TableBody>
    </Table>

    <!-- Pagination -->
    <div class="flex justify-center px-6 py-4 border-t border-black/5">
      <TablePagination :current-page="currentPage" :total-items="totalItems !== undefined ? totalItems : filtered.length"
        :items-per-page="itemsPerPage || 15" :current-page-items-count="paginated.length" @update:current-page="emit('update:currentPage', $event)" />
    </div>

  </div>


</template>
