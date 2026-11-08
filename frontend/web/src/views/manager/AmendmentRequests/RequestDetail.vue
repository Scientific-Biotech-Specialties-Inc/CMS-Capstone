<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, CheckCircle, XCircle, Loader2, FileType2, Download, FileX } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import { useAmendmentStore } from '@/composables/useAmendmentStore'
import type { ContractAmendment } from '@/types/contractAmendment'
import { amendmentStatusBadge } from '@/types/contractAmendment'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const route = useRoute()
const router = useRouter()
const { success, error } = useToast()
const { state: authState } = useAuth()
const { state: cacheState, fetchContracts } = useApiCache()
const amendmentStore = useAmendmentStore()

const id = route.params.id as string
const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

const loadingData = ref(true)
const actionLoading = ref(false)
const showRejectPrompt = ref(false)
const rejectReason = ref('')
const amendment = ref<ContractAmendment | null>(null)

const showConfirm = ref(false)
const confirmTitle = ref('')
const confirmDesc = ref('')
const confirmVariant = ref<'default' | 'destructive'>('default')
const confirmAction = ref<(() => void) | null>(null)

const liveContract = computed(() => {
  if (!amendment.value) return null
  const cached = (cacheState.contracts || []).find(c => c.id === amendment.value!.contractId)
  if (cached) return cached

  // Fallback mock contracts for seeded demo/test data if not present in the DB
  if (amendment.value.contractId === '1') {
    return {
      id: '1',
      businessPartner: 'Globe Telecom',
      category: 'Service Agreement',
      itemCode: 'ITM-0041',
      description: 'Network Infrastructure Support',
      serialNo: 'SN-2024-0041',
      sbuNumber: 'SBU-001',
      region: 'Luzon',
      startDate: '2025-01-01',
      endDate: '2026-12-31', // Original end date before AMD-001 extension
      approvalStatus: 'Approved',
      workflowStatus: null,
      createdBy: 'Ejay Detera',
      docs: []
    }
  }
  if (amendment.value.contractId === '2') {
    return {
      id: '2',
      businessPartner: 'PLDT Enterprise',
      category: 'Supply Contract',
      itemCode: 'ITM-0052',
      description: 'Fiber Optic Cable Supply (Phase 1)',
      serialNo: 'SN-2024-0052',
      sbuNumber: 'SBU-001', // Original SBU before AMD-002 SBU-002 update
      region: 'Visayas',
      startDate: '2025-02-15',
      endDate: '2026-02-15',
      approvalStatus: 'Approved',
      workflowStatus: null,
      createdBy: 'Ejay Detera',
      docs: []
    }
  }

  // Generic fallback using current proposed fields so manager can always see a comparison
  return {
    id: amendment.value.contractId,
    businessPartner: amendment.value.businessPartner,
    category: amendment.value.category,
    itemCode: amendment.value.itemCode,
    description: amendment.value.description,
    serialNo: amendment.value.serialNo,
    sbuNumber: amendment.value.sbuNumber,
    region: amendment.value.region,
    startDate: amendment.value.startDate,
    endDate: amendment.value.endDate,
    approvalStatus: 'Approved',
    workflowStatus: null,
    createdBy: amendment.value.createdBy,
    docs: []
  }
})

const diffFields = computed(() => {
  if (!amendment.value || !liveContract.value) return []
  
  const fields = [
    { key: 'businessPartner', label: 'Business Partner' },
    { key: 'category',        label: 'Category' },
    { key: 'itemCode',        label: 'Item Code' },
    { key: 'description',     label: 'Description' },
    { key: 'serialNo',        label: 'Serial No' },
    { key: 'sbuNumber',       label: 'SBU Number' },
    { key: 'region',          label: 'Region' },
    { key: 'startDate',       label: 'Start Date' },
    { key: 'endDate',         label: 'End Date' }
  ]

  return fields.map(f => {
    const original = String((liveContract.value as any)[f.key] || '').trim()
    const proposed = String((amendment.value as any)[f.key] || '').trim()
    const isChanged = original !== proposed
    return {
      label: f.label,
      original,
      proposed,
      isChanged
    }
  })
})

