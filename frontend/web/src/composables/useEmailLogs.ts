import { ref } from 'vue'
import { useAuth } from './useAuth'
import type { EmailSendLog } from '@/types/notification'

const BASE_URL = import.meta.env.VITE_NOTIFICATION_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  return {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
    ...(state.token ? { 'Authorization': `Bearer ${state.token}` } : {}),
  }
}

export function useEmailLogs() {
  const logs = ref<EmailSendLog[]>([])
  const loading = ref(false)
  const currentPage = ref(1)
  const lastPage = ref(1)
  const totalLogs = ref(0)

  async function fetchLogs(page = 1): Promise<void> {
    loading.value = true
    try {
      const res = await fetch(`${BASE_URL}/email-logs?page=${page}`, { headers: makeHeaders() })
      const json = await res.json()
      if (res.ok && json.data) {
        logs.value = json.data.map((d: any) => ({
          id: d.id,
          notificationId: d.notification_id,
          userId: d.user_id,
          recipientEmail: d.recipient_email,
          subject: d.subject,
          status: d.status,
          errorMessage: d.error_message,
          sentAt: d.sent_at,
          createdAt: d.created_at,
        }))
        if (json.meta) {
          currentPage.value = json.meta.current_page ?? 1
          lastPage.value = json.meta.last_page ?? 1
          totalLogs.value = json.meta.total ?? 0
        }
      }
    } catch (e) {
      console.error('Failed to fetch email logs', e)
    } finally {
      loading.value = false
    }
  }

  return {
    logs,
    loading,
    currentPage,
    lastPage,
    totalLogs,
    fetchLogs,
  }
}
