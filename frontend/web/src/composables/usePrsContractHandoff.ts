import type { LocationQuery } from 'vue-router'
import { useAuth } from './useAuth'

export function readPrsContractLink(query: LocationQuery) {
  const positiveId = (value: LocationQuery[string]) => {
    const id = typeof value === 'string' ? Number(value) : 0
    return Number.isSafeInteger(id) && id > 0 ? id : undefined
  }
  const sbu = typeof query.sbu === 'string' ? query.sbu.replace(/^SBU[- ]?/i, '') : ''

  return {
    prsActivityId: positiveId(query.prs_activity_id),
    productId: positiveId(query.product_id),
    prsInstitutionId: positiveId(query.prs_institution_id),
    prsBranchId: positiveId(query.prs_branch_id),
    businessPartner: typeof query.bp_name === 'string' ? query.bp_name : undefined,
    sbuNumber: /^\d{1,3}$/.test(sbu) ? `SBU-${sbu.padStart(3, '0')}` : undefined,
    description: typeof query.description === 'string' ? query.description : undefined,
  }
}

export function usePrsContractHandoff() {
  const { state } = useAuth()

  async function fetchCatalogProduct(productId: number) {
    const response = await fetch(`${import.meta.env.VITE_CONTRACT_API_URL}/products/${productId}`, {
      headers: { Authorization: `Bearer ${state.token}`, Accept: 'application/json' },
    })
    if (!response.ok) throw new Error('Product lookup failed')
    const { data }: { data: { item_code: string; sbu_number: string; description: string } } = await response.json()
    return { itemCode: data.item_code, sbuNumber: data.sbu_number, description: data.description }
  }

  return { fetchCatalogProduct }
}
