import { ref } from 'vue'
import type { ApiRole, ApiPermission, Category } from '@/types/role'

const AUTH_API = import.meta.env.VITE_AUTH_API_URL as string
const BYPASS_CONFIRMATION = 'cms.contracts.bypass_submission_confirmation'

// ── Slug → UI translation map ──────────────────────────────────────────
// Maps each DB permission slug to the frontend category key and pill label.
// Add new slugs here when new CMS permissions are seeded.
const SLUG_UI_MAP: Record<string, { category: string; label: string }> = {
  // Contracts
  'cms.contracts.view':    { category: 'contracts', label: 'View' },
  'cms.contracts.create':  { category: 'contracts', label: 'Create' },
  'cms.contracts.edit':    { category: 'contracts', label: 'Edit' },
  'cms.contracts.delete':  { category: 'contracts', label: 'Delete' },
  'cms.contracts.approve': { category: 'contracts', label: 'Approve' },
  [BYPASS_CONFIRMATION]: { category: 'contracts', label: 'Bypass Submission Confirmation' },

  // Business Partners & Suppliers
  'cms.partners.view':   { category: 'partners', label: 'View' },
  'cms.partners.create': { category: 'partners', label: 'Create' },
  'cms.partners.edit':   { category: 'partners', label: 'Edit' },
  'cms.partners.delete': { category: 'partners', label: 'Delete' },

  // AI Features
  'cms.ai.risk_assessment':    { category: 'ai_features', label: 'AI Risk Assessment' },
  'cms.ai.vendor_suggestions': { category: 'ai_features', label: 'AI Vendor Suggestions' },
  'cms.ai.ocr':                { category: 'ai_features', label: 'OCR Autofill' },
  'cms.ai.contract_generator': { category: 'ai_features', label: 'AI Contract Generator' },
}

// The fixed UI category structure (labels stay exactly as designed)
export const UI_CATEGORIES: Category[] = [
  {
    key: 'contracts',
    label: 'Contracts',
    permissions: [
      { key: 'cms.contracts.view',    label: 'View' },
      { key: 'cms.contracts.create',  label: 'Create' },
      { key: 'cms.contracts.edit',    label: 'Edit' },
      { key: 'cms.contracts.delete',  label: 'Delete' },
      { key: 'cms.contracts.approve', label: 'Approve' },
      { key: BYPASS_CONFIRMATION, label: 'Bypass Submission Confirmation' },
    ],
  },
  {
    key: 'partners',
    label: 'Business Partners & Suppliers',
    permissions: [
      { key: 'cms.partners.view',   label: 'View' },
      { key: 'cms.partners.create', label: 'Create' },
      { key: 'cms.partners.edit',   label: 'Edit' },
      { key: 'cms.partners.delete', label: 'Delete' },
    ],
  },
  {
    key: 'ai_features',
    label: 'AI Features',
    permissions: [
      { key: 'cms.ai.risk_assessment',    label: 'AI Risk Assessment' },
      { key: 'cms.ai.vendor_suggestions', label: 'AI Vendor Suggestions' },
      { key: 'cms.ai.ocr',                label: 'OCR Autofill' },
      { key: 'cms.ai.contract_generator', label: 'AI Contract Generator' },
    ],
  },
]

// ── Helpers ───────────────────────────────────────────────────────────
function authHeaders(): HeadersInit {
  const token = localStorage.getItem('access_token')
  const sessionId = localStorage.getItem('session_id')
  return {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...(sessionId ? { 'X-Session-ID': sessionId } : {}),
  }
}

async function apiFetch<T>(path: string, options?: RequestInit): Promise<T> {
  const res = await fetch(`${AUTH_API}${path}`, {
    ...options,
    headers: { ...authHeaders(), ...options?.headers },
  })
  if (res.status === 401) {
    localStorage.removeItem('user')
    localStorage.removeItem('session_id')
    localStorage.removeItem('access_token')
    const returnUrl = encodeURIComponent(window.location.href)
    window.location.href = `/?redirect_uri=${returnUrl}&error=session_expired`
    throw new Error('Session expired. Redirecting to login...')
  }
  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    throw new Error(body?.message ?? `HTTP ${res.status}`)
  }
  return res.json() as Promise<T>
}

