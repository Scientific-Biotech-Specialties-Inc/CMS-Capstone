import { ref } from 'vue'
import { useAuth } from './useAuth'
import type { VendorSuggestionBatch, VendorSuggestionCandidate } from '@/types/vendorSuggestion'

// Feature 3: Vendor AI Suggestions — reads/writes ai-service's
// /vendor-suggestions and /vendor-suggestion-candidates endpoints.
const BASE_URL = import.meta.env.VITE_AI_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  return {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
    'Authorization': `Bearer ${state.token}`,
  }
}

function mapCandidate(c: any): VendorSuggestionCandidate {
  return {
    id:               c.id,
    name:             c.name,
    industry:         c.industry,
    region:           c.region,
    contactEmail:     c.contact_email,
    contactNumber:    c.contact_number,
    address:          c.address,
    suggestionScore:  c.suggestion_score,
    suggestionReason: c.suggestion_reason,
    decision:         c.decision,
  }
}

function mapBatch(d: any): VendorSuggestionBatch {
  return {
    id:           d.id,
    status:       d.status,
    industryHint: d.industry_hint,
    regionHint:   d.region_hint,
    candidates:   (d.candidates ?? []).map(mapCandidate),
  }
}

export function useVendorSuggestions() {
  const batch = ref<VendorSuggestionBatch | null>(null)
  const loading = ref(false)
  const requesting = ref(false)

  async function requestSuggestions(industryHint: string, regionHint: string): Promise<boolean> {
    requesting.value = true
    try {
      const res = await fetch(`${BASE_URL}/vendor-suggestions`, {
        method: 'POST',
        headers: makeHeaders(),
        body: JSON.stringify({
          industry_hint: industryHint || null,
          region_hint:   regionHint || null,
        }),
      })
      if (!res.ok) return false
      const json = await res.json()
      // Poll for the batch id we just created rather than assuming it's
      // immediately "completed" — the pipeline runs async via a queued job.
      await fetchBatch(json.data.id)
      return true
    } catch (e) {
      console.error('Failed to request vendor suggestions', e)
      return false
    } finally {
      requesting.value = false
    }
  }

  async function fetchBatch(id: number): Promise<VendorSuggestionBatch | null> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/vendor-suggestions/${id}`, { headers: makeHeaders() })
      if (!res.ok) {
        batch.value = null
        return null
      }
      const json = await res.json()
      batch.value = mapBatch(json.data)
      return batch.value
    } catch (e) {
      console.error('Failed to fetch vendor suggestion batch', e)
      batch.value = null
      return null
    } finally {
      loading.value = false
    }
  }

  async function fetchLatestBatch(): Promise<VendorSuggestionBatch | null> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/vendor-suggestions/latest`, { headers: makeHeaders() })
      if (!res.ok) {
        batch.value = null
        return null
      }
      const json = await res.json()
      batch.value = json.data ? mapBatch(json.data) : null
      return batch.value
    } catch (e) {
      console.error('Failed to fetch latest vendor suggestion batch', e)
      batch.value = null
      return null
    } finally {
      loading.value = false
    }
  }

  async function decideCandidate(
    candidateId: number,
    decision: 'accepted' | 'dismissed',
    vendorType?: 'supplier' | 'partner',
    fields?: Record<string, string>,
  ): Promise<{ ok: boolean; message?: string; vendorId?: number }> {
    try {
      const res = await fetch(`${BASE_URL}/vendor-suggestion-candidates/${candidateId}`, {
        method: 'PATCH',
        headers: makeHeaders(),
        body: JSON.stringify({
          decision,
          ...(decision === 'accepted' ? { vendor_type: vendorType, fields } : {}),
        }),
      })
      const json = await res.json()
      if (!res.ok) {
        return { ok: false, message: json.message ?? 'Something went wrong.' }
      }
      return { ok: true, vendorId: json.vendor_id }
    } catch (e) {
      console.error('Failed to record vendor suggestion decision', e)
      return { ok: false, message: 'Could not reach the server. Please try again.' }
    }
  }

  return {
    batch,
    loading,
    requesting,
    requestSuggestions,
    fetchBatch,
    fetchLatestBatch,
    decideCandidate,
  }
}
