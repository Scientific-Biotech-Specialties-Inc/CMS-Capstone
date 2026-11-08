import type { ContractWithDays } from '@/types/contract'

/**
 * Section grouping + ordering for the contract lists.
 *
 * The "All" list is split into explicit, labelled sections so the ordering
 * reads intentionally instead of as a jumble:
 *
 *   Pending Approval -> Expiring Soon -> Active -> Expired -> Rejected
 *
 * Only `Approved` contracts participate in the lifecycle sections
 * (expiring/active/expired); `Pending` and `Rejected` are approval states and
 * get their own sections. This also fixes the old quirk where `Pending`
 * contracts were urgency-sorted alongside approved ones.
 */
export type ContractGroupKey =
  | 'pending'
  | 'expiring'
  | 'active'
  | 'expired'
  | 'rejected'

export interface ContractGroupMeta {
  key: ContractGroupKey
  label: string
  /** Section accent bar colour. Semantic DESIGN.md status colour. */
  hex: string
}

export const CONTRACT_GROUPS: ContractGroupMeta[] = [
  { key: 'pending',  label: 'Pending Approval', hex: '#F59E0B' }, // Warning / Pending
  { key: 'expiring', label: 'Expiring Soon',    hex: '#F59E0B' }, // Warning / Pending
  { key: 'active',   label: 'Active',           hex: '#10B981' }, // Success / Approved
  { key: 'expired',  label: 'Expired',          hex: '#EF4444' }, // Error
  { key: 'rejected', label: 'Rejected',         hex: '#EF4444' }, // Error
]

const GROUP_ORDER: Record<ContractGroupKey, number> = {
  pending: 0,
  expiring: 1,
  active: 2,
  expired: 3,
  rejected: 4,
}

const EXPIRING_THRESHOLD_DAYS = 30

/** Missing/invalid `end_date` yields NaN days — treat as "still in force". */
function normaliseDays(days: number): number {
  return Number.isFinite(days) ? days : Number.POSITIVE_INFINITY
}

export function contractGroup(c: ContractWithDays): ContractGroupKey {
  if (c.approvalStatus === 'Rejected') return 'rejected'
  if (c.approvalStatus === 'Pending') return 'pending'

  const days = normaliseDays(c.days)
  if (days < 0) return 'expired'
  if (days <= EXPIRING_THRESHOLD_DAYS) return 'expiring'
  return 'active'
}

export function groupCounts(list: ContractWithDays[]): Record<ContractGroupKey, number> {
  const counts: Record<ContractGroupKey, number> = {
    pending: 0, expiring: 0, active: 0, expired: 0, rejected: 0,
  }
  for (const c of list) counts[contractGroup(c)]++
  return counts
}

/** Newest first, using the numeric DB id (the display id may be a code). */
function newestFirst(a: ContractWithDays, b: ContractWithDays): number {
  return (b.contractDbId ?? 0) - (a.contractDbId ?? 0)
}

/**
 * Sort a filtered contract list into its sections. Stable and non-mutating.
 */
export function sortContractsForAll<T extends ContractWithDays>(list: T[]): T[] {
  return [...list].sort((a, b) => {
    const group = contractGroup(a)
    const orderDiff = GROUP_ORDER[group] - GROUP_ORDER[contractGroup(b)]
    if (orderDiff !== 0) return orderDiff

    switch (group) {
      // Awaiting action / terminal: newest first.
      case 'pending':
      case 'rejected':
        return newestFirst(a, b)
      // Most recently expired first (closest to 0).
      case 'expired':
        return normaliseDays(b.days) - normaliseDays(a.days)
      // Lifecycle: most urgent first (fewest days remaining).
      case 'expiring':
      case 'active':
      default:
        return normaliseDays(a.days) - normaliseDays(b.days)
    }
  })
}
