<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed, ref } from 'vue'
import { VisSingleContainer, VisDonut, VisTooltip } from '@unovis/vue'
import { Donut } from '@unovis/ts'
import type { AnalyticsSummary } from '@/types/analytics'

const props = defineProps<{
  summary: AnalyticsSummary | null
  service?: string
}>()

// ── Types ─────────────────────────────────────────────────────────────────────
type SliceItem = { label: string; value: number; color: string }
type BarItem   = { label: string; value: number; color: string; barColor: string }

const PALETTE = [BRAND_HEX.navy, BRAND_HEX.blue, BRAND_HEX.dark, '#5B7FD1', '#8FA8E0', '#A3BFFA']

// ── Helpers ───────────────────────────────────────────────────────────────────
function toSlices(record: Record<string, number> | null | undefined): SliceItem[] {
  if (!record) return []
  return Object.entries(record)
    .filter(([, v]) => typeof v === 'number' && v > 0)
    .map(([label, value], i) => ({ label, value, color: PALETTE[i % PALETTE.length] }))
}

function findMeta(service: string, metricType: string): Record<string, any> | null {
  return props.summary?.metrics[service]?.find(m => m.metricType === metricType)?.metadata ?? null
}

function getVal(service: string, metricType: string): number {
  const val = props.summary?.metrics[service]?.find(m => m.metricType === metricType)?.metricValue
  return val ? Number(val) : 0
}

// ── Contract bar chart data ────────────────────────────────────────────────────
const contractBars = computed<BarItem[]>(() => [
  { label: 'Total',        value: getVal('contract-management', 'contracts_total'),               color: BRAND_HEX.navy, barColor: BRAND_HEX.navy },
  { label: 'Expiring 30d', value: getVal('contract-management', 'contracts_expiring_soon_30d'),   color: BRAND_HEX.blue, barColor: BRAND_HEX.blue },
  { label: 'Expired',      value: getVal('contract-management', 'contracts_expired'),             color: '#EF4444', barColor: '#EF4444' },
  { label: 'High-Risk',    value: getVal('contract-management', 'high_risk_approvals_pending'),   color: '#F59E0B', barColor: '#F59E0B' },
  { label: 'Escalated',    value: getVal('contract-management', 'high_risk_approvals_escalated'), color: BRAND_HEX.dark, barColor: BRAND_HEX.dark },
].filter(b => b.value > 0))

const showContractBars = computed(() => (!props.service || props.service === 'contract-management') && contractBars.value.length > 0)
const barMax   = computed(() => Math.max(...contractBars.value.map(b => b.value), 1))

// Tooltip state for the bar chart
const hoveredBar = ref<BarItem | null>(null)
const tooltipPos = ref({ x: 0, y: 0 })

function onBarMouseEnter(item: BarItem, event: MouseEvent) {
  hoveredBar.value = item
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  const parent = (event.currentTarget as HTMLElement).closest('.bar-chart-container')!.getBoundingClientRect()
  tooltipPos.value = { x: rect.left - parent.left + rect.width / 2, y: rect.top - parent.top - 8 }
}
function onBarMouseLeave() { hoveredBar.value = null }

// ── Donut chart data ──────────────────────────────────────────────────────────
const contractsByStatus   = computed(() => toSlices(findMeta('contract-management', 'high_risk_approvals_escalated')?.by_status))
const contractsByCategory = computed(() => toSlices(findMeta('contract-management', 'high_risk_approvals_escalated')?.by_category))
const suppliersByRegion   = computed(() => toSlices(findMeta('vendor-management', 'partners_total')?.suppliers_by_region))
const partnersByType      = computed(() => toSlices(findMeta('vendor-management', 'partners_total')?.partners_by_type))
const aiByRiskLevel       = computed(() => toSlices(findMeta('ai-service', 'avg_risk_score')?.by_risk_level))
const notificationsByType = computed(() => toSlices(findMeta('notification', 'notifications_sent_24h')?.by_type))
const notificationsByStatus = computed(() => toSlices(findMeta('notification', 'notifications_sent_24h')?.by_status))

const donuts = computed(() => {
  const allDonuts = [
    { service: 'contract-management', title: 'Contracts by Status',       subtitle: 'Current workflow distribution',        data: contractsByStatus.value },
    { service: 'contract-management', title: 'Contracts by Category',     subtitle: 'Distribution by contract type',        data: contractsByCategory.value },
    { service: 'vendor-management',   title: 'Suppliers by Region',       subtitle: 'Geographic spread of partners',        data: suppliersByRegion.value },
    { service: 'vendor-management',   title: 'Partners by Type',          subtitle: 'Distribution of vendors & suppliers',  data: partnersByType.value },
    { service: 'ai-service',          title: 'Risk Assessments by Level', subtitle: 'AI-scanned contracts by risk band',    data: aiByRiskLevel.value },
    { service: 'notification',        title: 'Notifications by Type',     subtitle: 'Alerts sent in the last 24h',          data: notificationsByType.value },
    { service: 'notification',        title: 'Notifications by Status',   subtitle: 'Delivery status in the last 24h',      data: notificationsByStatus.value },
  ]

  return allDonuts
    .filter(c => (!props.service || c.service === props.service) && c.data.length > 0)
})