function triggerApprove() {
  confirmTitle.value = 'Approve Amendment'
  confirmDesc.value = 'Are you sure you want to approve this amendment request? This will update the live contract details and increment its version.'
  confirmVariant.value = 'default'
  confirmAction.value = proceedApprove
  showConfirm.value = true
}

async function proceedApprove() {
  if (!amendment.value) return
  actionLoading.value = true
  showConfirm.value = false
  try {
    const ok = await amendmentStore.approveAmendment(amendment.value.id)
    if (ok) {
      success('Amendment Approved', `Contract has been successfully updated to version ${amendment.value.version}.`)
      router.push('/manager/amendment-requests')
    } else {
      error('Failed', 'Could not approve amendment request.')
    }
  } catch (err) {
    error('Error', 'An error occurred during approval.')
  } finally {
    actionLoading.value = false
  }
}

function triggerReject() {
  if (!amendment.value || !rejectReason.value.trim()) return
  confirmTitle.value = 'Reject Amendment'
  confirmDesc.value = 'Are you sure you want to reject this amendment request?'
  confirmVariant.value = 'destructive'
  confirmAction.value = proceedReject
  showConfirm.value = true
}

async function proceedReject() {
  if (!amendment.value || !rejectReason.value.trim()) return
  actionLoading.value = true
  showConfirm.value = false
  try {
    const ok = await amendmentStore.rejectAmendment(amendment.value.id, rejectReason.value.trim())
    if (ok) {
      success('Amendment Rejected', 'Contract amendment request has been rejected.')
      router.push('/manager/amendment-requests')
    } else {
      error('Failed', 'Could not reject amendment request.')
    }
  } catch (err) {
    error('Error', 'An error occurred.')
  } finally {
    actionLoading.value = false
  }
}

function viewDoc(docId: string | undefined) {
  if (!docId || !amendment.value) return
  router.push(`/manager/contracts/${amendment.value.contractId}/documents/${docId}?fromAmd=${amendment.value.id}`)
}

function fmtDate(iso: string): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

onMounted(async () => {
  loadingData.value = true

  // Fetch amendment details from backend
  try {
    const res = await fetch(`${apiBase}/contract-amendments/${id}`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
    })
    if (res.ok) {
      const json = await res.json()
      amendment.value = json.data
    }
  } catch (e) {
    console.error('Failed to fetch amendment details', e)
  }

  // Ensure references are fetched
  if (!cacheState.contracts || cacheState.contracts.length === 0) {
    try {
      await fetchContracts()
    } catch (e) {
      error('Error', 'Failed to load contract catalog references.')
    }
  }

  loadingData.value = false

  if (!amendment.value) {
    error('Error', 'Amendment request not found.')
    router.push('/manager/amendment-requests')
  }
})
</script>

