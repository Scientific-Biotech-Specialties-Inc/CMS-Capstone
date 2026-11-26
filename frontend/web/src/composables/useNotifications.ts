import { ref, computed, watch } from 'vue'
import { useAuth } from './useAuth'
import { useApiCache } from './useApiCache'
import { useToast } from './useToast'
import { parseSseChunk } from '@/utils/sseParser'
import type { Notification, NotifType } from '@/types/notification'

const BASE_URL = (import.meta.env.VITE_NOTIFICATION_API_URL as string) || '/api/notifications'

// Shared singleton state so header bell dropdown and Notifications page stay in sync
const notifications = ref<Notification[]>([])
const loading = ref(false)
const streamConnected = ref(false)

let knownNotificationIds: Set<string> | null = null
let activeConsumers = 0
let streamController: AbortController | null = null
let reconnectTimer: ReturnType<typeof setTimeout> | null = null
let fallbackPollTimer: ReturnType<typeof setInterval> | null = null
let reconnectAttempt = 0
let lastEventId: string | null = null
let lastAuthKey: string | null = null

const FALLBACK_POLL_MS = 30_000

function makeHeaders(extra?: Record<string, string>): Record<string, string> {
  const { state } = useAuth()
  const headers: Record<string, string> = {
    'Accept':       'application/json',
    'Content-Type': 'application/json',
    ...extra,
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

function toastTitleFor(notif: Notification): string {
  if (notif.type === 'reminder') return 'Contract Expiry Alert'
  if (notif.type === 'system') return 'System Notification'
  return 'New Contract Notification'
}

function isContractEvent(notif: Notification): boolean {
  return notif.type === 'contract'
    || notif.contractId !== null
    || Boolean(notif.notifType?.startsWith('prs_'))
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

function clearTimers(): void {
  if (reconnectTimer) {
    clearTimeout(reconnectTimer)
    reconnectTimer = null
  }
  if (fallbackPollTimer) {
    clearInterval(fallbackPollTimer)
    fallbackPollTimer = null
  }
}

function startFallbackPoll(): void {
  if (fallbackPollTimer || activeConsumers <= 0) return
  fallbackPollTimer = setInterval(() => {
    if (!streamConnected.value && activeConsumers > 0) {
      void fetchNotifications()
    }
  }, FALLBACK_POLL_MS)
}

function stopFallbackPoll(): void {
  if (fallbackPollTimer) {
    clearInterval(fallbackPollTimer)
    fallbackPollTimer = null
  }
}

function teardownStream(): void {
  clearTimers()
  if (streamController) {
    streamController.abort()
    streamController = null
  }
  streamConnected.value = false
}

function resetNotificationState(): void {
  teardownStream()
  notifications.value = []
  knownNotificationIds = null
  lastEventId = null
  reconnectAttempt = 0
}

function ingestRealtimeNotification(raw: any): void {
  const mapped = mapNotification(raw)
  const existingIdx = notifications.value.findIndex(n => n.id === mapped.id)
  const isNewOrRefreshed = existingIdx === -1 || notifications.value[existingIdx]?.message !== mapped.message

  if (existingIdx >= 0) {
    notifications.value.splice(existingIdx, 1)
  }
  notifications.value = [mapped, ...notifications.value]

  if (knownNotificationIds === null) {
    knownNotificationIds = new Set([mapped.id])
  } else {
    knownNotificationIds.add(mapped.id)
  }

  if (isNewOrRefreshed) {
    const toast = useToast()
    if (mapped.type === 'reminder') {
      toast.warning(toastTitleFor(mapped), mapped.message)
    } else {
      toast.info(toastTitleFor(mapped), mapped.message)
    }
  }

  if (isContractEvent(mapped)) {
    useApiCache().refreshCachedInBackground()
  }
}

async function fetchNotifications(): Promise<void> {
  const { state } = useAuth()
  if (!state.token) return

  loading.value = true
  try {
    const res  = await fetch(`${BASE_URL}/notifications`, {
      headers: makeHeaders(),
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (res.ok) {
      const mapped: Notification[] = (json.data ?? []).map(mapNotification)
      const hasNewContractEvent = knownNotificationIds !== null && mapped.some(
        (n: Notification) => !knownNotificationIds!.has(n.id) && isContractEvent(n)
      )
      knownNotificationIds = new Set(mapped.map((n: Notification) => n.id))
      notifications.value = mapped

      if (hasNewContractEvent) {
        useApiCache().refreshCachedInBackground()
      }
    }
  } catch (e) {
    console.error('Failed to fetch notifications', e)
  } finally {
    loading.value = false
  }
}

function scheduleReconnect(immediate = false): void {
  if (activeConsumers <= 0) return
  if (reconnectTimer) return

  const delayMs = immediate
    ? 500
    : Math.min(15_000, 1000 * Math.pow(2, reconnectAttempt))
  if (!immediate) {
    reconnectAttempt = Math.min(reconnectAttempt + 1, 4)
    startFallbackPoll()
  }

  reconnectTimer = setTimeout(() => {
    reconnectTimer = null
    if (activeConsumers > 0) {
      void connectStream()
    }
  }, delayMs)
}

async function connectStream(): Promise<void> {
  if (activeConsumers <= 0) return

  const { state } = useAuth()
  if (!state.token || state.token === 'null' || state.token === 'undefined') {
    return
  }

  if (streamController) {
    streamController.abort()
  }

  const controller = new AbortController()
  streamController = controller

  const headers = makeHeaders({
    Accept: 'text/event-stream',
    ...(lastEventId ? { 'Last-Event-ID': lastEventId } : {}),
  })
  delete headers['Content-Type']

  let cleanClose = false

  try {
    const res = await fetch(`${BASE_URL}/notifications/stream`, {
      headers,
      credentials: 'same-origin',
      signal: controller.signal,
    })

    if (!res.ok || !res.body) {
      throw new Error(`SSE stream failed with status ${res.status}`)
    }

    streamConnected.value = true
    reconnectAttempt = 0
    stopFallbackPoll()

    const reader = res.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    while (!controller.signal.aborted) {
      const { value, done } = await reader.read()
      if (done) {
        cleanClose = true
        break
      }

      const chunk = decoder.decode(value, { stream: true })
      const parsed = parseSseChunk(buffer, chunk)
      buffer = parsed.remainder

      for (const frame of parsed.frames) {
        if (frame.id) {
          lastEventId = frame.id
        }
        if (frame.event === 'notification') {
          try {
            const payload = JSON.parse(frame.data)
            ingestRealtimeNotification(payload)
          } catch {
            // Ignore malformed SSE payloads
          }
        }
      }
    }
  } catch (err: any) {
    if (err?.name === 'AbortError' || controller.signal.aborted) {
      return
    }
    console.warn('Notification SSE stream disconnected; reconnecting...', err)
  } finally {
    if (streamController === controller) {
      streamController = null
    }
    streamConnected.value = false
    if (!controller.signal.aborted && activeConsumers > 0) {
      scheduleReconnect(cleanClose)
    }
  }
}

function startNotificationStream(): void {
  activeConsumers += 1
  if (activeConsumers === 1) {
    void fetchNotifications().finally(() => {
      if (activeConsumers > 0) {
        void connectStream()
      }
    })
  }
}

function stopNotificationStream(): void {
  activeConsumers = Math.max(0, activeConsumers - 1)
  if (activeConsumers === 0) {
    teardownStream()
  }
}

// Keep notification state & stream in sync with authentication changes
watch(
  () => {
    const { state } = useAuth()
    return `${state.token ?? ''}:${state.user?.id ?? ''}`
  },
  (authKey) => {
    if (lastAuthKey !== null && lastAuthKey !== authKey) {
      resetNotificationState()
      if (activeConsumers > 0 && authKey !== ':') {
        void fetchNotifications().finally(() => {
          if (activeConsumers > 0) {
            void connectStream()
          }
        })
      }
    }
    lastAuthKey = authKey
  },
  { immediate: true }
)

export function useNotifications() {
  const unreadCount = computed(() =>
    notifications.value.filter(n => !n.isRead && !n.isArchived).length
  )

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
    streamConnected,
    fetchNotifications,
    startNotificationStream,
    stopNotificationStream,
    markRead,
    markAllRead,
    updateState,
    deletePermanent,
    deletePermanentBatch,
  }
}

export const __testing = {
  resetNotificationState,
  ingestRealtimeNotification,
}
