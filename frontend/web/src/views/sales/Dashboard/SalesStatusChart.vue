<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisSingleContainer, VisDonut } from '@unovis/vue'
import type { ContractRequest } from '@/types/contractRequest'

const props = defineProps<{
  requests: ContractRequest[]
}>()

type StatusItem = { label: string; value: number; color: string }

const data = computed<StatusItem[]>(() => {
  const counts = {
    Approved: 0,
    Pending: 0,
    Rejected: 0,
  }

  props.requests.forEach(r => {
    if (r.status === 'Approved') {
      counts.Approved++
    } else if (r.status === 'Rejected') {
      counts.Rejected++
    } else {
      counts.Pending++
    }
  })

  return [
    { label: 'Approved',          value: counts.Approved, color: BRAND_HEX.navy },
    { label: 'Pending/Review',    value: counts.Pending,  color: BRAND_HEX.blue },
    { label: 'Rejected',          value: counts.Rejected, color: BRAND_HEX.dark },
  ]
})

const total = computed(() => data.value.reduce((s, d) => s + d.value, 0))
const value = (d: StatusItem) => d.value
const color = (d: StatusItem) => d.color
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full">
    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h3 class="text-sm font-semibold text-black">Request Status Overview</h3>
      <p class="text-xs text-black/40 mt-0.5">Distribution of your request outcomes</p>
    </div>
    <div class="px-6 py-6 flex flex-col items-center gap-5">

      <!-- Donut with total overlaid in the hole -->
      <div class="relative w-45 h-45 shrink-0">
        <VisSingleContainer :data="data" :height="180" :width="180">
          <VisDonut :value="value" :color="color" :arc-width="38" />
        </VisSingleContainer>
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <p class="text-2xl font-bold text-black tabular-nums leading-none">{{ total }}</p>
          <p class="text-[11px] text-black/35 mt-0.5">requests</p>
        </div>
      </div>

      <!-- Legend -->
      <div class="w-full space-y-2.5">
        <div v-for="item in data" :key="item.label" class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }" />
            <span class="text-xs text-black/60">{{ item.label }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-black">{{ item.value }}</span>
            <span class="text-[10px] text-black/30 w-6 text-right">
              {{ total > 0 ? Math.round(item.value / total * 100) : 0 }}%
            </span>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
