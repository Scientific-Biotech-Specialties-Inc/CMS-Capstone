<script setup lang="ts">
import { computed } from 'vue'
import { Sparkles, TrendingUp, TrendingDown, Minus, AlertCircle, ShieldAlert, Clock } from 'lucide-vue-next'
import type { DiagnosticInsight } from '@/types/analytics'
import { diagnosticLabels } from '@/types/analytics'
import DiagnosticSegmentChart from './DiagnosticSegmentChart.vue'

const props = defineProps<{
  insights: DiagnosticInsight[]
}>()

function trendMeta(metricType: string, trend: string) {
  const isSla = metricType === 'approval_sla_bottleneck'
  switch (trend) {
    case 'worsening':
    case 'slower':
      return {
        icon: TrendingUp,
        bgColor: 'bg-red-50',
        textColor: 'text-red-700',
        borderColor: 'border-red-200',
        badgeBg: 'bg-red-100 text-red-800',
        label: isSla ? 'SLA Slowing Down' : 'Needs Attention',
        desc: isSla ? 'Contract approval workflow is taking longer than the prior 30-day period.' : 'Contract risk scores are worsening compared to the prior period.',
      }
    case 'improving':
    case 'faster':
      return {
        icon: TrendingDown,
        bgColor: 'bg-emerald-50',
        textColor: 'text-emerald-700',
        borderColor: 'border-emerald-200',
        badgeBg: 'bg-emerald-100 text-emerald-800',
        label: isSla ? 'SLA Speed Improving' : 'On Track',
        desc: isSla ? 'Contract approvals are being processed faster than the prior period.' : 'Contract risk levels are improving compared to the prior period.',
      }
    case 'stable':
      return {
        icon: Minus,
        bgColor: 'bg-slate-50',
        textColor: 'text-slate-700',
        borderColor: 'border-slate-200',
        badgeBg: 'bg-slate-100 text-slate-800',
        label: 'Stable',
        desc: 'Metrics have remained consistent with no significant changes since last period.',
      }
    default:
      return {
        icon: AlertCircle,
        bgColor: 'bg-slate-50',
        textColor: 'text-slate-600',
        borderColor: 'border-slate-200',
        badgeBg: 'bg-slate-100 text-slate-700',
        label: 'Baseline Data',
        desc: 'Collecting period comparison metrics.',
      }
  }
}

function formatNum(v: number | null | undefined, metricType: string): string {
  if (v === null || v === undefined) return '—'
  if (metricType === 'approval_sla_bottleneck') return `${Number(v).toFixed(1)} hrs`
  return `${Number(v).toFixed(2)}`
}

function deltaText(insight: DiagnosticInsight): { text: string; positive: boolean | null } {
  const s = insight.findingSummary ?? {}
  const delta = s.delta ?? s.delta_hours ?? null
  if (delta === null || delta === undefined) return { text: 'No change data', positive: null }

  const num = Number(delta)
  const sign = num > 0 ? '+' : ''
  const isSla = insight.metricType === 'approval_sla_bottleneck'
  const unit = isSla ? 'hrs' : 'pts'
  
  // For SLA or risk score, positive delta means higher/worse
  const isPositive = isSla ? num < 0 : num < 0

  return {
    text: `${sign}${num.toFixed(2)} ${unit} vs. prior period`,
    positive: isPositive,
  }
}

const cards = computed(() => props.insights.map(insight => {
  const s = insight.findingSummary ?? {}
  const trend = trendMeta(insight.metricType, s.trend ?? 'unknown')
  const current = s.current_period_avg ?? s.current_period_avg_hours ?? null
  const prior = s.prior_period_avg ?? s.prior_period_avg_hours ?? null
  const delta = deltaText(insight)

  return {
    insight,
    trend,
    current,
    prior,
    delta,
  }
}))
</script>

