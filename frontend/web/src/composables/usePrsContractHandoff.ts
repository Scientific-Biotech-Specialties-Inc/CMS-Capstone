import type { LocationQuery } from 'vue-router'
import { useAuth } from './useAuth'

export interface CatalogProduct {
  id: number
  productId: number
  productName: string
  productCode: string
  serialNumber: string | null
  sbu: number | null
  sbuNumber: string
  category: string | null
  manufacturer: string | null
  brand: string | null
  model: string | null
  description: string | null
  isActive: boolean
}

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
    const { data }: { data: Record<string, any> } = await response.json()
    return {
      productId: Number(data.product_id ?? data.id ?? productId),
      itemCode: String(data.item_code ?? data.product_code ?? ''),
      sbuNumber: String(data.sbu_number ?? ''),
      description: String(data.description ?? data.product_name ?? ''),
      serialNo: data.serial_number ? String(data.serial_number) : undefined,
      equipmentName: data.product_name ? String(data.product_name) : undefined,
      brand: data.brand || data.manufacturer ? String(data.brand ?? data.manufacturer) : undefined,
      model: data.model ? String(data.model) : undefined,
      category: data.category ? String(data.category) : undefined,
    }
  }

  async function fetchCatalogProducts(search = ''): Promise<CatalogProduct[]> {
    const base = import.meta.env.VITE_CONTRACT_API_URL
    const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost'
    const url = new URL(`${base}/products`, origin)
    if (search.trim()) {
      url.searchParams.set('search', search.trim())
    }
    const response = await fetch(url.toString(), {
      headers: { Authorization: `Bearer ${state.token}`, Accept: 'application/json' },
    })
    if (!response.ok) return []
    const json = await response.json()
    return (json.data ?? []).map((d: Record<string, any>): CatalogProduct => ({
      id: Number(d.product_id ?? d.id),
      productId: Number(d.product_id ?? d.id),
      productName: String(d.product_name ?? ''),
      productCode: String(d.product_code ?? d.item_code ?? ''),
      serialNumber: d.serial_number ? String(d.serial_number) : null,
      sbu: d.sbu !== null && d.sbu !== undefined ? Number(d.sbu) : null,
      sbuNumber: String(d.sbu_number ?? ''),
      category: d.category ? String(d.category) : null,
      manufacturer: d.manufacturer ? String(d.manufacturer) : null,
      brand: d.brand ? String(d.brand) : null,
      model: d.model ? String(d.model) : null,
      description: d.description ? String(d.description) : null,
      isActive: Boolean(d.is_active ?? true),
    }))
  }

  return { fetchCatalogProduct, fetchCatalogProducts }
}
