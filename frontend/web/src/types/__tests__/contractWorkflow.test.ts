import { describe, it, expect } from 'vitest'
import {
  groupStatus,
  mapProgressResponse,
  nudgeLabelFor,
  type ApprovalProgressResponse,
  type WorkflowGroup,
} from '@/types/contractWorkflow'

function groupWith(statuses: Array<WorkflowGroup['nodes'][number]['status']>): WorkflowGroup {
  return {
    id: 'g1',
    mode: 'sequential',
    nodes: statuses.map((status, i) => ({
      id: `n${i}`,
      title: `Role ${i}`,
      status,
      actedAt: null,
      subText: null,
      delegation: null,
      roleDeleted: false,
      roleRenamedFrom: null,
    })),
  }
}

describe('groupStatus', () => {
  it('reports rejected when any node rejected (spec 5.3 precedence)', () => {
    expect(groupStatus(groupWith(['completed', 'rejected', 'canceled']))).toBe('rejected')
  })

  it('reports active when any node is active or on_hold', () => {
    expect(groupStatus(groupWith(['completed', 'active', 'pending']))).toBe('active')
    expect(groupStatus(groupWith(['completed', 'on_hold']))).toBe('active')
  })

  it('reports completed only when every node completed', () => {
    expect(groupStatus(groupWith(['completed', 'completed']))).toBe('completed')
    expect(groupStatus(groupWith(['completed', 'pending']))).toBe('pending')
  })

  it('reports canceled only when every node canceled', () => {
    expect(groupStatus(groupWith(['canceled', 'canceled']))).toBe('canceled')
    expect(groupStatus(groupWith(['canceled', 'pending']))).toBe('pending')
  })
})

const baseResponse: ApprovalProgressResponse = {
  engine: true,
  contract_id: 'CTR-1',
  contract_created_at: '2026-09-26T09:00:00Z',
  contract_created_by: 'Procurement',
  runs: [
    {
      run_number: 1,
      status: 'in_progress',
      current_step_id: 20,
      groups: [
        {
          step_id: 10,
          mode: 'sequential',
          roles: [
            { auth_role_id: 3, role_name: 'Regulatory', role_deleted: false, role_renamed_from: 'OldReg' },
          ],
          tasks: [
            { id: 1, step_id: 10, auth_role_id: 3, role_name: 'Regulatory', role_deleted: false, role_renamed_from: 'OldReg', delegate_name: null, is_current: false, status: 'approved', acted_by_user_id: 5, acted_via_delegation: false, acted_at: '2026-09-28T14:30:00Z', comment: 'Compliant' },
          ],
        },
        {
          step_id: 20,
          mode: 'parallel',
          roles: [
            { auth_role_id: 4, role_name: 'Sales', role_deleted: false, role_renamed_from: null },
            { auth_role_id: 5, role_name: null, role_deleted: true, role_renamed_from: null },
          ],
          tasks: [
            { id: 2, step_id: 20, auth_role_id: 4, role_name: 'Sales', role_deleted: false, role_renamed_from: null, delegate_name: null, is_current: true, status: 'pending', acted_by_user_id: null, acted_via_delegation: false, acted_at: null, comment: null },
            { id: 3, step_id: 20, auth_role_id: 5, role_name: null, role_deleted: true, role_renamed_from: null, delegate_name: null, is_current: true, status: 'on_hold', acted_by_user_id: null, acted_via_delegation: false, acted_at: null, comment: null },
          ],
        },
        {
          step_id: 30,
          mode: 'sequential',
          roles: [
            { auth_role_id: 6, role_name: 'CEO', role_deleted: false, role_renamed_from: null },
          ],
          tasks: [],
        },
      ],
    },
  ],
}

describe('nudgeLabelFor', () => {
  it("returns 'Notify Manager' when there is no current step (legacy)", () => {
    expect(nudgeLabelFor([])).toBe('Notify Manager')
  })

  it("returns 'Nudge {Role}' for a single-role step", () => {
    expect(nudgeLabelFor(['Sales'])).toBe('Nudge Sales')
  })

  it("returns 'Nudge Current Step' for a parallel multi-role step", () => {
    expect(nudgeLabelFor(['Sales', 'Accounting'])).toBe('Nudge Current Step')
  })
})

