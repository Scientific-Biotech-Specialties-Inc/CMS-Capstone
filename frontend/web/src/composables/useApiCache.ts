import { reactive, watch } from 'vue'
import { useAuth } from './useAuth'
import type { Contract, ContractRegion, ContractApprovalStatus, ContractWorkflowStatus, SupportingExpenseSummary } from '@/types/contract'
import type { ContractRequest, RequestStatus } from '@/types/contractRequest'

interface CacheState {
  contracts: Contract[] | null
  contractsScope: string | null // 'all' or 'user-<id>'
  contractsLoading: boolean
  contractsPagination: {
    total: number
    per_page: number
    current_page: number
    last_page: number
  } | null
  contractsStats: {
    total: number
    active: number
    inactive: number
    expiring: number
    expired: number
  } | null

  requests: ContractRequest[] | null
  requestsScope: string | null // 'all' or 'user-<id>'
  requestsLoading: boolean

  cachedToken: string | null
  cachedUserId: number | null
}

const state = reactive<CacheState>({
  contracts: null,
  contractsScope: null,
  contractsLoading: false,
  contractsPagination: null,
  contractsStats: null,

  requests: null,
  requestsScope: null,
  requestsLoading: false,

  cachedToken: null,
  cachedUserId: null,
})

const STALE_MS = 10_000
let contractsFetchedAt = 0
let requestsFetchedAt = 0
let dashboardFetchedAt = 0
let inFlightContracts: Promise<Contract[]> | null = null
let inFlightRequests: Promise<ContractRequest[]> | null = null
let inFlightDashboard: Promise<void> | null = null

function resetCacheEntries(clearAuth = false) {
  state.contracts = null
  state.contractsScope = null
  state.contractsLoading = false
  state.contractsPagination = null
  state.contractsStats = null

  state.requests = null
  state.requestsScope = null
  state.requestsLoading = false

  contractsFetchedAt = 0
  requestsFetchedAt = 0
  dashboardFetchedAt = 0
  inFlightContracts = null
  inFlightRequests = null
  inFlightDashboard = null

  if (clearAuth) {
    state.cachedToken = null
    state.cachedUserId = null
  }
}

// Reactively watch for auth state changes to clear cache instantly
watch(
  () => {
    const { state: authState } = useAuth()
    return [authState.token, authState.user?.id || null] as const
  },
  ([currentToken, currentUserId]: readonly [string | null, number | null]) => {
    if (state.cachedToken !== currentToken || state.cachedUserId !== currentUserId) {
      resetCacheEntries()
      state.cachedToken = currentToken
      state.cachedUserId = currentUserId
    }
  },
  { immediate: true }
)

function validateCacheCredentials() {
  const { state: authState } = useAuth()
  const currentToken = authState.token
  const currentUserId = authState.user?.id || null

  // If credentials changed, clear cache completely to avoid data exposure
  if (state.cachedToken !== currentToken || state.cachedUserId !== currentUserId) {
    resetCacheEntries()
    state.cachedToken = currentToken
    state.cachedUserId = currentUserId
  }
}

function normalizeDocumentUrl(url?: string): string {
  if (!url) return ''
  if (url.startsWith('blob:')) return url
  const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string
  const baseDomain = apiBase.replace(/\/api$/, '')
  if (url.startsWith('/storage')) {
    return `${baseDomain}${url}`
  }
  if (url.startsWith('http://localhost/storage')) {
    return url.replace('http://localhost', baseDomain)
  }
  return url
}

function sortContractsWithRejectedAtBottom(contracts: Contract[]): Contract[] {
  const rank = (status: Contract['approvalStatus']) =>
    status === 'Draft' ? 0 : status === 'Rejected' ? 2 : 1
  return [...contracts].sort((a, b) => rank(a.approvalStatus) - rank(b.approvalStatus))
}

