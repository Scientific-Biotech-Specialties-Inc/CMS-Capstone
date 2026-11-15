<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loader2, XCircle } from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import RequestsTable      from './RequestsTable.vue'
import type { ContractRequest, RequestFilterTab } from '@/types/contractRequest'
import { useApiCache } from '@/composables/useApiCache'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const { success, error } = useToast()
const router = useRouter()
const route = useRoute()
const { state: authState } = useAuth()

const {
  state: cacheState,
  fetchRequests: fetchRequestsCached,
  updateRequestStatusInCache,
  updateContractInCache
} = useApiCache()

const requests = computed(() => cacheState.requests || [])
const loading  = computed(() => cacheState.requestsLoading)
const actionInProgress = ref(false)
const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

const showApproveConfirm = ref(false)
const showRejectConfirm = ref(false)
const pendingRequestId = ref<string | null>(null)
const pendingPartnerName = ref('')
const rejectReason = ref('')

function triggerApprove(id: string) {
  pendingRequestId.value = id
  showApproveConfirm.value = true
}

function triggerReject(id: string) {
  pendingRequestId.value = id
  const r = requests.value.find(x => x.id === id)
  pendingPartnerName.value = r?.businessPartner || ''
  rejectReason.value = ''
  showRejectConfirm.value = true
}

async function fetchRequests() {
  try {
    await fetchRequestsCached()
  } catch {
    error('Network error', 'Could not reach the server.')
  }
}

onMounted(fetchRequests)

const activeFilter = ref<RequestFilterTab>('all')
const searchQuery  = ref('')
const currentPage  = ref(1)
const itemsPerPage = 15

const statCards = computed(() => ({
  total:     requests.value.length,
  pending:   requests.value.filter(r => r.status === 'Pending').length,
  reviewing: requests.value.filter(r => r.status === 'Under Review').length,
  approved:  requests.value.filter(r => r.status === 'Approved').length,
  rejected:  requests.value.filter(r => r.status === 'Rejected').length,
}))

const statCardList = computed(() => [
  { label: 'Total Requests', value: statCards.value.total,     valueClass: 'text-black', change: '+3.2%', positive: true },
  { label: 'Pending',        value: statCards.value.pending,   valueClass: 'text-black', change: '+1.8%', positive: true },
  { label: 'Under Review',   value: statCards.value.reviewing, valueClass: 'text-black', change: '+2.5%', positive: true },
  { label: 'Approved',       value: statCards.value.approved,  valueClass: 'text-black', change: '+4.1%', positive: true },
])

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return requests.value.filter(r => {
    const bySearch = !q
      || r.id.toLowerCase().includes(q)
      || r.businessPartner.toLowerCase().includes(q)
      || r.category.toLowerCase().includes(q)
    const byFilter =
      activeFilter.value === 'all'       ? true :
      activeFilter.value === 'pending'   ? r.status === 'Pending' :
      activeFilter.value === 'reviewing' ? r.status === 'Under Review' :
      activeFilter.value === 'approved'  ? r.status === 'Approved' :
      r.status === 'Rejected'
    return bySearch && byFilter
  })
})

watch([activeFilter, searchQuery], () => { currentPage.value = 1 })

const paginated = computed(() =>
  filtered.value.slice((currentPage.value - 1) * itemsPerPage, currentPage.value * itemsPerPage)
)

function openDetail(r: ContractRequest) {
  router.push({ name: `${String(route.name)}-detail`, params: { id: r.id } })
}

