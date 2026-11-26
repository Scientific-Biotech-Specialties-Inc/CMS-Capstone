import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EquipmentItemsInput from '../EquipmentItemsInput.vue'
import type { EquipmentItem } from '@/types/contract'

describe('EquipmentItemsInput', () => {
  it('renders a single row with a plus button and no trash button', () => {
    const items: EquipmentItem[] = [{ equipmentName: '', brand: '', model: '' }]
    const wrapper = mount(EquipmentItemsInput, {
      props: { modelValue: items },
    })

    expect(wrapper.findAll('input')).toHaveLength(3)
    expect(wrapper.find('button[aria-label="Add another equipment item"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label^="Remove equipment row"]').exists()).toBe(false)
  })

  it('emits an appended empty row when clicking the plus button', async () => {
    const items: EquipmentItem[] = [{ equipmentName: 'Analyzer', brand: 'Sysmex', model: 'XN-1000' }]
    const wrapper = mount(EquipmentItemsInput, {
      props: { modelValue: items },
    })

    await wrapper.find('button[aria-label="Add another equipment item"]').trigger('click')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted![0][0]).toEqual([
      { equipmentName: 'Analyzer', brand: 'Sysmex', model: 'XN-1000' },
      { equipmentName: '', brand: '', model: '' },
    ])
    expect(wrapper.emitted('add-row')).toHaveLength(1)
  })

  it('shows trash buttons for multiple rows and removes the clicked row', async () => {
    const items: EquipmentItem[] = [
      { equipmentName: 'Analyzer A', brand: 'Sysmex', model: 'XN-1000' },
      { equipmentName: 'Analyzer B', brand: 'Roche', model: 'Cobas 6000' },
    ]
    const wrapper = mount(EquipmentItemsInput, {
      props: { modelValue: items },
    })

    const removeButtons = wrapper.findAll('button[aria-label^="Remove equipment row"]')
    expect(removeButtons).toHaveLength(2)

    await removeButtons[0].trigger('click')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted![0][0]).toEqual([
      { equipmentName: 'Analyzer B', brand: 'Roche', model: 'Cobas 6000' },
    ])
    expect(wrapper.emitted('remove-row')?.[0]).toEqual([0])
  })
})
