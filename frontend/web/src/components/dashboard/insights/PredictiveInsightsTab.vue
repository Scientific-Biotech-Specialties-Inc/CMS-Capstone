<script setup lang="ts">
import { onMounted } from 'vue'
import { RefreshCw, BarChart3 } from 'lucide-vue-next'
import { useAnalytics } from '@/composables/useAnalytics'
import { useToast } from '@/composables/useToast'
import PredictiveTab from './PredictiveTab.vue'

// "Predictive" dashboard tab content for Admin and Manager (full
// system-wide analytics-service data). Rendered only when the AI Risk
// Assessment permission is enabled — gating is handled by the parent
// Dashboard index.vue. See DiagnosticInsightsTab.vue for the sibling tab.
const {
  predictions,
  loadingPredictive,
  refreshing,
  error,
  fetchPredictive,
  refresh,
} = useAnalytics()

const { success, error: toastError } = useToast()

async function handleRefresh() {
  const result = await refresh()
  if (result) {
    success('Analytics refreshed', 'Latest AI forecasts have been recomputed.')
    await fetchPredictive()
  } else {
    toastError('Refresh failed', error.value ?? 'Could not refresh analytics right now.')
  }
}

onMounted(fetchPredictive)
</script>

<template>
  <div class="space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
      <p class="text-sm text-black/40">AI-generated 30-day predictive forecasts across the system.</p>
      <button
        @click="handleRefresh"
        :disabled="refreshing"
        class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg bg-brand-navy text-white hover:bg-brand-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-xs"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
        {{ refreshing ? 'Refreshing…' : 'Refresh Analytics' }}
      </button>
    </div>

    <div v-if="loadingPredictive && predictions.length === 0" class="space-y-6 animate-pulse">
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
          <div class="h-3.5 w-24 bg-black/5 rounded mb-4"></div>
          <div class="h-8 w-12 bg-black/5 rounded"></div>
        </div>
      </div>
    </div>

    <div v-else-if="error && predictions.length === 0" class="bg-white rounded-lg border border-black/8 shadow-sm p-10 text-center">
      <BarChart3 class="w-8 h-8 mx-auto text-black/20 mb-3" />
      <p class="text-sm text-black/50">{{ error }}</p>
    </div>

    <PredictiveTab v-else :predictions="predictions" />

  </div>
</template>