export function resolveCreatorName(d: any, currentUserId: number | null, firstName?: string, lastName?: string): string {
  const isCreatedByCurrentUser = currentUserId !== null && d.created_by === currentUserId
  if (isCreatedByCurrentUser) {
    return `${firstName || ''} ${lastName || ''}`.trim() || 'Me'
  }
  if (d.creator_name) return d.creator_name
  if (d.source_system === 'SERMS') {
    return d.supporting_expense_summary?.forwarded_by_name || 'SERMS Accounting'
  }
  if (d.source_system === 'PRS') {
    return 'PRS Employee'
  }
  return d.created_by ? `User #${d.created_by}` : '—'
}

function mapApiContract(d: any, currentUserId: number | null, firstName?: string, lastName?: string): Contract {
  const createdBy = resolveCreatorName(d, currentUserId, firstName, lastName)

  const contractCode = d.contract_code || d.contract_id
  const contractId = contractCode ? String(contractCode) : String(d.contract_id || '')

  return {
    id: contractId,
    contractCode: d.contract_code,
    contractDbId: d.contract_db_id ? Number(d.contract_db_id) : (typeof d.contract_id === 'number' ? d.contract_id : undefined),
    businessPartner: d.bp_name ?? '',
    category: d.category ?? '',
    itemCode: d.item_code ?? '',
    description: d.description ?? '',
    serialNo: d.serial_number ?? '',
    sbuNumber: d.sbu_number ?? '',
    sbsiBranch: d.sbsi_branch ?? '',
    equipmentName: d.equipment_name ?? '',
    brand: d.brand ?? '',
    model: d.model ?? '',
    equipmentItems: Array.isArray(d.equipment_items) && d.equipment_items.length > 0
      ? d.equipment_items.map((item: any) => ({
          equipmentName: item?.equipment_name ?? item?.equipmentName ?? '',
          brand: item?.brand ?? '',
          model: item?.model ?? '',
        }))
      : (d.equipment_name || d.brand || d.model
          ? [{ equipmentName: d.equipment_name ?? '', brand: d.brand ?? '', model: d.model ?? '' }]
          : []),
    region: (d.region ?? 'Luzon') as ContractRegion,
    startDate: d.start_date ?? '',
    endDate: d.end_date ?? '',
    createdAt: d.created_at ?? null,
    approvalStatus: (d.approval_status ?? 'Pending') as ContractApprovalStatus,
    workflowStatus: (d.workflow_status ?? null) as ContractWorkflowStatus | null,
    lifecycleStatus: d.lifecycle_status,
    executionRequired: d.execution_required,
    hasPendingApprovalWorkflow: d.has_pending_approval_workflow,
    contractLink: '',
    createdBy,
    rejectionReason: d.rejection_reason ?? undefined,
    docs: (d.documents ?? []).map((doc: any) => ({
      id: doc.document_id || doc._id,
      name: doc.file_name,
      type: doc.file_type as 'pdf' | 'docx',
      size: doc.file_size ?? 0,
      previewUrl: normalizeDocumentUrl(doc.document_url),
      uploadStatus: 'success',
      category: doc.doc_category,
      isCurrentDraft: doc.is_current_draft,
      isPrimary: doc.is_primary,
      signerName: doc.signer_name,
    })),
    prsActivityId: d.prs_activity_id ? Number(d.prs_activity_id) : undefined,
    sourceSystem: d.source_system ?? null,
    sourceReferenceId: d.source_reference_id ?? null,
    supportingExpenseSummary: mapExpenseSummary(d.supporting_expense_summary),
  }
}

function mapExpenseSummary(raw: any): SupportingExpenseSummary | null {
  if (!raw || typeof raw !== 'object') return null
  return {
    vendorName: String(raw.vendor_name ?? ''),
    tinNumber: raw.tin_number ? String(raw.tin_number) : null,
    occurrenceCount: Number(raw.occurrence_count ?? 0),
    totalSpend: Number(raw.total_spend ?? 0),
    periodStart: String(raw.period_start ?? ''),
    periodEnd: String(raw.period_end ?? ''),
    expenseItems: Array.isArray(raw.expense_items)
      ? raw.expense_items.map((item: any) => ({
          date: String(item.date ?? ''),
          amount: Number(item.amount ?? 0),
          reference: String(item.reference ?? ''),
        }))
      : [],
  }
}

