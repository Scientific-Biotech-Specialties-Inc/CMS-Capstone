import {
  dateRangeFor,
  isWithinRange,
  parseLocalDate,
  yearRange,
  type DateRange,
  type TimeFilterOption,
} from '@/composables/useTimeFilter'
import {
  EXPIRING_THRESHOLD_DAYS,
  contractLifecycle,
  deriveLifecycleStatus,
  remainingDays,
  type Contract,
  type ContractLifecycleStatus,
  type ContractWithDays,
} from '@/types/contract'
import type { ContractRequest } from '@/types/contractRequest'

export type StatDeltaTone = 'positive' | 'negative' | 'neutral'

export interface StatDelta {
  change: string
  tone: StatDeltaTone
  tooltip: string
  current: number
  previous: number
}

export interface ComputeStatDeltaOptions {
  invertPolarity?: boolean
  comparisonLabel?: string
}

export interface PeriodScopeOptions {
  filter?: TimeFilterOption
  selectedYear?: number | null
  now?: Date
}

export interface ContractStatDeltaOptions extends PeriodScopeOptions {
  approvedOnly?: boolean
}

export interface ContractStatDeltas {
  total: StatDelta
  active: StatDelta
  inactive: StatDelta
  expiring: StatDelta
  expired: StatDelta
}

export interface RequestStatDeltas {
  total: StatDelta
  pending: StatDelta
  reviewing: StatDelta
  pendingOrReviewing: StatDelta
  approved: StatDelta
}

export interface UserStatDeltas {
  total: StatDelta
  byRole: Record<string, StatDelta>
}

function startOfDay(d: Date): Date {
  const copy = new Date(d)
  copy.setHours(0, 0, 0, 0)
  return copy
}

function endOfDay(d: Date): Date {
  const copy = new Date(d)
  copy.setHours(23, 59, 59, 999)
  return copy
}

function diffDaysFrom(endDateStr: string, referenceDate: Date): number {
  const end = parseLocalDate(endDateStr)
  if (!end) return Number.POSITIVE_INFINITY
  const endDay = startOfDay(end)
  const refDay = startOfDay(referenceDate)
  return Math.floor((endDay.getTime() - refDay.getTime()) / 86_400_000)
}

function contractLifecycleAsOf(contract: Contract, asOfDate: Date, isToday: boolean): ContractLifecycleStatus {
  if (contract.lifecycleStatus === 'pending_execution') return 'pending_execution'
  if (contract.approvalStatus !== 'Approved') return 'inactive'
  if (isToday) {
    const withDays: ContractWithDays = {
      ...contract,
      days: 'days' in contract && typeof (contract as ContractWithDays).days === 'number'
        ? (contract as ContractWithDays).days
        : remainingDays(contract.endDate),
    }
    return contractLifecycle(withDays)
  }
  if (!contract.endDate) return contract.lifecycleStatus ?? 'active'
  const days = diffDaysFrom(contract.endDate, asOfDate)
  return deriveLifecycleStatus(days, contract.approvalStatus)
}

function existedBefore(isoDate: string | null | undefined, cutoff: Date): boolean {
  const parsed = parseLocalDate(isoDate)
  if (!parsed) return true
  return parsed < cutoff
}

/**
 * Formats a numeric current vs. previous comparison into a percentage delta pill.
 * Handles 0-baselines and floating-point rounding cleanly without NaN, Infinity, or -0.0%.
 */
export function computeStatDelta(
  current: number,
  previous: number,
  options: ComputeStatDeltaOptions = {}
): StatDelta {
  const { invertPolarity = false, comparisonLabel = 'vs yesterday' } = options
  const diff = current - previous

  if (diff === 0) {
    return {
      change: '0.0%',
      tone: 'neutral',
      tooltip: `No change ${comparisonLabel} (${previous} → ${current})`,
      current,
      previous,
    }
  }

  if (previous <= 0) {
    const tone: StatDeltaTone = invertPolarity ? 'negative' : 'positive'
    return {
      change: '+100.0%',
      tone,
      tooltip: `+${diff} ${comparisonLabel} (${previous} → ${current})`,
      current,
      previous,
    }
  }

  const rawPct = (diff / previous) * 100
  if (Math.abs(rawPct) < 0.05) {
    return {
      change: '0.0%',
      tone: 'neutral',
      tooltip: `No change ${comparisonLabel} (${previous} → ${current})`,
      current,
      previous,
    }
  }

  const sign = rawPct > 0 ? '+' : '-'
  const change = `${sign}${Math.abs(rawPct).toFixed(1)}%`
  const isUp = rawPct > 0
  const tone: StatDeltaTone = isUp
    ? (invertPolarity ? 'negative' : 'positive')
    : (invertPolarity ? 'positive' : 'negative')
  const signedDiff = diff > 0 ? `+${diff}` : String(diff)

  return {
    change,
    tone,
    tooltip: `${signedDiff} ${comparisonLabel} (${previous} → ${current})`,
    current,
    previous,
  }
}

