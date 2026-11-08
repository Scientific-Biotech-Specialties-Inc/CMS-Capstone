import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SignerDetailsPanel from '../SignerDetailsPanel.vue'
import ExecutionActionDialog from '../../ContractDetail/ExecutionActionDialog.vue'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ path: '/sales/contracts/CTR-101' }),
}))

describe('SignerDetailsPanel', () => {
  it('renders required field asterisk indicators', () => {
    const wrapper = mount(SignerDetailsPanel, {
      props: {
        signerName: '',
        signature: null,
        signatureWidth: 0.20,
        consent: false,
        hasPlacement: false,
        submitting: false,
        disabled: false,
        attemptedSubmit: false,
        error: '',
      },
    })

    expect(wrapper.text()).toContain('Full Legal Name *')
    expect(wrapper.text()).toContain('Signature *')
    expect(wrapper.text()).toContain('Placement Required *')
    expect(wrapper.text()).toContain('I confirm my intent to sign this approved draft at the position shown. *')
  })

  it('displays inline validation errors when attemptedSubmit is true and fields are missing', () => {
    const wrapper = mount(SignerDetailsPanel, {
      props: {
        signerName: '',
        signature: null,
        signatureWidth: 0.20,
        consent: false,
        hasPlacement: false,
        submitting: false,
        disabled: false,
        attemptedSubmit: true,
        error: '',
      },
    })

    expect(wrapper.text()).toContain('Full name is required.')
    expect(wrapper.text()).toContain('Please draw or upload a signature.')
    expect(wrapper.text()).toContain('Consent confirmation is required.')
  })

  it('allows selecting signature size presets', async () => {
    const wrapper = mount(SignerDetailsPanel, {
      props: {
        signerName: 'Jane Doe',
        signature: 'data:image/png;base64,mock',
        signatureWidth: 0.20,
        consent: true,
        hasPlacement: true,
        submitting: false,
        disabled: false,
        attemptedSubmit: false,
        error: '',
      },
    })

    const buttons = wrapper.findAll('button')
    const compactBtn = buttons.find(b => b.text().includes('Compact'))
    expect(compactBtn).toBeDefined()

    await compactBtn!.trigger('click')
    expect(wrapper.emitted('update:signatureWidth')?.[0]).toEqual([0.10])
  })
})

describe('ExecutionActionDialog - Sign in the Application flow', () => {
  it('displays Continue to Sign in the Application button and required asterisks', () => {
    const wrapper = mount(ExecutionActionDialog, {
      props: {
        contractId: 'CTR-101',
        action: 'submit',
        execution: {
          enabled: true,
          stage: 'For Signing',
          paused: false,
          awaiting_confirmation: false,
          draft_document_id: 'doc-123',
          pending: null,
          history: [],
          allowed: {
            submit: true,
            confirm: false,
            pause: true,
            resume: false,
            revise: false,
            auto_confirm: true,
          },
        },
        save: vi.fn(),
        loadDraft: vi.fn(),
        draftUrl: '',
      },
      global: {
        stubs: {
          Dialog: { template: '<div><slot /></div>' },
          DialogContent: { template: '<div><slot /></div>' },
          DialogHeader: { template: '<div><slot /></div>' },
          DialogTitle: { template: '<h2><slot /></h2>' },
          DialogDescription: { template: '<p><slot /></p>' },
        },
      },
    })

    expect(wrapper.text()).toContain('Signing Method *')
    expect(wrapper.text()).toContain('Sign in the Application')
    expect(wrapper.text()).toContain('Signed PDF Document *')
  })
})

