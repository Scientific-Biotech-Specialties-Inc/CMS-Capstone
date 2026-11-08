import { ref, computed } from 'vue'
import { useAuth } from './useAuth'
import type { Notification, NotifType } from '@/types/notification'

const BASE_URL = import.meta.env.VITE_NOTIFICATION_API_URL as string

function makeHeaders(): HeadersInit {
  const { state } = useAuth()
  const headers: Record<string, string> = {
    'Accept':        'application/json',
    'Content-Type':  'application/json',
  }
  if (state.token && state.token !== 'null' && state.token !== 'undefined') {
    headers['Authorization'] = `Bearer ${state.token}`
  }
  return headers
}

function apiTypeToNotifType(notifType: string | undefined): NotifType {
  if (notifType === 'expiry_90' || notifType === 'expiry_30' || notifType === 'expiry_1') {
    return 'reminder'
  }
  if (notifType === 'email_dispatch' || notifType === 'new_vendor') {
    return 'system'
  }
  return 'contract'
}

function formatTimestamp(isoString: string | undefined): string {
  if (!isoString) return ''
  const date  = new Date(isoString)
  const now   = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMin = Math.floor(diffMs / 60000)
  const diffHr  = Math.floor(diffMin / 60)
  const diffDay = Math.floor(diffHr / 24)

  if (diffMin < 1)   return 'Just now'
  if (diffMin < 60)  return `${diffMin} min ago`
  if (diffHr < 24)   return `${diffHr}h ago`
  if (diffDay === 1) return 'Yesterday'
  if (diffDay < 7)   return `${diffDay} days ago`

  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function mapNotification(d: any): Notification {
  return {
    id:          String(d.id),
    type:        apiTypeToNotifType(d.notification_type),
    message:     d.message ?? '',
    timestamp:   formatTimestamp(d.notification_date),
    isRead:      d.is_read ?? false,
    isArchived:  d.is_archived ?? false,
    isFavorite:  d.is_favorite ?? false,
    contractId:  d.contract_id ?? null,
    notifType:   d.notification_type ?? undefined,
  }
}

export function useNotifications() {
  const notifications = ref<Notification[]>([])
  const loading       = ref(false)

  const unreadCount = computed(() =>
    notifications.value.filter(n => !n.isRead && !n.isArchived).length
  )

  async function fetchNotifications(): Promise<void> {
    loading.value = true
    try {
      const res  = await fetch(`${BASE_URL}/notifications`, {
        headers: makeHeaders(),
        credentials: 'same-origin',
      })
      const json = await res.json()
      if (res.ok) {
        notifications.value = (json.data ?? []).map(mapNotification)
      }
    } catch (e) {
      console.error('Failed to fetch notifications', e)
    } finally {
      loading.value = false
    }
  }

  async function markRead(id: string): Promise<void> {
    const notif = notifications.value.find(n => n.id === id)
    if (notif) notif.isRead = true
    try {
      await fetch(`${BASE_URL}/notifications/${id}/read`, {
        method:      'PATCH',
        headers:     makeHeaders(),
        credentials: 'same-origin',
      })
    } catch (e) {
      console.error('Failed to mark read', e)
    }
  }

  async function markAllRead(): Promise<void> {
    notifications.value.forEach(n => { if (!n.isArchived) n.isRead = true })
    try {
      await fetch(`${BASE_URL}/notifications/read-all`, {
        method:      'PATCH',
        headers:     makeHeaders(),
        credentials: 'same-origin',
      })
    } catch (e) {
      console.error('Failed to mark all read', e)
    }
  }

  async function updateState(id: string, patch: { isArchived?: boolean; isFavorite?: boolean }): Promise<void> {
    const notif = notifications.value.find(n => n.id === id)
    if (notif) {
      if (patch.isArchived !== undefined) notif.isArchived = patch.isArchived
      if (patch.isFavorite !== undefined) notif.isFavorite = patch.isFavorite
    }
    try {
      await fetch(`${BASE_URL}/notifications/${id}/state`, {
        method:      'PATCH',
        headers:     makeHeaders(),
        credentials: 'same-origin',
        body:        JSON.stringify({
          is_archived: patch.isArchived,
          is_favorite: patch.isFavorite,
        }),
      })
    } catch (e) {
      console.error('Failed to update notification state', e)
    }
  }

  async function deletePermanent(id: string): Promise<void> {
    notifications.value = notifications.value.filter(n => n.id !== id)
    try {
      await fetch(`${BASE_URL}/notifications/${id}`, {
        method:      'DELETE',
        headers:     makeHeaders(),
        credentials: 'same-origin',
      })
    } catch (e) {
      console.error('Failed to permanently delete notification', e)
    }
  }

  async function deletePermanentBatch(ids: string[]): Promise<void> {
    const idSet = new Set(ids)
    notifications.value = notifications.value.filter(n => !idSet.has(n.id))
    try {
      await fetch(`${BASE_URL}/notifications/batch-delete`, {
        method:      'POST',
        headers:     makeHeaders(),
        credentials: 'same-origin',
        body:        JSON.stringify({ ids }),
      })
    } catch (e) {
      console.error('Failed to permanently delete notifications', e)
    }
  }

  return {
    notifications,
    loading,
    unreadCount,
    fetchNotifications,
    markRead,
    markAllRead,
    updateState,
    deletePermanent,
    deletePermanentBatch,
  }
}
