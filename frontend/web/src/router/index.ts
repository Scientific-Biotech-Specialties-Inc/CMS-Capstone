import { createRouter, createWebHistory, type RouteRecordRaw, type RouteLocationNormalized } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { useLoader } from '@/composables/useLoader'
import { setBreadcrumbTitle } from '@/composables/useBreadcrumbs'

function requireRolesAdmin() {
  const { role } = useAuth()
  if (!['Admin', 'Super Admin'].includes(role.value || '')) return { name: 'admin-dashboard' }
}

const routes: Array<RouteRecordRaw> = [
  // Admin Route Group
  {
    path: '/admin',
    name: 'admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    redirect: '/admin/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'admin-dashboard',
        component: () => import('@/views/admin/Dashboard/index.vue'),
      },
      {
        path: 'contract-requests',
        name: 'admin-contract-requests',
        component: () => import('@/views/manager/ContractRequests/index.vue'),
        meta: { requiresPermission: 'cms.contracts.approve' },
      },
      {
        path: 'contract-requests/:id',
        name: 'admin-contract-requests-detail',
        component: () => import('@/views/sales/ContractRequests/RequestDetail/index.vue'),
        meta: { requiresPermission: 'cms.contracts.approve' },
      },
      {
        path: 'contracts',
        name: 'admin-contracts',
        component: () => import('@/views/admin/Contracts/index.vue'),
        meta: { requiresPermission: 'cms.contracts.view' },
      },
      {
        path: 'contracts/create',
        name: 'admin-contracts-create',
        component: () => import('@/views/admin/Contracts/CreateContract.vue'),
        meta: { requiresPermission: 'cms.contracts.create' },
      },
      {
        path: 'contracts/editor',
        name: 'admin-contracts-editor',
        component: () => import('@/views/shared/ContractEditor/index.vue'),
        meta: { requiresPermission: 'cms.ai.contract_generator' },
      },
      {
        path: 'contracts/:id',
        name: 'admin-contracts-detail',
        component: () => import('@/views/sales/Contracts/ContractDetail/index.vue'),
        meta: { requiresPermission: 'cms.contracts.view' },
      },
      {
        path: 'contracts/:id/sign',
        name: 'admin-contracts-sign',
        component: () => import('@/views/sales/Contracts/SignContract/index.vue'),
        meta: { requiresPermission: 'cms.contracts.view' },
      },
      {
        path: 'contracts/:id/documents/:docId',
        name: 'admin-contracts-document-view',
        component: () => import('@/views/sales/Contracts/DocumentViewer.vue'),
        meta: { requiresPermission: 'cms.contracts.view' },
      },
      {
        path: 'contracts/:id/risk-assessment',
        name: 'admin-contracts-risk-assessment',
        component: () => import('@/views/manager/RiskAssessment/index.vue'),
        meta: { requiresPermission: 'cms.ai.risk_assessment' },
      },
      {
        path: 'users',
        name: 'admin-users',
        component: () => import('@/views/admin/Users/index.vue'),
        beforeEnter: (_to, _from, next) => {
          const { role } = useAuth()
          if (['Admin', 'Super Admin'].includes(role.value || '')) {
            next()
          } else {
            next('/admin/dashboard')
          }
        },
      },
      {
        path: 'roles',
        name: 'admin-roles',
        component: () => import('@/views/admin/Roles/index.vue'),
        beforeEnter: requireRolesAdmin,
      },
      {
        path: 'roles/workflows/:contractTypeId/:workflowId?',
        name: 'admin-workflow-editor',
        component: () => import('@/views/admin/Roles/ApprovalWorkflows/WorkflowEditorPage.vue'),
        beforeEnter: requireRolesAdmin,
      },
      {
        path: 'partners',
        name: 'admin-partners',
        component: () => import('@/views/admin/Partners/index.vue'),
        meta: { requiresPermission: 'cms.partners.view' },
      },
      {
        path: 'partners/create',
        name: 'admin-partners-create',
        component: () => import('@/views/admin/Partners/AddPartnerPage.vue'),
        meta: { requiresPermission: 'cms.partners.create' },
      },
      {
        path: 'vendor-suggestions',
        name: 'admin-vendor-suggestions',
        component: () => import('@/views/admin/VendorSuggestions/index.vue'),
        meta: { requiresPermission: 'cms.ai.vendor_suggestions' },
      },
      {
        path: 'vendor-suggestions/review',
        name: 'admin-vendor-suggestions-review',
        component: () => import('@/views/admin/VendorSuggestions/ReviewSuggestions.vue'),
        meta: { requiresPermission: 'cms.ai.vendor_suggestions' },
      },
      {
        path: 'partners/:code/edit',
        name: 'admin-partners-edit',
        component: () => import('@/views/admin/Partners/EditPartnerPage.vue'),
        meta: { requiresPermission: 'cms.partners.edit' },
      },
      {
        path: 'partners/:code',
        name: 'admin-partners-detail',
        component: () => import('@/views/admin/Partners/PartnerDetail/index.vue'),
        meta: { requiresPermission: 'cms.partners.view' },
      },
      {
        path: 'notifications',
        name: 'admin-notifications',
        component: () => import('@/views/admin/Notifications/index.vue'),
      },
      {
        path: 'audit-log',
        name: 'admin-audit-log',
        component: () => import('@/views/admin/AuditLog/index.vue'),
        beforeEnter: (_to, _from, next) => {
          const { role, hasPermission } = useAuth()
          if (['Admin', 'Super Admin'].includes(role.value || '') || hasPermission('cms.audit.view') || hasPermission('view-audit-logs')) {
            next()
          } else {
            next('/admin/dashboard')
          }
        },
      },
      {
        path: 'system-config',
        name: 'admin-system-config',
        component: () => import('@/views/admin/SystemConfig/index.vue'),
        beforeEnter: (_to, _from, next) => {
          const { role } = useAuth()
          if (['Admin', 'Super Admin'].includes(role.value || '')) {
            next()
          } else {
            next('/admin/dashboard')
          }
        },
      },
      {
        path: 'profile',
        name: 'admin-profile',
        component: () => import('@/views/admin/Profile/index.vue'),
      },
    ],
  },

  // Manager Route Group
  {
    path: '/manager',
    name: 'manager',
    component: () => import('@/layouts/ManagerLayout.vue'),
    redirect: '/manager/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'manager-dashboard',
        component: () => import('@/views/manager/Dashboard/index.vue'),
      },
      {
        path: 'contracts',
        name: 'manager-contracts',
        component: () => import('@/views/manager/Contracts/index.vue'),
      },
      {
        path: 'contracts/create',
        name: 'manager-contracts-create',
        component: () => import('@/views/manager/Contracts/CreateContract.vue'),
      },
      {
        path: 'contracts/editor',
        name: 'manager-contracts-editor',
        component: () => import('@/views/shared/ContractEditor/index.vue'),
        meta: { requiresPermission: 'cms.ai.contract_generator' },
      },
      {
        path: 'contracts/:id',
        name: 'manager-contracts-detail',
        component: () => import('@/views/sales/Contracts/ContractDetail/index.vue'),
      },
      {
        path: 'contracts/:id/sign',
        name: 'manager-contracts-sign',
        component: () => import('@/views/sales/Contracts/SignContract/index.vue'),
      },
      {
        path: 'contracts/:id/amend',
        name: 'manager-contracts-amend',
        component: () => import('@/views/sales/ContractAmendments/CreateAmendment.vue'),
      },
      {
        path: 'contracts/:id/documents/:docId',
        name: 'manager-contracts-document-view',
        component: () => import('@/views/sales/Contracts/DocumentViewer.vue'),
      },
      {
        path: 'contracts/:id/risk-assessment',
        name: 'manager-contracts-risk-assessment',
        component: () => import('@/views/manager/RiskAssessment/index.vue'),
      },

      {
        path: 'contract-requests',
        name: 'manager-contract-requests',
        component: () => import('@/views/manager/ContractRequests/index.vue'),
        meta: { requiresPermission: 'cms.contracts.approve' },
      },
      {
        path: 'contract-requests/:id',
        name: 'manager-contract-requests-detail',
        component: () => import('@/views/sales/ContractRequests/RequestDetail/index.vue'),
        meta: { requiresPermission: 'cms.contracts.approve' },
      },
      {
        path: 'amendment-requests',
        name: 'manager-amendment-requests',
        component: () => import('@/views/manager/AmendmentRequests/index.vue'),
      },
      {
        path: 'amendment-requests/:id',
        name: 'manager-amendment-requests-detail',
        component: () => import('@/views/manager/AmendmentRequests/RequestDetail.vue'),
      },
      {
        path: 'notifications',
        name: 'manager-notifications',
        component: () => import('@/views/manager/Notifications/index.vue'),
      },
      {
        path: 'partners',
        name: 'manager-partners',
        component: () => import('@/views/manager/Partners/index.vue'),
      },
      {
        path: 'partners/create',
        name: 'manager-partners-create',
        component: () => import('@/views/admin/Partners/AddPartnerPage.vue'),
        meta: { requiresPermission: 'cms.partners.create' },
      },
      {
        path: 'partners/:code/edit',
        name: 'manager-partners-edit',
        component: () => import('@/views/admin/Partners/EditPartnerPage.vue'),
        meta: { requiresPermission: 'cms.partners.edit' },
      },
      {
        path: 'partners/:code',
        name: 'manager-partners-detail',
        component: () => import('@/views/admin/Partners/PartnerDetail/index.vue'),
      },
      {
        path: 'profile',
        name: 'manager-profile',
        component: () => import('@/views/manager/Profile/index.vue'),
      },
      {
        path: 'vendor-suggestions',
        name: 'manager-vendor-suggestions',
        component: () => import('@/views/admin/VendorSuggestions/index.vue'),
        meta: { requiresPermission: 'cms.ai.vendor_suggestions' },
      },
      {
        path: 'vendor-suggestions/review',
        name: 'manager-vendor-suggestions-review',
        component: () => import('@/views/admin/VendorSuggestions/ReviewSuggestions.vue'),
        meta: { requiresPermission: 'cms.ai.vendor_suggestions' },
      },
    ],
  },

  // Sales Route Group
  {
    path: '/sales',
    name: 'sales',
    component: () => import('@/layouts/SalesLayout.vue'),
    redirect: '/sales/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'sales-dashboard',
        component: () => import('@/views/sales/Dashboard/index.vue'),
      },
      {
        path: 'contracts',
        name: 'sales-contracts',
        component: () => import('@/views/sales/Contracts/index.vue'),
      },
      {
        path: 'contracts/create',
        name: 'sales-contracts-create',
        component: () => import('@/views/sales/Contracts/CreateContract.vue'),
      },
      {
        path: 'contracts/editor',
        name: 'sales-contracts-editor',
        component: () => import('@/views/shared/ContractEditor/index.vue'),
        meta: { requiresPermission: 'cms.ai.contract_generator' },
      },
      {
        path: 'contracts/:id',
        name: 'sales-contracts-detail',
        component: () => import('@/views/sales/Contracts/ContractDetail/index.vue'),
      },
      {
        path: 'contracts/:id/sign',
        name: 'sales-contracts-sign',
        component: () => import('@/views/sales/Contracts/SignContract/index.vue'),
      },
      {
        path: 'contracts/:id/documents/:docId',
        name: 'sales-contracts-document-view',
        component: () => import('@/views/sales/Contracts/DocumentViewer.vue'),
      },
      {
        path: 'contracts/:id/risk-assessment',
        name: 'sales-contracts-risk-assessment',
        component: () => import('@/views/manager/RiskAssessment/index.vue'),
      },
      {
        path: 'contract-requests',
        name: 'sales-contract-requests',
        component: () => import('@/views/sales/ContractRequests/index.vue'),
      },
      {
        path: 'contract-requests/:id',
        name: 'sales-contract-requests-detail',
        component: () => import('@/views/sales/ContractRequests/RequestDetail/index.vue'),
      },
      {
        path: 'contract-requests-review',
        name: 'sales-contract-requests-review',
        component: () => import('@/views/manager/ContractRequests/index.vue'),
        meta: { requiresPermission: 'cms.contracts.approve' },
      },
      {
        path: 'contract-requests-review/:id',
        name: 'sales-contract-requests-review-detail',
        component: () => import('@/views/sales/ContractRequests/RequestDetail/index.vue'),
        meta: { requiresPermission: 'cms.contracts.approve' },
      },
      {
        path: 'contracts/:id/amend',
        name: 'sales-contracts-amend',
        component: () => import('@/views/sales/ContractAmendments/CreateAmendment.vue'),
      },
      {
        path: 'contract-amendments',
        name: 'sales-contract-amendments',
        component: () => import('@/views/sales/ContractAmendments/index.vue'),
      },
      {
        path: 'contract-amendments/:id',
        name: 'sales-contract-amendments-detail',
        component: () => import('@/views/sales/ContractAmendments/AmendmentDetail.vue'),
      },
      {
        path: 'notifications',
        name: 'sales-notifications',
        component: () => import('@/views/sales/Notifications/index.vue'),
      },
      {
        path: 'partners',
        name: 'sales-partners',
        component: () => import('@/views/sales/Partners/index.vue'),
        meta: { requiresPermission: 'cms.partners.view' },
      },
      {
        path: 'partners/create',
        name: 'sales-partners-create',
        component: () => import('@/views/admin/Partners/AddPartnerPage.vue'),
        meta: { requiresPermission: 'cms.partners.create' },
      },
      {
        path: 'partners/:code/edit',
        name: 'sales-partners-edit',
        component: () => import('@/views/admin/Partners/EditPartnerPage.vue'),
        meta: { requiresPermission: 'cms.partners.edit' },
      },
      {
        path: 'partners/:code',
        name: 'sales-partners-detail',
        component: () => import('@/views/admin/Partners/PartnerDetail/index.vue'),
        meta: { requiresPermission: 'cms.partners.view' },
      },
      {
        path: 'profile',
        name: 'sales-profile',
        component: () => import('@/views/sales/Profile/index.vue'),
      },
      {
        path: 'vendor-suggestions',
        name: 'sales-vendor-suggestions',
        component: () => import('@/views/admin/VendorSuggestions/index.vue'),
        meta: { requiresPermission: 'cms.ai.vendor_suggestions' },
      },
      {
        path: 'vendor-suggestions/review',
        name: 'sales-vendor-suggestions-review',
        component: () => import('@/views/admin/VendorSuggestions/ReviewSuggestions.vue'),
        meta: { requiresPermission: 'cms.ai.vendor_suggestions' },
      },
    ],
  },

  {
    // This route handles the redirect from the Auth Module after login.
    // It reads the session cookie, calls the auth API, stores the user, then navigates to the dashboard.
    path: '/auth/callback',
    name: 'auth-callback',
    component: () => import('@/views/AuthCallback.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: { render: () => null }, // never actually rendered; beforeEnter always redirects or cancels
    // IMPORTANT: this used to be a `redirect` function. `redirect` must
    // always return a valid route location — it cannot cancel navigation.
    // The old fallback returned '/', which isn't a defined route, so
    // Vue Router re-resolved this exact catch-all again, synchronously,
    // forming an infinite loop inside the SPA (Chrome's "Throttling
    // navigation" warning) while window.location.href never got a chance
    // to take effect. A `beforeEnter` guard can legitimately return
    // `false` to cancel the in-SPA navigation outright, leaving
    // window.location.href as the only navigation that happens.
    beforeEnter: () => {
      const { navGroup, role } = useAuth()
      if (navGroup.value === 'admin' || role.value === 'Admin' || role.value === 'Super Admin' || role.value === 'CEO') return '/admin/dashboard'
      if (navGroup.value === 'manager') return '/manager/dashboard'
      if (navGroup.value === 'staff') return '/sales/dashboard'

      // Fallback: escape CMS router entirely and go to auth-module login.
      window.location.href = '/'
      return false
    },
  },
]

