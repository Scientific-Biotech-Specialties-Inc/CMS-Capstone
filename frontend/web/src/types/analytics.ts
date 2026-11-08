// Feature 4: Analytics — types matching analytics-service's
// /analytics/summary, /analytics/diagnostics, and /analytics/predictive response shapes.

export interface AggregatedMetricEntry {
  metricType: string
  metricValue: number | null
  metadata: Record<string, any> | null
}

export interface AnalyticsSummary {
  asOf: string | null
  // Keyed by source_service (contract-management, vendor-management, notification, ai-service)
  metrics: Record<string, AggregatedMetricEntry[]>
}

export interface DiagnosticInsight {
  metricType: string
  periodStart: string
  periodEnd: string
  findingSummary: Record<string, any>
  aiNarrative: string | null
  generatedAt: string
}

export interface TimePoint {
  date: string
  value: number
}

export interface PredictiveInsight {
  metricType: string
  forecastDate: string
  forecastHorizonDays: number
  historicalSeries: TimePoint[]
  predictedSeries: TimePoint[]
  confidence: 'high' | 'medium' | 'low'
  aiNarrative: string | null
  generatedAt: string
}

export interface RefreshResult {
  message: string
  metricsWritten: Record<string, number>
  insightsGenerated: number
  predictionsGenerated?: number
}

// ── Display labels ──────────────────────────────────────────────────

export const serviceLabels: Record<string, string> = {
  'contract-management': 'Contracts',
  'vendor-management':   'Vendors & Partners',
  'notification':        'Notifications',
  'ai-service':          'AI Services',
}

export const metricLabels: Record<string, string> = {
  contracts_total:                 'Total Contracts',
  contracts_expiring_soon_30d:     'Expiring in 30 Days',
  contracts_expired:               'Expired Contracts',
  contracts_avg_approval_hours:    'Avg. Approval Time (hrs)',
  high_risk_approvals_pending:     'High-Risk Approvals Pending',
  high_risk_approvals_escalated:   'High-Risk Approvals Escalated',
  suppliers_total:                 'Total Suppliers',
  partners_total:                  'Total Business Partners',
  notifications_total:             'Total Notifications',
  emails_sent:                     'Emails Sent',
  emails_failed:                   'Emails Failed',
  email_success_rate_pct:          'Email Success Rate (%)',
  contracts_scanned:               'Contracts Scanned',
  scans_completed:                 'AI Scans Completed',
  scans_failed:                    'AI Scans Failed',
  avg_risk_score:                  'Avg. Risk Score',
  vendor_suggestions_total:        'Vendor Suggestions Generated',
  vendor_suggestions_accepted:     'Vendor Suggestions Accepted',
}

export const diagnosticLabels: Record<string, string> = {
  risk_flag_rate:           'Risk Flag Rate',
  approval_sla_bottleneck:  'Approval SLA Bottleneck',
}

export const predictiveLabels: Record<string, string> = {
  risk_score_forecast:     'Average Contract Risk Score Forecast',
  approval_time_forecast:  'Approval SLA Turnaround Forecast',
}

export const findingFieldLabels: Record<string, string> = {
  metric:                          'Metric',
  current_period_avg:             'Current Period Avg.',
  prior_period_avg:                'Prior Period Avg.',
  delta:                           'Change',
  current_period_avg_hours:       'Current Period Avg. (hrs)',
  prior_period_avg_hours:         'Prior Period Avg. (hrs)',
  delta_hours:                    'Change (hrs)',
  high_risk_approvals_pending:    'High-Risk Approvals Pending',
  high_risk_approvals_escalated:  'High-Risk Approvals Escalated',
}