// Unovis donut accessors
const donutValue = (d: SliceItem) => d.value
const donutColor = (d: SliceItem) => d.color

function donutTooltip(d: any, total: number) {
  const item = d.data as SliceItem
  const pct = Math.round((item.value / total) * 100)
  return `
    <div style="background:#1e1e2e;color:#fff;border-radius:8px;padding:8px 12px;font-family:Poppins,sans-serif;font-size:12px;line-height:1.6;min-width:120px">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${item.color};flex-shrink:0"></span>
        <span style="font-weight:600">${item.label}</span>
      </div>
      <div style="color:#a0aec0">${item.value} entries · <b style="color:#fff">${pct}%</b></div>
    </div>
  `
}
</script>

<template>
  <div class="space-y-6">

    <!-- ── Contract Overview Bar Chart (Contract Management Section) ──────────────────────────────────── -->
    <div v-if="showContractBars" class="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden">
      <div class="px-6 pt-5 pb-4 border-b border-black/5">
        <h3 class="text-sm font-semibold text-black">Contract Metrics Overview</h3>
        <p class="text-xs text-black/40 mt-0.5">Summary bar breakdown for contract metrics</p>
      </div>
      <div class="px-6 py-6">
        <div class="bar-chart-container relative flex items-end gap-3 h-40">
          <Transition name="fade">
            <div
              v-if="hoveredBar"
              class="pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-full"
              :style="{ left: tooltipPos.x + 'px', top: tooltipPos.y + 'px' }"
            >
              <div class="bg-[#1e1e2e] text-white rounded-xl px-3 py-2 shadow-xl text-xs font-medium leading-relaxed whitespace-nowrap">
                <div class="flex items-center gap-1.5 mb-0.5">
                  <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: hoveredBar.barColor }" />
                  <span class="font-semibold">{{ hoveredBar.label }}</span>
                </div>
                <span class="text-white/60">{{ hoveredBar.value.toLocaleString() }} contracts</span>
              </div>
              <div class="w-2.5 h-2.5 bg-[#1e1e2e] rotate-45 mx-auto -mt-1 rounded-sm" />
            </div>
          </Transition>

          <div
            v-for="item in contractBars"
            :key="item.label"
            class="flex-1 flex flex-col items-center gap-1.5 min-w-0 cursor-pointer group"
            @mouseenter="onBarMouseEnter(item, $event)"
            @mouseleave="onBarMouseLeave"
          >
            <span
              class="text-xs font-bold tabular-nums transition-all duration-150"
              :style="{ color: item.barColor }"
              :class="hoveredBar?.label === item.label ? 'opacity-100 scale-110' : 'opacity-0 group-hover:opacity-100'"
            >
              {{ item.value }}
            </span>
            <div
              class="w-full rounded-t-lg transition-all duration-500 ease-out min-h-[6px]"
              :style="{
                backgroundColor: item.barColor,
                height: Math.max(6, (item.value / barMax) * 120) + 'px',
                opacity: hoveredBar === null || hoveredBar.label === item.label ? 1 : 0.35,
              }"
            />
            <span class="text-[10px] text-black/40 text-center truncate w-full leading-tight">
              {{ item.label }}
            </span>
          </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2">
          <div v-for="item in contractBars" :key="item.label + '-leg'" class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: item.barColor }" />
            <span class="text-xs text-black/50">{{ item.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Donut Charts ──────────────────────────────────────────────────── -->
    <div v-if="donuts.length > 0" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div
        v-for="chart in donuts"
        :key="chart.title"
        class="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden"
      >
        <div class="px-6 pt-5 pb-4 border-b border-black/5">
          <h3 class="text-sm font-semibold text-black">{{ chart.title }}</h3>
          <p class="text-xs text-black/40 mt-0.5">{{ chart.subtitle }} · hover to explore</p>
        </div>
        <div class="px-6 py-6 flex gap-8 items-center">
          <div class="relative shrink-0 w-40 h-40">
            <VisSingleContainer :data="chart.data" :height="160" :width="160">
              <VisDonut
                :value="donutValue"
                :color="donutColor"
                :arc-width="38"
              />
              <VisTooltip
                :triggers="{
                  [Donut.selectors.segment]: (d: SliceItem) => donutTooltip(d, chart.data.reduce((s, x) => s + x.value, 0))
                }"
              />
            </VisSingleContainer>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <p class="text-2xl font-bold text-black tabular-nums leading-none">
                {{ chart.data.reduce((s, d) => s + d.value, 0) }}
              </p>
              <p class="text-[10px] text-black/35 mt-0.5">total</p>
            </div>
          </div>

          <div class="flex-1 space-y-2.5 min-w-0">
            <div v-for="item in chart.data" :key="item.label" class="flex items-center justify-between gap-2 min-w-0">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }" />
                <span class="text-xs text-black/60 truncate">{{ item.label }}</span>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs font-semibold text-black">{{ item.value }}</span>
                <span class="text-[10px] text-black/35 tabular-nums">
                  {{ Math.round((item.value / chart.data.reduce((s, d) => s + d.value, 1)) * 100) }}%
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.12s ease, transform 0.12s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateX(-50%) translateY(calc(-100% - 4px)) scale(0.95); }
.fade-enter-to { transform: translateX(-50%) translateY(-100%) scale(1); }
</style>