const router = createRouter({
  history: createWebHistory('/cms/'),
  routes,
})

router.beforeEach((to: RouteLocationNormalized) => {
  const { isAuthenticated, navGroup } = useAuth()
  const { error } = useToast()

  // Always allow the auth callback route through — it handles its own auth handshake
  if (to.name === 'auth-callback' || to.path.includes('auth/callback')) {
    return true
  }

  const requiresAuth = to.path.startsWith('/admin') ||
    to.path.startsWith('/manager') ||
    to.path.startsWith('/sales')

  if (requiresAuth && !isAuthenticated.value) {
    try {
      throw new Error('Not authenticated')
    } catch (err) {
      const { hideLoader } = useLoader()
      hideLoader()
      error('Access Denied', 'You must log in to access this system.')
      console.warn('User not authenticated, redirecting to auth-service:', to.path)

      // Delay redirection slightly so the user can see the error toast
      setTimeout(() => {
        window.location.href = '/'
      }, 1500)

      return false
    }
  }

  // Role-based access control (Strict Nav-Group Isolation)
  // CMS routes by nav_group ("admin" | "manager" | "staff"), a field set per
  // role in auth-module, instead of hardcoding literal role names. This lets
  // new custom roles (e.g. "Regulatory Officer") become routable just by
  // tagging a nav_group on them in auth-module — no CMS code change needed.
  const allowedNavGroups = ['admin', 'manager', 'staff']

  // If the user's role has no recognized nav_group, block them entirely
  if (!allowedNavGroups.includes(navGroup.value || '')) {
    return { name: 'not-found' }
  }

  // admin nav_group can ONLY access /admin
  if (to.path.startsWith('/admin') && navGroup.value !== 'admin') {
    return { name: 'not-found' }
  }

  // manager nav_group can access /manager
  if (to.path.startsWith('/manager') && navGroup.value !== 'manager') {
    return { name: 'not-found' }
  }

  // staff nav_group can access /sales
  if (to.path.startsWith('/sales') && navGroup.value !== 'staff') {
    return { name: 'not-found' }
  }

  // Permission-gated routes: redirect to dashboard if user lacks the required permission
  if (to.meta?.requiresPermission) {
    const { hasPermission } = useAuth()
    if (!hasPermission(to.meta.requiresPermission as string)) {
      const dashName = to.path.startsWith('/admin')
        ? 'admin-dashboard'
        : (to.path.startsWith('/manager') ? 'manager-dashboard' : 'sales-dashboard')
      return { name: dashName }
    }
  }

  return true
})

router.afterEach(() => {
  setBreadcrumbTitle(null)
  const { hideLoader } = useLoader()
  hideLoader()
})

router.onError(() => {
  const { hideLoader } = useLoader()
  hideLoader()
})

export default router
