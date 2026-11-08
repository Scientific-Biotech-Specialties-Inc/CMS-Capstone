<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { Contract } from '@/types/contract'
import { fmtDate, workflowStatusBadge, approvalStatusBadge } from '@/types/contract'

const router = useRouter()

const props = defineProps<{
  contracts: Contract[]
}>()

const recentContracts = computed(() => {
  return props.contracts.filter(c => c.approvalStatus === 'Approved').slice(0, 5)
})

function getStatusDisplay(c: Contract) {
  if (c.workflowStatus) {
    return {
      text: c.workflowStatus,
      class: workflowStatusBadge[c.workflowStatus] || 'bg-black/5 text-black/50 border-black/10'
    }
  }
  return {
    text: c.approvalStatus,
    class: approvalStatusBadge[c.approvalStatus] || 'bg-black/5 text-black/50 border-black/10'
  }
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full">
    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-black">
        Recent Contracts
        <span class="text-black/30 font-normal">({{ recentContracts.length }})</span>
      </h3>
      <button
        @click="router.push('/manager/contracts')"
        class="flex items-center gap-1 text-xs font-semibold text-brand-blue hover:text-brand-navy transition-colors"
      >
        View all <ArrowRight class="w-3.5 h-3.5" />
      </button>
    </div>

    <div v-if="recentContracts.length === 0" class="p-8 text-center text-sm text-black/40">
      No contracts found.
    </div>
    <Table v-else>
      <TableHeader class="bg-black/1.8">
        <TableRow class="border-b border-black/4 hover:bg-transparent">
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider pl-6 py-3">Contract</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Category</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">End Date</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow
          v-for="c in recentContracts"
          :key="c.id"
          class="border-b border-black/4 last:border-0 hover:bg-black/1.2 transition-colors cursor-pointer"
          @click="router.push('/manager/contracts/' + c.id)"
        >
          <TableCell class="pl-6 py-3.5">
            <p class="text-sm font-medium text-black leading-snug">{{ c.businessPartner }}</p>
            <p class="text-[11px] font-mono text-black/35 mt-0.5">{{ c.id }}</p>
          </TableCell>
          <TableCell class="py-3.5 text-sm text-black/50">{{ c.category }}</TableCell>
          <TableCell class="py-3.5">
            <Badge variant="outline" class="text-xs font-medium rounded-full px-2.5 py-0.5" :class="getStatusDisplay(c).class">
              {{ getStatusDisplay(c).text }}
            </Badge>
          </TableCell>
          <TableCell class="py-3.5 text-sm text-black/50">{{ fmtDate(c.endDate) }}</TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>
