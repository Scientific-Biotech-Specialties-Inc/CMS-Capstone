<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Search, MoreHorizontal, Eye } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useAmendmentStore } from '@/composables/useAmendmentStore'
import type { ContractAmendment } from '@/types/contractAmendment'
import { amendmentStatusBadge } from '@/types/contractAmendment'
import TablePagination from '@/components/shared/TablePagination.vue'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const amendmentStore = useAmendmentStore()
const router = useRouter()

const activeFilter = ref<'all' | 'pending' | 'approved' | 'rejected'>('all')
const searchQuery  = ref('')
const currentPage  = ref(1)
const itemsPerPage = 10

onMounted(() => {
  amendmentStore.fetchAmendments(true)
})

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  return amendmentStore.amendments.value.filter(a => {
    const bySearch = !q
      || a.id.toLowerCase().includes(q)
      || a.contractId.toLowerCase().includes(q)
      || a.businessPartner.toLowerCase().includes(q)
      || a.category.toLowerCase().includes(q)
    
    const byFilter =
      activeFilter.value === 'all'       ? true :
      activeFilter.value === 'pending'   ? a.status === 'Pending' :
      activeFilter.value === 'approved'  ? a.status === 'Approved' :
      a.status === 'Rejected'
      
    return bySearch && byFilter
  })
})

const paginated = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  return filtered.value.slice(start, start + itemsPerPage)
})

function handleFilterChange(tab: 'all' | 'pending' | 'approved' | 'rejected') {
  activeFilter.value = tab
  currentPage.value = 1
}

function openDetail(a: ContractAmendment) {
  router.push(`/sales/contract-amendments/${a.id}`)
}

function fmtDate(iso: string): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <div class="p-8 space-y-6 font-poppins">
    <!-- Header -->
    <div>
      <h1 class="text-xl font-semibold text-black">My Amendments</h1>
      <p class="text-sm text-black/40 mt-0.5">Track and view history of amendment requests for your contracts.</p>
    </div>

    <!-- Filter Tabs + Search -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white border border-black/8 rounded-lg p-4 shadow-sm">
      <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1">
        <button v-for="tab in (['all', 'pending', 'approved', 'rejected'] as const)" :key="tab"
          @click="handleFilterChange(tab)"
          class="px-4 py-1.5 text-sm rounded capitalize transition-all font-medium"
          :class="activeFilter === tab
            ? 'bg-white text-black shadow-sm font-semibold'
            : 'text-black/40 hover:text-black/60'">
          {{ tab }}
        </button>
      </div>

      <!-- Search Input -->
      <div class="relative w-full lg:w-64">
        <Search class="w-3.5 h-3.5 text-black/30 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Search amendments..."
          class="w-full h-9 rounded-lg border border-black/10 bg-white pl-8.5 pr-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/15 transition" 
        />
      </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
      <Table>
        <TableHeader class="bg-black/[0.018]">
          <TableRow class="border-b border-black/[0.04] hover:bg-transparent">
            <TableHead class="text-[11px] font-bold text-black/40 uppercase tracking-wider py-3 pl-6 w-32">Amendment ID</TableHead>
            <TableHead class="text-[11px] font-bold text-black/40 uppercase tracking-wider py-3 w-32">Contract ID</TableHead>
            <TableHead class="text-[11px] font-bold text-black/40 uppercase tracking-wider py-3">Business Partner</TableHead>
            <TableHead class="text-[11px] font-bold text-black/40 uppercase tracking-wider py-3">Category</TableHead>
            <TableHead class="text-[11px] font-bold text-black/40 uppercase tracking-wider py-3 w-24 text-center">Version</TableHead>
            <TableHead class="text-[11px] font-bold text-black/40 uppercase tracking-wider py-3 w-36">Request Date</TableHead>
            <TableHead class="text-[11px] font-bold text-black/40 uppercase tracking-wider py-3 w-28">Status</TableHead>
            <TableHead class="w-12 py-3" />
          </TableRow>
        </TableHeader>

        <TableBody>
          <TableRow v-for="a in paginated" :key="a.id"
            @click="openDetail(a)"
            class="border-b border-black/[0.04] last:border-0 hover:bg-black/[0.01] transition-colors cursor-pointer">
            
            <TableCell class="py-4 pl-6 text-sm font-semibold text-black">{{ a.id }}</TableCell>
            <TableCell class="py-4 text-sm font-medium text-black/60">{{ a.contractId }}</TableCell>
            <TableCell class="py-4 text-sm font-semibold text-black leading-snug">{{ a.businessPartner }}</TableCell>
            <TableCell class="py-4 text-sm text-black/60">{{ a.category }}</TableCell>
            <TableCell class="py-4 text-sm font-medium text-center">
              <span class="inline-flex px-2 py-0.5 rounded-full bg-brand-blue/8 text-brand-blue text-xs font-bold border border-brand-blue/15">
                v{{ a.version }}
              </span>
            </TableCell>
            <TableCell class="py-4 text-sm text-black/60">{{ fmtDate(a.requestDate) }}</TableCell>
            <TableCell class="py-4 text-xs font-medium">
              <span class="px-2 py-0.5 rounded-full border whitespace-nowrap" :class="amendmentStatusBadge[a.status]">
                {{ a.status }}
              </span>
            </TableCell>
            <TableCell class="py-4 pr-4 text-right" @click.stop>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon" class="h-8 w-8 text-black/45 hover:text-black hover:bg-black/5 data-[state=open]:bg-black/5 data-[state=open]:text-black">
                    <MoreHorizontal class="w-4 h-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44 font-poppins">
                  <DropdownMenuLabel class="text-xs font-semibold text-black/38 pb-1">Actions</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem @click="openDetail(a)" class="gap-2.5 text-sm cursor-pointer">
                    <Eye class="w-3.5 h-3.5 text-black/40" /> View details
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>

          <TableRow v-if="paginated.length === 0">
            <TableCell colspan="8" class="text-center py-16">
              <p class="text-sm font-semibold text-black/28">No amendments found</p>
              <p class="text-xs text-black/20 mt-1">Try selecting a different status tab or search term.</p>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <!-- Pagination -->
      <div class="flex justify-center px-6 py-4 border-t border-black/5">
        <TablePagination 
          :current-page="currentPage" 
          :total-items="filtered.length"
          :items-per-page="itemsPerPage" 
          :current-page-items-count="paginated.length" 
          @update:current-page="currentPage = $event" 
        />
      </div>
    </div>
  </div>
</template>
