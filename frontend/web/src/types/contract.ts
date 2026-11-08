export type ContractWorkflowStatus = 'SBSI Review' | 'Client Review' | 'For Signing' | 'Signed' | 'Notarized PDF'
export type ContractApprovalStatus  = 'Pending' | 'Approved' | 'Rejected'

export interface UploadedDoc {
  file?:       File
  name:        string
  size:        number
  type:        'pdf' | 'docx'
  previewUrl?: string
  id?:         string
  uploadStatus?: 'uploading' | 'scanning' | 'success' | 'error'
  errorMessage?: string
  scanWarning?: string
  category?: 'draft' | 'signed' | 'notarized'
  isCurrentDraft?: boolean
  isPrimary?: boolean
}
export type ContractRegion = 'Luzon' | 'Visayas' | 'Mindanao'
export type FilterTab = 'all' | ContractLifecycleStatus
export type StatusFilter   = '' | ContractApprovalStatus | ContractWorkflowStatus

export interface Contract {
  id:              string
  contractCode?:   string
  contractDbId?:   number
  businessPartner: string
  category:        string
  itemCode:        string
  description:     string
  serialNo:        string
  sbuNumber?:      string
  region:          ContractRegion
  startDate:       string
  endDate:         string
  approvalStatus:  ContractApprovalStatus
  workflowStatus:  ContractWorkflowStatus | null
  lifecycleStatus?: ContractLifecycleStatus
  executionRequired?: boolean
  hasPendingApprovalWorkflow?: boolean
  contractLink:    string
  createdBy:       string
  docs:            UploadedDoc[]
  rejectionReason?: string
  prsActivityId?:  number
}

/** A contract decorated with its computed remaining days (shared by the contract lists). */
export type ContractWithDays = Contract & { days: number }

export const approvalStatusBadge: Record<ContractApprovalStatus, string> = {
  'Pending':  'bg-black/5 text-black/50 border-black/10',
  'Approved': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Rejected': 'bg-red-50 text-red-600 border-red-200',
}

export const workflowStatusBadge: Record<ContractWorkflowStatus, string> = {
  'For Signing': 'bg-amber-50 text-amber-700 border-amber-200',
  'Signed': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Notarized PDF': 'bg-black/5 text-black/60 border-black/12',
  'Client Review': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'SBSI Review':   'bg-red-50 text-red-600 border-red-200',
}

export function remainingDays(endDate: string): number {
  const end   = new Date(endDate)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  end.setHours(0, 0, 0, 0)
  return Math.floor((end.getTime() - today.getTime()) / 86_400_000)
}

export function formatRemainingTime(endDateStr: string): string {
  if (!endDateStr) return '—'
  const end = new Date(endDateStr)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  end.setHours(0, 0, 0, 0)

  const diffTime = end.getTime() - today.getTime()
  const diffDays = Math.floor(diffTime / 86_400_000)

  if (diffDays === 0) {
    return '0 days'
  }

  const isOverdue = diffDays < 0
  const targetDate = isOverdue ? today : end
  const baseDate = isOverdue ? end : today

  let years = targetDate.getFullYear() - baseDate.getFullYear()
  let months = targetDate.getMonth() - baseDate.getMonth()
  let days = targetDate.getDate() - baseDate.getDate()

  if (days < 0) {
    const prevMonth = new Date(targetDate.getFullYear(), targetDate.getMonth(), 0)
    days += prevMonth.getDate()
    months -= 1
  }

  if (months < 0) {
    months += 12
    years -= 1
  }

  const totalMonths = years * 12 + months

  const parts: string[] = []
  if (totalMonths > 0) {
    parts.push(`${totalMonths} ${totalMonths === 1 ? 'month' : 'months'}`)
  }
  if (days > 0) {
    parts.push(`${days} ${days === 1 ? 'day' : 'days'}`)
  }

  const resultStr = parts.join(', ') || '0 days'
  return isOverdue ? `${resultStr} overdue` : `${resultStr} left`
}

export function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

export type ContractLifecycleStatus = 'active' | 'expiring' | 'expired' | 'inactive' | 'pending_execution'

export const EXPIRING_THRESHOLD_DAYS = 30

export function deriveLifecycleStatus(days: number, approvalStatus?: ContractApprovalStatus): ContractLifecycleStatus {
  if (approvalStatus && approvalStatus !== 'Approved') return 'inactive'
  if (days < 0) return 'expired'
  if (days <= EXPIRING_THRESHOLD_DAYS) return 'expiring'
  return 'active'
}

export function contractLifecycle(contract: ContractWithDays): ContractLifecycleStatus {
  return contract.lifecycleStatus ?? deriveLifecycleStatus(contract.days, contract.approvalStatus)
}

