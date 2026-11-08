import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import WorkflowNode from '@/components/shared/ContractWorkflowTracker/WorkflowNode.vue'
import type { WorkflowNode as Node } from '@/types/contractWorkflow'

function makeNode(overrides: Partial<Node> = {}): Node {
  return {
    id: 'n1',
    title: 'Sales',
    status: 'pending',
    actedAt: null,
    subText: null,
    delegation: null,
    roleDeleted: false,
    roleRenamedFrom: null,
    ...overrides,
  }
}

function mountNode(node: Node, extra: Record<string, unknown> = {}) {
  return mount(WorkflowNode, {
    props: { node, isCurrent: false, layout: 'vertical', ...extra },
  })
}

describe('WorkflowNode', () => {
  it.each([
    ['completed', 'Done', 'Completed'],
    ['active', 'Now', 'In progress, current step'],
    ['on_hold', 'On hold', 'On hold, no active holder for this role'],
    ['rejected', 'Rejected', 'Rejected'],
    ['canceled', 'Canceled', 'Canceled'],
    ['pending', 'Pending', 'Not started, locked'],
  ] as const)('renders the %s pill and screen-reader label', (status, pill, label) => {
    const wrapper = mountNode(makeNode({ status }))
    expect(wrapper.text()).toContain(pill)
    expect(wrapper.html()).toContain(label)
  })

  it('marks the current step with aria-current', () => {
    const wrapper = mountNode(makeNode({ status: 'active' }), { isCurrent: true })
    // The template's leading HTML comment makes this a multi-root
    // component, so assert on the li itself rather than the wrapper.
    expect(wrapper.find('li').attributes('aria-current')).toBe('step')
  })

  it('shows the deleted-role warning and hides the renamed indicator', () => {
    const wrapper = mountNode(makeNode({ roleDeleted: true, roleRenamedFrom: 'Old' }))
    expect(wrapper.text()).toContain('Role deleted')
    expect(wrapper.html()).toContain('This role has been deleted')
    expect(wrapper.text()).not.toContain('renamed')
  })

  it('shows the renamed-role indicator with the previous name on hover', () => {
    const wrapper = mountNode(makeNode({ roleRenamedFrom: 'Regulatory' }))
    expect(wrapper.text()).toContain('Role renamed from Regulatory')
    expect(wrapper.html()).toContain('Previously named: Regulatory')
  })

  it('renders the delegation line plus the reviewer comment', () => {
    const wrapper = mountNode(makeNode({
      status: 'completed',
      delegation: { delegateName: 'Alex Reyes', onBehalfOfRole: 'CEO' },
      subText: 'Looks good',
    }))
    expect(wrapper.text()).toContain('Approved by Alex Reyes on behalf of CEO')
    expect(wrapper.text()).toContain('Looks good')
  })

  it('shows waiting copy for active and on-hold steps without dates', () => {
    expect(mountNode(makeNode({ status: 'active' })).text()).toContain('Awaiting action')
    expect(mountNode(makeNode({ status: 'on_hold' })).text()).toContain('On hold')
  })
})