/**
 * Returns the immediately preceding equivalent DateRange for a dashboard time filter,
 * or null when viewing "All Time" (which compares cumulative today vs. yesterday).
 */
export function previousDateRangeFor(
  filter: TimeFilterOption = 'all',
  selectedYear: number | null = null,
  now: Date = new Date()
): { range: DateRange; label: string } | null {
  if (selectedYear !== null) {
    const prevYear = selectedYear - 1
    return {
      range: yearRange(prevYear),
      label: `vs ${prevYear}`,
    }
  }

  if (filter === 'all') {
    return null
  }

  if (filter === 'today') {
    const yesterday = new Date(now)
    yesterday.setDate(yesterday.getDate() - 1)
    return {
      range: { from: startOfDay(yesterday), to: endOfDay(yesterday) },
      label: 'vs yesterday',
    }
  }

  if (filter === 'week') {
    const currentWeek = dateRangeFor('week', now)
    const prevMonday = new Date(currentWeek.from)
    prevMonday.setDate(prevMonday.getDate() - 7)
    const prevSunday = new Date(currentWeek.from)
    prevSunday.setDate(prevSunday.getDate() - 1)
    return {
      range: { from: startOfDay(prevMonday), to: endOfDay(prevSunday) },
      label: 'vs last week',
    }
  }

  // 'month' in useTimeFilter is the most recently completed calendar month;
  // compare it against the calendar month immediately before that.
  const firstOfTwoMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 2, 1)
  const lastOfTwoMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 1, 0)
  return {
    range: { from: startOfDay(firstOfTwoMonthsAgo), to: endOfDay(lastOfTwoMonthsAgo) },
    label: 'vs prior month',
  }
}

function currentDateRangeFor(
  filter: TimeFilterOption,
  selectedYear: number | null,
  now: Date
): DateRange | null {
  if (selectedYear !== null) return yearRange(selectedYear)
  if (filter === 'all') return null
  return dateRangeFor(filter, now)
}

function countContractBuckets(
  contracts: Contract[],
  asOfDate: Date,
  isToday: boolean
): { total: number; active: number; inactive: number; expiring: number; expired: number } {
  let active = 0
  let inactive = 0
  let expiring = 0
  let expired = 0

  for (const c of contracts) {
    const status = contractLifecycleAsOf(c, asOfDate, isToday)
    if (status === 'active') active++
    else if (status === 'inactive') inactive++
    else if (status === 'expiring') expiring++
    else if (status === 'expired') expired++
  }

  return { total: contracts.length, active, inactive, expiring, expired }
}

/**
 * Computes real percentage deltas for Contract stat cards.
 * - Filtered mode (today / week / month / year): compares contracts with startDate in the
 *   active period against the immediately preceding equivalent period.
 * - All-Time / List mode: compares today's total & lifecycle counts against yesterday's
 *   baseline (contracts existing before 00:00 today, evaluated at yesterday's date).
 */
export function computeContractStatDeltas(
  allContracts: Contract[],
  options: ContractStatDeltaOptions = {}
): ContractStatDeltas {
  const {
    filter = 'all',
    selectedYear = null,
    approvedOnly = false,
    now = new Date(),
  } = options

  const pool = approvedOnly
    ? allContracts.filter(c => c.approvalStatus === 'Approved')
    : allContracts

  const currentRange = currentDateRangeFor(filter, selectedYear, now)
  const prevInfo = previousDateRangeFor(filter, selectedYear, now)

  if (currentRange && prevInfo) {
    const currentSubset = pool.filter(c => isWithinRange(c.startDate, currentRange))
    const prevSubset = pool.filter(c => isWithinRange(c.startDate, prevInfo.range))
    const cur = countContractBuckets(currentSubset, now, true)
    const prev = countContractBuckets(prevSubset, now, true)

    return {
      total: computeStatDelta(cur.total, prev.total, { comparisonLabel: prevInfo.label }),
      active: computeStatDelta(cur.active, prev.active, { comparisonLabel: prevInfo.label }),
      inactive: computeStatDelta(cur.inactive, prev.inactive, { invertPolarity: true, comparisonLabel: prevInfo.label }),
      expiring: computeStatDelta(cur.expiring, prev.expiring, { invertPolarity: true, comparisonLabel: prevInfo.label }),
      expired: computeStatDelta(cur.expired, prev.expired, { invertPolarity: true, comparisonLabel: prevInfo.label }),
    }
  }

  // All-Time / List mode: compare today vs. yesterday's baseline
  const todayStart = startOfDay(now)
  const yesterday = new Date(todayStart)
  yesterday.setDate(yesterday.getDate() - 1)

  const cur = countContractBuckets(pool, now, true)
  const existedYesterday = pool.filter(c => existedBefore(c.createdAt ?? c.startDate, todayStart))
  const prev = countContractBuckets(existedYesterday, yesterday, false)

  return {
    total: computeStatDelta(cur.total, prev.total, { comparisonLabel: 'vs yesterday' }),
    active: computeStatDelta(cur.active, prev.active, { comparisonLabel: 'vs yesterday' }),
    inactive: computeStatDelta(cur.inactive, prev.inactive, { invertPolarity: true, comparisonLabel: 'vs yesterday' }),
    expiring: computeStatDelta(cur.expiring, prev.expiring, { invertPolarity: true, comparisonLabel: 'vs yesterday' }),
    expired: computeStatDelta(cur.expired, prev.expired, { invertPolarity: true, comparisonLabel: 'vs yesterday' }),
  }
}

