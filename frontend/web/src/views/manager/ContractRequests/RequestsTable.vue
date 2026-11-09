<script setup lang="ts">
import { BRAND_PALETTE } from '@/constants/theme'
import { Search, MoreHorizontal, Eye, CheckCircle, XCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import TablePagination from '@/components/shared/TablePagination.vue'
import { requestStatusBadge, fmtReqDate } from '@/types/contractRequest'
import type { ContractRequest, RequestFilterTab } from '@/types/contractRequest'

const props = defineProps<{
  paginated:    ContractRequest[]
  filtered:     ContractRequest[]
  activeFilter: RequestFilterTab
  searchQuery:  string
  currentPage:  number
  itemsPerPage: number
  loading?:     boolean
}>()

const emit = defineEmits<{
  openDetail:            [r: ContractRequest]
  approve:               [id: string]
  reject:                [id: string]
  'update:activeFilter': [v: RequestFilterTab]
  'update:searchQuery':  [v: string]
  'update:currentPage':  [v: number]
}>()

const filterTabs: { label: string; value: RequestFilterTab }[] = [
  { label: 'All',      value: 'all'      },
  { label: 'Pending',  value: 'pending'  },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
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

    <!-- Section heading -->
    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h2 class="text-sm font-semibold text-black">
        Contract Requests <span class="text-black/30 font-normal">({{ loading ? 0 : filtered.length }})</span>
      </h2>
    </div>

    <!-- Filters + search -->
    <div class="flex items-center justify-between px-6 py-3 border-b border-black/5 gap-4">
      <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1 flex-wrap">
        <button v-for="tab in filterTabs" :key="tab.value"
          @click="emit('update:activeFilter', tab.value)"
          class="px-4 py-1.5 text-sm rounded transition-all font-medium"
          :class="activeFilter === tab.value
            ? 'bg-white text-black shadow-sm'
            : 'text-black/40 hover:text-black/60'">
          {{ tab.label }}
        </button>
      </div>
      <div class="relative w-56 shrink-0">
        <Search class="w-3.5 h-3.5 text-black/30 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input :value="searchQuery"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value.trim())"
          type="text" placeholder="Search requests..."
          class="w-full h-9 rounded-lg border border-black/10 bg-white pl-8.5 pr-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/15 transition" />
      </div>
    </div>

    <!-- Table -->
    <Table>
      <TableHeader class="bg-black/1.8">
        <TableRow class="border-b border-black/4 hover:bg-transparent">
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3 pl-6 w-60">Request</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Category</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Region</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Requested</TableHead>

          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Sales Rep</TableHead>
          <TableHead class="w-12 py-3" />
        </TableRow>
      </TableHeader>

      <TableBody>
        <template v-if="loading">
          <TableRow v-for="i in itemsPerPage" :key="i" class="border-b border-black/4 last:border-0">
            <!-- Request ID + Partner -->
            <TableCell class="py-4 pl-6">
              <div class="h-4 w-32 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- Category -->
            <TableCell class="py-4">
              <div class="h-4 w-24 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- Region -->
            <TableCell class="py-4">
              <div class="h-4 w-16 bg-black/5 animate-pulse rounded"></div>
            </TableCell>

            <!-- Requested Date -->
            <TableCell class="py-4">
              <div class="h-4 w-28 bg-black/5 animate-pulse rounded"></div>
            </TableCell>



            <!-- Status -->
            <TableCell class="py-4">
              <div class="h-5 w-20 bg-black/5 animate-pulse rounded-full"></div>
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
          <TableRow v-for="r in paginated" :key="r.id"
            class="border-b border-black/4 last:border-0 transition-colors hover:bg-black/1.2 cursor-pointer"
            @click="emit('openDetail', r)">

            <TableCell class="py-4 pl-6">
              <p class="text-sm font-medium text-black leading-snug">{{ r.businessPartner }}</p>
            </TableCell>

            <TableCell class="py-4 text-sm text-black/60">{{ r.category }}</TableCell>

            <TableCell class="py-4 text-sm text-black/60">{{ r.region }}</TableCell>

            <TableCell class="py-4 text-sm text-black/60">{{ fmtReqDate(r.requestDate) }}</TableCell>



            <TableCell class="py-4" @click.stop>
              <span class="text-xs font-medium px-2.5 py-0.5 rounded-full border whitespace-nowrap"
                :class="requestStatusBadge[r.status]">
                {{ r.status }}
              </span>
            </TableCell>

            <!-- Sales Rep -->
            <TableCell class="py-4" @click.stop>
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0 select-none"
                  :style="{ backgroundColor: avatarColor(r.createdBy) }">
                  {{ initials(r.createdBy) }}
                </div>
                <span class="text-xs font-medium text-black/70">{{ r.createdBy }}</span>
              </div>
            </TableCell>

            <TableCell class="py-4 pr-4" @click.stop>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon"
                    class="h-8 w-8 text-black/60 hover:text-black hover:bg-black/5 data-[state=open]:bg-black/5 data-[state=open]:text-black">
                    <MoreHorizontal class="w-4 h-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuLabel class="text-xs font-semibold text-black/38 pb-1">Actions</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem @click="emit('openDetail', r)" class="gap-2.5 text-sm cursor-pointer">
                    <Eye class="w-3.5 h-3.5 text-black/40" /> View details
                  </DropdownMenuItem>
                  <template v-if="r.status === 'Pending' || r.status === 'Under Review'">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-if="r.hasPendingApprovalWorkflow" as-child>
                      <RouterLink :to="{ name: `${$route.path.split('/')[1]}-contracts-detail`, params: { id: r.contractId ?? r.contractDbId ?? r.id.replace(/^REQ-/, '') } }"
                        class="gap-2.5 text-sm cursor-pointer">
                        <Eye class="w-3.5 h-3.5 text-black/40" /> Review Approval
                      </RouterLink>
                    </DropdownMenuItem>
                    <template v-else-if="r.hasPendingApprovalWorkflow === false">
                      <DropdownMenuItem @click="emit('approve', r.id)"
                        class="gap-2.5 text-sm cursor-pointer text-emerald-600 focus:text-emerald-600 focus:bg-emerald-50">
                        <CheckCircle class="w-3.5 h-3.5" /> Approve
                      </DropdownMenuItem>
                      <DropdownMenuItem @click="emit('reject', r.id)"
                        class="gap-2.5 text-sm cursor-pointer text-red-600 focus:text-red-600 focus:bg-red-50">
                        <XCircle class="w-3.5 h-3.5" /> Reject
                      </DropdownMenuItem>
                    </template>
                  </template>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>

          <TableRow v-if="paginated.length === 0">
            <TableCell colspan="7" class="text-center py-16">
              <p class="text-sm font-semibold text-black/28">No requests found</p>
              <p class="text-xs text-black/20 mt-1">Try a different filter or search term</p>
            </TableCell>
          </TableRow>
        </template>
      </TableBody>
    </Table>

    <!-- Pagination -->
    <div class="flex justify-center px-6 py-4 border-t border-black/5">
      <TablePagination :current-page="currentPage" :total-items="loading ? itemsPerPage : filtered.length"
        :items-per-page="itemsPerPage" :current-page-items-count="paginated.length" @update:current-page="emit('update:currentPage', $event)" />
    </div>

  </div>
</template>
