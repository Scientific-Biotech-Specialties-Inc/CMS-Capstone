import { ref, computed } from 'vue'
import { useAuth } from './useAuth'

export interface RoleSystems {
  id: number
  name: string
  systems: string[]
}

/**
 * Roles with their permission systems, from auth-module
 * (GET /admin/roles/access-systems). CMS renders one User Management
 * tab per role whose systems include 'cms' — when the IT Admin grants a
 * role CMS access in auth's Role Builder, its tab appears here with no
 * CMS-side change. Scoped server-side (same rule as role listings).
 */
export function useRoleSystems() {
  const { state } = useAuth()

  const roleSystems = ref<RoleSystems[]>([])
  const loading = ref(false)

  async function fetchRoleSystems(): Promise<RoleSystems[]> {
    loading.value = true
    try {
      const authApiUrl = import.meta.env.VITE_AUTH_API_URL || '/api'
      const res = await fetch(`${authApiUrl}/admin/roles/access-systems`, {
        headers: {
          'Authorization': `Bearer ${state.token}`,
          'X-Session-ID': localStorage.getItem('session_id') || '',
          'Accept': 'application/json',
        },
      })
      if (!res.ok) throw new Error('Failed to load role systems.')
      const json = await res.json()
      const rows: RoleSystems[] = Array.isArray(json) ? json : (json.data || [])
      roleSystems.value = rows
      return rows
    } catch (err) {
      console.error('Failed to fetch role systems:', err)
      roleSystems.value = []
      return []
    } finally {
      loading.value = false
    }
  }

  const cmsRoleNames = computed(() =>
    roleSystems.value.filter(r => r.systems.includes('cms')).map(r => r.name),
  )

  return { roleSystems, cmsRoleNames, loading, fetchRoleSystems }
}
