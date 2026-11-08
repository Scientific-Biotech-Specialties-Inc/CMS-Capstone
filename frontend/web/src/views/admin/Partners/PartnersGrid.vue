<script setup lang="ts">
import { Building2, Truck, MoreHorizontal, Eye, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { Partner, TabKey } from '@/types/partner'

defineProps<{ partners: Partner[]; activeTab: TabKey; loading?: boolean; canDelete?: boolean }>()
const emit = defineEmits<{ openDetail: [p: Partner]; openDelete: [p: Partner] }>()

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
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

    <template v-if="loading">
      <div v-for="i in 8" :key="i"
        class="bg-white rounded-xl border border-black/8 shadow-sm p-5 space-y-4">
        <div class="flex items-start justify-between">
          <div class="w-10 h-10 bg-black/5 animate-pulse rounded-xl" />
          <div class="h-5 w-16 bg-black/5 animate-pulse rounded-full" />
        </div>
        <div class="space-y-2">
          <div class="h-4 w-36 bg-black/5 animate-pulse rounded" />
          <div class="h-3 w-24 bg-black/5 animate-pulse rounded" />
        </div>
        <div class="border-t border-black/5 pt-3 space-y-2">
          <div class="flex items-center justify-between">
            <div class="h-3 w-12 bg-black/5 animate-pulse rounded" />
            <div class="h-3 w-16 bg-black/5 animate-pulse rounded" />
          </div>
          <div class="flex items-center justify-between">
            <div class="h-3 w-14 bg-black/5 animate-pulse rounded" />
            <div class="h-3 w-20 bg-black/5 animate-pulse rounded" />
          </div>
          <div class="flex items-center justify-between">
            <div class="h-3 w-10 bg-black/5 animate-pulse rounded" />
            <div class="h-3 w-24 bg-black/5 animate-pulse rounded" />
          </div>
        </div>
      </div>
    </template>

    <template v-else>
    <div
      v-for="partner in partners"
      :key="partner.id"
      role="button"
      tabindex="0"
      @click="emit('openDetail', partner)"
      @keydown.enter="emit('openDetail', partner)"
      class="text-left bg-white rounded-xl border border-black/8 shadow-sm p-5 hover:border-brand-blue/40 hover:shadow-md transition-all duration-200 group cursor-pointer"
    >
      <div class="flex items-start justify-between mb-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-brand-navy/8 text-brand-navy">
          <component :is="activeTab === 'partners' ? Building2 : Truck" class="w-5 h-5" />
        </div>
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-medium px-2.5 py-0.5 rounded-full border" :class="statusClass(partner.status)">
            {{ partner.status }}
          </span>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" size="icon" @click.stop
                class="h-7 w-7 text-black/60 hover:text-black hover:bg-black/5 data-[state=open]:bg-black/5 data-[state=open]:text-black">
                <MoreHorizontal class="w-4 h-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-44" @click.stop>
              <DropdownMenuItem @click="emit('openDetail', partner)" class="gap-2.5 text-sm cursor-pointer">
                <Eye class="w-3.5 h-3.5 text-black/40" /> View details
              </DropdownMenuItem>
              <template v-if="canDelete">
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="emit('openDelete', partner)"
                  class="gap-2.5 text-sm cursor-pointer text-red-600 focus:text-red-600 focus:bg-red-50">
                  <Trash2 class="w-3.5 h-3.5" /> Delete
                </DropdownMenuItem>
              </template>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
      <p class="text-sm font-semibold text-black leading-snug group-hover:text-brand-navy transition-colors truncate"
         :title="partner.name">{{ partner.name }}</p>
      <p class="text-xs text-black/40 mt-0.5 mb-4 truncate"
         :title="partner.industry || undefined">{{ partner.industry || '—' }}</p>
      <div class="space-y-1.5 border-t border-black/5 pt-3">
        <div class="flex items-center justify-between text-xs">
          <span class="text-black/40">Region</span>
          <span class="font-medium text-black">{{ partner.region ?? '—' }}</span>
        </div>
        <div class="flex items-center justify-between text-xs">
          <span class="text-black/40">Contact</span>
          <span class="font-medium text-black truncate max-w-[120px] text-right">{{ partner.contactPerson || '—' }}</span>
        </div>
        <div class="flex items-center justify-between text-xs">
          <span class="text-black/40">Phone</span>
          <span class="font-medium text-black tabular-nums">{{ partner.phone || '—' }}</span>
        </div>
      </div>
    </div>

    <div v-if="partners.length === 0" class="col-span-full py-16 text-center">
      <p class="text-sm font-medium text-black/40">No results found.</p>
      <p class="text-xs text-black/25 mt-1">Try adjusting your search or region filter.</p>
    </div>
    </template>
  </div>
</template>
