import { shallowMount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import WorkflowEditor from './WorkflowEditor.vue'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import type { ContractTypeWithWorkflow, Workflow } from '@/types/workflow'

const { saveWorkflow } = vi.hoisted(() => ({ saveWorkflow: vi.fn().mockResolvedValue({}) }))
vi.mock('@/composables/useWorkflowBuilder', () => ({ useWorkflowBuilder: () => ({ saveWorkflow }) }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ error: vi.fn() }) }))

const contractType: ContractTypeWithWorkflow = {
  contract_type_id: 1,
  contract_type_name: 'Equipment Lease',
  is_active: true,
  active_workflow: { id: 4, name: 'Active version', status: 'active', resubmit_mode: 'restart', step_count: 1, updated_at: null },
  draft_workflow: null,
}

function mountEditor(id: number, status: Workflow['status'], hasRuns = false) {
  return shallowMount(WorkflowEditor, {
    props: {
      contractType, assignableRoles: [],
      workflow: { id, contract_type_id: 1, name: 'Edited version', status, has_runs: hasRuns, resubmit_mode: 'restart', steps: [] },
    },
    global: { renderStubDefaultSlot: true },
  })
}

describe('workflow version saves', () => {
  afterEach(() => saveWorkflow.mockClear())

  it('creates a new version from the active workflow', async () => {
    const editor = mountEditor(4, 'active', true)
    const button = editor.findAll('button-stub').find(button => button.text().includes('Activate New Version'))
    await button?.trigger('click')
    expect(saveWorkflow).not.toHaveBeenCalled()
    editor.getComponent(ConfirmationDialog).vm.$emit('confirm')
    expect(saveWorkflow).toHaveBeenCalledWith(expect.objectContaining({ status: 'active', replace_active_workflow_id: 4 }), undefined)
    editor.unmount()
  })

  it('activates the edited draft itself so it no longer appears as a draft', async () => {
    const editor = mountEditor(6, 'draft')
    const button = editor.findAll('button-stub').find(button => button.text().includes('Activate New Version'))
    await button?.trigger('click')
    editor.getComponent(ConfirmationDialog).vm.$emit('confirm')
    expect(saveWorkflow).toHaveBeenCalledWith(expect.objectContaining({ status: 'active', replace_active_workflow_id: 4 }), 6)
    editor.unmount()
  })
})
