<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, ShieldQuestion, RefreshCw, Brain } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useRiskAssessment } from '@/composables/useRiskAssessment'
import { useToast } from '@/composables/useToast'
import RiskSummaryHeader from './RiskSummaryHeader.vue'
import RiskFindingsTable from './RiskFindingsTable.vue'
import RiskExportButton  from './RiskExportButton.vue'

// US-026: AI Risk Assessment Summary — thin shell that fetches the summary
// for a contract id and owns loading/export state. Reachable from the
// contract detail header's risk flag badge and the manager approval queue.
const route  = useRoute()
const router = useRouter()
const { error: showError } = useToast()
const { summary, loading, scanning, exporting, fetchSummary, triggerScan, exportPdf } = useRiskAssessment()

const contractId = route.params.id as string

const backPath = computed(() => {
  if (route.path.startsWith('/admin')) return `/admin/contracts/${contractId}`
  if (route.path.startsWith('/manager')) return `/manager/contracts/${contractId}`
  return `/sales/contracts/${contractId}`
})

const isPolling = ref(false)

async function pollSummary() {
  isPolling.value = true
  // Give the backend a moment to queue and start the job
  await new Promise(resolve => setTimeout(resolve, 2000))
  while (isPolling.value) {
    const s = await fetchSummary(contractId)
    if (s && s.status !== 'pending') {
      break
    }
    await new Promise(resolve => setTimeout(resolve, 3000))
  }
  isPolling.value = false
}

async function load() {
  const s = await fetchSummary(contractId)
  if (s && s.status === 'pending') {
    pollSummary()
  }
}

onMounted(load)

async function handleRescan() {
  const ok = await triggerScan(contractId)
  if (ok) {
    // Force-set a pending state so the loading animation shows immediately.
    // Without this, pollSummary() would see the OLD completed/failed result,
    // think the scan is done, and exit before the new one finishes.
    if (summary.value) {
      summary.value = { ...summary.value, status: 'pending' }
    }
    await pollSummary()
    // Final fetch to ensure we have the latest result after polling ends
    await fetchSummary(contractId)
  } else {
    showError('Failed to trigger scan', 'Something went wrong.')
  }
}

async function handleExport() {
  const ok = await exportPdf(contractId)
  if (!ok) {
    showError('Export failed', 'Could not export the PDF. Please try again.')
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-center gap-4">
      <button @click="router.push(backPath)"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1">
        <h1 class="text-xl font-semibold text-black">AI Risk Assessment</h1>
        <p class="text-sm text-black/40 mt-0.5">Contract #{{ contractId }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" :disabled="scanning || isPolling" @click="handleRescan"
          class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
          <RefreshCw class="w-4 h-4" :class="scanning || isPolling ? 'animate-spin' : ''" />
          {{ scanning || isPolling ? 'Scanning…' : 'Re-run Scan' }}
        </Button>
        <RiskExportButton v-if="summary" :exporting="exporting" @export="handleExport" />
      </div>
    </div>

    <!-- Animated Scanning State -->
    <div v-if="loading || isPolling" class="flex flex-col items-center justify-center py-28 bg-white rounded-xl border border-black/8 shadow-sm">
      <div class="relative w-16 h-16 flex items-center justify-center mb-6">
        <div class="absolute inset-0 bg-brand-navy/10 rounded-full animate-ping" style="animation-duration: 2s;"></div>
        <div class="absolute inset-2 bg-brand-blue/20 rounded-full animate-pulse"></div>
        <Brain class="w-7 h-7 text-brand-navy relative z-10 animate-bounce" style="animation-duration: 2s;" />
      </div>
      <h3 class="text-lg font-semibold text-black mb-1">Scanning Document...</h3>
      <p class="text-sm text-black/40">Analyzing contract against playbook clauses.</p>
    </div>

    <div v-else-if="!summary" class="flex flex-col items-center gap-3 py-24 text-black/30">
      <ShieldQuestion class="w-12 h-12" />
      <p class="text-base font-semibold">No assessment yet</p>
      <p class="text-sm text-black/25">Run an AI Risk Assessment scan for this contract to see results here.</p>
      <Button :disabled="scanning" @click="handleRescan" class="mt-2 h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white">
        {{ scanning ? 'Queuing…' : 'Run Scan Now' }}
      </Button>
    </div>

    <template v-else>
      <RiskSummaryHeader :summary="summary" />
      <RiskFindingsTable :findings="summary.findings" />
    </template>

  </div>
</template>
