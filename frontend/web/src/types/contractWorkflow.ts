// Visual Contract Workflow Tracker (Phase 6) — presentational types.
// Shaped to map cleanly onto contract-management's
// GET /contracts/{id}/approval-progress response (Phase 5's
// ApprovalEngineController::formatRun/formatTask) once wired to live data.
// Until then, ContractDetail/index.vue uses local ref<WorkflowProgress>
// mock data (no API calls, no DB seeding) per the project's dummy-data
// convention.

export type NodeStatus = 'completed' | 'active' | 'pending' | 'rejected' | 'canceled' | 'on_hold'
export type GroupMode = 'sequential' | 'parallel'
export type TrackerStatus = 'loading' | 'ready' | 'error' | 'empty'

export interface NodeDelegation {
  delegateName: string
  onBehalfOfRole: string
}

export interface WorkflowNode {
  id: string
  title: string
  status: NodeStatus
  actedAt: string | null // ISO-8601, or null for not-yet-acted steps
  subText: string | null
  delegation: NodeDelegation | null
  // Decision #14: role display state. roleDeleted takes precedence over
  // roleRenamedFrom if somehow both are set (shouldn't happen in practice —
  // a role is either currently resolvable with history, or gone).
  roleDeleted: boolean
  roleRenamedFrom: string | null // previous name, shown on hover when set
}

export interface WorkflowGroup {
  id: string
  mode: GroupMode
  nodes: WorkflowNode[]
}

export interface WorkflowRun {
  runNumber: number
  groups: WorkflowGroup[]
}

export interface WorkflowProgress {
  contractId: string
  currentRun: number
  runs: WorkflowRun[] // at least one when status === 'ready'; latest last
  executionGroups?: WorkflowGroup[]
}

// ── Live-data API shapes (Phase 6 wiring, Phase 5 backend) ──────────────
// Mirrors ApprovalEngineController::progress/formatRun/formatTask after the
// Phase 6 enrichment (role_deleted / role_renamed_from / delegate_name /
// is_current / current_step_id + contract_created_* for the synthetic node).

export interface ApiApprovalTask {
  id: number
  step_id: number
  auth_role_id: number
  role_name: string | null
  role_deleted: boolean
  role_renamed_from: string | null
  delegate_name: string | null
  is_current: boolean
  // Backend task statuses: pending/approved/rejected/canceled/on_hold.
  // 'approved' maps to the tracker's 'completed' in mapProgressResponse.
  status: NodeStatus | 'approved'
  acted_by_user_id: number | null
  acted_via_delegation: boolean
  acted_at: string | null
  comment: string | null
}

export interface ApiStepRole {
  auth_role_id: number
  role_name: string | null
  role_deleted: boolean
  role_renamed_from: string | null
}

export interface ApiApprovalGroup {
  step_id: number
  mode: GroupMode
  roles: ApiStepRole[]
  tasks: ApiApprovalTask[]
}

export interface ApiApprovalRun {
  run_number: number
  status: string
  current_step_id: number | null
  groups: ApiApprovalGroup[]
}

export interface ApiExecutionStage {
  stage: string
  status: NodeStatus
  is_current: boolean
  acted_at: string | null
  reviewer_roles: string[]
  sub_text: string | null
  document_id: string | null
}

export interface ApprovalProgressResponse {
  engine: boolean
  contract_id?: string
  contract_created_at?: string | null
  contract_created_by?: string | null
  runs: ApiApprovalRun[]
  legacy_run?: ApiApprovalRun
  execution_stages?: ApiExecutionStage[]
}

/**
 * Map the backend approval-progress payload onto the presentational
 * WorkflowProgress shape. Pure function (no fetch, no component state)
 * so it stays unit-testable. Rules:
 * - approved → completed; pending/on_hold flagged is_current → active,
 *   otherwise pending; rejected/canceled/on_hold pass through.
 * - Steps with no tasks yet render as pending from the step definitions
 *   (tasks materialize only when reached); groups with neither are dropped.
 * - comment → subText; role_name snapshot → title (fallback Role #id).
 * - acted_via_delegation + delegate_name → delegation badge; the
 *   on-behalf-of label is the step's role title.
 * - A synthetic "Contract created" group is prepended from
 *   contract_created_* so the track matches the Phase 6 mock rhythm.
 */
