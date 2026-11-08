import type { UploadedDoc, ContractRegion } from './contract'

export type AmendmentStatus = 'Pending' | 'Approved' | 'Rejected'

export interface ContractAmendment {
  id:              string
  contractId:      string
  businessPartner: string
  category:        string
  itemCode:        string
  description:     string
  serialNo:        string
  sbuNumber:       string
  region:          ContractRegion
  startDate:       string
  endDate:         string
  reason:          string
  status:          AmendmentStatus
  requestDate:     string
  version:         number
  createdBy:       string
  approvedBy?:     string
  rejectionReason?: string
  docs:            UploadedDoc[]
}

export interface ContractVersionSnapshot {
  version:         number
  businessPartner: string
  category:        string
  itemCode:        string
  description:     string
  serialNo:        string
  sbuNumber:       string
  region:          ContractRegion
  startDate:       string
  endDate:         string
  docs:            UploadedDoc[]
  reason:          string
  amendedBy:       string
  approvedBy:      string
  approvedDate:    string
}

export const amendmentStatusBadge: Record<AmendmentStatus, string> = {
  'Pending':  'bg-black/5 text-black/50 border-black/10',
  'Approved': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Rejected': 'bg-red-50 text-red-600 border-red-200',
}