function countRequestBuckets(requests: ContractRequest[]) {
  let pending = 0
  let reviewing = 0
  let approved = 0

  for (const r of requests) {
    if (r.status === 'Pending') pending++
    else if (r.status === 'Under Review') reviewing++
    else if (r.status === 'Approved') approved++
  }

  return {
    total: requests.length,
    pending,
    reviewing,
    pendingOrReviewing: pending + reviewing,
    approved,
  }
}

/**
 * Computes real percentage deltas for Contract Request stat cards using `requestDate`.
 */
export function computeRequestStatDeltas(
  allRequests: ContractRequest[],
  options: PeriodScopeOptions = {}
): RequestStatDeltas {
  const { filter = 'all', selectedYear = null, now = new Date() } = options
  const currentRange = currentDateRangeFor(filter, selectedYear, now)
  const prevInfo = previousDateRangeFor(filter, selectedYear, now)

  if (currentRange && prevInfo) {
    const cur = countRequestBuckets(allRequests.filter(r => isWithinRange(r.requestDate, currentRange)))
    const prev = countRequestBuckets(allRequests.filter(r => isWithinRange(r.requestDate, prevInfo.range)))

    return {
      total: computeStatDelta(cur.total, prev.total, { comparisonLabel: prevInfo.label }),
      pending: computeStatDelta(cur.pending, prev.pending, { comparisonLabel: prevInfo.label }),
      reviewing: computeStatDelta(cur.reviewing, prev.reviewing, { comparisonLabel: prevInfo.label }),
      pendingOrReviewing: computeStatDelta(cur.pendingOrReviewing, prev.pendingOrReviewing, { comparisonLabel: prevInfo.label }),
      approved: computeStatDelta(cur.approved, prev.approved, { comparisonLabel: prevInfo.label }),
    }
  }

  const todayStart = startOfDay(now)
  const cur = countRequestBuckets(allRequests)
  const prev = countRequestBuckets(allRequests.filter(r => existedBefore(r.requestDate, todayStart)))

  return {
    total: computeStatDelta(cur.total, prev.total, { comparisonLabel: 'vs yesterday' }),
    pending: computeStatDelta(cur.pending, prev.pending, { comparisonLabel: 'vs yesterday' }),
    reviewing: computeStatDelta(cur.reviewing, prev.reviewing, { comparisonLabel: 'vs yesterday' }),
    pendingOrReviewing: computeStatDelta(cur.pendingOrReviewing, prev.pendingOrReviewing, { comparisonLabel: 'vs yesterday' }),
    approved: computeStatDelta(cur.approved, prev.approved, { comparisonLabel: 'vs yesterday' }),
  }
}

/**
 * Computes real percentage deltas for User / Employee stat cards using `createdAt`.
 * Always compares total headcount against the baseline before the active window starts
 * (or before today for All Time / Today).
 */
export function computeUserStatDeltas(
  users: { role?: string; createdAt?: string | null }[],
  roleNames: string[] = [],
  options: PeriodScopeOptions = {}
): UserStatDeltas {
  const { filter = 'all', selectedYear = null, now = new Date() } = options
  const currentRange = currentDateRangeFor(filter, selectedYear, now)
  const cutoff = currentRange ? currentRange.from : startOfDay(now)

  const comparisonLabel = selectedYear !== null
    ? `since start of ${selectedYear}`
    : filter === 'week'
      ? 'vs start of week'
      : filter === 'month'
        ? 'vs start of last month'
        : 'vs yesterday'

  const existedBeforeCutoff = users.filter(u => existedBefore(u.createdAt, cutoff))
  const total = computeStatDelta(users.length, existedBeforeCutoff.length, { comparisonLabel })

  const byRole: Record<string, StatDelta> = {}
  for (const roleName of roleNames) {
    const curCount = users.filter(u => u.role === roleName).length
    const prevCount = existedBeforeCutoff.filter(u => u.role === roleName).length
    byRole[roleName] = computeStatDelta(curCount, prevCount, { comparisonLabel })
  }

  return { total, byRole }
}

export const STAT_DELTA_EXPIRING_DAYS = EXPIRING_THRESHOLD_DAYS
