import { ref, computed } from 'vue'

// Shared "Today / This Week / Last Month" time filter used across dashboard
// Overview tabs (admin/manager/sales) to scope chart data consistently.
export type TimeFilterOption = 'today' | 'week' | 'month'

export const TIME_FILTER_LABELS: Record<TimeFilterOption, string> = {
  today: 'Today',
  week:  'This Week',
  month: 'Last Month',
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

/** Computes the [from, to] boundaries for a given filter option, anchored to now. */
export function dateRangeFor(option: TimeFilterOption, now: Date = new Date()): DateRange {
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
  if (!isoDate) return false
  const d = new Date(isoDate)
  if (isNaN(d.getTime())) return false
  return d >= range.from && d <= range.to
}

export function useTimeFilter(initial: TimeFilterOption = 'month') {
  const filter = ref<TimeFilterOption>(initial)
  const range = computed(() => dateRangeFor(filter.value))

  function setFilter(option: TimeFilterOption) {
    filter.value = option
  }

  return {
    filter,
    range,
    setFilter,
    options: (['today', 'week', 'month'] as TimeFilterOption[]),
    labels: TIME_FILTER_LABELS,
  }
}
