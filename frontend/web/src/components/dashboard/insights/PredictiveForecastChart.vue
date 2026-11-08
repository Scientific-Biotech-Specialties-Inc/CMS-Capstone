<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed, ref } from 'vue'
import { VisXYContainer, VisLine, VisAxis, VisTooltip, VisCrosshair } from '@unovis/vue'
import { Sparkles, TrendingUp, ShieldCheck, Eye, History } from 'lucide-vue-next'
import type { PredictiveInsight, TimePoint } from '@/types/analytics'
import { predictiveLabels } from '@/types/analytics'

const props = defineProps<{
  insight: PredictiveInsight
}>()

type ViewMode = 'all' | 'historical' | 'forecast'
const viewMode = ref<ViewMode>('all')

interface ChartPoint {
  index: number
  date: string
  historicalVal: number | null
  predictedVal: number | null
  isForecast: boolean
}

const chartData = computed<ChartPoint[]>(() => {
  const points: ChartPoint[] = []
  const hist = props.insight.historicalSeries ?? []
  const pred = props.insight.predictedSeries ?? []

  let idx = 0

  if (viewMode.value === 'all' || viewMode.value === 'historical') {
    hist.forEach((pt: TimePoint) => {
      points.push({
        index: idx++,
        date: pt.date,
        historicalVal: Number(pt.value),
        predictedVal: null,
        isForecast: false,
      })
    })
  }

  // Bridge historical to predicted so the line connects seamlessly in 'all' mode
  if (viewMode.value === 'all' && hist.length > 0 && pred.length > 0) {
    const lastHist = hist[hist.length - 1]
    points[points.length - 1].predictedVal = Number(lastHist.value)
  }

  if (viewMode.value === 'all' || viewMode.value === 'forecast') {
    pred.forEach((pt: TimePoint) => {
      points.push({
        index: idx++,
        date: pt.date,
        historicalVal: null,
        predictedVal: Number(pt.value),
        isForecast: true,
      })
    })
  }

  return points
})

const x = (d: ChartPoint) => d.index
const yHistorical = (d: ChartPoint) => d.historicalVal
const yPredicted = (d: ChartPoint) => d.predictedVal

// Fix the Y-axis domain to the full historical + forecast range (computed
// once, independent of the active view toggle) so switching between
// Combined / Historical Only / AI Forecast Only never rescales the axis.
// Without this, each view mode auto-scaled to only its own visible points,
// which could make a mild slope look dramatically steeper when viewed in
// isolation than it does in the combined view.
const yDomain = computed<[number, number]>(() => {
  const hist = props.insight.historicalSeries ?? []
  const pred = props.insight.predictedSeries ?? []
  const values = [...hist, ...pred].map(p => Number(p.value)).filter(v => !isNaN(v))

  if (values.length === 0) return [0, 1]

  const min = Math.min(...values)
  const max = Math.max(...values)
  const padding = (max - min) * 0.1 || 1
  return [Math.max(0, min - padding), max + padding]
})