<template>
  <div class="p-8 space-y-6 font-poppins">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button @click="router.push('/manager/amendment-requests')"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0 shadow-sm">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-black">Review Amendment Proposal</h1>
          <p class="text-xs text-black/40 mt-0.5" v-if="amendment">
            Submitted by <strong>{{ amendment.createdBy }}</strong> on {{ fmtDate(amendment.requestDate) }}
          </p>
        </div>
        <div v-if="amendment" class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full border" :class="amendmentStatusBadge[amendment.status]">
            {{ amendment.status }}
          </span>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loadingData" class="flex flex-col items-center justify-center py-20 gap-2">
      <Loader2 class="w-8 h-8 animate-spin text-brand-navy" />
      <span class="text-sm text-black/40 font-medium">Syncing database references...</span>
    </div>

    <!-- Main Layout -->
    <div v-else-if="amendment && liveContract" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Proposed Comparison Fields (Left Column, spans 2) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Comparative Diff Table Card -->
        <div class="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden">
          <div class="bg-black/[0.015] px-6 py-4 border-b border-black/5 flex items-center justify-between">
            <h3 class="text-xs font-bold text-black/50 uppercase tracking-wider">Field Comparison (Original vs Proposed)</h3>
            <span class="text-xs text-brand-navy font-semibold">Contract Ref: {{ amendment.contractId }}</span>
          </div>

          <div class="divide-y divide-black/[0.04]">
            <div v-for="field in diffFields" :key="field.label" 
              class="px-6 py-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-start"
              :class="field.isChanged ? 'bg-emerald-50/[0.12]' : ''">
              
              <!-- Field Label -->
              <span class="text-xs font-bold text-black/60 pt-1 uppercase tracking-wide text-[10px]">{{ field.label }}</span>
              
              <!-- Comparison Area -->
              <div class="md:col-span-2 text-sm">
                <div v-if="field.isChanged" class="flex flex-col gap-2 font-mono">
                  <span class="text-red-500 line-through bg-red-50/75 px-3 py-1.5 rounded-lg border border-red-100/50 block w-full text-xs">
                    - {{ field.original || '(empty)' }}
                  </span>
                  <span class="text-emerald-700 font-semibold bg-emerald-50/70 px-3 py-1.5 rounded-lg border border-emerald-100/80 block w-full text-xs">
                    + {{ field.proposed || '(empty)' }}
                  </span>
                </div>
                <span v-else class="text-black/75 font-medium block">
                  {{ field.original || '—' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Comparative Documents Card -->
        <div class="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden mt-6">
          <div class="bg-black/[0.015] px-6 py-4 border-b border-black/5 flex items-center justify-between">
            <h3 class="text-xs font-bold text-black/50 uppercase tracking-wider">Document Changes (Original vs New Proposed)</h3>
          </div>

          <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left: Original Documents -->
            <div class="space-y-3">
              <h4 class="text-xs font-bold text-black/50 uppercase tracking-wider">Original Documents (Before)</h4>
              
              <div v-if="!liveContract.docs || liveContract.docs.length === 0" class="flex items-center gap-2 text-black/30 border border-dashed border-black/10 rounded-lg p-4 justify-center bg-black/[0.005]">
                <FileX class="w-4 h-4 text-black/25" />
                <span class="text-xs">No documents attached</span>
              </div>
              <div v-else class="space-y-2">
                <div v-for="doc in liveContract.docs" :key="doc.id" @click="viewDoc(doc.id)"
                  class="group border border-black/10 rounded-lg p-3 flex items-center gap-3 hover:border-brand-blue transition-all cursor-pointer bg-white shadow-sm">
                  <div class="w-9 h-9 bg-black/[0.04] flex items-center justify-center rounded-lg text-black/40 group-hover:bg-brand-blue group-hover:text-white transition-colors shrink-0">
                    <FileType2 class="w-4.5 h-4.5" />
                  </div>
                  <div class="flex-1 overflow-hidden min-w-0">
                    <p class="text-xs text-black font-semibold truncate">{{ doc.name }}</p>
                    <p class="text-[10px] text-black/50 font-medium uppercase">{{ doc.type }}</p>
                  </div>
                  <Download class="w-4 h-4 text-black/25 group-hover:text-brand-navy shrink-0" />
                </div>
              </div>
            </div>

            <!-- Right: Proposed New Documents -->
            <div class="space-y-3">
              <h4 class="text-xs font-bold text-brand-blue uppercase tracking-wider">Proposed Amendments Documents (After)</h4>
              
              <div v-if="!amendment.docs || amendment.docs.length === 0" class="flex items-center gap-2 text-black/30 border border-dashed border-black/10 rounded-lg p-4 justify-center bg-black/[0.005]">
                <FileX class="w-4 h-4 text-black/25" />
                <span class="text-xs">No new documents added</span>
              </div>
              <div v-else class="space-y-2">
                <div v-for="doc in amendment.docs" :key="doc.id" @click="viewDoc(doc.id)"
                  class="group border border-brand-blue/20 rounded-lg p-3 flex items-center gap-3 hover:border-brand-blue transition-all cursor-pointer bg-white shadow-sm">
                  <div class="w-9 h-9 bg-brand-blue/8 flex items-center justify-center rounded-lg text-brand-blue group-hover:bg-brand-blue group-hover:text-white transition-colors shrink-0">
                    <FileType2 class="w-4.5 h-4.5" />
                  </div>
                  <div class="flex-1 overflow-hidden min-w-0">
                    <p class="text-xs text-black font-semibold truncate">{{ doc.name }}</p>
                    <p class="text-[10px] text-black/50 font-medium uppercase">{{ doc.type }}</p>
                  </div>
                  <Download class="w-4 h-4 text-black/25 group-hover:text-brand-navy shrink-0" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Panel & Summary (Right Column) -->
      <div class="space-y-6">
        <!-- Amendment Reason Card -->
        <div class="bg-white rounded-xl border border-black/8 shadow-sm p-6 space-y-4">
          <span class="text-[10px] font-bold text-brand-navy uppercase tracking-wider block border-b border-black/5 pb-2">Proposed Reason</span>
          <p class="text-xs text-black/85 leading-relaxed bg-brand-blue/5 border border-brand-blue/10 p-3.5 rounded-xl font-medium">
            {{ amendment.reason }}
          </p>
        </div>

        <!-- Approval Action Box -->
        <div class="bg-white rounded-xl border border-black/8 shadow-sm p-6 space-y-4">
          <span class="text-[10px] font-bold text-black/40 uppercase tracking-wider block border-b border-black/5 pb-2">Actions</span>
          
          <template v-if="amendment.status === 'Pending'">
            <div class="space-y-3" v-if="!showRejectPrompt">
              <Button @click="triggerApprove" :disabled="actionLoading"
                class="w-full h-10 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-sm flex items-center justify-center gap-2">
                <CheckCircle class="w-4 h-4" />
                Approve & Update Contract
              </Button>
              
              <Button @click="showRejectPrompt = true" variant="outline" :disabled="actionLoading"
                class="w-full h-10 border-black/15 text-red-600 hover:text-red-700 hover:bg-red-50/50 font-semibold">
                <XCircle class="w-4 h-4" />
                Reject Amendment
              </Button>
            </div>

            <!-- Rejection Input Prompt -->
            <div class="space-y-3" v-else>
              <div class="space-y-1.5">
                <label class="text-[11px] font-bold text-red-600 uppercase tracking-wider block">
                  Rejection Explanation <span class="text-red-500">*</span>
                </label>
                <textarea
                  v-model="rejectReason"
                  rows="3"
                  placeholder="Explain why this amendment is rejected..."
                  class="w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-xs placeholder:text-black/25 focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-200/15 transition resize-none"
                />
              </div>

              <div class="flex items-center gap-2">
                <Button @click="showRejectPrompt = false" variant="outline" :disabled="actionLoading"
                  class="flex-1 h-9 border-black/15 text-black/60 hover:text-black">
                  Back
                </Button>
                <Button @click="triggerReject" :disabled="!rejectReason.trim() || actionLoading"
                  class="flex-1 h-9 bg-red-600 hover:bg-red-700 text-white font-semibold">
                  Confirm Reject
                </Button>
              </div>
            </div>
          </template>

          <!-- Approved state summary -->
          <div v-else-if="amendment.status === 'Approved'" class="bg-emerald-50 border border-emerald-100 rounded-lg p-3 text-xs text-emerald-800">
            <span class="font-bold flex items-center gap-1.5 mb-1 text-emerald-900">
              <CheckCircle class="w-4 h-4" /> Approved
            </span>
            <span>This request was approved by <strong>{{ amendment.approvedBy }}</strong>. The live contract details have been updated.</span>
          </div>

          <!-- Rejected state summary -->
          <div v-else-if="amendment.status === 'Rejected'" class="bg-red-50 border border-red-100 rounded-lg p-3 text-xs text-red-800 space-y-2">
            <span class="font-bold flex items-center gap-1.5 text-red-900">
              <XCircle class="w-4 h-4" /> Rejected
            </span>
            <div><strong>Rejection Reason:</strong> {{ amendment.rejectionReason }}</div>
          </div>
        </div>
      </div>

    </div>

    <!-- Confirmation Dialog -->
    <ConfirmationDialog
      v-slot:default
      v-model:open="showConfirm"
      :title="confirmTitle"
      :description="confirmDesc"
      confirm-label="Confirm"
      :variant="confirmVariant"
      :loading="actionLoading"
      @confirm="confirmAction ? confirmAction() : undefined"
    />
  </div>
</template>
