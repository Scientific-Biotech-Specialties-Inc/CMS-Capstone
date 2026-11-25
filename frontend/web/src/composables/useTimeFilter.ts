import { ref, computed } from 'vue'

// Shared "Today / This Week / Last Month / All Time" time filter used across dashboard
// Overview tabs (admin/manager/sales) to scope chart data consistently.
export type TimeFilterOption = 'today' | 'week' | 'month' | 'all'

export const TIME_FILTER_LABELS: Record<TimeFilterOption, string> = {
  today: 'Today',
  week:  'This Week',
  month: 'Last Month',
  all:   'All Time',
}

export interface DateRange {
  from: Date
  to: Date
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

/** Formats a Date as local YYYY-MM-DD (avoids UTC day shifts in UTC+8). */
export function localDateKey(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/** Parses YYYY-MM-DD at local midnight; falls back to Date constructor for ISO timestamps. */
export function parseLocalDate(isoDate: string | null | undefined): Date | null {
  if (!isoDate) return null
  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate.trim())
  if (dateOnly) {
    return new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]))
  }
  const d = new Date(isoDate)
  return isNaN(d.getTime()) ? null : d
}

export function yearRange(year: number): DateRange {
  return {
    from: new Date(year, 0, 1, 0, 0, 0, 0),
    to:   new Date(year, 11, 31, 23, 59, 59, 999),
  }
}

/** Computes the [from, to] boundaries for a given filter option, anchored to now. */
export function dateRangeFor(option: TimeFilterOption, now: Date = new Date()): DateRange {
  if (option === 'all') {
    return {
      from: new Date(1970, 0, 1, 0, 0, 0, 0),
      to:   new Date(9999, 11, 31, 23, 59, 59, 999),
    }
  }

  const to = endOfDay(now)

  if (option === 'today') {
    return { from: startOfDay(now), to }
  }

  if (option === 'week') {
    // Monday-start week containing `now`.
    const day = now.getDay() // 0 = Sunday
    const diffToMonday = day === 0 ? 6 : day - 1
    const monday = startOfDay(now)
    monday.setDate(monday.getDate() - diffToMonday)
    return { from: monday, to }
  }

  // 'month': the most recently completed calendar month.
  const firstOfThisMonth = new Date(now.getFullYear(), now.getMonth(), 1)
  const firstOfLastMonth = new Date(firstOfThisMonth.getFullYear(), firstOfThisMonth.getMonth() - 1, 1)
  const lastOfLastMonth = new Date(firstOfThisMonth.getTime() - 1)
  return { from: startOfDay(firstOfLastMonth), to: endOfDay(lastOfLastMonth) }
}

/** Returns true if the given ISO date string falls inside the range. */
export function isWithinRange(isoDate: string | null | undefined, range: DateRange): boolean {
  const d = parseLocalDate(isoDate)
  if (!d) return false
  return d >= range.from && d <= range.to
}

export function useTimeFilter(initial: TimeFilterOption = 'all') {
  const filter = ref<TimeFilterOption>(initial)
  const range = computed(() => dateRangeFor(filter.value))

  function setFilter(option: TimeFilterOption) {
    filter.value = option
  }

  return {
    filter,
    range,
    setFilter,
    options: (['today', 'week', 'month', 'all'] as TimeFilterOption[]),
    labels: TIME_FILTER_LABELS,
  }
}
