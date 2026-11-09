import { ref } from 'vue'
import type { ContractAmendment, ContractVersionSnapshot } from '@/types/contractAmendment'
import type { Contract } from '@/types/contract'
import { useApiCache } from './useApiCache'
import { useAuth } from './useAuth'

const amendments = ref<ContractAmendment[]>([])
const versionHistory = ref<Record<string, ContractVersionSnapshot[]>>({})

export function useAmendmentStore() {
  const { state: authState } = useAuth()
  const { updateContractInCache, invalidateContracts, state: cacheState } = useApiCache()
  const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

  async function fetchAmendments(force = false) {
    if (amendments.value.length > 0 && !force) {
      return amendments.value
    }
    try {
      const res = await fetch(`${apiBase}/contract-amendments`, {
        headers: {
          'Accept': 'application/json',
          ...(authState.token ? { 'Authorization': `Bearer ${authState.token}` } : {}),
        },
      })
      if (!res.ok) throw new Error('Failed to fetch amendments')
      const json = await res.json()
      amendments.value = json.data ?? []
      return amendments.value
    } catch (err) {
      console.error(err)
      return []
    }
  }

  async function fetchVersionHistory(contractId: string, force = false) {
    if (versionHistory.value[contractId] && !force) {
      return versionHistory.value[contractId]
    }
    try {
      const res = await fetch(`${apiBase}/contracts/${contractId}/versions`, {
        headers: {
          'Accept': 'application/json',
          ...(authState.token ? { 'Authorization': `Bearer ${authState.token}` } : {}),
        },
      })
      if (!res.ok) throw new Error('Failed to fetch version history')
      const json = await res.json()
      versionHistory.value[contractId] = json.data ?? []
      return versionHistory.value[contractId]
    } catch (err) {
      console.error(err)
      return []
    }
  }

  function getNextVersion(contractId: string): number {
    const approvedAmendments = amendments.value.filter(
      a => a.contractId === contractId && a.status === 'Approved'
    )
    return approvedAmendments.length + 2
  }

  function getContractActiveVersion(contractId: string): number {
    const snaps = versionHistory.value[contractId] || []
    return snaps.length + 1
  }

  async function createAmendment(
    contract: Contract,
    formDetails: Omit<ContractAmendment, 'id' | 'contractId' | 'status' | 'requestDate' | 'version' | 'createdBy' | 'approvedBy' | 'reason'>,
    reason: string
  ) {
    try {
      const payload = {
        contractId: contract.id,
        ...formDetails,
        reason,
      }

      const res = await fetch(`${apiBase}/contract-amendments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(authState.token ? { 'Authorization': `Bearer ${authState.token}` } : {}),
        },
        body: JSON.stringify(payload),
      })

      if (!res.ok) {
        const data = await res.json()
        throw new Error(data.message || 'Failed to submit amendment')
      }

      const json = await res.json()
      const newAmd = json.data as ContractAmendment
      amendments.value.unshift(newAmd)
      // Invalidate contracts cache so the detail page re-fetches fresh data
      // (document list, contract fields) after the amendment is applied.
      invalidateContracts()
      return newAmd
    } catch (err) {
      console.error(err)
      throw err
    }
  }

  async function approveAmendment(amendmentId: string) {
    try {
      const res = await fetch(`${apiBase}/contract-amendments/${amendmentId}/status`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(authState.token ? { 'Authorization': `Bearer ${authState.token}` } : {}),
        },
        body: JSON.stringify({ status: 'Approved' }),
      })

      if (!res.ok) {
        const data = await res.json()
        throw new Error(data.message || 'Failed to approve amendment')
      }

      const json = await res.json()
      const updated = json.data as ContractAmendment

      // Update local store
      const idx = amendments.value.findIndex(a => a.id === amendmentId)
      if (idx !== -1) {
        amendments.value[idx] = updated
      }

      // Update the contract in useApiCache immediately
      const liveContract = (cacheState.contracts || []).find(c => c.id === updated.contractId)
      if (liveContract) {
        updateContractInCache(updated.contractId, {
          businessPartner: updated.businessPartner,
          category:        updated.category,
          itemCode:        updated.itemCode,
          description:     updated.description,
          serialNo:        updated.serialNo,
          sbuNumber:       updated.sbuNumber,
          region:          updated.region,
          startDate:       updated.startDate,
          endDate:         updated.endDate,
          docs:            [...updated.docs],
        })
      }

      // Invalidate version history to force fetch next time
      delete versionHistory.value[updated.contractId]

      return true
    } catch (err) {
      console.error(err)
      return false
    }
  }

  async function rejectAmendment(amendmentId: string, reason: string) {
    try {
      const res = await fetch(`${apiBase}/contract-amendments/${amendmentId}/status`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(authState.token ? { 'Authorization': `Bearer ${authState.token}` } : {}),
        },
        body: JSON.stringify({ status: 'Rejected', rejection_reason: reason }),
      })

      if (!res.ok) {
        const data = await res.json()
        throw new Error(data.message || 'Failed to reject amendment')
      }

      const json = await res.json()
      const updated = json.data as ContractAmendment

      // Update local store
      const idx = amendments.value.findIndex(a => a.id === amendmentId)
      if (idx !== -1) {
        amendments.value[idx] = updated
      }

      return true
    } catch (err) {
      console.error(err)
      return false
    }
  }

  async function deleteAmendment(amendmentId: string) {
    try {
      const res = await fetch(`${apiBase}/contract-amendments/${amendmentId}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          ...(authState.token ? { 'Authorization': `Bearer ${authState.token}` } : {}),
        },
      })

      if (!res.ok) {
        const data = await res.json()
        throw new Error(data.message || 'Failed to delete amendment')
      }

      // Update local store
      const idx = amendments.value.findIndex(a => a.id === amendmentId)
      if (idx !== -1) {
        amendments.value.splice(idx, 1)
      }

      return true
    } catch (err) {
      console.error(err)
      return false
    }
  }

  return {
    amendments,
    versionHistory,
    fetchAmendments,
    fetchVersionHistory,
    createAmendment,
    approveAmendment,
    rejectAmendment,
    deleteAmendment,
    getNextVersion,
    getContractActiveVersion
  }
}
