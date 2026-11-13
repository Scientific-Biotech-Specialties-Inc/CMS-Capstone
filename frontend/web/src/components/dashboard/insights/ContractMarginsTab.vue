<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, Coins, Flame, Receipt, Search, TrendingUp } from 'lucide-vue-next'
import { useAnalytics } from '@/composables/useAnalytics'
import type { ContractMarginItem } from '@/types/analytics'

const { contractMargins, loadingContractMargins, error, fetchContractMargins } = useAnalytics()
const searchQuery = ref('')

onMounted(() => {
  fetchContractMargins()
})

const filteredContracts = computed<ContractMarginItem[]>(() => {
  const list = contractMargins.value?.contracts ?? []
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return list
  return list.filter(
    c =>
      (c.contractCode ?? '').toLowerCase().includes(q) ||
      (c.clientName ?? '').toLowerCase().includes(q) ||
      (c.category ?? '').toLowerCase().includes(q),
  )
})

function formatCurrency(val: number | null): string {
  if (val === null) return '—'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(val)
}

function getMarginBadge(item: ContractMarginItem): { label: string; classes: string } {
  if (item.burnRatePct === null) {
    return { label: 'No Value Set', classes: 'bg-black/5 text-black/55 border-black/10' }
  }
  if (item.burnRatePct >= 100) {
    return { label: 'Over Budget', classes: 'bg-red-50 text-red-700 border-red-200' }
  }
  if (item.burnRatePct >= 75) {
    return { label: 'Watch', classes: 'bg-amber-50 text-amber-700 border-amber-200' }
  }
  return { label: 'Healthy', classes: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Skeleton loader during fetch -->
    <div v-if="loadingContractMargins" class="space-y-6 animate-pulse">
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="bg-white rounded-lg border border-black/8 p-6 shadow-xs">
          <div class="h-3 w-28 bg-black/5 rounded mb-4"></div>
          <div class="h-7 w-24 bg-black/5 rounded"></div>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-black/8 p-6 h-64"></div>
    </div>

    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6 text-sm text-red-700">
      {{ error }}
    </div>

    <template v-else-if="contractMargins">
      <div
        v-if="!contractMargins.sermsConnected"
        class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800"
      >
        <AlertTriangle class="w-4 h-4 shrink-0 text-amber-600" />
        <span>SERMS expense service is currently unreachable. Showing active contract values with ₱0.00 recorded expenses.</span>
      </div>

      <!-- Summary KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-black/8 p-6 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-black/50 uppercase tracking-wider">Active Contracts</span>
            <Receipt class="w-4 h-4 text-brand-navy" />
          </div>
          <p class="text-2xl font-bold text-black tabular-nums">{{ contractMargins.summary.contractsCount }}</p>
        </div>

        <div class="bg-white rounded-lg border border-black/8 p-6 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-black/50 uppercase tracking-wider">Total Contract Value</span>
            <Coins class="w-4 h-4 text-brand-blue" />
          </div>
          <p class="text-2xl font-bold text-black tabular-nums">{{ formatCurrency(contractMargins.summary.totalContractValue) }}</p>
        </div>

        <div class="bg-white rounded-lg border border-black/8 p-6 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-black/50 uppercase tracking-wider">Actual SERMS Expenses</span>
            <Flame class="w-4 h-4 text-amber-600" />
          </div>
          <p class="text-2xl font-bold text-black tabular-nums">{{ formatCurrency(contractMargins.summary.totalExpenses) }}</p>
        </div>

        <div class="bg-white rounded-lg border border-black/8 p-6 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-black/50 uppercase tracking-wider">True Net Margin</span>
            <TrendingUp class="w-4 h-4 text-emerald-600" />
          </div>
          <p class="text-2xl font-bold text-emerald-700 tabular-nums">{{ formatCurrency(contractMargins.summary.totalNetMargin) }}</p>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-lg border border-black/8 shadow-xs overflow-hidden">
        <div class="p-6 border-b border-black/8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 class="text-base font-semibold text-black">Contract Burn Rate &amp; True Net Margin</h2>
            <p class="text-xs text-black/50 mt-0.5">Comparing Total Contract Value against SERMS travel, spare parts, and field service expenses.</p>
          </div>
          <div class="relative w-full sm:w-64">
            <Search class="w-4 h-4 text-black/40 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search contract or client..."
              class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-black/15 focus:outline-none focus:border-brand-blue"
            />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-black/2 border-b border-black/8 text-xs font-semibold text-black/60">
                <th class="py-3 px-6">Contract</th>
                <th class="py-3 px-4">Client</th>
                <th class="py-3 px-4 text-right">Contract Value</th>
                <th class="py-3 px-4 text-right">Actual Expenses</th>
                <th class="py-3 px-4 text-right">Burn Rate</th>
                <th class="py-3 px-4 text-right">True Net Margin</th>
                <th class="py-3 px-6 text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/6 text-sm">
              <tr v-if="filteredContracts.length === 0">
                <td colspan="7" class="py-8 text-center text-xs text-black/45">
                  No active contracts found matching your filter.
                </td>
              </tr>
              <tr v-for="row in filteredContracts" :key="row.contractId" class="hover:bg-black/2 transition-colors">
                <td class="py-3.5 px-6">
                  <p class="font-semibold text-black text-xs">{{ row.contractCode || `#${row.contractId}` }}</p>
                  <p class="text-xs text-black/45">{{ row.category || 'Uncategorized' }}</p>
                </td>
                <td class="py-3.5 px-4 text-xs font-medium text-black/80">{{ row.clientName || '—' }}</td>
                <td class="py-3.5 px-4 text-right text-xs font-medium text-black tabular-nums">
                  {{ formatCurrency(row.totalContractValue) }}
                </td>
                <td class="py-3.5 px-4 text-right">
                  <p class="text-xs font-semibold text-black tabular-nums">{{ formatCurrency(row.actualExpenses) }}</p>
                  <p class="text-[11px] text-black/45 tabular-nums">
                    Travel {{ formatCurrency(row.expenseBreakdown.travel) }} · Parts {{ formatCurrency(row.expenseBreakdown.spareParts) }}
                  </p>
                </td>
                <td class="py-3.5 px-4 text-right text-xs font-semibold tabular-nums text-black">
                  {{ row.burnRatePct !== null ? `${row.burnRatePct}%` : '—' }}
                </td>
                <td class="py-3.5 px-4 text-right text-xs font-bold tabular-nums" :class="(row.trueNetMargin ?? 0) < 0 ? 'text-red-600' : 'text-emerald-700'">
                  {{ formatCurrency(row.trueNetMargin) }}
                </td>
                <td class="py-3.5 px-6 text-right">
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                    :class="getMarginBadge(row).classes"
                  >
                    {{ getMarginBadge(row).label }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
