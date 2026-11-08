<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { ShieldCheck, Loader2 } from 'lucide-vue-next'
import { VisSingleContainer, VisDonut } from '@unovis/vue'
import { useOwnRiskInsights } from '@/composables/useOwnRiskInsights'
import { severityLabel } from '@/types/riskAssessment'
import type { Contract } from '@/types/contract'

// Employee/Sales-scoped "Diagnostic" tab. Unlike the Admin/Manager version,
// this never calls the system-wide analytics endpoints — it only
// summarizes AI Risk Assessment results for contracts the current
// employee created, so no cross-user data is exposed.
const props = defineProps<{
  contracts: Contract[]
}>()

const { loading, load, breakdown, scannedCount } = useOwnRiskInsights()

async function loadData() {
  if (props.contracts.length > 0) {
    await load(props.contracts)
  }
}

onMounted(loadData)
watch(() => props.contracts.map(c => c.id).join(','), loadData)

const donutData = computed(() => {
  const colorMap: Record<string, string> = {
    low: '#10b981',
    medium: '#f59e0b',
    high: '#ef4444',
    critical: '#dc2626',
    unassessed: 'rgba(0,0,0,0.15)',
  }
  return breakdown(props.contracts)
    .filter(b => b.count > 0)
    .map(b => ({
      label: b.level === 'unassessed' ? 'Not Assessed' : severityLabel[b.level],
      value: b.count,
      color: colorMap[b.level],
    }))
})

const value = (d: { value: number }) => d.value
const color = (d: { color: string }) => d.color
</script>

<template>
  <div class="space-y-6">

    <p class="text-sm text-black/40">AI Risk Assessment breakdown for contracts you created.</p>

    <div v-if="loading" class="flex items-center justify-center py-16 text-black/30">
      <Loader2 class="w-5 h-5 animate-spin" />
    </div>

    <div v-else class="bg-white rounded-lg border border-black/8 shadow-sm p-6 max-w-md">
      <h3 class="text-sm font-semibold text-black mb-1">Your Contracts — Risk Breakdown</h3>
      <p class="text-xs text-black/35 mb-4">{{ scannedCount }} of {{ props.contracts.length }} contracts scanned so far.</p>
      <div v-if="donutData.length === 0" class="flex flex-col items-center gap-2 py-10 text-black/25">
        <ShieldCheck class="w-8 h-8" />
        <p class="text-xs">No contracts to assess yet.</p>
      </div>
      <template v-else>
        <VisSingleContainer :data="donutData" :height="200">
          <VisDonut :value="value" :color="color" :arc-width="24" />
        </VisSingleContainer>
        <div class="flex flex-wrap gap-3 mt-4">
          <div v-for="d in donutData" :key="d.label" class="flex items-center gap-1.5">
            <div class="w-2.5 h-2.5 rounded-sm" :style="{ backgroundColor: d.color }"></div>
            <span class="text-xs text-black/50">{{ d.label }} ({{ d.value }})</span>
          </div>
        </div>
      </template>
    </div>

  </div>
</template>
