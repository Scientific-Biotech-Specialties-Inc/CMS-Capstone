import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatDeltaPill from '../StatDeltaPill.vue'
import { computeStatDelta } from '@/utils/statDelta'

describe('StatDeltaPill', () => {
  it('renders positive delta in emerald styling with tooltip', () => {
    const delta = computeStatDelta(110, 100)
    const wrapper = mount(StatDeltaPill, { props: { delta } })

    expect(wrapper.text()).toBe('+10.0%')
    expect(wrapper.classes()).toContain('bg-emerald-50')
    expect(wrapper.classes()).toContain('text-emerald-600')
    expect(wrapper.attributes('title')).toContain('+10 vs yesterday (100 → 110)')
  })

  it('renders negative delta in red styling when inverted metric rises', () => {
    const delta = computeStatDelta(12, 10, { invertPolarity: true })
    const wrapper = mount(StatDeltaPill, { props: { delta } })

    expect(wrapper.text()).toBe('+20.0%')
    expect(wrapper.classes()).toContain('bg-red-50')
    expect(wrapper.classes()).toContain('text-red-500')
  })

  it('renders neutral delta in slate styling when unchanged', () => {
    const delta = computeStatDelta(25, 25)
    const wrapper = mount(StatDeltaPill, { props: { delta } })

    expect(wrapper.text()).toBe('0.0%')
    expect(wrapper.classes()).toContain('bg-slate-100')
    expect(wrapper.classes()).toContain('text-slate-500')
  })
})