function mapApiToRequest(d: any, currentUserId: number | null, firstName?: string, lastName?: string): ContractRequest {
  const createdBy = resolveCreatorName(d, currentUserId, firstName, lastName)

  const contractDbId = typeof d.contract_db_id === 'number'
    ? d.contract_db_id
    : (typeof d.contract_id === 'number' ? d.contract_id : (!isNaN(Number(d.contract_id)) ? Number(d.contract_id) : undefined))
  const rawContractId = d.contract_code ?? d.contract_db_id ?? d.contract_id

  return {
    id: `REQ-${String(d.contract_id).padStart(3, '0')}`,
    contractId: rawContractId ? String(rawContractId) : undefined,
    contractDbId,
    hasPendingApprovalWorkflow: d.has_pending_approval_workflow,
    businessPartner: d.bp_name ?? '',
    category: d.category ?? '',
    description: d.description ?? '',
    region: (d.region ?? 'Luzon') as ContractRequest['region'],
    requestDate: d.created_at ?? '',
    startDate: d.start_date ?? '',
    endDate: d.end_date ?? '',
    status: d.approval_status === 'Approved' ? 'Approved'
      : d.approval_status === 'Rejected' ? 'Rejected'
        : d.workflow_status ? 'Under Review'
          : 'Pending',
    notes: '',
    rejectionReason: d.rejection_reason ?? '',
    contractLink: '',
    createdBy,
    docs: (d.documents ?? []).map((doc: any) => ({
      id: doc.document_id || doc._id,
      name: doc.file_name,
      type: doc.file_type as 'pdf' | 'docx',
      size: doc.file_size ?? 0,
      previewUrl: normalizeDocumentUrl(doc.document_url),
      uploadStatus: 'success',
    })),
    itemCode: d.item_code ?? '',
    serialNo: d.serial_number ?? '',
    sbuNumber: d.sbu_number ?? '',
    sbsiBranch: d.sbsi_branch ?? '',
    equipmentName: d.equipment_name ?? '',
    brand: d.brand ?? '',
    model: d.model ?? '',
    equipmentItems: Array.isArray(d.equipment_items) && d.equipment_items.length > 0
      ? d.equipment_items.map((item: any) => ({
          equipmentName: item?.equipment_name ?? item?.equipmentName ?? '',
          brand: item?.brand ?? '',
          model: item?.model ?? '',
        }))
      : (d.equipment_name || d.brand || d.model
          ? [{ equipmentName: d.equipment_name ?? '', brand: d.brand ?? '', model: d.model ?? '' }]
          : []),
    prsActivityId: d.prs_activity_id ? Number(d.prs_activity_id) : undefined,
    sourceSystem: d.source_system ?? null,
    sourceReferenceId: d.source_reference_id ?? null,
    supportingExpenseSummary: mapExpenseSummary(d.supporting_expense_summary),
  }
}

async function fetchDashboard(force = false, silent = false): Promise<void> {
  validateCacheCredentials()

  const { role } = useAuth()
  const isManagerOrAdmin = ['Admin', 'Manager'].includes(role.value || '')
  const userId = state.cachedUserId
  const scope = isManagerOrAdmin ? 'all' : (userId ? `user-${userId}` : 'all')

  if (
    state.contracts !== null && state.contractsScope === scope &&
    state.requests !== null && state.requestsScope === scope &&
    !force
  ) {
    if (Date.now() - dashboardFetchedAt > STALE_MS) {
      void fetchDashboard(true, true).catch(() => {})
    }
    return
  }

  if (inFlightDashboard) return inFlightDashboard

  if (!silent) {
    state.contractsLoading = true
    state.requestsLoading = true
  }

  const { state: authState } = useAuth()
  const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

  inFlightDashboard = (async () => {
    try {
      const res = await fetch(`${apiBase}/dashboard`, {
        headers: {
          'Accept': 'application/json',
          ...(state.cachedToken ? { 'Authorization': `Bearer ${state.cachedToken}` } : {}),
        },
      })

      if (!res.ok) throw new Error('Failed to fetch dashboard data')

      const json = await res.json()
      const user = authState.user
      const firstName = (user as any)?.profile?.first_name || user?.first_name
      const lastName = (user as any)?.profile?.last_name || user?.last_name
      const data: any[] = json.data ?? []

      state.contracts = sortContractsWithRejectedAtBottom(data.map(d => mapApiContract(d, state.cachedUserId, firstName, lastName)))
      state.requests = data.map(d => mapApiToRequest(d, state.cachedUserId, firstName, lastName))
      state.contractsScope = scope
      state.requestsScope = scope
      const now = Date.now()
      dashboardFetchedAt = now
      contractsFetchedAt = now
      requestsFetchedAt = now
    } finally {
      if (!silent) {
        state.contractsLoading = false
        state.requestsLoading = false
      }
      inFlightDashboard = null
    }
  })()

  return inFlightDashboard
}

