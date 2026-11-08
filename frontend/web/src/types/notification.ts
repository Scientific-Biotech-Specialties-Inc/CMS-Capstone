export type NotifType = 'contract' | 'user' | 'partner' | 'system' | 'reminder'
export type TabKey    = 'all' | 'system' | 'archive' | 'favorite' | 'email_logs'

export interface Notification {
  id:           string
  type:         NotifType
  message:      string
  timestamp:    string
  isRead:       boolean
  isFavorite:   boolean
  isArchived:   boolean
  contractId?:  number | null
  notifType?:   string
}

export interface EmailPreference {
  emailNotificationsEnabled: boolean
  contractExpiryAlerts: boolean
  systemAlertsEnabled?: boolean
  smsNotificationsEnabled?: boolean
  loginAlertsEnabled?: boolean
  aiRiskAssessmentEnabled?: boolean
  aiVendorSuggestionsEnabled?: boolean
  timezone?: string
  language?: string
  dateFormat?: string
}

export interface EmailSendLog {
  id: number
  notificationId: number
  userId: number
  recipientEmail: string
  subject: string
  status: 'sent' | 'failed' | 'skipped'
  errorMessage: string | null
  sentAt: string | null
  createdAt: string
}

export const typeColor: Record<NotifType, string> = {
  contract: 'text-brand-blue bg-brand-blue/8',
  user:     'text-brand-navy bg-brand-navy/8',
  partner:  'text-brand-dark bg-brand-dark/8',
  system:   'text-amber-600 bg-amber-50',
  reminder: 'text-emerald-600 bg-emerald-50',
}
