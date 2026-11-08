<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { LayoutDashboard, Sparkles, TrendingUp } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import { useToast } from '@/composables/useToast'
import { remainingDays } from '@/types/contract'
import DashboardOverviewTab from './DashboardOverviewTab.vue'
import DiagnosticInsightsTab from '@/components/dashboard/insights/DiagnosticInsightsTab.vue'
import PredictiveInsightsTab from '@/components/dashboard/insights/PredictiveInsightsTab.vue'

const { state: authState, hasPermission } = useAuth()
const { state: cacheState, fetchDashboard } = useApiCache()
const { error } = useToast()

const canViewContracts = computed(() => hasPermission('cms.contracts.view'))
const canUseAiInsights = computed(() => hasPermission('cms.ai.risk_assessment'))
const canViewPartners = computed(() => hasPermission('cms.partners.view'))

type Tab = 'overview' | 'diagnostic' | 'predictive'
const activeTab = ref<Tab>('overview')

interface PartnerItem {
  id: string
  name: string
  region: string
  status: string
  type: 'Partner' | 'Supplier'
  createdAt: string | null
}

const partners = ref<PartnerItem[]>([])

async function fetchPartnersAndSuppliers() {
  const vendorApiUrl = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
  const token = authState.token
  const headers = { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }

  try {
    const pList: PartnerItem[] = []
    const [partnersRes, suppliersRes] = await Promise.all([
      fetch(`${vendorApiUrl}/partners?per_page=100`, { headers }),
      fetch(`${vendorApiUrl}/suppliers?per_page=100`, { headers }),
    ])

    if (partnersRes.ok) {
      const data = await partnersRes.json()
      if (data.data) {
        data.data.forEach((item: any) => {
          pList.push({
            id: item.bp_code || `BP-${item.partner_id}`,
            name: item.partner_name || '',
            region: item.region || 'Luzon',
            status: item.status || 'Active',
            type: 'Partner',
            createdAt: item.created_at ?? null,
          })
        })
      }
    }

    if (suppliersRes.ok) {
      const data = await suppliersRes.json()
      if (data.data) {
        data.data.forEach((item: any) => {
          pList.push({
            id: `SP-${item.supplier_id}`,
            name: item.supplier_name || '',
            region: item.region || 'Luzon',
            status: item.status || 'Active',
            type: 'Supplier',
            createdAt: item.created_at ?? null,
          })
        })
      }
    }

    partners.value = pList
  } catch (err) {
    console.error('Failed to fetch partners/suppliers for manager dashboard:', err)
  }
}

const now = ref(new Date())
let timer: ReturnType<typeof setInterval>
onMounted(() => { timer = setInterval(() => { now.value = new Date() }, 1000) })
onUnmounted(() => clearInterval(timer))

const greeting = computed(() => {
  const h = now.value.getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
})
const formattedDate = computed(() =>
  now.value.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
)
const formattedTime = computed(() =>
  now.value.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
)

const userFirstName = computed(() => (authState.user as any)?.profile?.first_name || authState.user?.first_name || 'Manager')

const contracts = computed(() => cacheState.contracts || [])
const loading = computed(() => cacheState.contractsLoading)

const withDays = computed(() =>
  contracts.value.map(c => ({ ...c, days: remainingDays(c.endDate) }))
)

async function fetchDashboardData() {
  try {
    await fetchDashboard()
  } catch {
    error('Network error', 'Could not reach the server.')
  }
}

watch(canViewContracts, (canView) => {
  if (canView) {
    fetchDashboardData()
  }
}, { immediate: true })

watch(canViewPartners, (canView) => {
  if (canView) {
    fetchPartnersAndSuppliers()
  }
}, { immediate: true })
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold text-black/35 uppercase tracking-widest mb-0.5">Manager Portal</p>
        <h1 class="text-xl font-semibold text-black">{{ greeting }}, {{ userFirstName }}.</h1>
        <p class="text-sm text-black/40 mt-0.5">Here's your contract overview for today.</p>
      </div>
      <div class="text-right hidden sm:block">
        <p class="text-sm font-semibold text-black tabular-nums">{{ formattedTime }}</p>
        <p class="text-xs text-black/40 mt-0.5">{{ formattedDate }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div v-if="canViewContracts" class="flex items-center gap-1 bg-black/4 rounded-xl p-1 w-fit border border-black/5">
      <button
        @click="activeTab = 'overview'"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all"
        :class="activeTab === 'overview' ? 'bg-white text-black shadow-xs' : 'text-black/50 hover:text-black'"
      >
        <LayoutDashboard class="w-4 h-4 text-brand-navy" />
        Overview
      </button>
      <button
        v-if="canUseAiInsights"
        @click="activeTab = 'diagnostic'"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all"
        :class="activeTab === 'diagnostic' ? 'bg-white text-black shadow-xs' : 'text-black/50 hover:text-black'"
      >
        <Sparkles class="w-4 h-4 text-brand-blue" />
        Diagnostic
      </button>
      <button
        v-if="canUseAiInsights"
        @click="activeTab = 'predictive'"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all"
        :class="activeTab === 'predictive' ? 'bg-white text-black shadow-xs' : 'text-black/50 hover:text-black'"
      >
        <TrendingUp class="w-4 h-4 text-brand-navy" />
        Predictive
      </button>
    </div>

    <!-- KPI Cards skeleton -->
    <template v-if="loading">
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="bg-white rounded-lg border border-black/8 px-5 py-5 shadow-sm animate-pulse">
          <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-black/5 shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3 w-16 bg-black/5 rounded"></div>
              <div class="h-6 w-12 bg-black/5 rounded"></div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <DashboardOverviewTab v-else-if="activeTab === 'overview' && canViewContracts" :contracts="withDays" :partners="partners" />

    <DiagnosticInsightsTab v-else-if="activeTab === 'diagnostic' && canUseAiInsights" />
    <PredictiveInsightsTab v-else-if="activeTab === 'predictive' && canUseAiInsights" />

    <div v-if="!canViewContracts" class="bg-white p-8 rounded-xl border border-black/10 text-center">
      <p class="text-black/40">You do not have permission to view contract data.</p>
    </div>

  </div>
</template>
