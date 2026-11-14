<script setup lang="ts">
import { Receipt } from 'lucide-vue-next'
import type { SupportingExpenseSummary } from '@/types/contract'

defineProps<{
  summary: SupportingExpenseSummary
  sourceSystem?: string | null
  sourceReferenceId?: string | null
}>()

function formatCurrency(val: number): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 2,
  }).format(val)
}
</script>

<template>
  <div class="bg-white border border-black/[0.08] rounded-xl overflow-hidden shadow-sm mt-6">
    <div class="p-8 pb-4 border-b border-black/[0.04] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
      <div class="flex items-center gap-2">
        <Receipt class="w-4 h-4 text-brand-navy" />
        <h3 class="text-[10px] font-bold text-brand-navy/60 uppercase tracking-widest">
          SERMS Recurring Expense Evidence
        </h3>
      </div>
      <div class="flex items-center gap-2 text-xs">
        <span class="px-2 py-0.5 rounded-full font-medium bg-brand-blue/10 text-brand-navy border border-brand-blue/20">
          Source: {{ sourceSystem || 'SERMS' }}
        </span>
        <span v-if="sourceReferenceId" class="font-mono text-black/50">
          {{ sourceReferenceId }}
        </span>
      </div>
    </div>

    <div class="p-8 space-y-6">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div>
          <p class="text-xs font-semibold text-black/45">Vendor / Merchant</p>
          <p class="text-sm font-semibold text-black mt-1">{{ summary.vendorName }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold text-black/45">Occurrences</p>
          <p class="text-sm font-semibold text-black tabular-nums mt-1">{{ summary.occurrenceCount }}x</p>
        </div>
        <div>
          <p class="text-xs font-semibold text-black/45">Total Spend</p>
          <p class="text-sm font-semibold text-brand-navy tabular-nums mt-1">{{ formatCurrency(summary.totalSpend) }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold text-black/45">Period</p>
          <p class="text-sm text-black/80 tabular-nums mt-1">{{ summary.periodStart }} to {{ summary.periodEnd }}</p>
        </div>
      </div>

      <div v-if="summary.expenseItems.length > 0" class="overflow-x-auto border border-black/[0.06] rounded-lg">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-black/[0.018] border-b border-black/[0.04]">
              <th class="px-4 py-2 text-xs font-semibold text-black/50">Reference</th>
              <th class="px-4 py-2 text-xs font-semibold text-black/50">Date</th>
              <th class="px-4 py-2 text-xs font-semibold text-black/50 text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-black/[0.04]">
            <tr v-for="(item, idx) in summary.expenseItems" :key="`${item.reference}-${idx}`">
              <td class="px-4 py-2.5 text-xs font-mono font-medium text-brand-dark">{{ item.reference }}</td>
              <td class="px-4 py-2.5 text-xs text-black/60 tabular-nums">{{ item.date }}</td>
              <td class="px-4 py-2.5 text-xs font-semibold text-black text-right tabular-nums">{{ formatCurrency(item.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
