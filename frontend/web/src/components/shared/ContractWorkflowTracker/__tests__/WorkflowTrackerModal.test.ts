import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import WorkflowTrackerModal from '../WorkflowTrackerModal.vue'
import type { WorkflowProgress } from '@/types/contractWorkflow'

const mockWorkflow: WorkflowProgress = {
  contractId: 'CTR-1001',
  currentRun: 1,
  runs: [
    {
      runNumber: 1,
      groups: [
        {
          id: 'g1',
          mode: 'sequential',
          nodes: [
            {
              id: 'n1',
              title: 'Contract created',
              status: 'completed',
              actedAt: '2026-09-26T09:00:00Z',
              subText: 'Submitted by Sales',
              delegation: null,
              roleDeleted: false,
              roleRenamedFrom: null,
            },
          ],
        },
      ],
    },
  ],
  executionGroups: [
    {
      id: 'eg1',
      mode: 'sequential',
      nodes: [
        {
          id: 'en1',
          title: 'SBSI Review',
          status: 'active',
          actedAt: null,
          subText: 'In progress · Reviewers: Manager',
          delegation: null,
          roleDeleted: false,
          roleRenamedFrom: null,
        },
      ],
    },
  ],
}

describe('WorkflowTrackerModal', () => {
  it('renders both Internal Approval and Execution Stages tabs', () => {
    const wrapper = mount(WorkflowTrackerModal, {
      props: {
        open: true,
        workflow: mockWorkflow,
        status: 'ready',
        initialTab: 'approval',
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Internal Approval')
    expect(wrapper.text()).toContain('Execution Stages')
    expect(wrapper.text()).toContain('Contract created')
  })

  it('switches to Execution Stages when tab is clicked', async () => {
    const wrapper = mount(WorkflowTrackerModal, {
      props: {
        open: true,
        workflow: mockWorkflow,
        status: 'ready',
        initialTab: 'approval',
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    const buttons = wrapper.findAll('button')
    const executionTabBtn = buttons.find((b) => b.text().includes('Execution Stages'))
    expect(executionTabBtn).toBeDefined()

    await executionTabBtn!.trigger('click')
    expect(wrapper.text()).toContain('SBSI Review')
  })

  it('opens directly in execution tab when initialTab is execution', () => {
    const wrapper = mount(WorkflowTrackerModal, {
      props: {
        open: true,
        workflow: mockWorkflow,
        status: 'ready',
        initialTab: 'execution',
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    expect(wrapper.text()).toContain('SBSI Review')
  })

  it('renders execution action button and emits executionAction when clicked', async () => {
    const wrapper = mount(WorkflowTrackerModal, {
      props: {
        open: true,
        workflow: mockWorkflow,
        status: 'ready',
        initialTab: 'execution',
        executionState: {
          enabled: true,
          stage: 'SBSI Review',
          paused: false,
          awaiting_confirmation: false,
          allowed: {
            submit: true,
            confirm: false,
            pause: true,
            resume: false,
            revise: false,
            auto_confirm: true,
          },
          history: [],
          draft_document_id: null,
          pending: null,
        },
        executionSubmitLabel: 'Submit SBSI Review',
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Submit SBSI Review')
    const actionBtn = wrapper.findAll('button').find((b) => b.text().includes('Submit SBSI Review'))
    expect(actionBtn).toBeDefined()

    await actionBtn!.trigger('click')
    expect(wrapper.emitted('executionAction')).toBeTruthy()
    expect(wrapper.emitted('executionAction')![0]).toEqual(['submit'])
  })

  it('shows a pending signing submission and only offers confirmation to eligible reviewers', async () => {
    const executionState = {
      enabled: true,
      stage: 'For Signing' as const,
      paused: false,
      awaiting_confirmation: true,
      allowed: { submit: false, confirm: false, pause: true, resume: false, revise: false, auto_confirm: false },
      history: [],
      draft_document_id: 'draft-1',
      pending: null,
    }
    const wrapper = mount(WorkflowTrackerModal, {
      props: { open: true, workflow: mockWorkflow, status: 'ready', initialTab: 'execution', executionState },
      global: { stubs: { Teleport: true } },
    })

    expect(wrapper.get('[role="status"]').text()).toBe('Awaiting Confirmation')
    expect(wrapper.text()).not.toContain('Update Execution Status')

    await wrapper.setProps({ executionState: { ...executionState, allowed: { ...executionState.allowed, confirm: true } } })
    const confirm = wrapper.findAll('button').find(button => button.text() === 'Confirm Submission')
    expect(confirm).toBeDefined()
    await confirm!.trigger('click')
    expect(wrapper.emitted('executionAction')).toEqual([['confirm']])
    expect(wrapper.text()).not.toContain('Update Execution Status')
  })
})

