import { RouterLinkStub, shallowMount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { ContractRequest } from '@/types/contractRequest'
import RequestDetailHeader from './RequestDetailHeader.vue'

const request: ContractRequest = {
  id: 'REQ-CTR-TEST', contractId: 'CTR-TEST', businessPartner: 'Partner', category: 'Equipment Lease',
  description: '', region: 'Luzon', requestDate: '', startDate: '', endDate: '', status: 'Pending', notes: '',
  rejectionReason: '', contractLink: '', createdBy: 'Sales', docs: [], itemCode: '', serialNo: '',
}

function mountHeader(hasPendingApprovalWorkflow: boolean) {
  return shallowMount(RequestDetailHeader, {
    props: { request: { ...request, hasPendingApprovalWorkflow }, days: 40, isEditing: false, isManager: true, isFollowedUp: false },
    global: { renderStubDefaultSlot: true, stubs: { RouterLink: RouterLinkStub } },
  })
}

describe('manager request approval actions', () => {
  it('opens contract approval tasks when the workflow handles approval', () => {
    const header = mountHeader(true)
    expect(header.text()).toContain('Review Approval')
    expect(header.text()).not.toContain('Approve Request')
    expect(header.text()).not.toContain('Reject Request')
    expect(header.findComponent(RouterLinkStub).props('to')).toEqual({
      name: 'manager-contracts-detail', params: { id: 'CTR-TEST' },
    })
    header.unmount()
  })

  it('keeps the standard approve and reject controls for requests without an approval workflow', () => {
    const header = mountHeader(false)
    expect(header.text()).toContain('Approve Request')
    expect(header.text()).toContain('Reject Request')
    expect(header.text()).not.toContain('Review Approval')
    header.unmount()
  })
})
