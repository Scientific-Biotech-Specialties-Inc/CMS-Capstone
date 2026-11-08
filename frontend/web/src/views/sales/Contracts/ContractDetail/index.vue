<script setup lang="ts">
import { ref, reactive, computed, onMounted, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FileX, Clock } from 'lucide-vue-next'
import { useAmendmentStore } from '@/composables/useAmendmentStore'
import { setBreadcrumbTitle } from '@/composables/useBreadcrumbs'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { useApiCache } from '@/composables/useApiCache'
import { useHighRiskApproval } from '@/composables/useHighRiskApproval'
import { useEmailPreferences } from '@/composables/useEmailPreferences'
import { remainingDays } from '@/types/contract'
import type { ContractApprovalStatus, ContractWorkflowStatus, ContractRegion, UploadedDoc } from '@/types/contract'
import type { StoredContract } from '@/composables/useContractStore'
import ContractDetailHeader     from './ContractDetailHeader.vue'
import ContractInfoSection      from './ContractInfoSection.vue'
import ContractDocumentsSection from './ContractDocumentsSection.vue'
import ContractExecutionSection from './ContractExecutionSection.vue'
import InternalApprovalActions from './InternalApprovalActions.vue'
import VersionHistoryDrawer     from './VersionHistoryDrawer.vue'
import ConfirmationDialog       from '@/components/shared/ConfirmationDialog.vue'
import RejectionReasonModal     from './RejectionReasonModal.vue'
import HighRiskApprovalModal    from '@/components/shared/HighRiskApprovalModal.vue'
import WorkflowTrackerModal     from '@/components/shared/ContractWorkflowTracker/WorkflowTrackerModal.vue'
import type { WorkflowProgress } from '@/types/contractWorkflow'
import { nudgeLabelFor } from '@/types/contractWorkflow'
import { useApprovalProgress } from '@/composables/useApprovalProgress'
import { AlertCircle } from 'lucide-vue-next'

const route  = useRoute()
const router = useRouter()
const { state: authState, role } = useAuth()
const isManager = computed(() => role.value === 'Manager' || role.value === 'Admin')
const isOwner   = computed(() => !!contract.value && !contract.value.createdBy.startsWith('User #'))
const { success, error } = useToast()
const { state: cacheState, fetchContracts, updateContractInCache, invalidateRequests } = useApiCache()

const id = route.params.id as string

const contract        = ref<StoredContract | null>(null)
const loadingContract = ref(true)
const savingEdit      = ref(false)