export function mapProgressResponse(contractId: string, json: ApprovalProgressResponse): WorkflowProgress {
  const rawRuns = json.runs && json.runs.length > 0
    ? json.runs
    : (json.legacy_run ? [json.legacy_run] : [])

  const runs: WorkflowRun[] = rawRuns.map((run) => {
    // Groups render from materialized tasks; steps with no tasks yet
    // (future steps — tasks are created only when the run reaches them)
    // render as pending/locked from the step definitions. A group with
    // neither is wreckage with nothing to show and is dropped.
    const groups: WorkflowGroup[] = run.groups
      .map((g) => ({
      id: `step-${g.step_id}`,
      mode: g.mode,
      nodes: g.tasks.length > 0
        ? g.tasks.map((t): WorkflowNode => {
        // Backend 'approved' becomes the tracker's 'completed'.
        let status: NodeStatus = t.status === 'approved' ? 'completed' : t.status
        if (t.status === 'pending') {
          // 'active' is the current pending step; future steps stay locked.
          // on_hold keeps its own status so the amber empty-role badge
          // (decision #12) still renders — index.vue treats it as current.
          status = t.is_current ? 'active' : 'pending'
        }
        const title = t.role_name ?? `Role #${t.auth_role_id}`
        return {
          id: `task-${t.id}`,
          title,
          status,
          actedAt: t.acted_at,
          subText: t.comment,
          delegation: t.acted_via_delegation && t.delegate_name
            ? { delegateName: t.delegate_name, onBehalfOfRole: title }
            : null,
          roleDeleted: t.role_deleted,
          roleRenamedFrom: t.role_deleted ? null : t.role_renamed_from,
        }
      })
        : (g.roles ?? []).map((r): WorkflowNode => {
          const title = r.role_name ?? `Role #${r.auth_role_id}`
          return {
            id: `future-${g.step_id}-${r.auth_role_id}`,
            title,
            status: 'pending',
            actedAt: null,
            subText: null,
            delegation: null,
            roleDeleted: r.role_deleted,
            roleRenamedFrom: r.role_deleted ? null : r.role_renamed_from,
          }
        }),
    }))
    .filter((g) => g.nodes.length > 0)

    // Synthetic creation node first (mirrors the mock scenarios' g1).
    groups.unshift({
      id: `created-run-${run.run_number}`,
      mode: 'sequential',
      nodes: [{
        id: `created-run-${run.run_number}-node`,
        title: 'Contract created',
        status: 'completed',
        actedAt: json.contract_created_at ?? null,
        subText: json.contract_created_by ? `Submitted by ${json.contract_created_by}` : null,
        delegation: null,
        roleDeleted: false,
        roleRenamedFrom: null,
      }],
    })

    return { runNumber: run.run_number, groups }
  })

  const executionGroups: WorkflowGroup[] = (json.execution_stages ?? []).map((stage, idx) => {
    const reviewerText = stage.reviewer_roles?.length ? `Reviewers: ${stage.reviewer_roles.join(', ')}` : ''
    const subText = stage.sub_text
      ? (reviewerText ? `${stage.sub_text} · ${reviewerText}` : stage.sub_text)
      : reviewerText || null

    return {
      id: `execution-stage-${idx}-${stage.stage}`,
      mode: 'sequential',
      nodes: [{
        id: `execution-node-${stage.stage}`,
        title: stage.stage,
        status: stage.status,
        actedAt: stage.acted_at,
        subText,
        delegation: null,
        roleDeleted: false,
        roleRenamedFrom: null,
      }],
    }
  })

  return {
    contractId,
    currentRun: runs.length ? runs[runs.length - 1].runNumber : 0,
    runs,
    executionGroups: executionGroups.length > 0 ? executionGroups : undefined,
  }
}

/**
 * Phase 7 (decision #13): nudge-button label from the progress response's
 * `current_step_roles`. Pure function so it's unit-testable without
 * mounting: empty (legacy) → "Notify Manager"; one role → "Nudge {Role}";
 * parallel multi-role step → "Nudge Current Step".
 */
export function nudgeLabelFor(currentStepRoles: string[]): string {
  if (currentStepRoles.length === 0) return 'Notify Manager'
  if (currentStepRoles.length === 1) return `Nudge ${currentStepRoles[0]}`
  return 'Nudge Current Step'
}

/**
 * Group status for connector styling (spec 5.3): rejected if any node is
 * rejected; else active if any node is active; else completed if all
 * nodes are completed; else pending. canceled nodes don't independently
 * drive group status — a group is only ever 'canceled' overall if every
 * node in it is canceled (the rejected sibling already makes the group
 * read as 'rejected' per the rule above).
 */
export function groupStatus(group: WorkflowGroup): NodeStatus {
  if (group.nodes.some(n => n.status === 'rejected')) return 'rejected'
  if (group.nodes.some(n => n.status === 'active' || n.status === 'on_hold')) return 'active'
  if (group.nodes.every(n => n.status === 'completed')) return 'completed'
  if (group.nodes.every(n => n.status === 'canceled')) return 'canceled'
  return 'pending'
}
