<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { LayoutDashboard, Sparkles, TrendingUp } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import { useToast } from '@/composables/useToast'
import { remainingDays } from '@/types/contract'
import DashboardOverviewTab from './DashboardOverviewTab.vue'
import DiagnosticInsightsTab from '@/components/dashboard/insights/DiagnosticInsightsTab.vue'
import PredictiveInsightsTab from '@/components/dashboard/insights/PredictiveInsightsTab.vue'

type Role   = 'Admin' | 'Manager' | 'Sales'
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
  createdAt: string | null
}

// ── Live clock ─────────────────────────────────────────────────────
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

const { state: authState, hasPermission } = useAuth()
const userFirstName = computed(() => (authState.user as any)?.profile?.first_name || authState.user?.first_name || 'Admin')

// ── Tabs ─────────────────────────────────────────────────────────────
type Tab = 'overview' | 'diagnostic' | 'predictive'
const activeTab = ref<Tab>('overview')
const canUseAiInsights = computed(() => hasPermission('cms.ai.risk_assessment'))

// ── Real Data & Fetching ───────────────────────────────────────────
const loading = ref(true)
const users = ref<DashUser[]>([])
const rawAuditLogs = ref<any[]>([])
const partners = ref<PartnerItem[]>([])

const { state: cacheState, fetchDashboard } = useApiCache()
const { error } = useToast()

const contracts = computed(() => cacheState.contracts || [])
const withDays = computed(() => contracts.value.map(c => ({ ...c, days: remainingDays(c.endDate) })))

type LogType = 'create' | 'update' | 'approve' | 'delete'
interface AuditLog {
  action: string; user: string; timestamp: string; type: LogType
}

const auditLogs = computed<AuditLog[]>(() => {
  return rawAuditLogs.value.map(log => {
    return {
      action: log.description || log.action,
      user: log.user_name || 'System',
      timestamp: formatTimestamp(log.performed_at),
      type: getLogType(log.action)
    }
  })
})

function getLogType(action: string): LogType {
  const act = action.toLowerCase()
  if (act.includes('created') || act.includes('added') || act.includes('upload')) return 'create'
  if (act.includes('deleted')) return 'delete'
  if (act.includes('approved') || act.includes('notarize')) return 'approve'
  return 'update'
}

function formatTimestamp(iso: string): string {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function normalizeRole(roleName: string): Role {
  const name = roleName.toLowerCase()
  if (name.includes('admin')) return 'Admin'
  if (name.includes('manager')) return 'Manager'
  return 'Sales'
}

async function fetchAdminDashboard() {
  loading.value = true
  const authApiUrl = import.meta.env.VITE_AUTH_API_URL || 'http://localhost:8000/api'
  const contractApiUrl = import.meta.env.VITE_CONTRACT_API_URL || 'http://localhost:8002/api'
  const vendorApiUrl = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
  const token = authState.token

  try {
    // 1. Fetch contracts through API cache
    await fetchDashboard()

    // 2. Fetch users list
    const usersRes = await fetch(`${authApiUrl}/admin/users?per_page=100`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'X-Session-ID': localStorage.getItem('session_id') || '',
        'Accept': 'application/json'
      }
    })
    if (usersRes.ok) {
      const data = await usersRes.json()
      if (data.data) {
        users.value = data.data.map((u: any) => ({
          id: u.id.toString(),
          name: `${u.profile?.first_name || ''} ${u.profile?.last_name || ''}`.trim() || u.email,
          email: u.email,
          role: normalizeRole(u.profile?.role?.name || 'Unknown'),
          status: (u.is_active ? 'Active' : 'Inactive') as Status,
        }))
      }
    }

    // 3. Fetch audit logs (latest 5)
    const logsRes = await fetch(`${contractApiUrl}/audit-logs?per_page=5`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    })
    if (logsRes.ok) {
      const data = await logsRes.json()
      if (data.data) {
        rawAuditLogs.value = data.data
      }
    }

    // 4. Fetch business partners and suppliers in parallel
    const pList: PartnerItem[] = []
    const [partnersRes, suppliersRes] = await Promise.all([
      fetch(`${vendorApiUrl}/partners?per_page=100`, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      }),
      fetch(`${vendorApiUrl}/suppliers?per_page=100`, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      })
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
    console.error('Failed to load admin dashboard:', err)
    error('Network error', 'Could not fetch admin dashboard data.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAdminDashboard()
})
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs font-semibold text-black/35 uppercase tracking-widest mb-0.5">Admin Portal</p>
        <h1 class="text-xl font-semibold text-black">{{ greeting }}, {{ userFirstName }}.</h1>
        <p class="text-sm text-black/40 mt-0.5">Here's what's happening at SBSI today.</p>
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

    <!-- Skeletal loader during fetch -->
    <div v-if="loading" class="space-y-6">
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 animate-pulse">
        <div v-for="i in 4" :key="i" class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
          <div class="h-3.5 w-24 bg-black/5 rounded mb-4"></div>
          <div class="h-8 w-12 bg-black/5 rounded"></div>
        </div>
      </div>
      <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 animate-pulse">
        <div class="xl:col-span-3 bg-white rounded-lg border border-black/8 p-6 h-[280px] flex flex-col justify-between">
          <div class="h-4 w-32 bg-black/5 rounded"></div>
          <div class="h-40 w-full bg-black/5 rounded"></div>
        </div>
        <div class="xl:col-span-2 bg-white rounded-lg border border-black/8 p-6 h-[280px] flex flex-col justify-between">
          <div class="h-4 w-24 bg-black/5 rounded"></div>
          <div class="h-40 w-full bg-black/5 rounded"></div>
        </div>
      </div>
    </div>

    <!-- Overview tab -->
    <DashboardOverviewTab
      v-else-if="activeTab === 'overview'"
      :contracts="withDays"
      :users="users"
      :partners="partners"
      :audit-logs="auditLogs"
    />

    <!-- Diagnostic / Predictive tabs (AI-permission gated) -->
    <DiagnosticInsightsTab v-else-if="activeTab === 'diagnostic' && canUseAiInsights" />
    <PredictiveInsightsTab v-else-if="activeTab === 'predictive' && canUseAiInsights" />

  </div>
</template>
