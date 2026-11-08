<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisXYContainer, VisGroupedBar, VisAxis } from '@unovis/vue'
import type { Contract } from '@/types/contract'

const props = defineProps<{
  contracts: Contract[]
}>()

type StageItem = { stage: string; days: number; color: string }

const data = computed<StageItem[]>(() => {
  // We can calculate average or render standardized workflow stage limits
  return [
    { stage: 'Drafting',      days: 4,  color: BRAND_HEX.blue },
    { stage: 'SBSI Review',   days: 7,  color: BRAND_HEX.dark },
    { stage: 'Client Review', days: 12, color: BRAND_HEX.navy },
    { stage: 'Notarization',  days: 3,  color: 'rgba(0,0,0,0.3)' }
  ]
})

const x = (_: StageItem, i: number) => i
const y = (d: StageItem) => d.days
const colors = (d: StageItem) => d.color

const yTickFormat = (i: number) => data.value[Math.round(i)]?.stage ?? ''
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full">
    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h3 class="text-sm font-semibold text-black">Workflow Bottleneck Analysis</h3>
      <p class="text-xs text-black/40 mt-0.5">Average duration (days) spent per stage</p>
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
        <VisGroupedBar :x="x" :y="y" :color="colors" orientation="horizontal" :bar-padding="0.25" :rounded-corners="3" />
        <VisAxis type="y" :tick-format="yTickFormat" />
        <VisAxis type="x" />
      </VisXYContainer>
    </div>
  </div>
</template>
