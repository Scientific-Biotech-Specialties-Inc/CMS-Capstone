<script setup lang="ts">
import { computed } from 'vue'
import { Building2, Truck, Phone, Mail, User, MapPin } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import type { Partner, TabKey } from '@/types/partner'

const props = defineProps<{ open: boolean; partner: Partner | null; activeTab: TabKey }>()
defineEmits<{ 'update:open': [v: boolean] }>()

const displayId = computed(() => {
  if (!props.partner) return ''
  if (props.activeTab === 'partners') {
    const raw = props.partner.bpCode || String(props.partner.id)
    if (raw.startsWith('BP-')) return raw.replace(/^(BP-)+/i, 'BP-')
    return `BP-${String(props.partner.db_id || props.partner.id).padStart(4, '0')}`
  }
  const raw = String(props.partner.id)
  if (raw.startsWith('SP-')) return raw.replace(/^(SP-)+/i, 'SP-')
  return `SP-${String(props.partner.db_id || props.partner.id).padStart(4, '0')}`
})

const statusClass = computed(() => {
  if (!props.partner) return ''
  switch (props.partner.status) {
    case 'Active':    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'Inactive':  return 'bg-black/4 text-black/40 border-black/8'
    case 'Suspended': return 'bg-amber-50 text-amber-700 border-amber-200'
    default:          return 'bg-black/4 text-black/40 border-black/8'
  }
})
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-2xl p-0 overflow-hidden gap-0" @pointer-down-outside="$emit('update:open', false)">
      <template v-if="partner">

        <!-- Header -->
        <div class="px-5 pt-5 pb-4 border-b border-black/6">
          <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0 bg-brand-navy">
              <component :is="activeTab === 'partners' ? Building2 : Truck" class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0 pr-6">
              <DialogTitle class="text-sm font-bold text-black leading-snug truncate">{{ partner.name }}</DialogTitle>
              <DialogDescription class="text-xs text-black/45 mt-0.5 truncate">{{ partner.industry }}</DialogDescription>
              <div class="flex items-center gap-2 mt-1.5">
                <span class="text-[10px] font-mono text-black/30 bg-black/4 px-1.5 py-0.5 rounded">{{ displayId }}</span>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full border" :class="statusClass">
                  {{ partner.status }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Region + Code strip -->
        <div class="grid grid-cols-2 divide-x divide-black/6 border-b border-black/6">
          <div class="px-4 py-3">
            <p class="text-[9px] font-bold text-black/30 uppercase tracking-widest mb-1">Region</p>
            <p class="text-sm font-bold text-black">{{ partner.region ?? '—' }}</p>
          </div>
          <div class="px-4 py-3">
            <p class="text-[9px] font-bold text-black/30 uppercase tracking-widest mb-1">
              {{ activeTab === 'partners' ? 'BP Code' : 'TIN Number' }}
            </p>
            <p class="text-sm font-mono text-black/70">
              {{ activeTab === 'partners' ? (partner.bpCode ?? '—') : (partner.tinNumber ?? '—') }}
            </p>
          </div>
        </div>

        <!-- Contact info -->
        <div class="px-5 py-4 space-y-0 divide-y divide-black/4">
          <div class="flex items-center gap-3 py-2.5">
            <User class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Contact</span>
              <span class="text-sm font-medium text-black truncate text-right">{{ partner.contactPerson || '—' }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 py-2.5">
            <Mail class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Email</span>
              <span class="text-sm text-brand-blue truncate text-right">{{ partner.email || '—' }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 py-2.5">
            <Phone class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Phone</span>
              <span class="text-sm font-medium text-black tabular-nums">{{ partner.phone || '—' }}</span>
            </div>
          </div>

          <div class="flex items-start gap-3 py-2.5">
            <MapPin class="w-3.5 h-3.5 text-black/25 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0 flex items-start justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0 mt-0.5">Address</span>
              <span class="text-sm font-medium text-black text-right leading-snug">{{ partner.address || '—' }}</span>
            </div>
          </div>
        </div>
      </template>
    </DialogContent>
  </Dialog>
</template>
