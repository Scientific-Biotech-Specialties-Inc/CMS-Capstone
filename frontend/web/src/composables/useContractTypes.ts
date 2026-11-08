import { ref } from 'vue'
import { useAuth } from './useAuth'

// Contract "types" are stored as contract-management's `contract_categories`
// lookup table (not a separate `contract_types` table — one already existed
// and is the FK target for `contracts.category_id`, so this composable
// reuses it instead of forking a second source of truth). Only active
// categories are returned by the backend lookup endpoint.
export function useContractTypes() {
  const { state: authState } = useAuth()

  const loading = ref(false)
  const contractTypes = ref<string[]>([])
  const error = ref<string | null>(null)

  const fetchContractTypes = async (): Promise<string[]> => {
    loading.value = true
    error.value = null
    try {
      const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string
      const res = await fetch(`${apiBase}/lookups/categories`, {
        headers: {
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json',
        },
      })

      if (!res.ok) throw new Error('Failed to fetch contract types')

      const json = await res.json()
      contractTypes.value = (json.data || []) as string[]
      return contractTypes.value
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch contract types'
      contractTypes.value = []
      return []
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    contractTypes,
    fetchContractTypes,
  }
}
