<script setup lang="ts">

import { Building2, Truck, MoreHorizontal, Eye, Pencil, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import TablePagination from '@/components/shared/TablePagination.vue'
import type { Partner, TabKey } from '@/types/partner'

const props = defineProps<{
  paginated:       Partner[]
  filtered:        Partner[]
  activeTab:       TabKey
  selectedIds:     (string | number)[]
  allPageSelected: boolean
  currentPage:     number
  itemsPerPage:    number
  totalItems?:     number
  canEdit?:        boolean
  canDelete?:      boolean
  loading?:        boolean
}>()

const emit = defineEmits<{
  openDetail:      [p: Partner]
  openEdit:        [p: Partner]
  openDelete:      [p: Partner]
  toggleRow:       [id: string | number]
  toggleSelectAll: []
  'update:currentPage': [page: number]
}>()

function displayId(p: Partner) {
  if (props.activeTab === 'partners') {
    const raw = p.bpCode || String(p.id)
    if (raw.startsWith('BP-')) return raw.replace(/^(BP-)+/i, 'BP-')
    return `BP-${String(p.db_id || p.id).padStart(4, '0')}`
  }
  const raw = String(p.id)
  if (raw.startsWith('SP-')) return raw.replace(/^(SP-)+/i, 'SP-')
  return `SP-${String(p.db_id || p.id).padStart(4, '0')}`
}

function statusClass(status: string) {
  switch (status) {
    case 'Active':    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'Inactive':  return 'bg-black/4 text-black/35 border-black/8'
    case 'Suspended': return 'bg-amber-50 text-amber-700 border-amber-200'
    default:          return 'bg-black/4 text-black/35 border-black/8'
  }
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h2 class="text-sm font-semibold text-black">
        {{ activeTab === 'partners' ? 'Business Partners' : 'Suppliers' }}
        <span class="text-black/30 font-normal">({{ totalItems ?? filtered.length }})</span>
      </h2>
    </div>

    <Table>
      <TableHeader class="bg-black/1.8">
        <TableRow class="border-b border-black/4 hover:bg-transparent">
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3 pl-6">Name</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3 w-40">Industry</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Region</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Contact</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
          <TableHead class="w-14 py-3" />
        </TableRow>
      </TableHeader>

      <TableBody>
        <template v-if="props.loading">
          <TableRow v-for="i in props.itemsPerPage" :key="'sk-' + i"
            class="border-b border-black/4 last:border-0 hover:bg-transparent">
            <TableCell class="py-4 pl-6">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-black/5 animate-pulse shrink-0" />
                <div>
                  <div class="h-3.5 w-28 bg-black/5 animate-pulse rounded" />
                </div>
              </div>
            </TableCell>
            <TableCell class="py-4"><div class="h-3.5 w-24 bg-black/5 animate-pulse rounded" /></TableCell>
            <TableCell class="py-4"><div class="h-3.5 w-16 bg-black/5 animate-pulse rounded" /></TableCell>
            <TableCell class="py-4">
              <div class="space-y-1.5">
                <div class="h-3.5 w-24 bg-black/5 animate-pulse rounded" />
                <div class="h-2.5 w-20 bg-black/5 animate-pulse rounded" />
              </div>
            </TableCell>
            <TableCell class="py-4"><div class="h-5 w-16 bg-black/5 animate-pulse rounded-full" /></TableCell>
            <TableCell class="py-4 pr-4"><div class="w-8 h-8 bg-black/5 animate-pulse rounded" /></TableCell>
          </TableRow>
        </template>

        <template v-else>
        <TableRow
          v-for="partner in paginated"
          :key="partner.id"
          class="border-b border-black/4 last:border-0 hover:bg-black/1.2 transition-colors"
        >
          <TableCell class="py-4 pl-6">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 bg-brand-navy/8 text-brand-navy">
                <component :is="activeTab === 'partners' ? Building2 : Truck" class="w-4 h-4" />
              </div>
              <div>
                <p class="text-sm font-medium text-black leading-snug truncate max-w-[220px]"
                   :title="partner.name">{{ partner.name }}</p>
                <p class="text-xs font-mono text-black/40 mt-0.5">{{ displayId(partner) }}</p>
              </div>
            </div>
          </TableCell>
          <TableCell class="py-4 text-sm text-black/60 max-w-[160px] truncate"
                     :title="partner.industry || undefined">{{ partner.industry || '—' }}</TableCell>
          <TableCell class="py-4 text-sm text-black/60">{{ partner.region ?? '—' }}</TableCell>
          <TableCell class="py-4">
            <div class="text-sm text-black/70">{{ partner.contactPerson || '—' }}</div>
            <div class="text-xs text-black/35 mt-0.5">{{ partner.phone || '' }}</div>
          </TableCell>
          <TableCell class="py-4">
            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full border" :class="statusClass(partner.status)">
              {{ partner.status }}
            </span>
          </TableCell>
          <TableCell class="py-4 pr-4">
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
                <DropdownMenuItem @click="emit('openDetail', partner)" class="gap-2.5 text-sm cursor-pointer">
                  <Eye class="w-3.5 h-3.5 text-black/40" /> View details
                </DropdownMenuItem>
                <template v-if="canEdit || canDelete">
                  <DropdownMenuSeparator />
                  <DropdownMenuItem v-if="canEdit" @click="emit('openEdit', partner)" class="gap-2.5 text-sm cursor-pointer">
                    <Pencil class="w-3.5 h-3.5 text-black/40" /> Edit
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="canDelete" @click="emit('openDelete', partner)"
                    class="gap-2.5 text-sm cursor-pointer text-red-600 focus:text-red-600 focus:bg-red-50">
                    <Trash2 class="w-3.5 h-3.5" /> Delete
                  </DropdownMenuItem>
                </template>
              </DropdownMenuContent>
            </DropdownMenu>
          </TableCell>
        </TableRow>

        <TableRow v-if="paginated.length === 0">
          <TableCell colspan="6" class="text-center py-16">
            <p class="text-sm font-semibold text-black/28">No records found</p>
            <p class="text-xs text-black/20 mt-1">Try a different search or region filter</p>
          </TableCell>
        </TableRow>
        </template>
      </TableBody>
    </Table>

    <div class="flex justify-center px-6 py-4 border-t border-black/5">
      <TablePagination :current-page="currentPage" :total-items="totalItems ?? filtered.length"
        :items-per-page="itemsPerPage" :current-page-items-count="paginated.length" @update:current-page="emit('update:currentPage', $event)" />
    </div>
  </div>
</template>
