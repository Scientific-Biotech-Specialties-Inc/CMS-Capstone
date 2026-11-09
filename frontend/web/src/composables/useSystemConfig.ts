import { reactive, ref } from 'vue'
import { useAuth } from './useAuth'

const BASE_URL = import.meta.env.VITE_NOTIFICATION_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  return {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
    ...(state.token ? { 'Authorization': `Bearer ${state.token}` } : {}),
  }
}

export interface SystemCfg {
  emailNotifs: boolean
  inAppNotifs: boolean
  contractExpiryAlerts: boolean
  approvalAlerts: boolean
  renewalReminders: boolean
  // Extended admin settings (bound by the SystemConfig tabs below).
  // NOTE: only the five notification fields above persist — the backend
  // PUT /system-config accepts just those, so the rest are local UI
  // state until the notification service grows matching columns. They
  // are defaulted here so the tabs render deterministically instead of
  // binding undefined.
  autoRenew: boolean
  dualApproval: boolean
  allowDraftSubmit: boolean
  requireNotarization: boolean
  maxContractDays: number
  expiryWarningDays: number
  systemName: string
  supportEmail: string
  timezone: string
  dateFormat: string
  auditLogging: boolean
  autoBackups: boolean
  backupFrequency: string
  dataRetentionDays: number
  allowSelfReg: boolean
  requireEmailVerify: boolean
  sessionTimeout: number
  maxLoginAttempts: number
  minPasswordLength: number
  requireStrongPass: boolean
  twoFactorAdmins: boolean
  twoFactorAll: boolean
}

const EXTENDED_DEFAULTS = {
  autoRenew: false,
  dualApproval: false,
  allowDraftSubmit: true,
  requireNotarization: false,
  maxContractDays: 365,
  expiryWarningDays: 30,
  systemName: 'SBSI CMS',
  supportEmail: '',
  timezone: 'Asia/Manila',
  dateFormat: 'MM/DD/YYYY',
  auditLogging: true,
  autoBackups: false,
  backupFrequency: 'daily',
  dataRetentionDays: 365,
  allowSelfReg: false,
  requireEmailVerify: true,
  sessionTimeout: 30,
  maxLoginAttempts: 5,
  minPasswordLength: 8,
  requireStrongPass: false,
  twoFactorAdmins: false,
  twoFactorAll: false,
}

export function useSystemConfig() {
  const cfg = reactive<SystemCfg>({
    emailNotifs: true,
    inAppNotifs: true,
    contractExpiryAlerts: true,
    approvalAlerts: true,
    renewalReminders: true,
    ...EXTENDED_DEFAULTS,
  })
  const loading = ref(false)

  async function fetchConfig(): Promise<void> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/system-config`, { headers: makeHeaders() })
      const json = await res.json()
      
      if (res.ok && json.data) {
        Object.assign(cfg, {
          emailNotifs: json.data.email_notifs_enabled ?? true,
          inAppNotifs: json.data.in_app_notifs_enabled ?? true,
          contractExpiryAlerts: json.data.contract_expiry_alerts ?? true,
          approvalAlerts: json.data.approval_alerts ?? true,
          renewalReminders: json.data.renewal_reminders ?? true,
        })
      }
    } catch (e) {
      console.error('Failed to fetch system config', e)
    } finally {
      loading.value = false
    }
  }

  async function saveConfig(updated: SystemCfg): Promise<boolean> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/system-config`, {
        method: 'PUT',
        headers: makeHeaders(),
        body: JSON.stringify({
          email_notifs_enabled: updated.emailNotifs,
          in_app_notifs_enabled: updated.inAppNotifs,
          contract_expiry_alerts: updated.contractExpiryAlerts,
          approval_alerts: updated.approvalAlerts,
          renewal_reminders: updated.renewalReminders,
        }),
      })
      const json = await res.json()
      if (res.ok && json.data) {
        Object.assign(cfg, {
          emailNotifs: json.data.email_notifs_enabled ?? true,
          inAppNotifs: json.data.in_app_notifs_enabled ?? true,
          contractExpiryAlerts: json.data.contract_expiry_alerts ?? true,
          approvalAlerts: json.data.approval_alerts ?? true,
          renewalReminders: json.data.renewal_reminders ?? true,
        })
        return true
      }
      return false
    } catch (e) {
      console.error('Failed to save system config', e)
      return false
    } finally {
      loading.value = false
    }
  }

  return {
    cfg,
    loading,
    fetchConfig,
    saveConfig,
  }
}