// ── Composable ────────────────────────────────────────────────────────
export function useRolePermissions() {
  const roles           = ref<ApiRole[]>([])
  const allPermissions  = ref<ApiPermission[]>([])
  // rolePermissionIds[roleId] = Set of permission IDs currently assigned
  const rolePermissionIds  = ref<Record<number, Set<number>>>({})
  const isLoading       = ref(false)
  const error           = ref<string | null>(null)

  /** Convert a DB permission slug to its UI category key (for SLUG_UI_MAP members) */
  function slugToCategory(slug: string): string | null {
    return SLUG_UI_MAP[slug]?.category ?? null
  }

  /** Load roles list */
  async function fetchRoles() {
    const data = await apiFetch<any>('/admin/roles')
    roles.value = Array.isArray(data) ? data : (data.data || [])
  }

  /** Load all available permissions */
  async function fetchAllPermissions() {
    type PermissionPage = ApiPermission[] | { data: ApiPermission[]; last_page?: number }
    const data = await apiFetch<PermissionPage>('/admin/permissions?per_page=100')
    const permissionsArray = Array.isArray(data) ? data : data.data
    if (!permissionsArray.some(permission => permission.slug === BYPASS_CONFIRMATION)) {
      const search = await apiFetch<PermissionPage>(`/admin/permissions?search=${BYPASS_CONFIRMATION}`)
      const bypass = (Array.isArray(search) ? search : search.data).find(permission => permission.slug === BYPASS_CONFIRMATION)
      if (bypass) permissionsArray.push(bypass)
    }
    // Only keep CMS CRUD permissions that appear in the UI map
    const dbPerms = permissionsArray.filter((p: ApiPermission) => SLUG_UI_MAP[p.slug] !== undefined)
    
    // Inject frontend-only virtual permissions
    const virtualPerms: ApiPermission[] = [
      { id: -1, name: 'AI Risk Assessment', slug: 'cms.ai.risk_assessment', system: 'cms' },
      { id: -2, name: 'AI Vendor Suggestions', slug: 'cms.ai.vendor_suggestions', system: 'cms' },
      { id: -3, name: 'OCR Autofill', slug: 'cms.ai.ocr', system: 'cms' },
      { id: -4, name: 'AI Contract Generator', slug: 'cms.ai.contract_generator', system: 'cms' },
    ]
    
    allPermissions.value = [...dbPerms, ...virtualPerms]
  }

  /** Load permissions already assigned to a single role */
  async function fetchRolePermissions(roleId: number) {
    const ids = await apiFetch<number[]>(`/admin/roles/${roleId}/permissions`)
    // Filter to only include IDs that exist in allPermissions (which contains only cms slugs)
    const allowedIds = new Set(allPermissions.value.map(p => p.id))
    const validIds = ids.filter(id => allowedIds.has(id))
    
    const set = new Set(validIds)
    
    // Read frontend-only permissions state from localStorage keyed by role name
    const roleName = roles.value.find(r => r.id === roleId)?.name
    if (roleName) {
      const localSaved = localStorage.getItem(`cms_frontend_perms_${roleName}`)
      if (localSaved) {
        const savedIds: number[] = JSON.parse(localSaved)
        savedIds.forEach(id => {
          if (id < 0) {
            set.add(id)
          }
        })
      } else {
        if (roleName === 'Sales') {
          set.add(-3) // OCR Autofill
          set.add(-4) // AI Contract Generator
        }
      }
    }
    
    rolePermissionIds.value[roleId] = set
  }

  /** Initialise: load everything in parallel */
  async function init() {
    isLoading.value = true
    error.value = null
    try {
      await Promise.all([fetchRoles(), fetchAllPermissions()])
      await Promise.all(roles.value.map(r => fetchRolePermissions(r.id)))
    } catch (e: any) {
      error.value = e.message ?? 'Failed to load permissions'
    } finally {
      isLoading.value = false
    }
  }

  /** Toggle a single permission for a role (local, optimistic) */
  function togglePermission(roleId: number, permId: number) {
    const set = rolePermissionIds.value[roleId] ??= new Set()
    set.has(permId) ? set.delete(permId) : set.add(permId)
    // Replace the inner Set AND the top-level object so all computed
    // properties (enabledCounts, activePermSlugs) re-evaluate.
    rolePermissionIds.value = {
      ...rolePermissionIds.value,
      [roleId]: new Set(set),
    }
  }

  /** Toggle an entire category for a role (local, optimistic) */
  function toggleCategory(roleId: number, categoryKey: string, allOn: boolean) {
    const categoryPerms = allPermissions.value.filter(
      p => SLUG_UI_MAP[p.slug]?.category === categoryKey
    )
    const set = new Set(rolePermissionIds.value[roleId] ?? [])
    for (const p of categoryPerms) {
      allOn ? set.delete(p.id) : set.add(p.id)
    }
    // Replace both the inner Set and the top-level object for full reactivity
    rolePermissionIds.value = {
      ...rolePermissionIds.value,
      [roleId]: set,
    }
  }

  /** Persist current state for a role to the backend */
  async function saveRolePermissions(roleId: number): Promise<void> {
    const permIds = [...(rolePermissionIds.value[roleId] ?? new Set())]

    const backendIds  = permIds.filter(id => id > 0)
    const frontendIds = permIds.filter(id => id < 0)

    const roleName = roles.value.find(r => r.id === roleId)?.name
    if (roleName) {
      // Save frontend-only AI permissions
      localStorage.setItem(`cms_frontend_perms_${roleName}`, JSON.stringify(frontendIds))
    }
    
    await apiFetch(`/admin/roles/${roleId}/permissions`, {
      method: 'POST',
      body: JSON.stringify({ permissions: backendIds }),
    })
    // Re-sync from server so pill state and count badge always match reality
    await fetchRolePermissions(roleId)
    rolePermissionIds.value = { ...rolePermissionIds.value }
  }

  /** Get the active permission IDs for a role as an array (for prop passing) */
  function getActivePermIds(roleId: number): number[] {
    return [...(rolePermissionIds.value[roleId] ?? new Set())]
  }

  /** Look up a DB permission ID by its slug */
  function permIdBySlug(slug: string): number | undefined {
    return allPermissions.value.find(p => p.slug === slug)?.id
  }

  return {
    roles,
    allPermissions,
    rolePermissionIds,
    isLoading,
    error,
    UI_CATEGORIES,
    init,
    fetchRoles,
    fetchAllPermissions,
    fetchRolePermissions,
    togglePermission,
    toggleCategory,
    saveRolePermissions,
    getActivePermIds,
    permIdBySlug,
    slugToCategory,
  }
}
