// Dynamic Approval Workflow Engine (Phase 4) — Workflow Builder types.
// Mirrors contract-management's Workflow/WorkflowStep/WorkflowStepRole
// models (Phase 3). Roles are soft references to auth-module — this
// frontend never stores more than id + a display name snapshot for a role.

export type WorkflowStatus = 'draft' | 'active'
export type WorkflowStepMode = 'sequential' | 'parallel'
export type ResubmitMode = 'restart' | 'resume_at_rejected'
export type ExecutionReviewStage = 'SBSI Review' | 'Client Review' | 'For Signing' | 'Signed'
export type ExecutionReviewers = Partial<Record<ExecutionReviewStage, WorkflowStepRole[]>>

export interface WorkflowStepRole {
  id?: number
  auth_role_id: number
  role_name: string
}

export interface WorkflowStep {
  id?: number
  order_index: number
  mode: WorkflowStepMode
  roles: WorkflowStepRole[]
}

export interface Workflow {
  id: number
  contract_type_id: number
  name: string
  status: WorkflowStatus
  has_runs: boolean
  resubmit_mode: ResubmitMode
  execution_reviewers?: ExecutionReviewers | null
  steps: WorkflowStep[]
}

export interface WorkflowSummary {
  id: number
  name: string
  status: WorkflowStatus
  resubmit_mode: ResubmitMode
  step_count: number
  updated_at: string | null
}

export interface ContractTypeWithWorkflow {
  contract_type_id: number
  contract_type_name: string
  is_active: boolean
  active_workflow: WorkflowSummary | null
  draft_workflow: WorkflowSummary | null
}

// Minimal shape of an auth-module role, as returned by
// GET /workflows/assignable-roles (proxied from auth-service /admin/roles).
export interface AssignableRole {
  id: number
  name: string
  description?: string | null
  nav_group?: string | null
}

// Payload sent to POST /workflows and PUT /workflows/{id}.
export interface WorkflowSavePayload {
  contract_type_id: number
  name: string
  status: WorkflowStatus
  replace_active_workflow_id?: number
  replace_draft_workflow_id?: number
  resubmit_mode: ResubmitMode
  execution_reviewers?: ExecutionReviewers
  steps: WorkflowStep[]
}
