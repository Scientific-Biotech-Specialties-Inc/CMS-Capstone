import { ref } from 'vue'
import { useAuth } from './useAuth'
import type {
  AggregatedMetricEntry,
  AnalyticsSummary,
  ContractMarginsResponse,
  DiagnosticInsight,
  PredictiveInsight,
  RefreshResult,
} from '@/types/analytics'

// Feature 4: Analytics — reads analytics-service's /analytics/summary,
// /analytics/diagnostics, and /analytics/predictive endpoints. Admin/Manager-only,
// enforced both by the router guard and the backend's auth_role check.
const BASE_URL = import.meta.env.VITE_ANALYTICS_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  return {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
    'Authorization': `Bearer ${state.token}`,
  }
}

function mapMetricEntry(m: any): AggregatedMetricEntry {
  return {
    metricType:  m.metric_type,
    metricValue: m.metric_value !== undefined ? m.metric_value : null,
    metadata:    m.metadata ?? null,
  }
}

function mapSummary(d: any): AnalyticsSummary {
  const metrics: Record<string, AggregatedMetricEntry[]> = {}
  const rawMetrics = d.metrics ?? {}
  Object.keys(rawMetrics).forEach(service => {
    metrics[service] = (rawMetrics[service] ?? []).map(mapMetricEntry)
  })
  return { asOf: d.as_of ?? null, metrics }
}

function mapInsight(i: any): DiagnosticInsight {
  return {
    metricType:     i.metric_type,
    periodStart:    i.period_start,
    periodEnd:      i.period_end,
    findingSummary: i.finding_summary,
    aiNarrative:    i.ai_narrative,
    generatedAt:    i.generated_at,
  }
}

function mapPredictive(p: any): PredictiveInsight {
  return {
    metricType:           p.metric_type,
    forecastDate:         p.forecast_date,
    forecastHorizonDays: p.forecast_horizon_days ?? 30,
    historicalSeries:     p.historical_series ?? [],
    predictedSeries:      p.predicted_series ?? [],
    confidence:           p.confidence ?? 'medium',
    aiNarrative:          p.ai_narrative ?? null,
    generatedAt:          p.generated_at,
  }
}

