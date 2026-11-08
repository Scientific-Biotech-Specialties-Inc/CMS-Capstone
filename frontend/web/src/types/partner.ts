export type TabKey = 'partners' | 'suppliers'
export type Region = 'Luzon' | 'Visayas' | 'Mindanao'
export type Status = 'Active' | 'Inactive' | 'Suspended'

export interface Partner {
  id: string
  db_id?: number
  name: string
  industry: string
  region: Region | null
  status: Status
  contactPerson: string
  email: string
  phone: string
  address: string
  linkedContracts?: LinkedContract[]
  bpCode?: string | null
  tinNumber?: string | null
  contracts?: number
  totalValue?: string
  /** ISO date the partner/supplier record was created, if the API returns it. */
  createdAt?: string | null
}

export interface AddPartnerForm {
  name: string
  industry: string
  region: Region | ''
  status: Status
  contactPerson: string
  email: string
  phone: string
  address: string
  tinNumber: string
}

export type EngagementStatus = 'active' | 'expiring' | 'expired' | 'inactive'

export interface LinkedContract {
  associationId: string
  contractId: string
  description: string
  businessPartner: string
  startDate: string
  endDate: string
  engagementStatus: EngagementStatus
  attachedBy: string  // display name
}

export const engagementBadge: Record<EngagementStatus, string> = {
  active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  expiring: 'bg-amber-50 text-amber-700 border-amber-200',
  expired: 'bg-black/5 text-black/40 border-black/10',
  inactive: 'bg-brand-navy/5 text-brand-navy/70 border-brand-navy/15',
}
