// Types shared by the AI Risk Assessment (US-026) and High-Risk Approval Gate
// (US-023) features. Severity uses the RAG (Red/Amber/Green) convention as a
// deliberate, scoped exception to the brand-colors-only rule — see
// IMPLEMENTATION_PLAN_AI_RISK_VENDOR_ANALYTICS.md for the rationale. All other
// chrome on these screens still uses the brand navy/blue palette.

export type RiskLevel = 'low' | 'medium' | 'high' | 'critical'

export interface RiskFinding {
  id:                     number
  clauseReference:        string
  severity:                RiskLevel
  playbookClauseCode?:    string
  playbookClauseTitle?:   string
  deviationReason:        string
  recommendedRemediation: string
}

export interface RiskAssessmentSummary {
  contractId:  string
  riskScore:   number | null
  riskLevel:   RiskLevel | null
  status:      'pending' | 'completed' | 'failed'
  scannedAt:   string | null
  findings:    RiskFinding[]
}

// RAG severity → badge classes (pill / text tags).
export const severityBadge: Record<RiskLevel, string> = {
  low:      'bg-emerald-50 text-emerald-700 border-emerald-200',
  medium:   'bg-amber-50 text-amber-700 border-amber-200',
  high:     'bg-red-50 text-red-600 border-red-200',
  critical: 'bg-red-50 text-red-600 border-red-200',
}

// RAG severity → icon color (Tailwind text color classes) for the compact
// flag/warning indicator shown on contract tables, headers, and the approval queue.
export const severityIconColor: Record<RiskLevel, string> = {
  low:      'text-emerald-600',
  medium:   'text-amber-600',
  high:     'text-red-600',
  critical: 'text-red-600',
}

export const severityLabel: Record<RiskLevel, string> = {
  low:      'Low Risk',
  medium:   'Medium Risk',
  high:     'High Risk',
  critical: 'Critical Risk',
}

export function isHighRisk(level: RiskLevel | null | undefined): boolean {
  return level === 'high' || level === 'critical'
}

// ── US-023: High-Risk Contract Approval Gate ────────────────────────────────

export interface HighRiskApproval {
  id:           number
  contractId:   number
  riskLevel:    RiskLevel
  approverId:   number | null
  rationale:    string | null
  decision:     'approved' | 'rejected' | null
  decidedAt:    string | null
  flaggedAt:    string | null
  slaDueAt:     string | null
  escalatedAt:  string | null
}

export interface HighRiskGateState {
  contractId:                 number
  riskLevel:                  RiskLevel | null
  requiresHighRiskApproval:   boolean
  blocked:                    boolean
  approval:                   HighRiskApproval | null
}
