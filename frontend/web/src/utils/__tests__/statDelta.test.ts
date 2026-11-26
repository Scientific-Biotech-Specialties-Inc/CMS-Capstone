import { describe, expect, it } from 'vitest'
import {
  computeContractStatDeltas,
  computeRequestStatDeltas,
  computeStatDelta,
  computeUserStatDeltas,
  previousDateRangeFor,
} from '../statDelta'
import type { Contract } from '@/types/contract'
import type { ContractRequest } from '@/types/contractRequest'

const NOW = new Date(2026, 10, 26, 14, 0, 0) // 2026-11-26 14:00 local

function makeContract(overrides: Partial<Contract> = {}): Contract {
  return {
    id: 'CON-001',
    businessPartner: 'Acme Corp',
    category: 'Service',
    itemCode: 'ITM-1',
    description: 'Test',
    serialNo: 'SN-1',
    region: 'Luzon',
    startDate: '2026-01-10',
    endDate: '2027-06-01',
    createdAt: '2026-11-20T09:00:00',
    approvalStatus: 'Approved',
    workflowStatus: 'Signed',
    contractLink: '',
    createdBy: 'User',
    docs: [],
    ...overrides,
  }
}

function makeRequest(overrides: Partial<ContractRequest> = {}): ContractRequest {
  return {
    id: 'REQ-001',
    businessPartner: 'Acme Corp',
    category: 'Service',
    description: 'Test',
    region: 'Luzon',
    requestDate: '2026-11-20T09:00:00',
    startDate: '2026-11-20',
    endDate: '2027-11-20',
    status: 'Pending',
    notes: '',
    rejectionReason: '',
    contractLink: '',
    createdBy: 'Sales',
    docs: [],
    itemCode: 'ITM-1',
    serialNo: 'SN-1',
    ...overrides,
  }
}

describe('computeStatDelta', () => {
  it('returns +10.0% positive when +10 contracts are added on a baseline of 100', () => {
    const res = computeStatDelta(110, 100)
    expect(res.change).toBe('+10.0%')
    expect(res.tone).toBe('positive')
    expect(res.tooltip).toContain('+10 vs yesterday (100 → 110)')
  })

  it('returns 0.0% neutral when unchanged including 0 → 0', () => {
    expect(computeStatDelta(0, 0)).toMatchObject({ change: '0.0%', tone: 'neutral' })
    expect(computeStatDelta(42, 42)).toMatchObject({ change: '0.0%', tone: 'neutral' })
  })

  it('returns +100.0% without NaN/Infinity when baseline is 0 and current > 0', () => {
    const res = computeStatDelta(5, 0)
    expect(res.change).toBe('+100.0%')
    expect(res.tone).toBe('positive')
  })

  it('inverts polarity for negative-is-good metrics (Expiring Soon, Expired)', () => {
    expect(computeStatDelta(12, 10, { invertPolarity: true })).toMatchObject({
      change: '+20.0%',
      tone: 'negative',
    })
    expect(computeStatDelta(8, 10, { invertPolarity: true })).toMatchObject({
      change: '-20.0%',
      tone: 'positive',
    })
  })

  it('avoids -0.0% for tiny fractional drops', () => {
    expect(computeStatDelta(9999, 10000)).toMatchObject({
      change: '0.0%',
      tone: 'neutral',
    })
  })
})

describe('previousDateRangeFor', () => {
  it('computes yesterday for today filter', () => {
    const prev = previousDateRangeFor('today', null, NOW)
    expect(prev?.label).toBe('vs yesterday')
    expect(prev?.range.from.getDate()).toBe(25)
    expect(prev?.range.to.getDate()).toBe(25)
  })

  it('computes previous year when selectedYear is set', () => {
    const prev = previousDateRangeFor('all', 2026, NOW)
    expect(prev?.label).toBe('vs 2025')
    expect(prev?.range.from.getFullYear()).toBe(2025)
    expect(prev?.range.to.getFullYear()).toBe(2025)
  })

  it('returns null for All Time without selectedYear', () => {
    expect(previousDateRangeFor('all', null, NOW)).toBeNull()
  })
})