const xTickFormat = (i: number) => {
  const pt = chartData.value[Math.round(i)]
  if (!pt) return ''
  const d = new Date(pt.date)
  return isNaN(d.getTime()) ? pt.date : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const confidenceConfig = computed(() => {
  switch (props.insight.confidence) {
    case 'high':
      return { bg: 'bg-emerald-100 text-emerald-800', label: 'High Confidence' }
    case 'low':
      return { bg: 'bg-amber-100 text-amber-800', label: 'Low Confidence' }
    default:
      return { bg: 'bg-blue-100 text-blue-800', label: 'Medium Confidence' }
  }
})

const tooltipTemplate = (d: ChartPoint) => {
  const dateStr = new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  const val = d.isForecast ? d.predictedVal : d.historicalVal
  const typeLabel = d.isForecast ? 'Predicted AI Forecast' : 'Historical Metric'
  return `<div class="bg-[#1e1e2e] text-white rounded-lg shadow-xl px-3 py-2 text-xs font-medium">
    <div class="text-white/60 mb-0.5">${dateStr} · ${typeLabel}</div>
    <div class="font-bold text-sm text-white">${val !== null ? Number(val).toFixed(2) : '—'}</div>
  </div>`
}
</script>

<template>
  <div class="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden">
    
    <!-- Card Header -->
    <div class="px-6 py-4 border-b border-black/6 flex items-center justify-between gap-4 flex-wrap bg-slate-50/50">
      <div class="flex items-center gap-3">
        <div class="p-2 rounded-lg bg-brand-navy text-white shadow-xs">
          <TrendingUp class="w-5 h-5" />
        </div>
        <div>
          <h3 class="text-base font-bold text-black">
            {{ predictiveLabels[props.insight.metricType] ?? props.insight.metricType }}
          </h3>
          <p class="text-xs text-black/40">
            Generated {{ new Date(props.insight.generatedAt).toLocaleDateString() }} · 30-Day AI Projection
          </p>
        </div>
      </div>

      <div class="flex items-center gap-3 flex-wrap">
        <!-- View Toggle Switch -->
        <div class="flex items-center gap-0.5 bg-black/5 p-1 rounded-lg border border-black/8">
          <button
            @click="viewMode = 'all'"
            class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all flex items-center gap-1"
            :class="viewMode === 'all' ? 'bg-white text-black shadow-xs' : 'text-black/50 hover:text-black'"
          >
            <Eye class="w-3.5 h-3.5 text-brand-navy" />
            Combined View
          </button>

          <button
            @click="viewMode = 'historical'"
            class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all flex items-center gap-1"
            :class="viewMode === 'historical' ? 'bg-white text-black shadow-xs' : 'text-black/50 hover:text-black'"
          >
            <History class="w-3.5 h-3.5 text-brand-navy" />
            Historical Only
          </button>

          <button
            @click="viewMode = 'forecast'"
            class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all flex items-center gap-1"
            :class="viewMode === 'forecast' ? 'bg-white text-black shadow-xs' : 'text-black/50 hover:text-black'"
          >
            <Sparkles class="w-3.5 h-3.5 text-brand-blue" />
            AI Forecast Only
          </button>
        </div>

        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full" :class="confidenceConfig.bg">
          <ShieldCheck class="w-3.5 h-3.5" />
          {{ confidenceConfig.label }}
        </span>
      </div>
    </div>

    <div class="p-6 space-y-6">

      <!-- Chart Legend -->
      <div class="flex items-center gap-6 text-xs font-semibold text-black/60 flex-wrap">
        <div v-if="viewMode === 'all' || viewMode === 'historical'" class="flex items-center gap-2">
          <span class="w-4 h-1 bg-brand-navy rounded-full inline-block"></span>
          <span>Historical (Actual Data)</span>
        </div>
        <div v-if="viewMode === 'all' || viewMode === 'forecast'" class="flex items-center gap-2">
          <span class="w-4 h-1 bg-brand-blue rounded-full border-t border-dashed border-brand-blue inline-block"></span>
          <span>AI Forecast (Next 30 Days)</span>
        </div>
      </div>

      <!-- Forecast Line Chart -->
      <div class="bg-slate-50/50 rounded-xl p-4 border border-black/5">
        <VisXYContainer
          :data="chartData"
          :height="240"
          :y-domain="yDomain"
          :style="{
            '--vis-axis-tick-label-color': 'rgba(0,0,0,0.5)',
            '--vis-axis-domain-color': 'transparent',
            '--vis-axis-tick-line-color': 'transparent',
            '--vis-axis-grid-color': 'rgba(0,0,0,0.05)',
            '--vis-font-family': 'Poppins, sans-serif',
          }"
        >
          <!-- Solid Historical Line -->
          <VisLine v-if="viewMode === 'all' || viewMode === 'historical'" :x="x" :y="yHistorical" :color="BRAND_HEX.navy" :stroke-width="2.5" />
          
          <!-- Dotted Forecast Line -->
          <VisLine v-if="viewMode === 'all' || viewMode === 'forecast'" :x="x" :y="yPredicted" :color="BRAND_HEX.blue" :stroke-dasharray="'4 4'" :stroke-width="2.5" />
          
          <VisAxis type="x" :tick-format="xTickFormat" />
          <VisAxis type="y" :tickFormat="(v: number) => String(Number(v).toFixed(1))" />
          <VisTooltip :horizontal-shift="20" />
          <VisCrosshair :template="tooltipTemplate" :color="BRAND_HEX.navy" />
        </VisXYContainer>
      </div>

      <!-- AI Executive Summary Narrative -->
      <div v-if="props.insight.aiNarrative" class="bg-brand-blue/8 border border-brand-blue/20 rounded-xl p-4 flex items-start gap-3">
        <div class="p-1.5 bg-brand-blue text-white rounded-lg shrink-0 mt-0.5">
          <Sparkles class="w-4 h-4" />
        </div>
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-brand-blue mb-1">AI Predictive Analysis & Recommendation</p>
          <p class="text-sm text-black/80 leading-relaxed font-normal">
            {{ props.insight.aiNarrative }}
          </p>
        </div>
      </div>

    </div>

  </div>
</template>