describe('mapProgressResponse', () => {
  it('prepends the synthetic Contract created node', () => {
    const out = mapProgressResponse('CTR-1', baseResponse)
    expect(out.contractId).toBe('CTR-1')
    expect(out.currentRun).toBe(1)
    const first = out.runs[0].groups[0].nodes[0]
    expect(first.title).toBe('Contract created')
    expect(first.status).toBe('completed')
    expect(first.subText).toBe('Submitted by Procurement')
  })

  it('maps approved to completed and preserves comment + rename history', () => {
    const out = mapProgressResponse('CTR-1', baseResponse)
    const node = out.runs[0].groups[1].nodes[0]
    expect(node.status).toBe('completed')
    expect(node.subText).toBe('Compliant')
    expect(node.roleRenamedFrom).toBe('OldReg')
    expect(node.roleDeleted).toBe(false)
  })

  it('maps the current pending task to active', () => {
    const out = mapProgressResponse('CTR-1', baseResponse)
    expect(out.runs[0].groups[2].nodes[0].status).toBe('active')
  })

  it('keeps on_hold distinct and falls back to Role #id with the deleted flag', () => {
    const out = mapProgressResponse('CTR-1', baseResponse)
    const node = out.runs[0].groups[2].nodes[1]
    expect(node.status).toBe('on_hold')
    expect(node.title).toBe('Role #5')
    expect(node.roleDeleted).toBe(true)
    expect(node.roleRenamedFrom).toBeNull()
  })

  it('builds the delegation badge from delegate_name', () => {
    const resp: ApprovalProgressResponse = {
      ...baseResponse,
      runs: [{
        run_number: 1,
        status: 'approved',
        current_step_id: null,
        groups: [{
          step_id: 10,
          mode: 'sequential',
          roles: [
            { auth_role_id: 7, role_name: 'CEO', role_deleted: false, role_renamed_from: null },
          ],
          tasks: [{
            id: 9, step_id: 10, auth_role_id: 7, role_name: 'CEO', role_deleted: false,
            role_renamed_from: null, delegate_name: 'Alex Reyes', is_current: false,
            status: 'approved', acted_by_user_id: 11, acted_via_delegation: true,
            acted_at: '2026-10-01T08:00:00Z', comment: null,
          }],
        }],
      }],
    }
    const node = mapProgressResponse('CTR-9', resp).runs[0].groups[1].nodes[0]
    expect(node.status).toBe('completed')
    expect(node.delegation).toEqual({ delegateName: 'Alex Reyes', onBehalfOfRole: 'CEO' })
  })

  it('renders future steps with no tasks as pending from the step definitions', () => {
    const out = mapProgressResponse('CTR-1', baseResponse)
    // groups[0] is the synthetic created node; step 30 (no tasks) lands last.
    const future = out.runs[0].groups[out.runs[0].groups.length - 1]
    expect(future.id).toBe('step-30')
    expect(future.nodes).toHaveLength(1)
    expect(future.nodes[0].title).toBe('CEO')
    expect(future.nodes[0].status).toBe('pending')
    expect(future.nodes[0].actedAt).toBeNull()
  })

  it('suppresses the renamed-from label when the role is deleted', () => {
    const resp: ApprovalProgressResponse = {
      ...baseResponse,
      runs: [{
        run_number: 1,
        status: 'in_progress',
        current_step_id: 10,
        groups: [{
          step_id: 10,
          mode: 'sequential',
          roles: [
            { auth_role_id: 8, role_name: 'Old', role_deleted: true, role_renamed_from: null },
          ],
          tasks: [{
            id: 4, step_id: 10, auth_role_id: 8, role_name: 'Old', role_deleted: true,
            role_renamed_from: 'Older', delegate_name: null, is_current: false,
            status: 'rejected', acted_by_user_id: 5, acted_via_delegation: false,
            acted_at: '2026-09-30T10:00:00Z', comment: 'No',
          }],
        }],
      }],
    }
    const node = mapProgressResponse('CTR-2', resp).runs[0].groups[1].nodes[0]
    expect(node.status).toBe('rejected')
    expect(node.roleDeleted).toBe(true)
    expect(node.roleRenamedFrom).toBeNull()
  })
})