async function fetchContracts(
  userId?: number,
  force = false,
  queryParams?: {
    paginate?: boolean
    page?: number
    per_page?: number
    search?: string
    category?: string
    region?: string
    status?: string
    lifecycle_status?: string
    start_date?: string
    end_date?: string
  },
  silent = false
): Promise<Contract[]> {
  validateCacheCredentials()

  const scope = userId ? `user-${userId}` : 'all'
  const isQuerying = !!(queryParams && Object.keys(queryParams).length > 0)

  // If already cached and scope matches and not forced and not querying, return cached data (and revalidate silently when stale)
  if (state.contracts !== null && state.contractsScope === scope && !force && !isQuerying) {
    if (Date.now() - contractsFetchedAt > STALE_MS) {
      void fetchContracts(userId, true, undefined, true).catch(() => {})
    }
    return state.contracts as Contract[]
  }

  if (!isQuerying && inFlightContracts) {
    return inFlightContracts
  }

  if (!silent) {
    state.contractsLoading = true
  }
  const { state: authState } = useAuth()
  const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

  const run = (async (): Promise<Contract[]> => {
    try {
      let queryString = ''
      if (queryParams) {
        const parts = Object.entries(queryParams)
          .filter(([_, val]) => val !== undefined && val !== null && val !== '')
          .map(([key, val]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(val))}`)
        if (parts.length > 0) {
          queryString = parts.join('&')
        }
      }

      let url = userId
        ? `${apiBase}/contracts?created_by=${userId}`
        : `${apiBase}/contracts`

      if (queryString) {
        url += (url.includes('?') ? '&' : '?') + queryString
      }

      const res = await fetch(url, {
        headers: {
          'Accept': 'application/json',
          ...(state.cachedToken ? { 'Authorization': `Bearer ${state.cachedToken}` } : {}),
        },
      })

      if (!res.ok) {
        throw new Error('Failed to fetch contracts')
      }

      const json = await res.json()
      const user = authState.user
      const firstName = (user as any)?.profile?.first_name || user?.first_name
      const lastName = (user as any)?.profile?.last_name || user?.last_name

      state.contracts = sortContractsWithRejectedAtBottom(
        (json.data ?? []).map((d: any) =>
          mapApiContract(d, state.cachedUserId, firstName, lastName)
        )
      )
      state.contractsScope = scope
      if (!isQuerying) {
        contractsFetchedAt = Date.now()
      }

      if (json.pagination) {
        state.contractsPagination = json.pagination
      } else {
        state.contractsPagination = null
      }

      if (json.stats) {
        state.contractsStats = json.stats
      } else {
        state.contractsStats = null
      }
    } catch (err) {
      console.error('Error fetching contracts:', err)
      throw err
    } finally {
      if (!silent) {
        state.contractsLoading = false
      }
      if (!isQuerying) {
        inFlightContracts = null
      }
    }

    return (state.contracts || []) as Contract[]
  })()

  if (!isQuerying) {
    inFlightContracts = run
  }
  return run
}

async function fetchRequests(userId?: number, force = false, silent = false): Promise<ContractRequest[]> {
  validateCacheCredentials()

  const scope = userId ? `user-${userId}` : 'all'

  // If already cached and scope matches and not forced, return cached data (and revalidate silently when stale)
  if (state.requests !== null && state.requestsScope === scope && !force) {
    if (Date.now() - requestsFetchedAt > STALE_MS) {
      void fetchRequests(userId, true, true).catch(() => {})
    }
    return state.requests as ContractRequest[]
  }

  if (inFlightRequests) {
    return inFlightRequests
  }

  if (!silent) {
    state.requestsLoading = true
  }
  const { state: authState } = useAuth()
  const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

  inFlightRequests = (async (): Promise<ContractRequest[]> => {
    try {
      const url = userId
        ? `${apiBase}/contract-requests?created_by=${userId}`
        : `${apiBase}/contract-requests`

      const res = await fetch(url, {
        headers: {
          'Accept': 'application/json',
          ...(state.cachedToken ? { 'Authorization': `Bearer ${state.cachedToken}` } : {}),
        },
      })

      if (!res.ok) {
        throw new Error('Failed to fetch contract requests')
      }

      const json = await res.json()
      const user = authState.user
      const firstName = (user as any)?.profile?.first_name || user?.first_name
      const lastName = (user as any)?.profile?.last_name || user?.last_name
      state.requests = (json.data ?? []).map((d: any) =>
        mapApiToRequest(d, state.cachedUserId, firstName, lastName)
      )
      state.requestsScope = scope
      requestsFetchedAt = Date.now()
    } catch (err) {
      console.error('Error fetching requests:', err)
      throw err
    } finally {
      if (!silent) {
        state.requestsLoading = false
      }
      inFlightRequests = null
    }

    return (state.requests || []) as ContractRequest[]
  })()

  return inFlightRequests
}

function refreshCachedInBackground() {
  validateCacheCredentials()
  if (!state.cachedToken) return
  if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return

  if (state.contracts !== null) {
    const userId = state.contractsScope?.startsWith('user-') ? Number(state.contractsScope.slice(5)) : undefined
    void fetchContracts(userId, true, undefined, true).catch(() => {})
  }
  if (state.requests !== null) {
    const reqUserId = state.requestsScope?.startsWith('user-') ? Number(state.requestsScope.slice(5)) : undefined
    void fetchRequests(reqUserId, true, true).catch(() => {})
  }
}

if (typeof document !== 'undefined') {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && Date.now() - Math.max(contractsFetchedAt, requestsFetchedAt) > STALE_MS) {
      refreshCachedInBackground()
    }
  })
}

function invalidateContracts() {
  state.contracts = null
  state.contractsScope = null
}

function invalidateRequests() {
  state.requests = null
  state.requestsScope = null
}

function clearCache() {
  resetCacheEntries(true)
}

function updateContractInCache(id: string, patch: Partial<Contract>) {
  if (state.contracts) {
    const idx = state.contracts.findIndex((c: Contract) => c.id === id)
    if (idx !== -1) {
      state.contracts[idx] = { ...state.contracts[idx], ...patch }
    }
  }
}

function deleteContractFromCache(id: string) {
  if (state.contracts) {
    state.contracts = state.contracts.filter((c: Contract) => c.id !== id)
  }
}

function updateRequestStatusInCache(id: string, status: RequestStatus, patch: Partial<ContractRequest> = {}) {
  if (state.requests) {
    const idx = state.requests.findIndex((r: ContractRequest) => r.id === id)
    if (idx !== -1) {
      state.requests[idx] = { ...state.requests[idx], status, ...patch }
    }
  }
}

function updateRequestInCache(id: string, patch: Partial<ContractRequest>) {
  if (state.requests) {
    const idx = state.requests.findIndex((r: ContractRequest) => r.id === id)
    if (idx !== -1) {
      state.requests[idx] = { ...state.requests[idx], ...patch }
    }
  }
}

export function useApiCache() {
  validateCacheCredentials()

  return {
    state,
    fetchDashboard,
    fetchContracts,
    fetchRequests,
    refreshCachedInBackground,
    invalidateContracts,
    invalidateRequests,
    clearCache,
    updateContractInCache,
    deleteContractFromCache,
    updateRequestStatusInCache,
    updateRequestInCache,
  }
}
