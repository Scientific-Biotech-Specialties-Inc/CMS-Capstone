<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisXYContainer, VisStackedBar, VisAxis } from '@unovis/vue'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'

const props = defineProps<{
  contracts: (Contract & { days: number })[]
}>()

type PipelineItem = { horizon: string; count: number; color: string }

const data = computed<PipelineItem[]>(() => {
  const expired = props.contracts.filter(c => contractLifecycle(c) === 'expired').length
  const urgent = props.contracts.filter(c => contractLifecycle(c) === 'expiring').length
  const soon = props.contracts.filter(c => contractLifecycle(c) === 'active' && c.days <= 90).length
  const safe = props.contracts.filter(c => contractLifecycle(c) === 'active' && c.days > 90).length

  return [
    { horizon: 'Expired',       count: expired, color: BRAND_HEX.dark },
    { horizon: '0 - 30 Days',   count: urgent,  color: BRAND_HEX.navy },
    { horizon: '31 - 90 Days',  count: soon,    color: BRAND_HEX.blue },
    { horizon: '90+ Days',      count: safe,    color: '#e2e8f0' }
  ]
})

const x = (_: PipelineItem, i: number) => i
const y = (d: PipelineItem) => d.count
const colors = (d: PipelineItem) => d.color

const xTickFormat = (i: number) => data.value[Math.round(i)]?.horizon ?? ''

const yTickValues = computed(() => {
  const maxVal = Math.max(...data.value.map(d => d.count), 0)
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
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full">
    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h3 class="text-sm font-semibold text-black">Contract Renewal Pipeline</h3>
      <p class="text-xs text-black/40 mt-0.5">Contracts grouped by remaining validity duration</p>
    </div>
    <div class="px-6 py-5 overflow-x-auto">
      <VisXYContainer
        :data="data"
        :height="200"
        :style="{
          '--vis-axis-tick-label-color': 'rgba(0,0,0,0.38)',
          '--vis-axis-domain-color': 'rgba(0,0,0,0.08)',
          '--vis-axis-tick-line-color': 'transparent',
          '--vis-axis-grid-color': 'rgba(0,0,0,0.04)',
          '--vis-font-family': 'inherit',
        }"
      >
        <VisStackedBar :x="x" :y="y" :color="colors" :bar-padding="0.3" :rounded-corners="4" />
        <VisAxis type="x" :tick-format="xTickFormat" :tickValues="data.map((_, i) => i)" />
        <VisAxis type="y" :tickValues="yTickValues" :tickFormat="(v: number) => String(Math.round(v))" />
      </VisXYContainer>
    </div>
  </div>
</template>