<template>
  <div class="space-y-6">
    <div v-if="cards.length === 0" class="bg-white rounded-xl border border-black/8 shadow-sm p-12 text-center">
      <Sparkles class="w-8 h-8 mx-auto text-black/20 mb-3" />
      <p class="text-sm font-semibold text-black/60">No Diagnostic Insights Available</p>
      <p class="text-xs text-black/40 mt-1">Click "Refresh Analytics" above to run the latest diagnostic pipeline pass.</p>
    </div>

    <!-- Health Report Cards -->
    <div v-for="card in cards" :key="card.insight.metricType" class="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden">
      
      <!-- Card Header -->
      <div class="px-6 py-4 border-b border-black/6 flex items-center justify-between gap-4 flex-wrap bg-slate-50/50">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-white border border-black/8 shadow-xs">
            <component :is="card.insight.metricType === 'approval_sla_bottleneck' ? Clock : ShieldAlert" class="w-5 h-5 text-brand-navy" />
          </div>
          <div>
            <h3 class="text-base font-bold text-black">
              {{ diagnosticLabels[card.insight.metricType] ?? card.insight.metricType }}
            </h3>
            <p class="text-xs text-black/40">
              Analysis window: {{ card.insight.periodStart }} to {{ card.insight.periodEnd }}
            </p>
          </div>
        </div>

        <!-- Status Badge -->
        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full" :class="card.trend.badgeBg">
          <component :is="card.trend.icon" class="w-3.5 h-3.5" />
          {{ card.trend.label }}
        </span>
      </div>

      <div class="p-6 space-y-6">

        <!-- 1. What This Means (Plain English) -->
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-black/40 mb-1">Overview</p>
          <p class="text-sm text-black/75 leading-relaxed font-medium">
            {{ card.trend.desc }}
          </p>
        </div>

        <!-- 2. AI Summary (Promoted to Top) -->
        <div v-if="card.insight.aiNarrative" class="bg-brand-navy/5 border border-brand-navy/15 rounded-xl p-4 flex items-start gap-3">
          <div class="p-1.5 bg-brand-navy text-white rounded-lg shrink-0 mt-0.5">
            <Sparkles class="w-4 h-4" />
          </div>
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-brand-navy mb-1">AI Executive Summary</p>
            <p class="text-sm text-black/80 leading-relaxed font-normal">
              {{ card.insight.aiNarrative }}
            </p>
          </div>
        </div>

        <!-- 3. Key Numbers (3-Column Clean Row) -->
        <div class="bg-black/2 rounded-xl border border-black/6 p-4">
          <p class="text-xs font-bold uppercase tracking-wider text-black/40 mb-3">Key Period Metrics</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            
            <div class="bg-white rounded-lg border border-black/8 p-3 shadow-xs">
              <span class="text-xs text-black/45 font-medium">Current Period</span>
              <p class="text-xl font-extrabold text-black mt-1">
                {{ formatNum(card.current, card.insight.metricType) }}
              </p>
            </div>

            <div class="bg-white rounded-lg border border-black/8 p-3 shadow-xs">
              <span class="text-xs text-black/45 font-medium">Prior Period</span>
              <p class="text-xl font-extrabold text-black/60 mt-1">
                {{ formatNum(card.prior, card.insight.metricType) }}
              </p>
            </div>

            <div class="bg-white rounded-lg border border-black/8 p-3 shadow-xs">
              <span class="text-xs text-black/45 font-medium">Net Variance</span>
              <p
                class="text-sm font-bold mt-1.5 flex items-center gap-1"
                :class="card.delta.positive === true ? 'text-emerald-600' : card.delta.positive === false ? 'text-red-600' : 'text-black/60'"
              >
                {{ card.delta.text }}
              </p>
            </div>

          </div>
        </div>

        <!-- 4. Segment Breakdown Chart -->
        <div>
          <DiagnosticSegmentChart :insight="card.insight" />
        </div>

      </div>

    </div>
  </div>
</template>
