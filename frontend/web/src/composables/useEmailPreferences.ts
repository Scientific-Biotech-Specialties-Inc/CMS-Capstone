import { ref } from 'vue'
import { useAuth } from './useAuth'
import type { EmailPreference } from '@/types/notification'

const BASE_URL = import.meta.env.VITE_NOTIFICATION_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  return {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
    ...(state.token ? { 'Authorization': `Bearer ${state.token}` } : {}),
  }
}

export function useEmailPreferences() {
  const preferences = ref<EmailPreference>({
    emailNotificationsEnabled: true,
    contractExpiryAlerts: true,
    systemAlertsEnabled: true,
    smsNotificationsEnabled: false,
    loginAlertsEnabled: true,
    aiRiskAssessmentEnabled: true,
    aiVendorSuggestionsEnabled: true,
    timezone: 'Asia/Manila',
    language: 'English',
    dateFormat: 'MM/DD/YYYY',
  })
  const loading = ref(false)

  async function fetchPreferences(): Promise<void> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/email-preferences`, { headers: makeHeaders() })
      const json = await res.json()
      
      const timezone = localStorage.getItem('pref_timezone') || 'Asia/Manila'
      const language = localStorage.getItem('pref_language') || 'English'
      const dateFormat = localStorage.getItem('pref_dateFormat') || 'MM/DD/YYYY'

      if (res.ok && json.data) {
        preferences.value = {
          emailNotificationsEnabled: json.data.email_notifications_enabled ?? true,
          contractExpiryAlerts: json.data.contract_expiry_alerts ?? true,
          systemAlertsEnabled: json.data.system_alerts_enabled ?? true,
          smsNotificationsEnabled: json.data.sms_notifications_enabled ?? false,
          loginAlertsEnabled: json.data.login_alerts_enabled ?? true,
          aiRiskAssessmentEnabled: json.data.ai_risk_assessment_enabled ?? true,
          aiVendorSuggestionsEnabled: json.data.ai_vendor_suggestions_enabled ?? true,
          timezone,
          language,
          dateFormat,
        }
      } else {
        preferences.value = {
          emailNotificationsEnabled: true,
          contractExpiryAlerts: true,
          systemAlertsEnabled: true,
          smsNotificationsEnabled: false,
          loginAlertsEnabled: true,
          aiRiskAssessmentEnabled: true,
          aiVendorSuggestionsEnabled: true,
          timezone,
          language,
          dateFormat,
        }
      }
    } catch (e) {
      console.error('Failed to fetch email preferences', e)
      preferences.value = {
        emailNotificationsEnabled: true,
        contractExpiryAlerts: true,
        systemAlertsEnabled: true,
        smsNotificationsEnabled: false,
        loginAlertsEnabled: true,
        aiRiskAssessmentEnabled: true,
        aiVendorSuggestionsEnabled: true,
        timezone: localStorage.getItem('pref_timezone') || 'Asia/Manila',
        language: localStorage.getItem('pref_language') || 'English',
        dateFormat: localStorage.getItem('pref_dateFormat') || 'MM/DD/YYYY',
      }
    } finally {
      loading.value = false
    }
  }

  async function savePreferences(updated: EmailPreference): Promise<boolean> {
    loading.value = true
    try {
      if (updated.timezone !== undefined) {
        localStorage.setItem('pref_timezone', updated.timezone)
      }
      if (updated.language !== undefined) {
        localStorage.setItem('pref_language', updated.language)
      }
      if (updated.dateFormat !== undefined) {
        localStorage.setItem('pref_dateFormat', updated.dateFormat)
      }

      const res = await fetch(`${BASE_URL}/email-preferences`, {
        method: 'PUT',
        headers: makeHeaders(),
        body: JSON.stringify({
          email_notifications_enabled: updated.emailNotificationsEnabled,
          contract_expiry_alerts: updated.contractExpiryAlerts,
          system_alerts_enabled: updated.systemAlertsEnabled,
          sms_notifications_enabled: updated.smsNotificationsEnabled,
          login_alerts_enabled: updated.loginAlertsEnabled,
          ai_risk_assessment_enabled: updated.aiRiskAssessmentEnabled ?? true,
          ai_vendor_suggestions_enabled: updated.aiVendorSuggestionsEnabled ?? true,
        }),
      })
      const json = await res.json()
      if (res.ok && json.data) {
        preferences.value = {
          emailNotificationsEnabled: json.data.email_notifications_enabled ?? true,
          contractExpiryAlerts: json.data.contract_expiry_alerts ?? true,
          systemAlertsEnabled: json.data.system_alerts_enabled ?? true,
          smsNotificationsEnabled: json.data.sms_notifications_enabled ?? false,
          loginAlertsEnabled: json.data.login_alerts_enabled ?? true,
          aiRiskAssessmentEnabled: json.data.ai_risk_assessment_enabled ?? true,
          aiVendorSuggestionsEnabled: json.data.ai_vendor_suggestions_enabled ?? true,
          timezone: updated.timezone || 'Asia/Manila',
          language: updated.language || 'English',
          dateFormat: updated.dateFormat || 'MM/DD/YYYY',
        }
        return true
      }
      return false
    } catch (e) {
      console.error('Failed to save email preferences', e)
      return false
    } finally {
      loading.value = false
    }
  }

  return {
    preferences,
    loading,
    fetchPreferences,
    savePreferences,
  }
}