export function useAnalytics() {
  const summary = ref<AnalyticsSummary | null>(null)
  const insights = ref<DiagnosticInsight[]>([])
  const predictions = ref<PredictiveInsight[]>([])
  const contractMargins = ref<ContractMarginsResponse | null>(null)
  const loadingSummary = ref(false)
  const loadingDiagnostics = ref(false)
  const loadingPredictive = ref(false)
  const loadingContractMargins = ref(false)
  const refreshing = ref(false)
  const error = ref<string | null>(null)

  async function fetchSummary(): Promise<AnalyticsSummary | null> {
    loadingSummary.value = true
    error.value = null
    try {
      const res = await fetch(`${BASE_URL}/analytics/summary`, { headers: makeHeaders() })
      if (!res.ok) {
        error.value = res.status === 403 ? 'You do not have access to analytics.' : 'Failed to load analytics summary.'
        summary.value = null
        return null
      }
      const json = await res.json()
      summary.value = mapSummary(json.data)
      return summary.value
    } catch (e) {
      console.error('Failed to fetch analytics summary', e)
      error.value = 'Could not reach the analytics service.'
      summary.value = null
      return null
    } finally {
      loadingSummary.value = false
    }
  }

  async function fetchDiagnostics(): Promise<DiagnosticInsight[]> {
    loadingDiagnostics.value = true
    error.value = null
    try {
      const res = await fetch(`${BASE_URL}/analytics/diagnostics`, { headers: makeHeaders() })
      if (!res.ok) {
        error.value = res.status === 403 ? 'You do not have access to analytics.' : 'Failed to load diagnostic insights.'
        insights.value = []
        return []
      }
      const json = await res.json()
      insights.value = (json.data ?? []).map(mapInsight)
      return insights.value
    } catch (e) {
      console.error('Failed to fetch diagnostic insights', e)
      error.value = 'Could not reach the analytics service.'
      insights.value = []
      return []
    } finally {
      loadingDiagnostics.value = false
    }
  }

  async function fetchPredictive(): Promise<PredictiveInsight[]> {
    loadingPredictive.value = true
    error.value = null
    try {
      const res = await fetch(`${BASE_URL}/analytics/predictive`, { headers: makeHeaders() })
      if (!res.ok) {
        error.value = res.status === 403 ? 'You do not have access to analytics.' : 'Failed to load predictive insights.'
        predictions.value = []
        return []
      }
      const json = await res.json()
      predictions.value = (json.data ?? []).map(mapPredictive)
      return predictions.value
    } catch (e) {
      console.error('Failed to fetch predictive insights', e)
      error.value = 'Could not reach the analytics service.'
      predictions.value = []
      return []
    } finally {
      loadingPredictive.value = false
    }
  }

  async function refresh(): Promise<RefreshResult | null> {
    refreshing.value = true
    error.value = null
    try {
      const res = await fetch(`${BASE_URL}/analytics/refresh`, { method: 'POST', headers: makeHeaders() })
      if (!res.ok) {
        error.value = 'Failed to refresh analytics.'
        return null
      }
      const json = await res.json()
      return {
        message: json.message,
        metricsWritten: json.metrics_written,
        insightsGenerated: json.insights_generated,
        predictionsGenerated: json.predictions_generated,
      }
    } catch (e) {
      console.error('Failed to refresh analytics', e)
      error.value = 'Could not reach the analytics service.'
      return null
    } finally {
      refreshing.value = false
    }
  }

  async function fetchContractMargins(): Promise<ContractMarginsResponse | null> {
    loadingContractMargins.value = true
    error.value = null
    try {
      const res = await fetch(`${BASE_URL}/analytics/contract-margins`, { headers: makeHeaders() })
      if (!res.ok) {
        error.value = res.status === 403 ? 'You do not have access to contract margin analytics.' : 'Failed to load contract margins.'
        contractMargins.value = null
        return null
      }
      const json = await res.json()
      const d = json.data ?? {}
      contractMargins.value = {
        sermsConnected: Boolean(d.serms_connected),
        summary: {
          contractsCount: Number(d.summary?.contracts_count ?? 0),
          totalContractValue: Number(d.summary?.total_contract_value ?? 0),
          totalExpenses: Number(d.summary?.total_expenses ?? 0),
          totalNetMargin: Number(d.summary?.total_net_margin ?? 0),
        },
        contracts: (d.contracts ?? []).map((c: any) => ({
          contractId: Number(c.contract_id),
          contractCode: c.contract_code ?? null,
          clientName: c.client_name ?? null,
          category: c.category ?? null,
          totalContractValue: c.total_contract_value !== null && c.total_contract_value !== undefined ? Number(c.total_contract_value) : null,
          actualExpenses: Number(c.actual_expenses ?? 0),
          expenseBreakdown: {
            travel: Number(c.expense_breakdown?.travel ?? 0),
            spareParts: Number(c.expense_breakdown?.spare_parts ?? 0),
            fieldService: Number(c.expense_breakdown?.field_service ?? 0),
            other: Number(c.expense_breakdown?.other ?? 0),
          },
          burnRatePct: c.burn_rate_pct !== null && c.burn_rate_pct !== undefined ? Number(c.burn_rate_pct) : null,
          trueNetMargin: c.true_net_margin !== null && c.true_net_margin !== undefined ? Number(c.true_net_margin) : null,
          marginPct: c.margin_pct !== null && c.margin_pct !== undefined ? Number(c.margin_pct) : null,
          startDate: c.start_date ?? null,
          endDate: c.end_date ?? null,
        })),
      }
      return contractMargins.value
    } catch (e) {
      console.error('Failed to fetch contract margins', e)
      error.value = 'Could not reach the analytics service.'
      contractMargins.value = null
      return null
    } finally {
      loadingContractMargins.value = false
    }
  }

  return {
    summary,
    insights,
    predictions,
    contractMargins,
    loadingSummary,
    loadingDiagnostics,
    loadingPredictive,
    loadingContractMargins,
    refreshing,
    error,
    fetchSummary,
    fetchDiagnostics,
    fetchPredictive,
    fetchContractMargins,
    refresh,
  }
}
