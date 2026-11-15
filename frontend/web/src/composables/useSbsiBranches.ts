import { ref } from 'vue'
import { useAuth } from './useAuth'

const FALLBACK_BRANCHES = ['Main Office']

/**
 * Fetch SBSI branches from contract-management's `/lookups/branches` endpoint,
 * which proxies `auth-service`'s canonical branch list.
 */
export function useSbsiBranches() {
  const { state: authState } = useAuth()

  const loading = ref(false)
  const branches = ref<string[]>([...FALLBACK_BRANCHES])
  const error = ref<string | null>(null)

  const fetchBranches = async (): Promise<string[]> => {
    loading.value = true
    error.value = null

    try {
      const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string
      const res = await fetch(`${apiBase}/lookups/branches`, {
        headers: {
          'Authorization': `Bearer ${authState.token || ''}`,
          'Accept': 'application/json',
        },
      })

      if (!res.ok) throw new Error('Failed to fetch SBSI branches')

      const json = await res.json()
      const list = Array.isArray(json.data)
        ? json.data.filter((item: unknown): item is string => typeof item === 'string' && item.trim() !== '')
        : []

      branches.value = list.length > 0 ? list : [...FALLBACK_BRANCHES]
      return branches.value
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch SBSI branches'
      branches.value = [...FALLBACK_BRANCHES]
      return branches.value
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    branches,
    fetchBranches,
  }
}
