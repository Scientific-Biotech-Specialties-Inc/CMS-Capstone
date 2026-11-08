import { reactive, computed } from 'vue'

const AUTH_API = import.meta.env.VITE_AUTH_API_URL as string

interface User {
  id: number
  email: string
  first_name: string
  last_name: string
  role: string
  permissions: string[]
  department?: string
}

interface AuthState {
  user: User | null
  token: string | null
  isLoading: boolean
}

const state = reactive<AuthState>({
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  token: localStorage.getItem('access_token'),
  isLoading: false,
})

export function useAuth() {
  const isAuthenticated = computed(() => !!state.user)
  const user = computed(() => state.user)
  // Safely extract role, handling both direct role property and nested profile.role.name
  const role = computed(() => {
    if (!state.user) return undefined
    // Handle nested profile structure (from auth-module)
    const profileRole = (state.user as any).profile?.role?.name
    return profileRole || state.user.role
  })

  // The role's navigation group ("admin" | "manager" | "staff"), set by auth-module
  // per role rather than hardcoded by role name. Drives CMS routing/layout
  // selection so new custom roles (e.g. "Regulatory Officer") are routable
  // just by tagging a nav_group — no CMS code change required.
  const navGroup = computed<string | undefined>(() => {
    if (!state.user) return undefined
    const explicitGroup = (state.user as any).profile?.role?.nav_group
    if (explicitGroup) return explicitGroup

    // Fallback based on role name for safety before refreshPermissions finishes
    const roleName = role.value || ''
    if (['Admin', 'Super Admin', 'CEO', 'IT Admin'].includes(roleName)) return 'admin'
    if (['Manager', 'Finance Manager', 'Supervisor'].includes(roleName)) return 'manager'
    if (['Sales', 'Employee', 'Finance', 'Finance Employee', 'Accountant', 'Engineer'].includes(roleName)) return 'staff'
    return undefined
  })
  
  // Robustly extract and normalize permissions from state.user
  const permissions = computed<string[]>(() => {
    const u = state.user as any
    if (!u) return []

    let rawPerms: any[] = []
    if (Array.isArray(u.permissions)) {
      rawPerms = u.permissions
    } else if (Array.isArray(u.profile?.permissions)) {
      rawPerms = u.profile.permissions
    } else if (Array.isArray(u.profile?.role?.permissions)) {
      rawPerms = u.profile.role.permissions
    } else if (Array.isArray(u.role?.permissions)) {
      rawPerms = u.role.permissions
    }

    const perms = rawPerms.map((p: any) => {
      if (!p) return ''
      if (typeof p === 'string') return p
      return p.slug || p.name || p.key || ''
    }).filter(Boolean)



    return perms
  })

  const setAuth = (user: User, token: string) => {
    state.user = user
    state.token = token
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('access_token', token)
  }

  /**
   * Re-fetch the current user's permissions from the auth-service.
   * Called on layout mount so sidebar items always reflect the latest
   * role permissions without requiring a re-login.
   */
  const refreshPermissions = async () => {
    if (!state.user) return
    try {
      // Fetch /api/user and /api/me/permissions in parallel
      const [userRes, permRes] = await Promise.all([
        fetch(`${AUTH_API}/user`, {
          headers: {
            'Accept': 'application/json',
            ...(localStorage.getItem('session_id')
              ? { 'X-Session-ID': localStorage.getItem('session_id') as string }
              : {}),
          },
          credentials: 'same-origin',
        }),
        fetch(`${AUTH_API}/me/permissions`, {
          headers: {
            'Accept': 'application/json',
            ...(localStorage.getItem('session_id')
              ? { 'X-Session-ID': localStorage.getItem('session_id') as string }
              : {}),
          },
          credentials: 'same-origin',
        })
      ])

      if (userRes.ok && permRes.ok) {
        const freshUser = await userRes.json()
        const freshPerms = await permRes.json()

        if (state.user && freshUser) {
          // Extract permissions array robustly
          let rawPerms: any[] = []
          const permsSource = freshPerms?.permissions ?? freshUser?.permissions
          if (Array.isArray(permsSource)) {
            rawPerms = permsSource
          } else if (Array.isArray(freshUser?.profile?.permissions)) {
            rawPerms = freshUser.profile.permissions
          } else if (Array.isArray(freshUser?.profile?.role?.permissions)) {
            rawPerms = freshUser.profile.role.permissions
          } else if (Array.isArray(freshUser?.role?.permissions)) {
            rawPerms = freshUser.role.permissions
          }

          const parsedPerms = rawPerms.map((p: any) => {
            if (!p) return ''
            if (typeof p === 'string') return p
            return p.slug || p.name || p.key || ''
          }).filter(Boolean)

          // Update user in state with fresh data and the extracted permissions list.
          // Shallow-replace the whole object so every computed that reads state.user
          // (including hasPermission in the layout sidebars) is guaranteed to re-evaluate.
          state.user = {
            ...state.user,
            ...freshUser,
            permissions: parsedPerms,
          }
          localStorage.setItem('user', JSON.stringify(state.user))
        }
      }
    } catch {
      // Silent fail — stale permissions are better than a broken page
    }
  }

  const logout = async () => {
    const token = state.token
    const sessionId = localStorage.getItem('session_id')

    // 1. Fire the logout API call FIRST — before clearing state or redirecting.
    //    The previous pattern (redirect → fetch) caused the browser to cancel the
    //    in-flight request the moment window.location.href changed, so the auth
    //    service never received the logout event and no audit log was written.
    try {
      const headers: Record<string, string> = {
        'Accept': 'application/json',
      }
      if (token) {
        headers['Authorization'] = `Bearer ${token}`
      }
      if (sessionId) {
        headers['X-Session-ID'] = sessionId
      }

      await fetch('/api/logout', {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        // keepalive: true tells the browser to let this request outlive the page
        keepalive: true,
      })
    } catch (e) {
      console.error('Logout API call failed', e)
    }

    // 2. Clear local state after the API call finishes (or errors)
    state.user = null
    state.token = null
    localStorage.removeItem('user')
    localStorage.removeItem('session_id')
    localStorage.removeItem('access_token')

    // 3. Redirect to login
    window.location.href = '/'
  }

  const hasFrontendPermission = (slug: string): boolean => {
    if (role.value === 'Admin' || role.value === 'Super Admin') return true
    const roleName = role.value
    if (!roleName) return false
    const FRONTEND_PERM_IDS: Record<string, number> = {
      'cms.ai.risk_assessment':    -1,
      'cms.ai.vendor_suggestions': -2,
      'cms.ai.ocr':                -3,
      'cms.ai.contract_generator': -4,
    }
    const stored = localStorage.getItem(`cms_frontend_perms_${roleName}`)
    if (!stored) {
      // Default enabled for Sales/Admin for OCR & Contract Generator
      if (roleName === 'Sales' && (slug === 'cms.ai.contract_generator' || slug === 'cms.ai.ocr')) {
        return true
      }
      return false
    }
    const ids: number[] = JSON.parse(stored)
    const id = FRONTEND_PERM_IDS[slug]
    return id !== undefined && ids.includes(id)
  }

  const hasPermission = (permission: string) => {
    if (role.value === 'Admin' || role.value === 'Super Admin') return true

    if (permission.startsWith('cms.ai.')) {
      return hasFrontendPermission(permission)
    }

    // 1. Exact match
    if (permissions.value.includes(permission)) return true

    // 2. Map standard dot slugs like "cms.partners.view" to alternative formats:
    // e.g. "view-partners", "partners.view", "view_partners", "partners-view", "view partners"
    const match = permission.match(/^cms\.([^.]+)\.([^.]+)$/)
    if (match) {
      const [, resource, action] = match
      const alts = [
        `${action}-${resource}`,      // view-partners
        `${resource}.${action}`,      // partners.view
        `${action}_${resource}`,      // view_partners
        `${resource}-${action}`,      // partners-view
        `${action} ${resource}`,      // view partners
      ]
      for (const alt of alts) {
        if (permissions.value.includes(alt)) return true
      }
    }
    return false
  }

  const hasRole = (roleName: string) => {
    return role.value === roleName
  }

  return {
    state,
    isAuthenticated,
    user,
    role,
    navGroup,
    permissions,
    setAuth,
    refreshPermissions,
    logout,
    hasPermission,
    hasRole,
  }
}
