<script setup lang="ts">
import { BRAND_HEX } from '@/constants/theme'
import { computed } from 'vue'
import { VisSingleContainer, VisDonut, VisXYContainer, VisGroupedBar, VisAxis } from '@unovis/vue'

type Role = 'Admin' | 'Manager' | 'Sales'
type Status = 'Active' | 'Inactive'

interface DashUser {
  id: string; name: string; email: string; role: Role; status: Status
}

interface PartnerItem {
  id: string
  name: string
  region: string
  status: string
  type: 'Partner' | 'Supplier'
}

const props = defineProps<{
  users: DashUser[]
  partners: PartnerItem[]
}>()

// ── User Data Processing ───────────────────────────────────────────
type UserStatusItem = { label: string; value: number; color: string }

const userData = computed<UserStatusItem[]>(() => {
  const counts = { Admin: 0, Manager: 0, Sales: 0 }
  props.users.forEach(u => {
    if (u.role in counts) counts[u.role]++
  })
  return [
    { label: 'Admin',   value: counts.Admin,   color: BRAND_HEX.navy },
    { label: 'Manager', value: counts.Manager, color: BRAND_HEX.dark },
    { label: 'Sales',   value: counts.Sales,   color: BRAND_HEX.blue },
  ]
})

const totalUsers = computed(() => userData.value.reduce((s, d) => s + d.value, 0))
const userValue = (d: UserStatusItem) => d.value
const userColor = (d: UserStatusItem) => d.color

// ── Partner Data Processing ────────────────────────────────────────
type RegionalPartnerData = { region: string; partners: number; suppliers: number }

const partnerData = computed<RegionalPartnerData[]>(() => {
  const regions = ['Luzon', 'Visayas', 'Mindanao']
  return regions.map(reg => {
    const list = props.partners.filter(p => p.region === reg)
    const partners = list.filter(p => p.type === 'Partner').length
    const suppliers = list.filter(p => p.type === 'Supplier').length
    return {
      region: reg,
      partners,
      suppliers
    }
  })
})

const yPartner     = [(d: RegionalPartnerData) => d.partners, (d: RegionalPartnerData) => d.suppliers]
const colorsPartner = [BRAND_HEX.navy, BRAND_HEX.blue]

const yTickValues = computed(() => {
  const maxVal = Math.max(...partnerData.value.map(d => Math.max(d.partners, d.suppliers)), 0)
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

const xPartner = (_: RegionalPartnerData, i: number) => i
const xTickFormat = (i: number) => partnerData.value[Math.round(i)]?.region ?? ''
</script>

<template>
  <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

    <!-- User Roles Donut Chart (2/5) -->
    <div class="xl:col-span-2 bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden flex flex-col justify-between">
      <div>
        <div class="px-6 pt-5 pb-4 border-b border-black/5">
          <h3 class="text-sm font-semibold text-black">User Roles</h3>
          <p class="text-xs text-black/40 mt-0.5">Distribution of user accounts</p>
        </div>
        <div class="px-6 py-6 flex flex-col items-center gap-5">
          <!-- Donut -->
          <div class="relative w-45 h-45 shrink-0">
            <VisSingleContainer :data="userData" :height="180" :width="180">
              <VisDonut :value="userValue" :color="userColor" :arc-width="38" />
            </VisSingleContainer>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <p class="text-2xl font-bold text-black tabular-nums leading-none">{{ totalUsers }}</p>
              <p class="text-[11px] text-black/35 mt-0.5">users</p>
            </div>
          </div>

          <!-- Legend -->
          <div class="w-full space-y-2.5">
            <div v-for="item in userData" :key="item.label" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }" />
                <span class="text-xs text-black/60">{{ item.label }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-black">{{ item.value }}</span>
                <span class="text-[10px] text-black/30 w-6 text-right">
                  {{ totalUsers > 0 ? Math.round(item.value / totalUsers * 100) : 0 }}%
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Business Partners by Region Grouped Bar Chart (3/5) -->
    <div class="xl:col-span-3 bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden flex flex-col justify-between">
      <div>
        <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between gap-4">
          <div>
            <h3 class="text-sm font-semibold text-black">Business Partners &amp; Suppliers</h3>
            <p class="text-xs text-black/40 mt-0.5">Geographical distribution of vendors</p>
          </div>
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5">
              <div class="w-2.5 h-2.5 rounded-sm bg-brand-navy"></div>
              <span class="text-xs text-black/40">Partners</span>
            </div>
            <div class="flex items-center gap-1.5">
              <div class="w-2.5 h-2.5 rounded-sm bg-brand-blue"></div>
              <span class="text-xs text-black/40">Suppliers</span>
            </div>
          </div>
        </div>
        <div class="px-6 py-8 overflow-x-auto">
          <VisXYContainer
            :data="partnerData"
            :height="200"
            :style="{
              '--vis-axis-tick-label-color': 'rgba(0,0,0,0.38)',
              '--vis-axis-domain-color': 'rgba(0,0,0,0.08)',
              '--vis-axis-tick-line-color': 'transparent',
              '--vis-axis-grid-color': 'rgba(0,0,0,0.04)',
              '--vis-font-family': 'inherit',
            }"
          >
            <VisGroupedBar :x="xPartner" :y="yPartner" :color="colorsPartner" :bar-padding="0.25" :group-padding="0.25" :rounded-corners="3" />
            <VisAxis type="x" :tick-format="xTickFormat" :tickValues="partnerData.map((_, i) => i)" />
            <VisAxis type="y" :tickValues="yTickValues" :tickFormat="(v: number) => String(Math.round(v))" />
          </VisXYContainer>
        </div>
      </div>
    </div>

  </div>
</template>