describe('computeContractStatDeltas', () => {
  it('reflects +10 contracts added today on a 100-contract baseline as +10.0%', () => {
    const existing = Array.from({ length: 100 }, (_, i) =>
      makeContract({ id: `OLD-${i}`, createdAt: '2026-11-20T10:00:00' })
    )
    const addedToday = Array.from({ length: 10 }, (_, i) =>
      makeContract({ id: `NEW-${i}`, createdAt: '2026-11-26T09:30:00' })
    )

    const deltas = computeContractStatDeltas([...existing, ...addedToday], { now: NOW })
    expect(deltas.total.change).toBe('+10.0%')
    expect(deltas.total.tone).toBe('positive')
    expect(deltas.active.change).toBe('+10.0%')
    expect(deltas.active.tone).toBe('positive')
  })

  it('tracks midnight expiration transition from Expiring Soon to Expired', () => {
    // endDate = 2026-11-25: on 2026-11-25 (yesterday) days=0 -> expiring; on 2026-11-26 (today) days=-1 -> expired
    const contracts = [
      makeContract({ id: 'EXP-1', endDate: '2026-11-25', createdAt: '2026-01-01T00:00:00' }),
      makeContract({ id: 'EXP-2', endDate: '2026-11-10', createdAt: '2026-01-01T00:00:00' }),
    ]

    const deltas = computeContractStatDeltas(contracts, { now: NOW })
    // Expired went from 1 yesterday -> 2 today (+100.0%, inverted -> negative/red)
    expect(deltas.expired).toMatchObject({ current: 2, previous: 1, change: '+100.0%', tone: 'negative' })
    // Expiring went from 1 yesterday -> 0 today (-100.0%, inverted -> positive/green)
    expect(deltas.expiring).toMatchObject({ current: 0, previous: 1, change: '-100.0%', tone: 'positive' })
  })

  it('compares today vs yesterday when dashboard filter is "today"', () => {
    const contracts = [
      makeContract({ id: 'Y1', startDate: '2026-11-25' }),
      makeContract({ id: 'Y2', startDate: '2026-11-25' }),
      makeContract({ id: 'T1', startDate: '2026-11-26' }),
      makeContract({ id: 'T2', startDate: '2026-11-26' }),
      makeContract({ id: 'T3', startDate: '2026-11-26' }),
    ]

    const deltas = computeContractStatDeltas(contracts, { filter: 'today', now: NOW })
    expect(deltas.total).toMatchObject({ current: 3, previous: 2, change: '+50.0%', tone: 'positive' })
  })

  it('filters to Approved contracts only when approvedOnly is true (Manager/Sales dashboards)', () => {
    const contracts = [
      makeContract({ id: 'A1', approvalStatus: 'Approved', createdAt: '2026-11-20T08:00:00' }),
      makeContract({ id: 'A2', approvalStatus: 'Approved', createdAt: '2026-11-26T08:00:00' }),
      makeContract({ id: 'P1', approvalStatus: 'Pending', createdAt: '2026-11-26T08:00:00' }),
    ]

    const deltas = computeContractStatDeltas(contracts, { approvedOnly: true, now: NOW })
    expect(deltas.total).toMatchObject({ current: 2, previous: 1, change: '+100.0%', tone: 'positive' })
  })
})

describe('computeRequestStatDeltas', () => {
  it('computes real daily growth per request status', () => {
    const requests = [
      makeRequest({ id: 'R1', status: 'Pending', requestDate: '2026-11-20T09:00:00' }),
      makeRequest({ id: 'R2', status: 'Pending', requestDate: '2026-11-20T09:00:00' }),
      makeRequest({ id: 'R3', status: 'Pending', requestDate: '2026-11-26T09:00:00' }),
      makeRequest({ id: 'R4', status: 'Approved', requestDate: '2026-11-20T09:00:00' }),
    ]

    const deltas = computeRequestStatDeltas(requests, { now: NOW })
    expect(deltas.total).toMatchObject({ current: 4, previous: 3, change: '+33.3%', tone: 'positive' })
    expect(deltas.pending).toMatchObject({ current: 3, previous: 2, change: '+50.0%', tone: 'positive' })
    expect(deltas.approved).toMatchObject({ current: 1, previous: 1, change: '0.0%', tone: 'neutral' })
  })
})

describe('computeUserStatDeltas', () => {
  it('computes total and per-role user growth from createdAt', () => {
    const users = [
      { role: 'Admin', createdAt: '2026-10-01T00:00:00Z' },
      { role: 'Sales', createdAt: '2026-10-01T00:00:00Z' },
      { role: 'Sales', createdAt: '2026-10-01T00:00:00Z' },
      { role: 'Sales', createdAt: '2026-11-26T02:00:00' },
    ]

    const deltas = computeUserStatDeltas(users, ['Admin', 'Sales'], { now: NOW })
    expect(deltas.total).toMatchObject({ current: 4, previous: 3, change: '+33.3%', tone: 'positive' })
    expect(deltas.byRole.Admin).toMatchObject({ current: 1, previous: 1, change: '0.0%', tone: 'neutral' })
    expect(deltas.byRole.Sales).toMatchObject({ current: 3, previous: 2, change: '+50.0%', tone: 'positive' })
  })
})
