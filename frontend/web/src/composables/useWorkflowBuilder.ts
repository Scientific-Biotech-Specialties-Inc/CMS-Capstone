import { ref } from 'vue'
import { useAuth } from './useAuth'
import type {
  AssignableRole,
  ContractTypeWithWorkflow,
  Workflow,
  WorkflowSavePayload,
} from '@/types/workflow'

/**
 * Admin-only Workflow Builder data layer (Phase 4). Talks to
 * contract-management's /workflows/* endpoints (role:Admin gated), which
 * in turn proxy role data from auth-module.
 */
export function useWorkflowBuilder() {
  const { state: authState } = useAuth()

  const loading = ref(false)
  const error = ref<string | null>(null)
  const contractTypes = ref<ContractTypeWithWorkflow[]>([])

  function authHeaders(): HeadersInit {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${authState.token || ''}`,
    }
  }

  function apiBase(): string {
    return import.meta.env.VITE_CONTRACT_API_URL as string
  }

  async function fetchContractTypesWithWorkflows(): Promise<ContractTypeWithWorkflow[]> {
    loading.value = true
    error.value = null
    try {
      const res = await fetch(`${apiBase()}/workflows/contract-types`, { headers: authHeaders() })
      if (!res.ok) throw new Error('Failed to load contract types.')
      const json = await res.json()
      contractTypes.value = json.data || []
      return contractTypes.value
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to load contract types.'
      return []
    } finally {
      loading.value = false
    }
  }

  async function fetchAssignableRoles(): Promise<AssignableRole[] | null> {
    try {
      const res = await fetch(`${apiBase()}/workflows/assignable-roles`, { headers: authHeaders() })
      if (!res.ok) throw new Error('Failed to load roles.')
      const json = await res.json()
      return json.data || []
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to load roles.'
      return null
    }
  }

  async function fetchWorkflow(id: number): Promise<Workflow | null> {
    try {
      const res = await fetch(`${apiBase()}/workflows/${id}`, { headers: authHeaders() })
      if (!res.ok) throw new Error('Failed to load workflow.')
      const json = await res.json()
      return json.data as Workflow
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to load workflow.'
      return null
    }
  }

  /**
   * Create or update a workflow. Throws with a readable message (built
   * from the backend's Laravel validation error bag) on failure, so the
   * editor can surface exactly which step/role is missing a permission
   * (decision #6) or which contract type already has an active workflow
   * (decision #9).
   */
  async function saveWorkflow(payload: WorkflowSavePayload, workflowId?: number): Promise<Workflow> {
    const url = workflowId ? `${apiBase()}/workflows/${workflowId}` : `${apiBase()}/workflows`
    const method = workflowId ? 'PUT' : 'POST'

    const res = await fetch(url, {
      method,
      headers: authHeaders(),
      body: JSON.stringify(payload),
    })

    const json = await res.json()

    if (!res.ok) {
      const messages = json.errors
        ? Object.values(json.errors as Record<string, string[]>).flat()
        : [json.message || 'Failed to save workflow.']
      throw new Error(messages.join(' '))
    }

    return json.data as Workflow
  }

  async function duplicateWorkflow(sourceWorkflowId: number, targetContractTypeId: number, name: string): Promise<Workflow> {
    const res = await fetch(`${apiBase()}/workflows/${sourceWorkflowId}/duplicate`, {
      method: 'POST',
      headers: authHeaders(),
      body: JSON.stringify({ target_contract_type_id: targetContractTypeId, name }),
    })

    const json = await res.json()
    if (!res.ok) {
      throw new Error(json.message || 'Failed to duplicate workflow.')
    }
    return json.data as Workflow
  }

  async function deleteWorkflow(workflowId: number): Promise<void> {
    const res = await fetch(`${apiBase()}/workflows/${workflowId}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })

    if (!res.ok) {
      const json = await res.json().catch(() => ({}))
      throw new Error(json.message || 'Failed to delete workflow.')
    }
  }

  return {
    loading,
    error,
    contractTypes,
    fetchContractTypesWithWorkflows,
    fetchAssignableRoles,
    fetchWorkflow,
    saveWorkflow,
    duplicateWorkflow,
    deleteWorkflow,
  }
}
