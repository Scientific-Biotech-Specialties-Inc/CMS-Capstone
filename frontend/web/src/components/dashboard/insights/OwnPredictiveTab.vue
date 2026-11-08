<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { ShieldAlert, ShieldCheck, TrendingUp, Loader2 } from 'lucide-vue-next'
import { useOwnRiskInsights } from '@/composables/useOwnRiskInsights'
import { severityIconColor } from '@/types/riskAssessment'
import type { Contract } from '@/types/contract'

// Employee/Sales-scoped "Predictive"-style tab: surfaces the employee's own
// contracts currently flagged High/Critical risk as forward-looking items
// to watch (renewal/approval risk exposure), without calling the
// system-wide analytics-service forecast endpoint.
const props = defineProps<{
  contracts: Contract[]
}>()

const { loading, load, highRiskContracts, scannedCount } = useOwnRiskInsights()

async function loadData() {
  if (props.contracts.length > 0) {
    await load(props.contracts)
  }
}

onMounted(loadData)
watch(() => props.contracts.map(c => c.id).join(','), loadData)
</script>

<template>
  <div class="space-y-6">

    <p class="text-sm text-black/40">Contracts you created that are flagged as high risk exposure going forward.</p>

    <div v-if="loading" class="flex items-center justify-center py-16 text-black/30">
      <Loader2 class="w-5 h-5 animate-spin" />
    </div>

    <div v-else class="bg-white rounded-lg border border-black/8 shadow-sm p-6">
      <div class="flex items-center gap-2 mb-4">
        <TrendingUp class="w-4 h-4 text-brand-navy" />
        <h3 class="text-sm font-semibold text-black">Flagged High/Critical Risk Contracts</h3>
      </div>
      <p class="text-xs text-black/35 mb-4">{{ scannedCount }} of {{ props.contracts.length }} contracts scanned so far.</p>
      <div v-if="highRiskContracts(props.contracts).length === 0" class="flex flex-col items-center gap-2 py-10 text-black/25">
        <ShieldCheck class="w-8 h-8" />
        <p class="text-xs">No high or critical risk contracts flagged.</p>
      </div>
      <ul v-else class="divide-y divide-black/[0.04]">
        <li v-for="c in highRiskContracts(props.contracts)" :key="c.id" class="py-2.5 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-medium text-black truncate">{{ c.businessPartner }}</p>
            <p class="text-xs text-black/35">{{ c.id }}</p>
          </div>
          <ShieldAlert class="w-4 h-4 shrink-0" :class="severityIconColor.high" />
        </li>
      </ul>
    </div>

  </div>
</template>
