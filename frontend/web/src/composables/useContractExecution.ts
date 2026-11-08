import { ref } from 'vue'
import { useAuth } from '@/composables/useAuth'
import type { ContractWorkflowStatus } from '@/types/contract'

export type ExecutionAction = 'submit' | 'confirm' | 'return' | 'pause' | 'resume' | 'revise'

export interface ExecutionEvent {
  id: number
  action: ExecutionAction
  stage: ContractWorkflowStatus
  state: 'recorded' | 'pending' | 'confirmed' | 'returned' | 'superseded'
  document_id: string | null
  draft_document_id: string | null
  created_at: string
  reason: string | null
}

export interface ExecutionState {
  enabled: boolean
  stage: ContractWorkflowStatus | null
  paused: boolean
  awaiting_confirmation: boolean
  draft_document_id: string | null
  pending: ExecutionEvent | null
  history: ExecutionEvent[]
  allowed: Record<'submit' | 'confirm' | 'pause' | 'resume' | 'revise' | 'auto_confirm', boolean>
}

export function useContractExecution(id: string) {
  const { state: auth } = useAuth()
  const state = ref<ExecutionState | null>(null)
  const loading = ref(true)
  const loadError = ref('')
  const base = import.meta.env.VITE_CONTRACT_API_URL
  const url = `${base}/contracts/${encodeURIComponent(id)}/execution`
  const headers = () => ({ Accept: 'application/json', Authorization: `Bearer ${auth.token}` })

  async function load() {
    loading.value = true
    loadError.value = ''
    try {
      const response = await fetch(url, { headers: headers() })
      if (!response.ok) throw new Error('Execution workflow could not be loaded.')
      state.value = (await response.json()).data
    } catch {
      loadError.value = 'Execution workflow could not be loaded.'
    } finally {
      loading.value = false
    }
  }

  async function submit(body: FormData) {
    const response = await fetch(url, { method: 'POST', body, headers: headers() })
    const json = await response.json()
    if (!response.ok) {
      const validation = json.errors as Record<string, string[]> | undefined
      throw new Error(Object.values(validation ?? {}).flat()[0] ?? json.message ?? 'The action could not be saved.')
    }
    return json.data as ExecutionEvent
  }

  async function loadDraft(documentId: string) {
    const response = await fetch(`${base}/documents/${encodeURIComponent(documentId)}/file`, { headers: headers() })
    if (!response.ok) throw new Error('The approved draft could not be opened.')
    return response.arrayBuffer()
  }

  return { state, loading, loadError, load, submit, loadDraft }
}
