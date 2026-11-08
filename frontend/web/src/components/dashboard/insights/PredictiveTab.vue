<script setup lang="ts">
import { Brain } from 'lucide-vue-next'
import type { PredictiveInsight } from '@/types/analytics'
import PredictiveForecastChart from './PredictiveForecastChart.vue'

const props = defineProps<{
  predictions: PredictiveInsight[]
}>()
</script>

<template>
  <div class="space-y-6">
    <div v-if="props.predictions.length === 0" class="bg-white rounded-xl border border-black/8 shadow-sm p-12 text-center">
      <Brain class="w-8 h-8 mx-auto text-black/20 mb-3" />
      <p class="text-sm font-semibold text-black/60">No Predictive Insights Available Yet</p>
      <p class="text-xs text-black/40 mt-1">Click "Refresh Analytics" above to trigger Gemini to generate 30-day forecasts.</p>
    </div>

    <template v-else>
      <PredictiveForecastChart
        v-for="p in props.predictions"
        :key="p.metricType"
        :insight="p"
      />
    </template>
  </div>
</template>