async function confirmApprove() {
  if (!pendingRequestId.value) return
  const id = pendingRequestId.value
  showApproveConfirm.value = false
  pendingRequestId.value = null

  const r = requests.value.find(x => x.id === id)
  if (!r || actionInProgress.value) return

  const targetId = r.contractDbId ?? r.contractId ?? id.replace(/^REQ-/, '')
  const contractId = String(r.contractId ?? targetId)

  actionInProgress.value = true
  try {
    const res = await fetch(`${apiBase}/contracts/${targetId}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
      body: JSON.stringify({ approval_status: 'Approved', workflow_status: 'SBSI Review' }),
    })

    const data = await res.json()
    if (!res.ok) {
      error('Failed to approve', data.message ?? 'Something went wrong.')
      return
    }

    updateRequestStatusInCache(id, 'Approved')
    updateContractInCache(contractId, { approvalStatus: 'Approved', workflowStatus: 'SBSI Review' })
    success('Request approved', `${r.businessPartner}'s contract request has been approved.`)
  } catch {
    error('Network error', 'Could not reach the server. Please try again.')
  } finally {
    actionInProgress.value = false
  }
}

async function confirmRejectAction() {
  if (!pendingRequestId.value || !rejectReason.value.trim()) return
  const id = pendingRequestId.value
  const reason = rejectReason.value.trim()
  showRejectConfirm.value = false
  pendingRequestId.value = null
  rejectReason.value = ''

  const r = requests.value.find(x => x.id === id)
  if (!r || actionInProgress.value) return

  const targetId = r.contractDbId ?? r.contractId ?? id.replace(/^REQ-/, '')
  const contractId = String(r.contractId ?? targetId)

  actionInProgress.value = true
  try {
    const res = await fetch(`${apiBase}/contracts/${targetId}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
      body: JSON.stringify({ approval_status: 'Rejected', rejection_reason: reason }),
    })

    const data = await res.json()
    if (!res.ok) {
      error('Failed to reject', data.message ?? 'Something went wrong.')
      return
    }

    updateRequestStatusInCache(id, 'Rejected', { rejectionReason: reason })
    updateContractInCache(contractId, { approvalStatus: 'Rejected', workflowStatus: null })
    success('Request rejected', `${r.businessPartner}'s contract request has been rejected.`)
  } catch {
    error('Network error', 'Could not reach the server. Please try again.')
  } finally {
    actionInProgress.value = false
  }
}

function closeRejectConfirm() {
  if (actionInProgress.value) return
  showRejectConfirm.value = false
  rejectReason.value = ''
  pendingRequestId.value = null
}

</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Header -->
    <div>
      <h1 class="text-xl font-semibold text-black">Contract Requests</h1>
      <p class="text-sm text-black/40 mt-0.5">Review and action incoming contract requests.</p>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
      <template v-if="loading">
        <div v-for="i in 4" :key="i"
          class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
          <div class="h-3.5 w-24 bg-black/5 animate-pulse rounded mb-4"></div>
          <div class="flex items-end justify-between gap-2">
            <div class="h-8 w-12 bg-black/5 animate-pulse rounded"></div>
            <div class="h-5 w-10 bg-black/5 animate-pulse rounded mb-0.5"></div>
          </div>
        </div>
      </template>
      <template v-else>
        <div v-for="card in statCardList" :key="card.label"
          class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
          <p class="text-xs font-medium text-black/40 uppercase tracking-wide mb-3">{{ card.label }}</p>
          <div class="flex items-end justify-between gap-2">
            <span class="text-3xl font-semibold tabular-nums" :class="card.valueClass">{{ card.value }}</span>
            <span class="text-xs font-medium px-2 py-0.5 rounded-md mb-0.5 shrink-0"
              :class="card.positive ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'">
              {{ card.change }}
            </span>
          </div>
        </div>
      </template>
    </div>

    <!-- Table -->
    <RequestsTable
      :loading="loading"
      :paginated="paginated"
      :filtered="filtered"
      :active-filter="activeFilter"
      :search-query="searchQuery"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      @open-detail="openDetail"
      @approve="triggerApprove"
      @reject="triggerReject"
      @update:active-filter="activeFilter = $event"
      @update:search-query="searchQuery = $event"
      @update:current-page="currentPage = $event"
    />

  </div>

  <ConfirmationDialog
    v-model:open="showApproveConfirm"
    title="Approve Request"
    description="Are you sure you want to approve this contract request? This will change the status to Approved and advance the workflow."
    confirm-label="Approve"
    variant="default"
    :loading="actionInProgress"
    @confirm="confirmApprove"
  />

  <!-- Reject Confirmation Dialog -->
  <Dialog :open="showRejectConfirm" @update:open="val => { if (!val) closeRejectConfirm() }">
    <DialogContent class="max-w-md p-6 gap-4 font-sans" @pointer-down-outside="closeRejectConfirm">
      <DialogHeader class="space-y-2">
        <div class="flex items-center gap-2.5 text-red-600">
          <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
            <XCircle class="w-5 h-5 text-red-600" />
          </div>
          <DialogTitle class="text-base font-bold text-black font-poppins">Reject Contract Request</DialogTitle>
        </div>
        <DialogDescription class="text-xs text-black/60 leading-relaxed font-montserrat pt-1">
          Are you sure you want to reject this contract request<span v-if="pendingPartnerName"> from <strong class="text-black font-semibold">{{ pendingPartnerName }}</strong></span>?
          Please provide a reason below. The requester will be notified.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-1.5 mt-2">
        <div class="flex items-center justify-between">
          <label class="text-xs font-semibold text-black/70 font-poppins">
            Rejection Reason <span class="text-red-500">*</span>
          </label>
          <span class="text-[11px] text-black/40 font-montserrat">{{ rejectReason.trim().length }}/500</span>
        </div>
        <textarea
          v-model="rejectReason"
          rows="3"
          maxlength="500"
          placeholder="State why this contract request cannot be approved..."
          :disabled="actionInProgress"
          class="w-full rounded-lg border border-black/15 bg-white px-3 py-2 text-sm font-montserrat placeholder:text-black/30 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200/50 transition resize-none disabled:opacity-50"
        />
      </div>

      <DialogFooter class="flex items-center justify-end gap-3 mt-3">
        <Button
          type="button"
          variant="outline"
          @click="closeRejectConfirm"
          :disabled="actionInProgress"
          class="h-9 px-4 text-xs font-medium border-black/15 text-black/70 hover:text-black hover:bg-black/5"
        >
          Cancel
        </Button>
        <Button
          type="button"
          @click="confirmRejectAction"
          :disabled="!rejectReason.trim() || actionInProgress"
          class="h-9 px-4 text-xs bg-red-600 hover:bg-red-700 text-white font-semibold shadow-sm gap-1.5 disabled:opacity-40"
        >
          <Loader2 v-if="actionInProgress" class="w-3.5 h-3.5 animate-spin" />
          <XCircle v-else class="w-3.5 h-3.5" />
          <span>{{ actionInProgress ? 'Rejecting...' : 'Reject Request' }}</span>
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
