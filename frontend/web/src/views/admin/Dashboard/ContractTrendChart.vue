<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisXYContainer, VisLine, VisAxis, VisTooltip, VisCrosshair } from '@unovis/vue'
import type { Contract } from '@/types/contract'
import type { TimeFilterOption } from '@/composables/useTimeFilter'
import { TIME_FILTER_LABELS } from '@/composables/useTimeFilter'

// Pure display component: the Today/This Week/Last Month filter and the
// Year override both live in the parent Overview tab (single filter row,
// see TimeFilterBar + YearFilterSelect) so there's only one set of filter
// controls on screen. This chart just renders whichever mode is active.
const props = defineProps<{
  contracts: Contract[]
  timeFilter: TimeFilterOption
  year: number | null
}>()

// Buckets to plot along the X axis, depending on mode:
// - Year override: 12 calendar months of that year.
// - 'today': hourly buckets for the current day.
// - 'week': the 7 days of the current Monday-start week.
// - 'month': daily buckets across the prior completed calendar month.
type Bucket = { label: string; key: string; count: number }

const today = new Date()

const buckets = computed<Bucket[]>(() => {
  if (props.year !== null) {
    const yr = props.year
    return Array.from({ length: 12 }, (_, m) => {
      const d = new Date(yr, m, 1)
      return {
        label: d.toLocaleString('en-US', { month: 'short' }),
        key: `${yr}-${String(m + 1).padStart(2, '0')}`,
        count: 0,
      }
    })
  }

  if (props.timeFilter === 'today') {
    return Array.from({ length: 24 }, (_, h) => ({
      label: h === 0 ? '12am' : h < 12 ? `${h}am` : h === 12 ? '12pm' : `${h - 12}pm`,
      key: `${today.toISOString().split('T')[0]}-${String(h).padStart(2, '0')}`,
      count: 0,
    }))
  }

  if (props.timeFilter === 'week') {
    const day = today.getDay()
    const diffToMonday = day === 0 ? 6 : day - 1
    const monday = new Date(today)
    monday.setDate(monday.getDate() - diffToMonday)
    return Array.from({ length: 7 }, (_, i) => {
      const d = new Date(monday)
      d.setDate(monday.getDate() + i)
      return {
        label: d.toLocaleString('en-US', { weekday: 'short' }),
        key: d.toISOString().split('T')[0],
        count: 0,
      }
    })
  }

  // 'month': daily buckets across the most recently completed calendar month
  const firstOfThisMonth = new Date(today.getFullYear(), today.getMonth(), 1)
  const firstOfLastMonth = new Date(firstOfThisMonth.getFullYear(), firstOfThisMonth.getMonth() - 1, 1)
  const daysInLastMonth = new Date(firstOfLastMonth.getFullYear(), firstOfLastMonth.getMonth() + 1, 0).getDate()
  return Array.from({ length: daysInLastMonth }, (_, i) => {
    const d = new Date(firstOfLastMonth.getFullYear(), firstOfLastMonth.getMonth(), i + 1)
    return {
      label: String(d.getDate()),
      key: d.toISOString().split('T')[0],
      count: 0,
    }
  })
})

function bucketKeyFor(date: Date): string {
  if (props.year !== null) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
  }
  if (props.timeFilter === 'today') {
    return `${date.toISOString().split('T')[0]}-${String(date.getHours()).padStart(2, '0')}`
  }
  return date.toISOString().split('T')[0]
}

const trendData = computed(() => {
  const filled = buckets.value.map(b => ({ ...b }))
  props.contracts.forEach(c => {
    if (!c.startDate) return
    const d = new Date(c.startDate)
    if (isNaN(d.getTime())) return
    const key = bucketKeyFor(d)
    const found = filled.find(b => b.key === key)
    if (found) found.count++
  })
  return filled
})

const chartSubtitle = computed(() => {
  if (props.year !== null) return `Monthly trend for year ${props.year}`
  return `Trend for: ${TIME_FILTER_LABELS[props.timeFilter]}`
})

const yTickValues = computed(() => {
  const maxVal = Math.max(...trendData.value.map(d => d.count), 0)
  if (maxVal === 0) return [0]
  if (maxVal <= 5) {
    return Array.from({ length: maxVal + 1 }, (_, i) => i)
  }
  const step = Math.ceil(maxVal / 5)
  const ticks = []
  for (let val = 0; val <= maxVal; val += step) {
    ticks.push(val)
  }
  if (ticks[ticks.length - 1] < maxVal) {
    ticks.push(maxVal)
  }
  return ticks
})

const x = (_: any, i: number) => i
const y = (d: any) => d.count
const xTickFormat = (i: number) => trendData.value[Math.round(i)]?.label ?? ''
const tooltipTemplate = (d: any) =>
  `<div class="bg-white border border-black/10 rounded-lg shadow-lg px-3 py-2 text-xs">
    <p class="font-semibold text-black">${d.label}</p>
    <p class="text-black/50 mt-0.5">${d.count} contracts</p>
  </div>`
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full">
    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h3 class="text-sm font-semibold text-black">Contracts Trend</h3>
        <p class="text-xs text-black/40 mt-0.5">{{ chartSubtitle }}</p>
      </div>
      <div class="flex items-center gap-1.5">
        <div class="w-2.5 h-2.5 rounded-sm bg-brand-blue"></div>
        <span class="text-xs text-black/40">Contracts</span>
      </div>
    </div>
    <div class="px-6 py-5 overflow-x-auto">
      <VisXYContainer
        :data="trendData"
        :height="220"
        :style="{
          '--vis-axis-tick-label-color': 'rgba(0,0,0,0.38)',
          '--vis-axis-domain-color': 'rgba(0,0,0,0.08)',
          '--vis-axis-tick-line-color': 'transparent',
          '--vis-axis-grid-color': 'rgba(0,0,0,0.04)',
          '--vis-font-family': 'inherit',
        }"
      >
        <VisLine :x="x" :y="y" :color="BRAND_HEX.blue" />
        <VisAxis type="x" :tick-format="xTickFormat" :tickValues="trendData.map((_, i) => i)" />
        <VisAxis type="y" :tickValues="yTickValues" :tickFormat="(v: number) => String(Math.round(v))" />
        <VisTooltip :horizontal-shift="20" />
        <VisCrosshair :template="tooltipTemplate" :color="BRAND_HEX.blue" />
      </VisXYContainer>
    </div>
  </div>
</template>
