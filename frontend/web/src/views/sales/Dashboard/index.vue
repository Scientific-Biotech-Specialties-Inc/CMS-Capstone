<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { LayoutDashboard, Sparkles, TrendingUp } from 'lucide-vue-next'
import { remainingDays } from '@/types/contract'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { useApiCache } from '@/composables/useApiCache'
import DashboardOverviewTab from './DashboardOverviewTab.vue'
import OwnDiagnosticTab from '@/components/dashboard/insights/OwnDiagnosticTab.vue'
import OwnPredictiveTab from '@/components/dashboard/insights/OwnPredictiveTab.vue'

const { state: authState, hasPermission } = useAuth()
const { error } = useToast()

const canUseAiInsights = computed(() => hasPermission('cms.ai.risk_assessment'))

type Tab = 'overview' | 'diagnostic' | 'predictive'
const activeTab = ref<Tab>('overview')

// ── Live clock ──────────────────────────────────────────────────
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

const userFirstName = computed(() => (authState.user as any)?.profile?.first_name || authState.user?.first_name || 'Sales Rep')

const { state: cacheState, fetchDashboard } = useApiCache()

// ── Cached live data — scoped to the current user by useApiCache/backend ──
const contracts = computed(() => cacheState.contracts || [])
const requests = computed(() => cacheState.requests || [])
const loading = computed(() => cacheState.contractsLoading || cacheState.requestsLoading)

async function fetchDashboardData() {
  try {
    await fetchDashboard()
  } catch {
    error('Network error', 'Could not reach the server.')
  }
}

onMounted(() => {
  fetchDashboardData()
})

const withDays = computed(() => contracts.value.map(c => ({ ...c, days: remainingDays(c.endDate) })))
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Greeting header -->
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold text-black/35 uppercase tracking-widest mb-0.5">Sales Portal</p>
        <h1 class="text-xl font-semibold text-black">{{ greeting }}, {{ userFirstName }}.</h1>
        <p class="text-sm text-black/40 mt-0.5">Here's an overview of your contracts and requests.</p>
      </div>
      <div class="text-right hidden sm:block">
        <p class="text-sm font-semibold text-black tabular-nums">{{ formattedTime }}</p>
        <p class="text-xs text-black/40 mt-0.5">{{ formattedDate }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 bg-black/4 rounded-xl p-1 w-fit border border-black/5">
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
        My Diagnostic
      </button>
      <button
        v-if="canUseAiInsights"
        @click="activeTab = 'predictive'"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all"
        :class="activeTab === 'predictive' ? 'bg-white text-black shadow-xs' : 'text-black/50 hover:text-black'"
      >
        <TrendingUp class="w-4 h-4 text-brand-navy" />
        My Predictive
      </button>
    </div>

    <!-- Skeletal loader during fetch -->
    <div v-if="loading" class="space-y-6">
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 animate-pulse">
        <div v-for="i in 4" :key="i" class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
          <div class="h-3.5 w-24 bg-black/5 rounded mb-4"></div>
          <div class="h-8 w-12 bg-black/5 rounded"></div>
        </div>
      </div>
    </div>

    <DashboardOverviewTab v-else-if="activeTab === 'overview'" :contracts="withDays" :requests="requests" />

    <OwnDiagnosticTab v-else-if="activeTab === 'diagnostic' && canUseAiInsights" :contracts="withDays" />
    <OwnPredictiveTab v-else-if="activeTab === 'predictive' && canUseAiInsights" :contracts="withDays" />

  </div>
</template>
