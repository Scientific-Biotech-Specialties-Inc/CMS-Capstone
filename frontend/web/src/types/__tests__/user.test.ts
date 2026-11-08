import { describe, it, expect } from 'vitest'
import { buildRoleTabs, pluralRoleName, roleBadgeFor } from '@/types/user'

describe('buildRoleTabs', () => {
  it('always starts with the View all tab', () => {
    expect(buildRoleTabs([])).toEqual([{ label: 'View all', value: 'all' }])
  })

  it('adds one tab per CMS-accessible role', () => {
    expect(buildRoleTabs(['Admin', 'Manager', 'Employee'])).toEqual([
      { label: 'View all', value: 'all' },
      { label: 'Admin', value: 'Admin' },
      { label: 'Manager', value: 'Manager' },
      { label: 'Employee', value: 'Employee' },
    ])
  })

  it('adds a newly granted role with no code change', () => {
    const tabs = buildRoleTabs(['Manager', 'Employee', 'CEO'])
    expect(tabs.map(t => t.value)).toContain('CEO')
  })
})

describe('roleBadgeFor', () => {
  it('returns brand styles for known roles', () => {
    expect(roleBadgeFor('Admin')).toContain('brand-navy')
    expect(roleBadgeFor('Manager')).toContain('brand-dark')
    expect(roleBadgeFor('Employee')).toContain('brand-blue')
  })

  it('falls back to a neutral style for custom roles', () => {
    expect(roleBadgeFor('CEO')).toContain('black')
  })
})

describe('pluralRoleName', () => {
  it('pluralizes regular role names', () => {
    expect(pluralRoleName('Admin')).toBe('Admins')
    expect(pluralRoleName('Manager')).toBe('Managers')
    expect(pluralRoleName('Employee')).toBe('Employees')
    expect(pluralRoleName('CEO')).toBe('CEOs')
  })

  it('keeps already-plural names intact', () => {
    expect(pluralRoleName('Sales')).toBe('Sales')
  })
})
