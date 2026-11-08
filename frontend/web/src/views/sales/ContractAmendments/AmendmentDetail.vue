<script setup lang="ts">
import { ref, computed, onMounted, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Trash2, Loader2, CheckCircle, XCircle, FileType2, Download, FileX } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import { useAmendmentStore } from '@/composables/useAmendmentStore'
import { setBreadcrumbTitle } from '@/composables/useBreadcrumbs'
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
const showUnsubmitConfirm = ref(false)
const amendment = ref<ContractAmendment | null>(null)

watchEffect(() => {
  if (amendment.value?.businessPartner) {
    setBreadcrumbTitle(amendment.value.businessPartner)
  }
})

const liveContract = computed(() => {
  if (!amendment.value) return null
  const cached = (cacheState.contracts || []).find(c => c.id === amendment.value!.contractId)
  if (cached) return cached

  // Fallback mock contracts for seeded demo/test data
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
      endDate: '2026-12-31',
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
      sbuNumber: 'SBU-001',
      region: 'Visayas',
      startDate: '2025-02-15',
      endDate: '2026-02-15',
      approvalStatus: 'Approved',
      workflowStatus: null,
      createdBy: 'Ejay Detera',
      docs: []
    }
  }

  // Generic fallback using current proposed fields
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

async function handleUnsubmit() {
  if (!amendment.value) return
  actionLoading.value = true
  showUnsubmitConfirm.value = false
  try {
    const ok = await amendmentStore.deleteAmendment(amendment.value.id)
    if (ok) {
      success('Amendment request withdrawn', 'The request has been successfully deleted.')
      router.push('/sales/contract-amendments')
    } else {
      error('Error', 'Could not delete the amendment request.')
    }
  } catch (err) {
    error('Error', 'An error occurred.')
  } finally {
    actionLoading.value = false
  }
}

function viewDoc(docId: string | undefined) {
  if (!docId || !amendment.value) return
  router.push(`/sales/contracts/${amendment.value.contractId}/documents/${docId}?fromAmd=${amendment.value.id}`)
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
    router.push('/sales/contract-amendments')
  }
})
</script>

<template>
  <div class="p-8 space-y-6 font-poppins">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button @click="router.push('/sales/contract-amendments')"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0 shadow-sm">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-black">Amendment Proposal Details</h1>
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
            <span class="text-xs text-brand-blue font-semibold">Contract Ref: {{ amendment.contractId }}</span>
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

        <!-- Details Card -->
        <div class="bg-white rounded-xl border border-black/8 shadow-sm p-6 space-y-4">
          <span class="text-[10px] font-bold text-black/40 uppercase tracking-wider block border-b border-black/5 pb-2">Request Status Details</span>
          
          <div class="space-y-3">
            <div>
              <span class="text-[10px] font-bold text-black/40 block">Version Target</span>
              <span class="text-xs text-brand-blue font-bold block mt-0.5">v{{ amendment.version }}</span>
            </div>

            <!-- Approved state summary -->
            <div v-if="amendment.status === 'Approved'" class="bg-emerald-50 border border-emerald-100 rounded-lg p-3 text-xs text-emerald-800">
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

            <!-- Pending actions -->
            <div v-else-if="amendment.status === 'Pending'" class="space-y-2">
              <p class="text-xs text-black/50 leading-normal">
                This request is currently pending review by the manager. You can withdraw/delete it before it is processed.
              </p>
              <Button @click="showUnsubmitConfirm = true" variant="destructive" :disabled="actionLoading"
                class="w-full h-10 font-semibold shadow-sm flex items-center justify-center gap-2">
                <Trash2 class="w-4 h-4" />
                Unsubmit Request
              </Button>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Unsubmit Confirmation Dialog -->
    <ConfirmationDialog
      v-slot:default
      v-model:open="showUnsubmitConfirm"
      title="Unsubmit Amendment Request"
      description="Are you sure you want to withdraw and delete this pending amendment request? This action cannot be undone."
      confirm-label="Yes, Unsubmit"
      variant="destructive"
      :loading="actionLoading"
      @confirm="handleUnsubmit"
    />
  </div>
</template>