// ── Visual Contract Workflow Tracker — live data (Phase 6) ────────────────
// Primary source is GET /contracts/{id}/approval-progress via
// useApprovalProgress (engine:false → empty status, decision #9). The
// `?workflowMock=A..E` scenarios below are a dev-only override for
// exercising every visual state without seeded engine data — they apply
// only when the query param is explicitly present.
const { workflow: liveWorkflow, status: liveTrackerStatus, currentStepRoles, myTasks, decide, fetchProgress: fetchWorkflowProgress, retry: retryWorkflow } = useApprovalProgress()
const workflowMockScenarios: Record<string, WorkflowProgress> = {
  // Scenario A: normal progress — some completed, one active, rest pending.
  A: {
    contractId: 'CTR-1001', currentRun: 1,
    runs: [{ runNumber: 1, groups: [
      { id: 'g1', mode: 'sequential', nodes: [{ id: 'n1', title: 'Contract created', status: 'completed', actedAt: '2026-09-26T09:00:00Z', subText: 'Submitted by Procurement', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g2', mode: 'sequential', nodes: [{ id: 'n2', title: 'Regulatory', status: 'completed', actedAt: '2026-09-28T14:30:00Z', subText: 'Compliant with regional rules', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g3', mode: 'sequential', nodes: [{ id: 'n3', title: 'Sales', status: 'active', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g4', mode: 'sequential', nodes: [{ id: 'n4', title: 'Accounting', status: 'pending', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g5', mode: 'sequential', nodes: [{ id: 'n5', title: 'CEO (for review)', status: 'pending', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
    ] }],
  },
  // Scenario B: rejection — Sales rejects, downstream steps stay pending.
  B: {
    contractId: 'CTR-1002', currentRun: 1,
    runs: [{ runNumber: 1, groups: [
      { id: 'g1', mode: 'sequential', nodes: [{ id: 'n1', title: 'Contract created', status: 'completed', actedAt: '2026-09-26T09:00:00Z', subText: 'Submitted by Procurement', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g2', mode: 'sequential', nodes: [{ id: 'n2', title: 'Regulatory', status: 'completed', actedAt: '2026-09-28T14:30:00Z', subText: 'Compliant with regional rules', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g3', mode: 'sequential', nodes: [{ id: 'n3', title: 'Sales', status: 'rejected', actedAt: '2026-09-30T10:15:00Z', subText: 'Pricing terms need revision', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g4', mode: 'sequential', nodes: [{ id: 'n4', title: 'Accounting', status: 'pending', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g5', mode: 'sequential', nodes: [{ id: 'n5', title: 'CEO (for review)', status: 'pending', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
    ] }],
  },
  // Scenario C: parallel group + delegation on the final step, plus a
  // renamed-role indicator on Regulatory to exercise decision #14.
  C: {
    contractId: 'CTR-1003', currentRun: 1,
    runs: [{ runNumber: 1, groups: [
      { id: 'g1', mode: 'sequential', nodes: [{ id: 'n1', title: 'Contract created', status: 'completed', actedAt: '2026-09-26T09:00:00Z', subText: 'Submitted by Procurement', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g2', mode: 'sequential', nodes: [{ id: 'n2', title: 'Regulatory Officer', status: 'completed', actedAt: '2026-09-28T14:30:00Z', subText: 'Compliant with regional rules', delegation: null, roleDeleted: false, roleRenamedFrom: 'Regulatory' }] },
      { id: 'g3', mode: 'parallel', nodes: [
        { id: 'n3a', title: 'Sales', status: 'completed', actedAt: '2026-09-29T11:00:00Z', subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null },
        { id: 'n3b', title: 'Accounting', status: 'active', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null },
      ] },
      { id: 'g4', mode: 'sequential', nodes: [{ id: 'n4', title: 'CEO (for review)', status: 'completed', actedAt: '2026-10-01T08:00:00Z', subText: null, delegation: { delegateName: 'Alex Reyes', onBehalfOfRole: 'CEO' }, roleDeleted: false, roleRenamedFrom: null }] },
    ] }],
  },
  // Scenario D: parallel rejection — Sales rejects, Accounting's sibling
  // task is canceled (not rejected), per spec 5.4.
  D: {
    contractId: 'CTR-1004', currentRun: 1,
    runs: [{ runNumber: 1, groups: [
      { id: 'g1', mode: 'sequential', nodes: [{ id: 'n1', title: 'Contract created', status: 'completed', actedAt: '2026-09-26T09:00:00Z', subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g2', mode: 'sequential', nodes: [{ id: 'n2', title: 'Regulatory', status: 'completed', actedAt: '2026-09-28T14:30:00Z', subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g3', mode: 'parallel', nodes: [
        { id: 'n3a', title: 'Sales', status: 'rejected', actedAt: '2026-09-30T10:15:00Z', subText: 'Pricing terms need revision', delegation: null, roleDeleted: false, roleRenamedFrom: null },
        { id: 'n3b', title: 'Accounting', status: 'canceled', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null },
      ] },
      { id: 'g4', mode: 'sequential', nodes: [{ id: 'n4', title: 'CEO (for review)', status: 'pending', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
    ] }],
  },
  // Scenario E: restart — run 1 ended rejected (collapsed history), run 2
  // in progress. Also exercises the deleted-role warning icon.
  E: {
    contractId: 'CTR-1005', currentRun: 2,
    runs: [
      { runNumber: 1, groups: [
        { id: 'g1-r1', mode: 'sequential', nodes: [{ id: 'n1-r1', title: 'Contract created', status: 'completed', actedAt: '2026-08-01T09:00:00Z', subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
        { id: 'g2-r1', mode: 'sequential', nodes: [{ id: 'n2-r1', title: 'Regulatory', status: 'rejected', actedAt: '2026-08-05T10:00:00Z', subText: 'Missing permit documentation', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      ] },
      { runNumber: 2, groups: [
        { id: 'g1-r2', mode: 'sequential', nodes: [{ id: 'n1-r2', title: 'Contract created', status: 'completed', actedAt: '2026-09-26T09:00:00Z', subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
        { id: 'g2-r2', mode: 'sequential', nodes: [{ id: 'n2-r2', title: 'Compliance Reviewer', status: 'active', actedAt: null, subText: null, delegation: null, roleDeleted: true, roleRenamedFrom: null }] },
      ] },
    ],
  },
  // Scenario F: on-hold step — the Accounting role currently has no
  // active holders (decision #12): amber badge, current-step emphasis.
  F: {
    contractId: 'CTR-1006', currentRun: 1,
    runs: [{ runNumber: 1, groups: [
      { id: 'g1', mode: 'sequential', nodes: [{ id: 'n1', title: 'Contract created', status: 'completed', actedAt: '2026-09-26T09:00:00Z', subText: 'Submitted by Procurement', delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g2', mode: 'sequential', nodes: [{ id: 'n2', title: 'Regulatory', status: 'completed', actedAt: '2026-09-28T14:30:00Z', subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g3', mode: 'sequential', nodes: [{ id: 'n3', title: 'Accounting', status: 'on_hold', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
      { id: 'g4', mode: 'sequential', nodes: [{ id: 'n4', title: 'CEO (for review)', status: 'pending', actedAt: null, subText: null, delegation: null, roleDeleted: false, roleRenamedFrom: null }] },
    ] }],
  },
}

const workflowTracker = computed<WorkflowProgress | null>(() => {
  const scenario = route.query.workflowMock as string | undefined
  if (scenario) return workflowMockScenarios[scenario] ?? workflowMockScenarios.A
  return liveWorkflow.value
})
const workflowTrackerStatus = computed(() => {
  if (route.query.workflowMock) return (workflowTracker.value ? 'ready' as const : 'empty' as const)
  return liveTrackerStatus.value
})
function handleWorkflowRetry() {
  retryWorkflow(id)
}

// ── Phase 7: generalized nudge (decision #13) ────────────────────────────
// Engine-routed contracts nudge the current step's role(s) via
// POST /notify-step; legacy contracts (empty currentStepRoles) fall back
// to the historic manager-targeted endpoint. The button label derives
// from the progress response, not the tracker nodes.
const nudgeLabel = computed(() => nudgeLabelFor(currentStepRoles.value))
const isEngineRouted = computed(() => contract.value?.hasPendingApprovalWorkflow === true || currentStepRoles.value.length > 0)
const showWorkflowTracker = ref(false)

const amendmentStore = useAmendmentStore()
const viewingSnapshotVersion = ref<number | null>(null)
const viewingSnapshotDate    = ref<string>('')

const displayedContract = computed<StoredContract | null>(() => {
  if (!contract.value) return null
  if (viewingSnapshotVersion.value === null) return contract.value

  const snaps = amendmentStore.versionHistory.value[contract.value.id] || []
  const found = snaps.find(s => s.version === viewingSnapshotVersion.value)
  if (!found) return contract.value

  return {
    ...contract.value,
    businessPartner: found.businessPartner,
    category:        found.category,
    itemCode:        found.itemCode,
    description:     found.description,
    serialNo:        found.serialNo,
    sbuNumber:       found.sbuNumber,
    region:          found.region,
    startDate:       found.startDate,
    endDate:         found.endDate,
    docs:            found.docs,
  }
})

watchEffect(() => {
  if (displayedContract.value?.businessPartner) {
    setBreadcrumbTitle(displayedContract.value.businessPartner)
  }
})

const days = computed(() => displayedContract.value ? remainingDays(displayedContract.value.endDate) : 0)

const backPath = computed(() => {
  if (route.path.startsWith('/admin')) return '/admin/contracts'
  if (route.path.startsWith('/manager')) return '/manager/contracts'
  return '/sales/contracts'
})

const riskAssessmentPath = computed(() => `${backPath.value}/${id}/risk-assessment`)

const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

function normalizeDocumentUrl(url?: string): string {
  if (!url) return ''
  if (url.startsWith('blob:')) return url
  const baseDomain = apiBase.replace(/\/api$/, '')
  if (url.startsWith('/storage')) {
    return `${baseDomain}${url}`
  }
  if (url.startsWith('http://localhost/storage')) {
    return url.replace('http://localhost', baseDomain)
  }
  return url
}

function mapApiToContract(data: any): StoredContract {
  const user = authState.user
  const firstName = (user as any)?.profile?.first_name || user?.first_name
  const lastName = (user as any)?.profile?.last_name || user?.last_name
  const createdBy = (user && data.created_by === user.id)
    ? `${firstName || ''} ${lastName || ''}`.trim() || 'Me'
    : `User #${data.created_by}`

  return {
    id:              String(data.contract_code || data.contract_id),
    contractCode:    data.contract_code,
    contractDbId:    data.contract_db_id || (typeof data.contract_id === 'number' ? data.contract_id : undefined),
    businessPartner: data.bp_name ?? '',
    category:        data.category ?? '',
    itemCode:        data.item_code ?? '',
    description:     data.description ?? '',
    serialNo:        data.serial_number ?? '',
    sbuNumber:       data.sbu_number ?? '',
    region:          (data.region ?? '') as ContractRegion,
    startDate:       data.start_date ?? '',
    endDate:         data.end_date ?? '',
    approvalStatus:  (data.approval_status ?? 'Pending') as ContractApprovalStatus,
    workflowStatus:  (data.workflow_status ?? null) as ContractWorkflowStatus | null,
    lifecycleStatus: data.lifecycle_status,
    executionRequired: data.execution_required,
    hasPendingApprovalWorkflow: data.has_pending_approval_workflow,
    rejectionReason: data.rejection_reason ?? undefined,
    contractLink:    '',
    createdBy,
    docs: (data.documents ?? []).map((d: any): UploadedDoc => ({
      id: d.document_id || d._id,
      name: d.file_name,
      type: d.file_type as 'pdf' | 'docx',
      size: d.file_size ?? 0,
      previewUrl: normalizeDocumentUrl(d.document_url),
      uploadStatus: 'success',
      category: d.doc_category,
      isCurrentDraft: d.is_current_draft,
      isPrimary: d.is_primary,
    })),
  }
}

function loadLocalContract() {
  const c = (cacheState.contracts || []).find(item => 
    item.id === id || 
    item.contractCode === id || 
    (item.contractDbId !== undefined && String(item.contractDbId) === String(id))
  )
  if (c) {
    contract.value = c as StoredContract
  }
}

async function loadContract(force = false) {
  loadLocalContract()
  if (contract.value) {
    loadingContract.value = false
  } else {
    loadingContract.value = true
  }

  try {
    const isAdmin = route.path.startsWith('/admin')
    const isManager = route.path.startsWith('/manager')
    const userId = (isAdmin || isManager) ? undefined : authState.user?.id
    await fetchContracts(userId)
    loadLocalContract()

    // If contract not found or docs are empty, fetch from direct backend endpoint with documents relation
    if (force || !contract.value || (contract.value.docs && contract.value.docs.length === 0)) {
      const res = await fetch(`${apiBase}/contracts/${id}`, {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${authState.token}`,
        }
      })
      if (res.ok) {
        const json = await res.json()
        if (json.data) {
          contract.value = mapApiToContract(json.data)
          if (force) updateContractInCache(contract.value.id, contract.value)
        }
      }
    }
  } catch {
    if (!contract.value) {
      contract.value = null
    }
  } finally {
    loadingContract.value = false
  }
}

async function refreshExecution() {
  invalidateRequests()
  await Promise.all([loadContract(true), fetchWorkflowProgress(id)])
}

const showRejectionModal = ref(false)

// AI Risk Assessment is advisory only: gateState.blocked is always false
// while the gate feature flag is off (HIGH_RISK_APPROVAL_GATE_ENABLED=false
// on contract-management), so this never prevents approval — it only
// carries risk_level for the flag/warning badge below. That badge is itself
// hidden entirely if the current user has turned off "AI Risk Assessment"
// in their own profile preferences.
const { gateState, loading: loadingHighRiskGate, saving: highRiskSaving, fetchGateState, recordDecision } = useHighRiskApproval()
const { preferences: aiPreferences, fetchPreferences: fetchAiPreferences } = useEmailPreferences()
const showHighRiskModal = ref(false)

const aiRiskAssessmentVisible = computed(() => aiPreferences.value.aiRiskAssessmentEnabled ?? true)

onMounted(async () => {
  await loadContract()
  if (!route.query.workflowMock) {
    await fetchWorkflowProgress(id)
  }
  await fetchAiPreferences()
  if (contract.value) {
    await amendmentStore.fetchVersionHistory(contract.value.id, true)
    if (aiRiskAssessmentVisible.value) {
      await fetchGateState(contract.value.id)
    }
  }
  if (route.query.snapshotVersion && contract.value) {
    const snaps = amendmentStore.versionHistory.value[contract.value.id] || []
    const verNum = parseInt(route.query.snapshotVersion as string, 10)
    const found = snaps.find(s => s.version === verNum)
    if (found) {
      viewingSnapshotVersion.value = verNum
      viewingSnapshotDate.value = found.approvedDate
    }
  }
  if (contract.value && contract.value.approvalStatus === 'Rejected') {
    showRejectionModal.value = true
  }
  if (route.query.edit === '1' && contract.value) {
    startEdit()
  }
})

// ── Manager Actions ──────────────────────────────────────────────────────────

const showRejectInput  = ref(false)
const rejectReason     = ref('')
const actionInProgress = ref(false)

const showConfirm = ref(false)
const confirmTitle = ref('')
const confirmDesc = ref('')
const confirmAction = ref<(() => void) | null>(null)

function triggerApprove() {
  // US-023's mandatory high-risk approval gate is DISABLED for now — AI Risk
  // Assessment is advisory only (a flag/warning on the contract), and never
  // blocks the normal Approve flow. gateState.blocked is always false while
  // the backend feature flag (HIGH_RISK_APPROVAL_GATE_ENABLED) is off, so
  // this check is inert, but left in place (commented-out behavior below)
  // so the gate can be re-enabled later without rewriting this flow.
  //
  // if (gateState.value?.blocked) {
  //   showHighRiskModal.value = true
  //   return
  // }
  confirmTitle.value = 'Approve Contract'
  confirmDesc.value = 'Are you sure you want to approve this contract? This will change the status to Approved.'
  confirmAction.value = handleApprove
  showConfirm.value = true
}

async function handleHighRiskDecision(decision: 'approved' | 'rejected', rationale: string) {
  if (!contract.value) return
  const result = await recordDecision(contract.value.id, decision, rationale)
  if (!result.ok) {
    error('Failed to record decision', result.message ?? 'Something went wrong.')
    return
  }
  showHighRiskModal.value = false
  if (decision === 'approved') {
    success('High-risk approval recorded', 'You can now approve the contract.')
    // Proceed straight to the normal approve confirmation now that the gate is clear.
    confirmTitle.value = 'Approve Contract'
    confirmDesc.value = 'Are you sure you want to approve this contract? This will change the status to Approved.'
    confirmAction.value = handleApprove
    showConfirm.value = true
  } else {
    success('Decision recorded', 'The high-risk approval was recorded as rejected.')
  }
}

function triggerReject() {
  if (!rejectReason.value.trim()) return
  confirmTitle.value = 'Reject Contract'
  confirmDesc.value = 'Are you sure you want to reject this contract? This will mark it as Rejected.'
  confirmAction.value = confirmReject
  showConfirm.value = true
}

async function handleApprove() {
  if (!contract.value || actionInProgress.value) return
  showConfirm.value = false
  actionInProgress.value = true
  try {
    const res = await fetch(`${apiBase}/contracts/${id}/status`, {
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
      // Inert while HIGH_RISK_APPROVAL_GATE_ENABLED is off on the backend
      // (the backend never returns requires_high_risk_approval in that
      // state). Left in place so the gate can be re-enabled later without
      // rewriting this handler.
      if (res.status === 422 && data.requires_high_risk_approval) {
        await fetchGateState(contract.value.id)
        showHighRiskModal.value = true
        return
      }
      error('Failed to approve', data.message ?? 'Something went wrong.')
      return
    }

    updateContractInCache(id, mapApiToContract(data.data))
    success('Contract approved', `${contract.value.businessPartner}'s contract has been approved.`)
    loadLocalContract()
  } catch {
    error('Network error', 'Could not reach the server. Please try again.')
  } finally {
    actionInProgress.value = false
  }
}

function handleToggleReject() {
  showRejectInput.value = !showRejectInput.value
  if (!showRejectInput.value) rejectReason.value = ''
}

async function confirmReject() {
  if (!contract.value || !rejectReason.value.trim() || actionInProgress.value) return
  showConfirm.value = false
  actionInProgress.value = true
  try {
    const res = await fetch(`${apiBase}/contracts/${id}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
      body: JSON.stringify({ approval_status: 'Rejected', rejection_reason: rejectReason.value.trim() }),
    })
    const data = await res.json()
    if (!res.ok) { error('Failed to reject', data.message ?? 'Something went wrong.'); return }

    updateContractInCache(id, mapApiToContract(data.data))
    success('Contract rejected', `${contract.value.businessPartner}'s contract has been rejected.`)
    showRejectInput.value = false
    rejectReason.value = ''
    loadLocalContract()
  } catch {
    error('Network error', 'Could not reach the server. Please try again.')
  } finally {
    actionInProgress.value = false
  }
}

// ── Inline edit state ────────────────────────────────────────────────────────

const isEditing = ref(false)

const editForm = reactive({
  businessPartner: '',
  category:        '',
  itemCode:        '',
  description:     '',
  serialNo:        '',
  sbuNumber:       '',
  region:          '' as ContractRegion | '',
  startDate:       '',
  endDate:         '',
  workflowStatus:  '' as ContractWorkflowStatus | '',
})

const touched = reactive<Record<string, boolean>>({
  businessPartner: false,
  category:        false,
  itemCode:        false,
  description:     false,
  serialNo:        false,
  sbuNumber:       false,
  region:          false,
  startDate:       false,
  endDate:         false,
})

const dateError = computed(() =>
  touched.startDate && touched.endDate && editForm.startDate && editForm.endDate
    ? editForm.endDate <= editForm.startDate ? 'End date must be after start date.' : ''
    : ''
)

const isFormValid = computed(() =>
  !!editForm.businessPartner &&
  !!editForm.category &&
  !!editForm.itemCode &&
  !!editForm.description &&
  !!editForm.serialNo &&
  !!editForm.sbuNumber &&
  !!editForm.region &&
  !!editForm.startDate &&
  !!editForm.endDate &&
  !dateError.value
)

const contractDocs = ref<UploadedDoc[]>([])
const isUploadingOrScanFailed = computed(() => {
  return contractDocs.value.some(d => d.uploadStatus === 'uploading' || d.uploadStatus === 'scanning' || d.uploadStatus === 'error')
})

function startEdit() {
  if (!contract.value) return
  Object.assign(editForm, {
    businessPartner: contract.value.businessPartner,
    category:        contract.value.category,
    itemCode:        contract.value.itemCode,
    description:     contract.value.description,
    serialNo:        contract.value.serialNo,
    sbuNumber:       contract.value.sbuNumber ?? '',
    region:          contract.value.region,
    startDate:       contract.value.startDate,
    endDate:         contract.value.endDate,
    workflowStatus:  contract.value.workflowStatus ?? '',
  })
  contractDocs.value = [...contract.value.docs]
  Object.keys(touched).forEach(k => (touched[k] = false))
  isEditing.value = true
}

function cancelEdit() {
  isEditing.value = false
}

function triggerSaveEdit() {
  Object.keys(touched).forEach(k => (touched[k] = true))
  if (!isFormValid.value) return
  confirmTitle.value = 'Save Contract Changes'
  confirmDesc.value = 'Are you sure you want to apply these changes to the contract? This action will update the contract details in the system.'
  confirmAction.value = confirmSaveEdit
  showConfirm.value = true
}

async function confirmSaveEdit() {
  showConfirm.value = false
  savingEdit.value = true
  try {
    const payload: Record<string, unknown> = {
      bp_name:       editForm.businessPartner,
      category:      editForm.category,
      item_code:     editForm.itemCode,
      description:   editForm.description,
      serial_number: editForm.serialNo,
      sbu_number:    editForm.sbuNumber,
      region:        editForm.region,
      start_date:    editForm.startDate,
      end_date:      editForm.endDate,
      document_ids:  contractDocs.value.filter(d => d.id).map(d => d.id),
    }
    if (!contract.value?.executionRequired && (isManager.value || isOwner.value)) {
      payload.workflow_status = editForm.workflowStatus || null
    }

    const res = await fetch(`${apiBase}/contracts/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
      body: JSON.stringify(payload),
    })

    const data = await res.json()

    if (!res.ok) {
      error('Failed to save', data.message ?? 'Something went wrong.')
      return
    }

    contract.value = mapApiToContract(data.data)
    updateContractInCache(id, contract.value)
    isEditing.value = false
    success('Contract updated', 'Changes have been saved.')
  } catch {
    error('Network error', 'Could not reach the server. Please try again.')
  } finally {
    savingEdit.value = false
  }
}

async function handleNotifyStep() {
  try {
    const endpoint = isEngineRouted.value ? 'notify-step' : 'notify-manager'
    const res = await fetch(`${apiBase}/contracts/${id}/${endpoint}`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
    })
    const data = await res.json()
    if (!res.ok) {
      if (res.status === 429) {
        error('Action limited', data.message ?? 'Already notified, please wait.')
      } else {
        error('Failed to notify', data.message ?? 'Something went wrong.')
      }
      return
    }
    const targetDesc = data.targetRoles ?? 'Manager'
    success('Notified', data.message ?? `${targetDesc} has been notified.`)
  } catch {
    error('Network error', 'Could not reach the server.')
  }
}

// ── Version History & Amendments ─────────────────────────────────────────────
const showHistoryDrawer = ref(false)
const expandedVersion = ref<number | null>(null)

function selectSnapshot(version: number, date: string) {
  viewingSnapshotVersion.value = version
  viewingSnapshotDate.value = date
  showHistoryDrawer.value = false
  router.replace({ query: { ...route.query, snapshotVersion: String(version) } })
}

function clearSnapshotMode() {
  viewingSnapshotVersion.value = null
  viewingSnapshotDate.value = ''
  router.replace({ query: { ...route.query, snapshotVersion: undefined } })
}

const historicalSnapshots = computed(() => {
  if (!contract.value) return []
  const snaps = amendmentStore.versionHistory.value[contract.value.id] || []
  return [...snaps].sort((a, b) => b.version - a.version)
})

const activeVersion = computed(() => {
  if (!contract.value) return 1
  return amendmentStore.getContractActiveVersion(contract.value.id)
})

function toggleVersionExpand(ver: number) {
  if (expandedVersion.value === ver) {
    expandedVersion.value = null
  } else {
    expandedVersion.value = ver
  }
}

function handleEditClick() {
  if (contract.value?.approvalStatus === 'Approved') {
    let rolePrefix = '/sales'
    if (route.path.startsWith('/admin')) rolePrefix = '/admin'
    else if (route.path.startsWith('/manager')) rolePrefix = '/manager'
    router.push(`${rolePrefix}/contracts/${id}/amend`)
  } else {
    startEdit()
  }
}

function getDiffList(snap: any) {
  const diffs: { field: string; old: string; new: string }[] = []
  if (!contract.value) return diffs

  const allSnaps = amendmentStore.versionHistory.value[contract.value.id] || []
  const sortedSnaps = [...allSnaps].sort((a, b) => a.version - b.version)
  
  let prevState: any = null
  
  if (snap.version === activeVersion.value) {
    // Diffing current active contract against the latest snapshot in history
    if (sortedSnaps.length > 0) {
      prevState = sortedSnaps[sortedSnaps.length - 1]
    }
  } else {
    // Historical snapshot diffing
    const currentIndex = sortedSnaps.findIndex(s => s.version === snap.version)
    if (currentIndex > 0) {
      prevState = sortedSnaps[currentIndex - 1]
    }
  }
  
  if (!prevState) {
    return diffs
  }

  const fields = [
    { key: 'businessPartner', label: 'Business Partner' },
    { key: 'category', label: 'Category' },
    { key: 'itemCode', label: 'Item Code' },
    { key: 'description', label: 'Description' },
    { key: 'serialNo', label: 'Serial No' },
    { key: 'sbuNumber', label: 'SBU Number' },
    { key: 'region', label: 'Region' },
    { key: 'startDate', label: 'Start Date' },
    { key: 'endDate', label: 'End Date' }
  ]

  for (const f of fields) {
    const oldVal = String(prevState[f.key] || '').trim()
    const newVal = String(snap[f.key] || '').trim()
    if (oldVal !== newVal) {
      diffs.push({
        field: f.label,
        old: oldVal || '(empty)',
        new: newVal || '(empty)'
      })
    }
  }

  return diffs
}

const activeSnapForDiff = computed(() => {
  if (!contract.value) return null
  return {
    version: activeVersion.value,
    businessPartner: contract.value.businessPartner,
    category: contract.value.category,
    itemCode: contract.value.itemCode,
    description: contract.value.description,
    serialNo: contract.value.serialNo,
    sbuNumber: contract.value.sbuNumber || '',
    region: contract.value.region,
    startDate: contract.value.startDate,
    endDate: contract.value.endDate,
    reason: 'Active details shown on main contract page.'
  }
})
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Loading state -->
    <div v-if="loadingContract" class="space-y-6">
      <!-- Header Skeleton -->
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-black/5 animate-pulse rounded-lg shrink-0"></div>
          <div>
            <div class="flex items-center gap-3 mb-1">
              <div class="w-9 h-9 bg-black/5 animate-pulse rounded-lg"></div>
              <div class="h-6 w-64 bg-black/5 animate-pulse rounded"></div>
            </div>
            <div class="h-4 w-40 bg-black/5 animate-pulse rounded mt-2"></div>
            <div class="flex gap-2 mt-3">
              <div class="h-6 w-16 bg-black/5 animate-pulse rounded-full"></div>
              <div class="h-6 w-24 bg-black/5 animate-pulse rounded-full"></div>
            </div>
          </div>
        </div>
        <div class="h-10 w-32 bg-black/5 animate-pulse rounded-lg"></div>
      </div>
      
      <!-- Contract Info Skeleton -->
      <div class="bg-white rounded-xl border border-black/[0.08] shadow-sm p-8 space-y-5">
        <div class="h-3 w-32 bg-black/5 animate-pulse rounded"></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-for="i in 3" :key="i" class="space-y-2">
            <div class="h-3 w-20 bg-black/5 animate-pulse rounded"></div>
            <div class="h-4 w-40 bg-black/5 animate-pulse rounded"></div>
          </div>
        </div>
      </div>
      
      <!-- Item Details Skeleton -->
      <div class="bg-white rounded-xl border border-black/[0.08] shadow-sm p-8 space-y-5">
        <div class="h-3 w-32 bg-black/5 animate-pulse rounded"></div>
        <div class="h-10 w-full bg-black/5 animate-pulse rounded"></div>
      </div>

      <!-- Schedule & Location Skeleton -->
      <div class="bg-white rounded-xl border border-black/[0.08] shadow-sm p-8 space-y-5">
        <div class="h-3 w-32 bg-black/5 animate-pulse rounded"></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-for="i in 3" :key="i" class="space-y-2">
            <div class="h-3 w-20 bg-black/5 animate-pulse rounded"></div>
            <div class="h-4 w-40 bg-black/5 animate-pulse rounded"></div>
          </div>
        </div>
      </div>
      
      <!-- Documents Section Skeleton -->
      <div class="bg-white rounded-xl border border-black/[0.08] shadow-sm p-8 space-y-5">
        <div class="h-3 w-32 bg-black/5 animate-pulse rounded"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="i in 2" :key="i" class="h-20 w-full bg-black/5 animate-pulse rounded-lg"></div>
        </div>
      </div>
    </div>

    <!-- Not-found state -->
    <div v-else-if="!contract"
      class="flex flex-col items-center gap-3 py-24 text-black/30">
      <FileX class="w-12 h-12" />
      <p class="text-base font-semibold">Contract not found</p>
      <p class="text-sm text-black/25">The contract may have been deleted or the ID is invalid.</p>
      <Button variant="outline" @click="router.push(backPath)"
        class="mt-2 h-9 px-5 text-sm border-black/15 text-black/60 hover:text-black">
        Back to Contracts
      </Button>
    </div>

    <template v-else>
      <!-- Historical Snapshot Warning Banner -->
      <div v-if="viewingSnapshotVersion !== null" 
        class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm font-poppins animate-in fade-in slide-in-from-top-4 duration-300">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-amber-100/80 rounded-lg text-amber-800 flex items-center justify-center shrink-0">
            <Clock class="w-5 h-5 text-amber-600" />
          </div>
          <div>
            <h4 class="text-sm font-bold text-amber-900">Viewing Historical Snapshot (Version {{ viewingSnapshotVersion }})</h4>
            <p class="text-xs text-amber-700/80 mt-0.5">
              You are viewing a read-only archived version of this contract approved on {{ viewingSnapshotDate }}.
            </p>
          </div>
        </div>
        <button 
          @click="clearSnapshotMode"
          class="px-4 py-2 bg-white hover:bg-amber-100/30 border border-amber-200 text-amber-900 font-semibold text-xs rounded-lg shadow-sm transition-all duration-200 shrink-0 self-start sm:self-center"
        >
          Return to Current (Version {{ activeVersion }})
        </button>
      </div>

      <ContractDetailHeader
        v-if="displayedContract"
        :contract="displayedContract"
        :days="days"
        :is-editing="isEditing"
        :saving="savingEdit"
        :action-in-progress="actionInProgress"
        :is-manager="isManager"
        :show-reject-input="showRejectInput"
        :reject-reason-valid="rejectReason.trim().length > 0"
        :disabled="isUploadingOrScanFailed"
        :is-snapshot="viewingSnapshotVersion !== null"
        :risk-level="aiRiskAssessmentVisible ? (gateState?.riskLevel ?? null) : null"
        :is-risk-loading="aiRiskAssessmentVisible && loadingHighRiskGate"
        :approval-locked="false"
        :nudge-label="nudgeLabel"
        :is-engine-routed="isEngineRouted"
        @back="router.push(backPath)"
        @edit="handleEditClick"
        @open-history="showHistoryDrawer = true"
        @save="triggerSaveEdit"
        @cancel="cancelEdit"
        @notify-step="handleNotifyStep"
        @approve="triggerApprove"
        @toggle-reject="handleToggleReject"
        @confirm-reject="triggerReject"
        @open-risk-assessment="router.push(riskAssessmentPath)"
        @open-workflow-tracker="showWorkflowTracker = true"
      />

      <!-- Rejection input (manager rejecting) -->
      <div v-if="showRejectInput" class="bg-white rounded-lg border border-black/8 shadow-sm p-6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Rejection Reason</h2>
        <label class="text-xs font-semibold text-red-600/80 block mb-1.5">
          Reason <span class="text-red-500">*</span>
        </label>
        <textarea v-model="rejectReason" rows="3" placeholder="Provide a reason for rejection..."
          class="w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm placeholder:text-black/25 focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-200/15 transition resize-none" />
      </div>

      <!-- Rejection reason display (already rejected) -->
      <div v-else-if="contract.approvalStatus === 'Rejected'"
        class="bg-white rounded-lg border border-red-100 shadow-sm p-6 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <AlertCircle class="w-5 h-5 text-red-500 shrink-0" />
          <div>
            <span class="text-sm font-semibold text-red-600 block">Contract Rejected</span>
            <span class="text-xs text-red-500/80 mt-0.5 block">This contract was rejected by the manager.</span>
          </div>
        </div>
        <Button variant="outline" @click="showRejectionModal = true" class="h-9 text-xs border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700">
          View Rejection Reason
        </Button>
      </div>

      <InternalApprovalActions v-if="!viewingSnapshotVersion && !isEditing && myTasks.length" :tasks="myTasks"
        :save="(task, outcome, reason) => decide(id, task, outcome, reason)" @changed="refreshExecution" />

      <ContractInfoSection
        v-if="displayedContract"
        :contract="displayedContract"
        :is-editing="isEditing"
        :edit-form="editForm"
        :touched="touched"
        :date-error="dateError"
        :is-manager="isManager"
        :is-owner="isOwner"
        :is-approved="contract.approvalStatus === 'Approved'"
      />
      <ContractDocumentsSection
        v-if="displayedContract"
        :docs="isEditing ? contractDocs : displayedContract.docs"
        :is-editing="isEditing"
        :snapshot-version="viewingSnapshotVersion"
        @update:docs="contractDocs = $event"
      />
      <ContractExecutionSection v-if="!viewingSnapshotVersion && !isEditing" :key="`${id}-${contract.approvalStatus}`"
        :contract-id="id" @changed="refreshExecution" />
    </template>

    <ConfirmationDialog
      v-model:open="showConfirm"
      :title="confirmTitle"
      :description="confirmDesc"
      confirm-label="Confirm"
      variant="default"
      :loading="actionInProgress || savingEdit"
      @confirm="confirmAction ? confirmAction() : undefined"
    />

    <WorkflowTrackerModal
      v-model:open="showWorkflowTracker"
      :workflow="workflowTracker"
      :status="workflowTrackerStatus"
      :on-retry="handleWorkflowRetry"
    />

    <RejectionReasonModal
      v-if="contract"
      v-model:open="showRejectionModal"
      :reason="contract.rejectionReason || ''"
    />

    <HighRiskApprovalModal
      v-model:open="showHighRiskModal"
      :risk-level="gateState?.riskLevel ?? null"
      :saving="highRiskSaving"
      @submit="handleHighRiskDecision"
    />

    <VersionHistoryDrawer
      :key="String(showHistoryDrawer)"
      :open="showHistoryDrawer"
      :snapshots="historicalSnapshots"
      :active-version="activeVersion"
      :active-snap-for-diff="activeSnapForDiff"
      :expanded-version="expandedVersion"
      :get-diff-list="getDiffList"
      @close="showHistoryDrawer = false"
      @toggle="toggleVersionExpand"
      @view-snapshot="(ver, date) => selectSnapshot(ver, date)"
    />
  </div>
</template>
