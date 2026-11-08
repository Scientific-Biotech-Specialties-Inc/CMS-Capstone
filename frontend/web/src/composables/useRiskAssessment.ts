import { ref } from 'vue'
import { useAuth } from './useAuth'
import type { RiskAssessmentSummary, RiskFinding, RiskLevel } from '@/types/riskAssessment'

// US-026: AI Risk Assessment — reads/triggers the RAG pipeline exposed by
// ai-service's /contracts/{id}/risk-assessment/* endpoints.
const BASE_URL = import.meta.env.VITE_AI_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  return {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
    'Authorization': `Bearer ${state.token}`,
  }
}

function mapFinding(f: any): RiskFinding {
  return {
    id:                  f.id,
    clauseReference:     f.clause_reference,
    severity:            f.severity,
    playbookClauseCode:  f.playbook_clause_code,
    playbookClauseTitle: f.playbook_clause_title,
    deviationReason:     f.deviation_reason,
    recommendedRemediation: f.recommended_remediation,
  }
}

export function useRiskAssessment() {
  const summary = ref<RiskAssessmentSummary | null>(null)
  const loading = ref(false)
  const scanning = ref(false)

  async function fetchSummary(contractId: string): Promise<RiskAssessmentSummary | null> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/contracts/${contractId}/risk-assessment/summary`, {
        headers: makeHeaders(),
      })
      if (!res.ok) {
        summary.value = null
        return null
      }
      const json = await res.json()
      summary.value = {
        contractId: contractId,
        riskScore:  json.data.risk_score,
        riskLevel:  json.data.risk_level,
        status:     json.data.status,
        scannedAt:  json.data.scanned_at,
        findings:   (json.data.findings ?? []).map(mapFinding),
      }
      return summary.value
    } catch (e) {
      console.error('Failed to fetch AI risk assessment summary', e)
      summary.value = null
      return null
    } finally {
      loading.value = false
    }
  }

  async function fetchBulkLevels(contractIds: string[]): Promise<Record<string, { riskLevel: RiskLevel, findingsCount: number, status: string }> | null> {
    if (!contractIds.length) return {}
    try {
      const res = await fetch(`${BASE_URL}/contracts/risk-assessment/bulk-levels?ids=${contractIds.join(',')}`, {
        headers: makeHeaders(),
      })
      if (!res.ok) return null
      const json = await res.json()
      
      const result: Record<string, { riskLevel: RiskLevel, findingsCount: number, status: string }> = {}
      for (const id in json.data) {
        result[id] = {
          riskLevel: json.data[id].risk_level as RiskLevel,
          findingsCount: json.data[id].findings_count,
          status: json.data[id].status
        }
      }
      return result
    } catch (e) {
      console.error('Failed to fetch bulk risk levels', e)
      return null
    }
  }

  async function triggerScan(contractId: string): Promise<boolean> {
    scanning.value = true
    try {
      const res = await fetch(`${BASE_URL}/contracts/${contractId}/risk-assessment/scan`, {
        method: 'POST',
        headers: makeHeaders(),
      })
      return res.ok
    } catch (e) {
      console.error('Failed to trigger AI risk assessment scan', e)
      return false
    } finally {
      scanning.value = false
    }
  }

  const exporting = ref(false)

  /**
   * Fetches the PDF as a blob (auth token required, so a plain <a href>
   * can't be used) and triggers a browser download.
   */
  async function exportPdf(contractId: string): Promise<boolean> {
    exporting.value = true
    try {
      const { state } = useAuth()
      const res = await fetch(`${BASE_URL}/contracts/${contractId}/risk-assessment/summary/pdf`, {
        headers: { 'Authorization': `Bearer ${state.token}` },
      })
      if (!res.ok) return false

      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `risk-assessment-contract-${contractId}.pdf`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
      return true
    } catch (e) {
      console.error('Failed to export AI risk assessment PDF', e)
      return false
    } finally {
      exporting.value = false
    }
  }

  return {
    summary,
    loading,
    scanning,
    exporting,
    fetchSummary,
    fetchBulkLevels,
    triggerScan,
    exportPdf,
  }
}
