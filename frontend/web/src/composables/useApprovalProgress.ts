import { ref, onUnmounted } from 'vue'
import { useAuth } from './useAuth'
import type { TrackerStatus, WorkflowProgress } from '@/types/contractWorkflow'
import { mapProgressResponse } from '@/types/contractWorkflow'

/**
 * Live-data wiring for the Visual Contract Workflow Tracker (Phase 6).
 * Fetches contract-management's GET /contracts/{id}/approval-progress
 * and maps it onto the presentational WorkflowProgress shape.
 * `engine: false` (legacy single-manager flow, decision #9) maps to the
 * tracker's `empty` status. Aborts in-flight requests on unmount.
 */
export function useApprovalProgress() {
  const { state: authState } = useAuth()

  const workflow = ref<WorkflowProgress | null>(null)
  const status = ref<TrackerStatus>('loading')
  const error = ref<string | null>(null)
  // Phase 7: role names owning the latest run's current step (top-level
  // `current_step_roles` from the progress response). Empty for legacy
  // contracts — the nudge button falls back to "Notify Manager".
  const currentStepRoles = ref<string[]>([])
  const myTasks = ref<{ id: number; role_name: string }[]>([])
  let aborter: AbortController | null = null

  function apiBase(): string {
    return import.meta.env.VITE_CONTRACT_API_URL as string
  }

  async function fetchProgress(contractId: string | number): Promise<void> {
    aborter?.abort()
    aborter = new AbortController()
    status.value = 'loading'
    error.value = null

    try {
      const res = await fetch(`${apiBase()}/contracts/${contractId}/approval-progress`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${authState.token || ''}`,
        },
        signal: aborter.signal,
      })
      if (!res.ok) throw new Error('Failed to load approval progress.')
      const json = await res.json()
      const data = json.data ?? json
      myTasks.value = data.my_tasks ?? []

      if (!data.engine || !data.runs?.length) {
        workflow.value = null
        status.value = 'empty'
        currentStepRoles.value = []
        return
      }

      workflow.value = mapProgressResponse(String(contractId), data)
      status.value = 'ready'
      currentStepRoles.value = Array.isArray(data.current_step_roles) ? data.current_step_roles : []
    } catch (err) {
      if (err instanceof DOMException && err.name === 'AbortError') return
      error.value = err instanceof Error ? err.message : 'Failed to load approval progress.'
      status.value = 'error'
      currentStepRoles.value = []
      myTasks.value = []
    }
  }

  function retry(contractId: string | number): void {
    void fetchProgress(contractId)
  }

  onUnmounted(() => aborter?.abort())

  async function decide(contractId: string, taskId: number, decision: 'approved' | 'rejected', comment?: string) {
    const response = await fetch(`${apiBase()}/contracts/${contractId}/approval-tasks/${taskId}/decision`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${authState.token}` },
      body: JSON.stringify({ decision, comment }),
    })
    const result = await response.json()
    if (!response.ok) throw new Error(result.message ?? 'Approval decision could not be saved.')
  }

  return { workflow, status, error, currentStepRoles, myTasks, fetchProgress, retry, decide }
}
