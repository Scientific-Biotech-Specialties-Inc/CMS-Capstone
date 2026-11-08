import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useApiCache } from '@/composables/useApiCache'

export interface BreadcrumbItem {
  label: string
  to?: string
}

// Module-level reactive store for custom dynamic titles (e.g., loaded contract or partner name)
const dynamicTitle = ref<string | null>(null)

export function setBreadcrumbTitle(title: string | null) {
  dynamicTitle.value = title
}

export function useBreadcrumbs() {
  const route = useRoute()
  const { state: cacheState } = useApiCache()

  const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const path = route.path
    const parts = path.split('/').filter(Boolean)
    if (parts.length === 0) return []

    const role = parts[0] // 'admin' | 'manager' | 'sales'
    const section = parts[1] // 'contracts', 'contract-requests', 'dashboard', etc.
    const sub = parts[2]
    const deep = parts[3]
    const leaf = parts[4]

    const items: BreadcrumbItem[] = []
    const base = `/${role}`

    // 1. Dashboard
    if (section === 'dashboard' || !section) {
      items.push({ label: 'Dashboard' })
      return items
    }

    // 2. Contracts
    if (section === 'contracts') {
      const contractsRoot = `${base}/contracts`
      items.push({ label: 'Contracts', to: sub ? contractsRoot : undefined })

      if (sub === 'create') {
        items.push({ label: 'Create Contract' })
      } else if (sub === 'editor') {
        items.push({ label: 'Create Contract', to: `${contractsRoot}/create` })
        items.push({ label: 'AI Contract Generator' })
      } else if (sub === 'preview' && deep === 'documents' && leaf) {
        items.push({ label: 'Create Contract', to: `${contractsRoot}/create` })
        items.push({ label: 'Document Preview' })
      } else if (sub) {
        // sub is contract ID (:id)
        const cachedContract = cacheState.contracts?.find(c => c.id === sub)
        const contractLabel = dynamicTitle.value || cachedContract?.businessPartner || cachedContract?.itemCode || (cachedContract?.serialNo ? `Contract ${cachedContract.serialNo}` : `Contract (${sub})`)
        const contractDetailUrl = `${contractsRoot}/${sub}`

        if (deep === 'amend') {
          items.push({ label: contractLabel, to: contractDetailUrl })
          items.push({ label: 'Amend Contract' })
        } else if (deep === 'sign') {
          items.push({ label: contractLabel, to: contractDetailUrl })
          items.push({ label: 'Sign Contract' })
        } else if (deep === 'documents' && leaf) {
          items.push({ label: contractLabel, to: contractDetailUrl })
          items.push({ label: 'Document Viewer' })
        } else if (deep === 'risk-assessment') {
          items.push({ label: contractLabel, to: contractDetailUrl })
          items.push({ label: 'Risk Assessment' })
        } else {
          items.push({ label: contractLabel })
        }
      }
      return items
    }

    // 3. Contract Requests
    if (section === 'contract-requests' || section === 'contract-requests-review') {
      const requestsRoot = `${base}/${section}`
      items.push({ label: 'Contract Requests', to: sub ? requestsRoot : undefined })

      if (sub === 'create') {
        items.push({ label: 'Create Request' })
      } else if (sub) {
        const cachedReq = cacheState.requests?.find(r => r.id === sub)
        const reqLabel = dynamicTitle.value || cachedReq?.businessPartner || (cachedReq?.serialNo ? `Request ${cachedReq.serialNo}` : `Request (${sub})`)
        items.push({ label: reqLabel })
      }
      return items
    }

    // 4. Contract Amendments / Amendment Requests
    if (section === 'contract-amendments' || section === 'amendment-requests') {
      const isAmendReq = section === 'amendment-requests'
      const label = isAmendReq ? 'Amendment Requests' : 'Contract Amendments'
      const amendRoot = `${base}/${section}`
      items.push({ label, to: sub ? amendRoot : undefined })

      if (sub === 'create') {
        items.push({ label: 'Create Amendment' })
      } else if (sub) {
        const amendLabel = dynamicTitle.value || (isAmendReq ? `Request (${sub})` : `Amendment (${sub})`)
        items.push({ label: amendLabel })
      }
      return items
    }

    // 5. Business & Suppliers / Partners
    if (section === 'partners') {
      const partnersLabel = role === 'sales' ? 'Partners' : 'Business & Suppliers'
      const partnersRoot = `${base}/partners`
      items.push({ label: partnersLabel, to: sub ? partnersRoot : undefined })

      if (sub === 'create') {
        items.push({ label: 'Add Partner' })
      } else if (sub) {
        const partnerDetailUrl = `${partnersRoot}/${sub}`
        const partnerLabel = dynamicTitle.value || sub

        if (deep === 'edit') {
          items.push({ label: partnerLabel, to: partnerDetailUrl })
          items.push({ label: 'Edit Partner' })
        } else {
          items.push({ label: partnerLabel })
        }
      }
      return items
    }

    // 6. Vendor Suggestions
    if (section === 'vendor-suggestions') {
      const vsRoot = `${base}/vendor-suggestions`
      items.push({ label: 'Vendor Suggestions', to: sub ? vsRoot : undefined })

      if (sub === 'review') {
        items.push({ label: 'Review Suggestions' })
      }
      return items
    }

    // 7. Users
    if (section === 'users') {
      items.push({ label: 'User Management' })
      return items
    }

    // 8. Roles
    if (section === 'roles') {
      items.push({ label: 'Roles & Permissions' })
      return items
    }

    // 9. Analytics
    if (section === 'analytics') {
      items.push({ label: 'Analytics' })
      return items
    }

    // 10. Notifications
    if (section === 'notifications') {
      items.push({ label: 'Notifications' })
      return items
    }

    // 11. Audit Log
    if (section === 'audit-log') {
      items.push({ label: 'Audit Log' })
      return items
    }

    // 12. System Configuration
    if (section === 'system-config') {
      items.push({ label: 'System Configuration' })
      return items
    }

    // 13. Profile
    if (section === 'profile') {
      items.push({ label: 'Profile' })
      return items
    }

    // Fallback: capitalize section name
    const fallbackLabel = section.charAt(0).toUpperCase() + section.slice(1).replace(/-/g, ' ')
    items.push({ label: fallbackLabel })
    if (sub) {
      items[0].to = `${base}/${section}`
      items.push({ label: dynamicTitle.value || sub })
    }

    return items
  })

  return {
    breadcrumbs,
    setBreadcrumbTitle,
  }
}
