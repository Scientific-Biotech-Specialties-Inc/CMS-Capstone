import type { UploadedDoc } from './contract'

export type RequestStatus   = 'Pending' | 'Under Review' | 'Approved' | 'Rejected'
export type RequestFilterTab = 'all' | 'pending' | 'reviewing' | 'approved' | 'rejected'

export interface ContractRequest {
  id:               string
  contractId?:      string
  contractDbId?:    number
  hasPendingApprovalWorkflow?: boolean
  businessPartner:  string
  category:         string
  description:      string
  region:           'Luzon' | 'Visayas' | 'Mindanao'
  requestDate:      string
  startDate:        string
  endDate:          string
  status:           RequestStatus
  notes:            string
  rejectionReason:  string
  contractLink:     string
  createdBy:        string
  docs:             UploadedDoc[]
  itemCode:         string
  serialNo:         string
  sbuNumber?:       string
  prsActivityId?:   number
}

export const requestStatusBadge: Record<RequestStatus, string> = {
  'Pending':      'bg-black/5 text-black/50 border-black/10',
  'Under Review': 'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
  'Approved':     'bg-brand-navy/8 text-brand-navy border-brand-navy/20',
  'Rejected':     'bg-black/5 text-black/40 border-black/8',
}

export function fmtReqDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
