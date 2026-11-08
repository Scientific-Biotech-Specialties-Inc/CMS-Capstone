import { ref, computed } from 'vue'
import { useRiskAssessment } from './useRiskAssessment'
import type { Contract } from '@/types/contract'
import type { RiskLevel } from '@/types/riskAssessment'

// Employee/Sales-scoped diagnostic & predictive view. Unlike useAnalytics
// (system-wide /analytics/diagnostics + /analytics/predictive, which has no
// per-user scoping), this composable derives insights purely from the
// current employee's own contracts via the existing bulk risk-levels
// endpoint, so no cross-user data is ever pulled into an Employee's view.

export interface OwnRiskBreakdown {
  level: RiskLevel | 'unassessed'
  count: number
}

export function useOwnRiskInsights() {
  const { fetchBulkLevels } = useRiskAssessment()

  const loading = ref(false)
  const levelsByContract = ref<Record<string, { riskLevel: RiskLevel; findingsCount: number; status: string }>>({})

  async function load(contracts: Contract[]) {
    loading.value = true
    try {
      const ids = contracts.map(c => c.id).filter(Boolean)
      const result = await fetchBulkLevels(ids)
      levelsByContract.value = result ?? {}
    } finally {
      loading.value = false
    }
  }

  function breakdown(contracts: Contract[]): OwnRiskBreakdown[] {
    const counts: Record<string, number> = { low: 0, medium: 0, high: 0, critical: 0, unassessed: 0 }
    contracts.forEach(c => {
      const entry = levelsByContract.value[c.id]
      if (entry?.riskLevel && entry.status === 'completed') {
        counts[entry.riskLevel] = (counts[entry.riskLevel] ?? 0) + 1
      } else {
        counts.unassessed++
      }
    })
    return (['low', 'medium', 'high', 'critical', 'unassessed'] as const).map(level => ({
      level,
      count: counts[level] ?? 0,
    }))
  }

  function highRiskContracts(contracts: Contract[]): Contract[] {
    return contracts.filter(c => {
      const entry = levelsByContract.value[c.id]
      return entry?.status === 'completed' && (entry.riskLevel === 'high' || entry.riskLevel === 'critical')
    })
  }

  const scannedCount = computed(() =>
    Object.values(levelsByContract.value).filter(e => e.status === 'completed').length
  )

  return {
    loading,
    levelsByContract,
    load,
    breakdown,
    highRiskContracts,
    scannedCount,
  }
}
