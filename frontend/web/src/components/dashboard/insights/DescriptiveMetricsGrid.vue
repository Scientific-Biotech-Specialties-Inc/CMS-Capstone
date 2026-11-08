<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { FileText, Users, ShieldAlert, Brain, Bell } from 'lucide-vue-next'
import type { AnalyticsSummary } from '@/types/analytics'
import { metricLabels } from '@/types/analytics'

const props = defineProps<{
  summary: AnalyticsSummary | null
  service?: string
  hideHeader?: boolean
}>()

const percentMetrics = new Set(['email_success_rate_pct'])
const decimalMetrics = new Set(['contracts_avg_approval_hours', 'avg_risk_score'])

function formatValue(metricType: string, value: number | null): string {
  if (value === null || value === undefined) return '—'
  if (percentMetrics.has(metricType)) return `${Number(value).toFixed(1)}%`
  if (decimalMetrics.has(metricType)) return Number(value).toFixed(2)
  return Math.round(value).toString()
}

// Icon + color per service
const serviceConfig: Record<string, { icon: any; color: string; bg: string; bar: string }> = {
  'contract-management': { icon: FileText,   color: 'text-brand-navy', bg: 'bg-brand-navy/8',  bar: BRAND_HEX.navy },
  'vendor-management':   { icon: Users,      color: 'text-brand-blue', bg: 'bg-brand-blue/8',  bar: BRAND_HEX.blue },
  'ai-service':          { icon: Brain,      color: 'text-brand-dark', bg: 'bg-brand-dark/8',  bar: BRAND_HEX.dark },
  'notification':        { icon: Bell,       color: 'text-[#5B7FD1]', bg: 'bg-[#5B7FD1]/8',  bar: '#5B7FD1' },
}

const serviceOrder = ['contract-management', 'vendor-management', 'ai-service', 'notification']

const groups = computed(() => {
  if (!props.summary) return []
  const filterList = props.service ? [props.service] : serviceOrder

  return filterList
    .filter(s => props.summary!.metrics[s]?.length)
    .map(service => {
      const cfg = serviceConfig[service] ?? { icon: ShieldAlert, color: 'text-black/50', bg: 'bg-black/5', bar: '#888' }
      const metrics = props.summary!.metrics[service].filter(m => m.metricValue !== null)
      // Compute max for relative bar width
      const maxVal = Math.max(...metrics.map(m => Math.abs(m.metricValue ?? 0)), 1)
      return {
        service,
        cfg,
        metrics: metrics.map(m => ({
          ...m,
          barPct: Math.round((Math.abs(m.metricValue ?? 0) / maxVal) * 100),
        })),
      }
    })
})
</script>

<template>
  <div class="space-y-6">
    <div v-if="groups.length === 0" class="bg-white rounded-lg border border-black/8 shadow-sm p-10 text-center">
      <Brain class="w-8 h-8 mx-auto text-black/20 mb-3" />
      <p class="text-sm text-black/40">No metrics available yet. Try refreshing analytics.</p>
    </div>

    <div v-for="group in groups" :key="group.service" class="space-y-3">
      <!-- Section header (optional) -->
      <div v-if="!props.hideHeader" class="flex items-center gap-2">
        <div class="p-1.5 rounded-lg" :class="group.cfg.bg">
          <component :is="group.cfg.icon" class="w-4 h-4" :class="group.cfg.color" />
        </div>
        <h3 class="text-xs font-semibold uppercase tracking-widest" :class="group.cfg.color">
          {{ { 'contract-management': 'Contracts', 'vendor-management': 'Vendors & Partners', 'ai-service': 'AI Services', 'notification': 'Notifications' }[group.service] ?? group.service }}
        </h3>
      </div>

      <!-- KPI card grid -->
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div
          v-for="m in group.metrics"
          :key="m.metricType"
          class="bg-white rounded-xl border border-black/8 px-5 py-4 shadow-sm flex flex-col gap-3"
        >
          <p class="text-xs font-medium text-black/40 truncate leading-tight" :title="metricLabels[m.metricType] ?? m.metricType">
            {{ metricLabels[m.metricType] ?? m.metricType }}
          </p>
          <span class="text-2xl font-bold tabular-nums" :class="group.cfg.color">
            {{ formatValue(m.metricType, m.metricValue) }}
          </span>
          <!-- Relative bar -->
          <div class="w-full h-1.5 rounded-full bg-black/5 overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-700"
              :style="{ width: m.barPct + '%', backgroundColor: group.cfg.bar }"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
