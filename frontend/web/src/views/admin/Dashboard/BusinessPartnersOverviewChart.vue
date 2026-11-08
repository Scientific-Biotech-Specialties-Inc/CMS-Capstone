<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisSingleContainer, VisDonut } from '@unovis/vue'
import { dateRangeFor, isWithinRange } from '@/composables/useTimeFilter'
import type { TimeFilterOption } from '@/composables/useTimeFilter'

interface PartnerItem {
  id: string
  name: string
  region: string
  status: string
  type: 'Partner' | 'Supplier'
  createdAt: string | null
}

const props = defineProps<{
  partners: PartnerItem[]
  /** The dashboard's active Today/This Week/Last Month filter, so this
   *  chart visibly reacts to the same control everything else on the tab
   *  responds to (highlights the matching "added" card below). */
  timeFilter?: TimeFilterOption
}>()

// ── Status breakdown (pie/donut) ─────────────────────────────────────
type StatusItem = { label: string; value: number; color: string }

const statusData = computed<StatusItem[]>(() => {
  const active    = props.partners.filter(p => p.status === 'Active').length
  const inactive   = props.partners.filter(p => p.status === 'Inactive').length
  const suspended = props.partners.filter(p => p.status === 'Suspended').length

  return [
    { label: 'Active',    value: active,    color: BRAND_HEX.blue },
    { label: 'Inactive',  value: inactive,  color: BRAND_HEX.navy },
    { label: 'Suspended', value: suspended, color: BRAND_HEX.dark },
  ]
})

const total = computed(() => props.partners.length)
const partnersCount = computed(() => props.partners.filter(p => p.type === 'Partner').length)
const suppliersCount = computed(() => props.partners.filter(p => p.type === 'Supplier').length)

const value = (d: StatusItem) => d.value
const color = (d: StatusItem) => d.color

// ── "Added" counts, per time window ───────────────────────────────────
// Requires the API to return a created_at timestamp for each partner/
// supplier record. If the backend doesn't provide it yet, these show
// "—" rather than a misleading 0.
const hasCreatedAtData = computed(() => props.partners.some(p => !!p.createdAt))

function countAddedIn(range: { from: Date; to: Date }): number {
  return props.partners.filter(p => isWithinRange(p.createdAt, range)).length
}

const addedToday = computed(() => countAddedIn(dateRangeFor('today')))
const addedThisWeek = computed(() => countAddedIn(dateRangeFor('week')))
const addedLastMonth = computed(() => countAddedIn(dateRangeFor('month')))
const addedThisYear = computed(() => {
  const now = new Date()
  const from = new Date(now.getFullYear(), 0, 1)
  const to = new Date(now.getFullYear(), 11, 31, 23, 59, 59, 999)
  return countAddedIn({ from, to })
})

const addedCards = computed(() => [
  { key: 'today' as const,  label: 'Today',      value: addedToday.value },
  { key: 'week' as const,   label: 'This Week',  value: addedThisWeek.value },
  { key: 'month' as const,  label: 'Last Month', value: addedLastMonth.value },
  { key: 'year' as const,   label: 'This Year',  value: addedThisYear.value },
])
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h3 class="text-sm font-semibold text-black">Business Partners &amp; Suppliers</h3>
      <p class="text-xs text-black/40 mt-0.5">Status breakdown and new additions over time</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 p-6">

      <!-- Status pie chart -->
      <div class="xl:col-span-2 flex flex-col items-center gap-5">
        <div class="relative w-40 h-40 shrink-0">
          <VisSingleContainer :data="statusData" :height="160" :width="160">
            <VisDonut :value="value" :color="color" :arc-width="0" />
          </VisSingleContainer>
          <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <p class="text-2xl font-bold text-black tabular-nums leading-none">{{ total }}</p>
            <p class="text-[11px] text-black/35 mt-0.5">total</p>
          </div>
        </div>

        <div class="w-full space-y-2">
          <div v-for="item in statusData" :key="item.label" class="flex items-center justify-between">
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

        <div class="w-full flex items-center justify-between pt-3 border-t border-black/[0.04] text-xs">
          <span class="text-black/40">Partners</span>
          <span class="font-semibold text-black">{{ partnersCount }}</span>
        </div>
        <div class="w-full flex items-center justify-between text-xs -mt-3">
          <span class="text-black/40">Suppliers</span>
          <span class="font-semibold text-black">{{ suppliersCount }}</span>
        </div>
      </div>

      <!-- Newly added over time -->
      <div class="xl:col-span-3 flex flex-col">
        <p class="text-xs font-semibold text-black/40 uppercase tracking-wide mb-3">New Business Partners Added</p>
        <div v-if="!hasCreatedAtData" class="flex-1 flex items-center justify-center text-center text-xs text-black/30 px-4">
          Date-added data isn't available from the system yet, so new-addition counts can't be shown.
        </div>
        <div v-else class="grid grid-cols-2 gap-4">
          <div v-for="card in addedCards" :key="card.label"
            class="rounded-lg border px-4 py-4 transition-colors"
            :class="props.timeFilter === card.key
              ? 'bg-brand-navy/6 border-brand-navy/25'
              : 'bg-black/[0.02] border-black/6'">
            <p class="text-xs font-medium mb-2" :class="props.timeFilter === card.key ? 'text-brand-navy/70' : 'text-black/40'">{{ card.label }}</p>
            <p class="text-2xl font-bold tabular-nums" :class="props.timeFilter === card.key ? 'text-brand-navy' : 'text-black'">{{ card.value }}</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
