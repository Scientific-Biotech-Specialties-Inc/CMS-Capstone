<script setup lang="ts">
import { AlertTriangle, Loader2, Receipt, RefreshCw } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { TabKey, VendorReimbursement } from '@/types/partner'

defineProps<{
  items: VendorReimbursement[]
  loading: boolean
  refreshing: boolean
  sermsConnected: boolean
  totalAmount: number
  syncedAt: string | null
  vendorType: TabKey
}>()

const emit = defineEmits<{
  (e: 'refresh'): void
}>()

function formatCurrency(val: number): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 2,
  }).format(val)
}

function statusClass(status: string): string {
  switch (status.toLowerCase()) {
    case 'approved':
    case 'paid':
    case 'reimbursed':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'rejected':
      return 'bg-red-50 text-red-700 border-red-200'
    case 'pending':
    default:
      return 'bg-amber-50 text-amber-700 border-amber-200'
  }
}

function toTitleCase(val: string): string {
  if (!val) return 'Pending'
  return val.charAt(0).toUpperCase() + val.slice(1).toLowerCase()
}
</script>

<template>
  <div class="space-y-4 font-poppins">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <Receipt class="w-4 h-4 text-brand-navy" />
        <div>
          <div class="flex items-center gap-2">
            <h3 class="text-base font-semibold text-brand-dark">Related Reimbursement History</h3>
            <span class="px-2 py-0.5 text-xs font-medium bg-brand-blue/10 text-brand-blue rounded-full tabular-nums">
              {{ items.length }}
            </span>
          </div>
          <p class="text-xs text-black/45 font-montserrat">
            SERMS reimbursements matched to this {{ vendorType === 'partners' ? 'business partner' : 'supplier' }}.
            <span v-if="items.length > 0" class="font-medium text-black/65">
              Total: {{ formatCurrency(totalAmount) }}
            </span>
          </p>
        </div>
      </div>

      <Button
        variant="outline"
        size="sm"
        :disabled="loading || refreshing"
        @click="emit('refresh')"
        class="h-8 px-3 text-xs border-black/15 text-black/70 hover:text-black gap-1.5 shrink-0"
      >
        <Loader2 v-if="refreshing" class="w-3.5 h-3.5 animate-spin" />
        <RefreshCw v-else class="w-3.5 h-3.5" />
        Sync from SERMS
      </Button>
    </div>

    <div
      v-if="!loading && !sermsConnected"
      class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800"
    >
      <AlertTriangle class="w-4 h-4 shrink-0 text-amber-600" />
      <span>SERMS service is currently unreachable. Showing stored reimbursement records synced in CMS.</span>
    </div>

    <div v-if="loading" class="space-y-2 animate-pulse">
      <div v-for="i in 3" :key="i" class="h-10 bg-black/5 rounded-lg" />
    </div>

    <div
      v-else-if="items.length === 0"
      class="flex flex-col items-center justify-center p-6 border border-dashed border-black/[0.08] rounded-lg bg-black/[0.005]"
    >
      <p class="text-sm text-black/40">No SERMS reimbursement records found for this {{ vendorType === 'partners' ? 'business partner' : 'supplier' }}.</p>
      <p class="text-xs text-black/30 mt-1 font-montserrat">Matching SERMS reimbursements will be stored here automatically when synced.</p>
    </div>

    <div v-else class="overflow-x-auto border border-black/[0.06] rounded-lg modal-scrollbar">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-black/[0.018] border-b border-black/[0.04]">
            <th class="px-4 py-2 text-xs font-semibold text-black/50">Reference</th>
            <th class="px-4 py-2 text-xs font-semibold text-black/50">Description</th>
            <th class="px-4 py-2 text-xs font-semibold text-black/50">Category</th>
            <th class="px-4 py-2 text-xs font-semibold text-black/50">Employee</th>
            <th class="px-4 py-2 text-xs font-semibold text-black/50">Expense Date</th>
            <th class="px-4 py-2 text-xs font-semibold text-black/50 text-right">Amount</th>
            <th class="px-4 py-2 text-xs font-semibold text-black/50">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-black/[0.04]">
          <tr v-for="item in items" :key="item.id" class="hover:bg-black/[0.018] transition-colors">
            <td class="px-4 py-3 text-xs font-mono font-semibold text-brand-dark whitespace-nowrap">
              {{ item.referenceNo }}
            </td>
            <td class="px-4 py-3 text-xs text-black/75 max-w-[260px] truncate" :title="item.description ?? ''">
              {{ item.description || '—' }}
            </td>
            <td class="px-4 py-3 text-xs text-black/60 whitespace-nowrap">
              {{ item.category || '—' }}
            </td>
            <td class="px-4 py-3 text-xs text-black/60 whitespace-nowrap">
              {{ item.employeeName || '—' }}
            </td>
            <td class="px-4 py-3 text-xs text-black/50 tabular-nums whitespace-nowrap">
              {{ item.expenseDate || '—' }}
            </td>
            <td class="px-4 py-3 text-xs font-semibold text-black text-right tabular-nums whitespace-nowrap">
              {{ formatCurrency(item.amount) }}
            </td>
            <td class="px-4 py-3 text-xs whitespace-nowrap">
              <span
                class="inline-flex px-2 py-0.5 text-[11px] font-semibold border rounded-full"
                :class="statusClass(item.status)"
              >
                {{ toTitleCase(item.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
