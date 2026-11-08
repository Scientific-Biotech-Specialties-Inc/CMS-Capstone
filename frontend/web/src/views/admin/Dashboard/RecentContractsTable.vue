<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { Contract } from '@/types/contract'
import { fmtDate, workflowStatusBadge } from '@/types/contract'

const router = useRouter()

const props = defineProps<{
  contracts: (Contract & { days: number })[]
}>()

const timeFilter = ref<'1D' | '1W' | '1M' | 'All'>('All')

const recentContracts = computed(() => {
  let list = props.contracts

  if (timeFilter.value !== 'All') {
    const now = new Date()
    const cutoff = new Date()
    if (timeFilter.value === '1D') cutoff.setDate(now.getDate() - 1)
    else if (timeFilter.value === '1W') cutoff.setDate(now.getDate() - 7)
    else if (timeFilter.value === '1M') cutoff.setMonth(now.getMonth() - 1)

    list = list.filter(c => {
      const d = new Date(c.startDate)
      return d >= cutoff
    })
  }

  return list.slice(0, 5).map(c => ({
    id: c.id,
    partner: c.businessPartner,
    category: c.category,
    status: c.workflowStatus || 'SBSI Review',
    endDate: fmtDate(c.endDate)
  }))
})
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full flex flex-col justify-between">
    <div>
      <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-black">
          Recent contracts
          <span class="text-black/30 font-normal">({{ recentContracts.length }})</span>
        </h3>
        <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1">
          <button
            v-for="f in ['1D','1W','1M','All']"
            :key="f"
            @click="timeFilter = f as any"
            class="px-3 py-1 text-xs rounded transition-all font-medium"
            :class="timeFilter === f ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'"
          >
            {{ f }}
          </button>
        </div>
      </div>

      <div v-if="recentContracts.length === 0" class="p-8 text-center text-sm text-black/40">
        No recent contracts found.
      </div>
      <Table v-else>
        <TableHeader class="bg-black/[0.018]">
          <TableRow class="border-b border-black/4 hover:bg-transparent">
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider pl-6 py-3">Contract ID</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Partner</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Category</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">End Date</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="contract in recentContracts"
            :key="contract.id"
            class="border-b border-black/4 last:border-0 hover:bg-black/1.2 transition-colors cursor-pointer"
            @click="router.push('/admin/contracts/' + contract.id)"
          >
            <TableCell class="pl-6 py-3.5 text-xs font-medium text-brand-navy/70">{{ contract.id }}</TableCell>
            <TableCell class="py-3.5 text-sm text-black">{{ contract.partner }}</TableCell>
            <TableCell class="py-3.5 text-sm text-black/40">{{ contract.category }}</TableCell>
            <TableCell class="py-3.5">
              <Badge variant="outline" class="text-xs font-medium rounded-full px-2.5 py-0.5"
                :class="workflowStatusBadge[contract.status as keyof typeof workflowStatusBadge] || 'bg-black/5 text-black/50 border-black/10'">
                {{ contract.status }}
              </Badge>
            </TableCell>
            <TableCell class="py-3.5 text-sm text-black/40">{{ contract.endDate }}</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div class="px-6 py-3 border-t border-black/5">
      <button @click="router.push('/admin/contracts')" class="flex items-center gap-1.5 text-xs font-semibold text-brand-blue hover:text-brand-navy transition-colors">
        View all contracts <ArrowRight class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
