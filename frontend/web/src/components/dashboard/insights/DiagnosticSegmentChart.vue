<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisXYContainer, VisStackedBar, VisAxis, VisTooltip, VisCrosshair } from '@unovis/vue'
import type { DiagnosticInsight } from '@/types/analytics'

const props = defineProps<{
  insight: DiagnosticInsight
}>()

// The segment breakdown is expected to be an array of: { category_name: string, region_name: string, count: number }
const segmentData = computed(() => {
  const breakdown = props.insight.findingSummary?.segment_breakdown ?? []
  if (!Array.isArray(breakdown) || breakdown.length === 0) return null

  // Map and sort by count descending, taking top 5
  return breakdown
    .map((item: any) => ({
      label: `${item.category_name ?? 'Category'} (${item.region_name ?? 'Region'})`,
      count: Number(item.count || 0)
    }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

const x = (_: any, i: number) => i
const y = (d: any) => d.count
const color = () => BRAND_HEX.navy // Brand Navy

const xTickFormat = (i: number) => {
  const label = segmentData.value?.[Math.round(i)]?.label ?? ''
  return label.length > 22 ? label.substring(0, 20) + '…' : label
}

const yTickValues = computed(() => {
  if (!segmentData.value) return [0]
  const maxVal = Math.max(...segmentData.value.map(d => d.count), 0)
  if (maxVal === 0) return [0]
  if (maxVal <= 5) return Array.from({ length: maxVal + 1 }, (_, i) => i)
  
  const step = Math.ceil(maxVal / 5)
  const ticks = []
  for (let val = 0; val <= maxVal; val += step) ticks.push(val)
  if (ticks[ticks.length - 1] < maxVal) ticks.push(maxVal)
  return ticks
})

const tooltipTemplate = (d: any) =>
  `<div class="bg-[#1e1e2e] text-white rounded-lg shadow-xl px-3 py-2 text-xs font-medium">
    <p class="font-bold">${d.label}</p>
    <p class="text-white/60 mt-0.5">${d.count} contracts</p>
  </div>`
</script>

<template>
  <div v-if="segmentData && segmentData.length > 0" class="border-t border-black/6 pt-5">
    <div class="flex items-center justify-between mb-3">
      <p class="text-xs font-bold uppercase tracking-wider text-black/40">Top Driving Segments</p>
      <span class="text-xs text-black/40">Category + Region breakdown</span>
    </div>
    <div class="overflow-x-auto bg-slate-50/50 rounded-xl p-3 border border-black/5">
      <VisXYContainer
        :data="segmentData"
        :height="160"
        :style="{
          '--vis-axis-tick-label-color': 'rgba(0,0,0,0.5)',
          '--vis-axis-domain-color': 'transparent',
          '--vis-axis-tick-line-color': 'transparent',
          '--vis-axis-grid-color': 'rgba(0,0,0,0.05)',
          '--vis-font-family': 'Poppins, sans-serif',
        }"
      >
        <VisStackedBar :x="x" :y="y" :color="color" :bar-padding="0.4" :rounded-corners="4" />
        <VisAxis type="x" :tick-format="xTickFormat" :tickValues="segmentData.map((_, i) => i)" />
        <VisAxis type="y" :tickValues="yTickValues" :tickFormat="(v: number) => String(Math.round(v))" />
        <VisTooltip :horizontal-shift="20" />
        <VisCrosshair :template="tooltipTemplate" :color="BRAND_HEX.navy" />
      </VisXYContainer>
    </div>
  </div>
</template>
