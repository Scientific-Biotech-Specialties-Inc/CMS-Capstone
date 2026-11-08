import { ref } from 'vue'
import { useAuth } from './useAuth'
import type { HighRiskGateState } from '@/types/riskAssessment'

// US-023: Approve High-Risk Contracts — reads/writes the mandatory approval
// gate tracked by contract-management's /contracts/{id}/high-risk-approval
// endpoints (see HighRiskApprovalGateService on the backend).
const BASE_URL = import.meta.env.VITE_CONTRACT_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  return {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
    'Authorization': `Bearer ${state.token}`,
  }
}

function mapGateState(contractId: string, data: any): HighRiskGateState {
  return {
    contractId:               Number(contractId),
    riskLevel:                data.risk_level ?? null,
    requiresHighRiskApproval: !!data.requires_high_risk_approval,
    blocked:                  !!data.blocked,
    approval: data.approval
      ? {
          id:          data.approval.id,
          contractId:  data.approval.contract_id,
          riskLevel:   data.approval.risk_level,
          approverId:  data.approval.approver_id,
          rationale:   data.approval.rationale,
          decision:    data.approval.decision,
          decidedAt:   data.approval.decided_at,
          flaggedAt:   data.approval.flagged_at,
          slaDueAt:    data.approval.sla_due_at,
          escalatedAt: data.approval.escalated_at,
        }
      : null,
  }
}

export function useHighRiskApproval() {
  const gateState = ref<HighRiskGateState | null>(null)
  const loading   = ref(false)
  const saving    = ref(false)

  async function fetchGateState(contractId: string): Promise<HighRiskGateState | null> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/contracts/${contractId}/high-risk-approval`, {
        headers: makeHeaders(),
      })
      const json = await res.json()
      if (!res.ok) {
        gateState.value = null
        return null
      }
      gateState.value = mapGateState(contractId, json.data)
      return gateState.value
    } catch (e) {
      console.error('Failed to fetch high-risk approval gate state', e)
      gateState.value = null
      return null
    } finally {
      loading.value = false
    }
  }

  async function recordDecision(
    contractId: string,
    decision: 'approved' | 'rejected',
    rationale: string,
  ): Promise<{ ok: boolean; message?: string }> {
    saving.value = true
    try {
      const res = await fetch(`${BASE_URL}/contracts/${contractId}/high-risk-approval`, {
        method: 'POST',
        headers: makeHeaders(),
        body: JSON.stringify({ decision, rationale }),
      })
      const json = await res.json()
      if (!res.ok) {
        return { ok: false, message: json.message ?? 'Something went wrong.' }
      }
      await fetchGateState(contractId)
      return { ok: true }
    } catch (e) {
      console.error('Failed to record high-risk approval decision', e)
      return { ok: false, message: 'Could not reach the server. Please try again.' }
    } finally {
      saving.value = false
    }
  }

  return {
    gateState,
    loading,
    saving,
    fetchGateState,
    recordDecision,
  }
}
