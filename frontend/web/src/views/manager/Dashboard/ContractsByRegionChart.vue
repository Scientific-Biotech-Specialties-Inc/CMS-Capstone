<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisXYContainer, VisStackedBar, VisAxis, VisTooltip, VisCrosshair } from '@unovis/vue'
import type { Contract } from '@/types/contract'

const props = defineProps<{
  contracts: Contract[]
}>()

type RegionItem = { region: string; count: number; color: string }

const data = computed<RegionItem[]>(() => {
  const luzon = props.contracts.filter(c => c.region === 'Luzon').length
  const visayas = props.contracts.filter(c => c.region === 'Visayas').length
  const mindanao = props.contracts.filter(c => c.region === 'Mindanao').length

  return [
    { region: 'Luzon',    count: luzon,    color: BRAND_HEX.navy },
    { region: 'Visayas',  count: visayas,  color: BRAND_HEX.blue },
    { region: 'Mindanao', count: mindanao, color: BRAND_HEX.dark }
  ]
})

const x = (_: RegionItem, i: number) => i
const y = (d: RegionItem) => d.count
const colors = (d: RegionItem) => d.color

const xTickFormat = (i: number) => data.value[Math.round(i)]?.region ?? ''

// Integer tick values for Y-axis (no decimals)
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

const tooltipTemplate = (d: RegionItem) =>
  `<div class="bg-white border border-black/10 rounded-lg shadow-lg px-3 py-2 text-xs">
    <p class="font-semibold text-black">${d.region}</p>
    <p class="text-black/50 mt-0.5">${d.count} contracts</p>
  </div>`
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h3 class="text-sm font-semibold text-black">Contracts by Region</h3>
        <p class="text-xs text-black/40 mt-0.5">Distribution across Luzon, Visayas, and Mindanao</p>
      </div>
      <div class="flex items-center gap-3">
        <!-- Legend -->
        <div class="flex items-center gap-3">
          <div v-for="l in data" :key="l.region" class="flex items-center gap-1.5">
            <div class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ backgroundColor: l.color }" />
            <span class="text-xs text-black/40">{{ l.region }}</span>
          </div>
        </div>
      </div>
    </div>
    <div class="px-6 py-5 overflow-x-auto">
      <VisXYContainer
        :data="data"
        :height="220"
        :style="{
          '--vis-axis-tick-label-color': 'rgba(0,0,0,0.38)',
          '--vis-axis-domain-color': 'rgba(0,0,0,0.08)',
          '--vis-axis-tick-line-color': 'transparent',
          '--vis-axis-grid-color': 'rgba(0,0,0,0.04)',
          '--vis-font-family': 'inherit',
        }"
      >
        <VisStackedBar :x="x" :y="y" :color="colors" :bar-padding="0.4" :rounded-corners="4" />
        <VisAxis type="x" :tick-format="xTickFormat" :tickValues="data.map((_, i) => i)" />
        <VisAxis type="y" :tickValues="yTickValues" :tickFormat="(v: number) => String(Math.round(v))" />
        <VisTooltip :horizontal-shift="20" />
        <VisCrosshair :template="tooltipTemplate" :color="BRAND_HEX.blue" />
      </VisXYContainer>
    </div>
  </div>
</template>
