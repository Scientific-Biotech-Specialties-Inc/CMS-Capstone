import { ref } from 'vue'
import { useAuth } from './useAuth'
import type { Partner, TabKey, Region, Status, LinkedContract } from '@/types/partner'

export function usePartners() {
  const { state: authState } = useAuth()

  const loading = ref(false)
  const partners = ref<Partner[]>([])
  const totalItems = ref(0)
  const lastPage = ref(1)

  const fetchPartners = async (
    type: TabKey,
    page = 1,
    perPage = 8,
    search = '',
    region = 'All',
    status = 'All',
    industry = 'All'
  ) => {
    loading.value = true
    try {
      const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
      const endpoint = type === 'partners' ? 'partners' : 'suppliers'
      const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost'
      const url = new URL(`${apiBase}/${endpoint}`, origin)
      
      url.searchParams.append('page', String(page))
      url.searchParams.append('per_page', String(perPage))
      if (search) {
        url.searchParams.append('search', search)
      }
      if (region && region !== 'All') {
        url.searchParams.append('region', region)
      }
      if (status && status !== 'All') {
        url.searchParams.append('status', status)
      }
      if (industry && industry !== 'All') {
        url.searchParams.append('industry', industry)
      }

      const res = await fetch(url.toString(), {
        headers: {
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json'
        }
      })

      if (!res.ok) throw new Error(`Failed to fetch ${type}`)

      const json = await res.json()
      
      totalItems.value = json.total || 0
      lastPage.value = json.last_page || 1

      partners.value = (json.data || []).map((item: any) => {
        if (type === 'partners') {
          const rawCode = item.bp_code || `BP-${String(item.partner_id).padStart(4, '0')}`
          const bpCode = rawCode.replace(/^(BP-)+/i, 'BP-')
          return {
            id: bpCode,
            db_id: item.partner_id,
            name: item.partner_name || '',
            industry: item.industry || 'Banking & Finance',
            region: (item.region || 'Luzon') as Region,
            status: (item.status || 'Active') as Status,
            contracts: item.associations_count || 0,
            totalValue: '₱0',
            contactPerson: item.contact_person || '',
            email: item.email || '',
            phone: item.contact_number || '',
            address: item.address || '',
            bpCode: bpCode,
            createdAt: item.created_at ?? null,
          }
        } else {
          return {
            id: `SP-${String(item.supplier_id).padStart(3, '0')}`,
            db_id: item.supplier_id,
            name: item.supplier_name || '',
            industry: item.industry || 'Medical Supplies',
            region: (item.region || 'Luzon') as Region,
            status: (item.status || 'Active') as Status,
            contracts: item.associations_count || 0,
            totalValue: '₱0',
            contactPerson: item.contact_person || '',
            email: item.email || '',
            phone: item.contact_number || '',
            address: item.address || '',
            createdAt: item.created_at ?? null,
          }
        }
      })
    } catch (err) {
      console.error(err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchVendorContracts = async (type: TabKey, dbId: number): Promise<LinkedContract[]> => {
    try {
      const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
      const endpoint = type === 'partners' ? 'partners' : 'suppliers'
      
      const res = await fetch(`${apiBase}/${endpoint}/${dbId}/contracts`, {
        headers: {
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json'
        }
      })

      if (!res.ok) throw new Error('Failed to fetch linked contracts')

      const json = await res.json()
      const data = Array.isArray(json) ? json : (json.data || [])

      return data.map((item: any) => ({
        associationId: String(item.association_id),
        contractId: item.contract?.contract_code || String(item.contract_id),
        description: item.contract?.description || 'No description',
        businessPartner: item.contract?.bp_name || '',
        startDate: item.contract?.start_date || '',
        endDate: item.contract?.end_date || '',
        engagementStatus: item.engagement_status || 'active',
        attachedBy: item.attached_by ? `User #${item.attached_by}` : '—'
      }))
    } catch (err) {
      console.error(err)
      return []
    }
  }

  const addPartner = async (
    type: TabKey,
    form: Omit<Partner, 'contracts' | 'totalValue'>
  ) => {
    loading.value = true
    try {
      const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
      const endpoint = type === 'partners' ? 'partners' : 'suppliers'
      
      let payload: any = {}
      if (type === 'partners') {
        payload = {
          bp_code: form.id || `BP-${Date.now().toString().slice(-6)}`,
          partner_name: form.name,
          contact_number: form.phone,
          email: form.email,
          address: form.address,
          region: form.region,
          industry: form.industry,
          status: form.status,
          contact_person: form.contactPerson
        }
      } else {
        payload = {
          supplier_name: form.name,
          contact_number: form.phone,
          email: form.email,
          address: form.address,
          region: form.region,
          industry: form.industry,
          status: form.status,
          contact_person: form.contactPerson
        }
      }

      const res = await fetch(`${apiBase}/${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      })

      const json = await res.json()
      if (!res.ok) {
        if (res.status === 422 && json.errors) {
          const firstErr = Object.values(json.errors as Record<string, string[]>)[0]?.[0]
          throw new Error(firstErr || json.message)
        }
        throw new Error(json.message || `Failed to add ${type}`)
      }
      return json
    } finally {
      loading.value = false
    }
  }

  const deletePartner = async (type: TabKey, dbId: number | string) => {
    loading.value = true
    try {
      const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
      const endpoint = type === 'partners' ? 'partners' : 'suppliers'
      const res = await fetch(`${apiBase}/${endpoint}/${dbId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json'
        }
      })
      if (!res.ok) {
        const json = await res.json().catch(() => ({}))
        throw new Error(json.message || `Failed to delete ${type}`)
      }
    } finally {
      loading.value = false
    }
  }

  const linkContract = async (type: TabKey, dbId: number, contractId: string) => {
    try {
      const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
      const endpoint = type === 'partners' ? 'partners' : 'suppliers'
      
      const res = await fetch(`${apiBase}/${endpoint}/${dbId}/contracts`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ contract_id: Number(contractId) })
      })

      const json = await res.json()
      if (!res.ok) {
        throw new Error(json.message || 'Failed to link contract')
      }
      return json
    } catch (err) {
      console.error(err)
      throw err
    }
  }

  const detachContract = async (type: TabKey, dbId: number, contractId: string) => {
    try {
      const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
      const endpoint = type === 'partners' ? 'partners' : 'suppliers'
      
      const res = await fetch(`${apiBase}/${endpoint}/${dbId}/contracts/${contractId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json'
        }
      })

      if (!res.ok) {
        const json = await res.json().catch(() => ({}))
        throw new Error(json.message || 'Failed to detach contract')
      }
    } catch (err) {
      console.error(err)
      throw err
    }
  }

  const fetchLinkedContractIds = async (): Promise<string[]> => {
    try {
      const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
      const res = await fetch(`${apiBase}/vendor-contracts/linked-ids`, {
        headers: {
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json'
        }
      })
      if (!res.ok) return []
      const json = await res.json()
      return (json.data || []) as string[]
    } catch {
      return []
    }
  }

  /**
   * Distinct industries, used to populate the Industry filter.
   *
   * Prefers the dedicated `/<type>/industries` endpoint. If that endpoint is
   * unavailable (older deployment) or errors out, it falls back to walking the
   * *unfiltered* list pages — never the caller's region/status-filtered page —
   * so a newly created industry can never be hidden by an active region filter.
   */
  const fetchIndustries = async (type: TabKey): Promise<string[]> => {
    const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
    const endpoint = type === 'partners' ? 'partners' : 'suppliers'
    const headers = {
      'Authorization': `Bearer ${authState.token || ''}`,
      'Accept': 'application/json'
    }

    const collect = (values: unknown[]): string[] =>
      Array.from(new Set(
        values
          .map(v => String(v ?? '').trim())
          .filter(v => v.length > 0)
      )).sort((a, b) => a.localeCompare(b))

    try {
      const res = await fetch(`${apiBase}/${endpoint}/industries`, { headers })
      if (res.ok) {
        const json = await res.json()
        const data: unknown[] = Array.isArray(json) ? json : (json.data ?? [])
        return collect(data)
      } else {
        console.warn(`[partners] ${endpoint}/industries returned ${res.status}; deriving from the unfiltered list instead.`)
      }
    } catch (err) {
      console.warn(`[partners] ${endpoint}/industries request failed; deriving from the unfiltered list instead.`, err)
    }

    try {
      const rows: unknown[] = []
      const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost'
      let page = 1
      let lastPage = 1

      do {
        const url = new URL(`${apiBase}/${endpoint}`, origin)
        url.searchParams.set('page', String(page))
        url.searchParams.set('per_page', '100')

        const res = await fetch(url.toString(), { headers })
        if (!res.ok) {
          console.warn(`[partners] Could not load ${endpoint} page ${page} while deriving industries (${res.status}).`)
          break
        }

        const json = await res.json()
        rows.push(...(json.data ?? []))
        lastPage = Number(json.last_page ?? 1)
        page += 1
      } while (page <= lastPage)

      return collect(rows.map(row => (row as Record<string, unknown>)?.industry))
    } catch (err) {
      console.error(`[partners] Failed to derive ${endpoint} industries from the unfiltered list.`, err)
      return []
    }
  }

  return {
    loading,
    partners,
    totalItems,
    lastPage,
    fetchPartners,
    fetchIndustries,
    fetchVendorContracts,
    fetchLinkedContractIds,
    addPartner,
    deletePartner,
    linkContract,
    detachContract
  }
}
