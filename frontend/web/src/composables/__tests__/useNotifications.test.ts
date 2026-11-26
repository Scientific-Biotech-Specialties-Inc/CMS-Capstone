import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useNotifications, __testing } from '../useNotifications'
import { useToast } from '../useToast'

const refreshCachedInBackgroundMock = vi.fn()

vi.mock('../useAuth', () => ({
  useAuth: () => ({
    state: {
      token: 'test-jwt-token',
      user: { id: 7, role: 'Manager' },
    },
  }),
}))

vi.mock('../useApiCache', () => ({
  useApiCache: () => ({
    refreshCachedInBackground: refreshCachedInBackgroundMock,
  }),
}))

describe('useNotifications (real-time SSE)', () => {
  beforeEach(() => {
    __testing.resetNotificationState()
    refreshCachedInBackgroundMock.mockClear()
    const { toasts, remove } = useToast()
    while (toasts.length > 0) {
      remove(toasts[0]!.id)
    }
  })

  it('shares singleton notification state across consumers and pops a toast on real-time ingest', () => {
    const header = useNotifications()
    const page = useNotifications()
    const { toasts } = useToast()

    __testing.ingestRealtimeNotification({
      id: '101',
      contract_id: 55,
      notification_type: 'manager_approval_request',
      message: 'Contract CON-055 needs your approval.',
      notification_date: new Date().toISOString(),
      is_read: false,
      is_archived: false,
      is_favorite: false,
    })

    expect(header.notifications.value).toHaveLength(1)
    expect(page.notifications.value).toHaveLength(1)
    expect(header.unreadCount.value).toBe(1)
    expect(toasts).toHaveLength(1)
    expect(toasts[0]?.type).toBe('info')
    expect(toasts[0]?.description).toContain('CON-055')
    expect(refreshCachedInBackgroundMock).toHaveBeenCalledTimes(1)
  })

  it('shows warning toast for expiry reminders and deduplicates repeated IDs', () => {
    const { notifications } = useNotifications()
    const { toasts } = useToast()

    const payload = {
      id: '202',
      contract_id: 99,
      notification_type: 'expiry_30',
      message: 'Contract CON-099 expires in 30 days.',
      notification_date: new Date().toISOString(),
      is_read: false,
      is_archived: false,
      is_favorite: false,
    }

    __testing.ingestRealtimeNotification(payload)
    __testing.ingestRealtimeNotification(payload)

    expect(notifications.value).toHaveLength(1)
    expect(toasts).toHaveLength(1)
    expect(toasts[0]?.type).toBe('warning')
  })

  it('streams SSE events from fetch ReadableStream and updates notifications live', async () => {
    const encoder = new TextEncoder()
    let streamCalls = 0

    const fetchMock = vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.endsWith('/notifications/stream')) {
        streamCalls += 1
        const body = new ReadableStream({
          start(controller) {
            controller.enqueue(encoder.encode('event: connected\ndata: {"status":"ok"}\n\n'))
            if (streamCalls === 1) {
              controller.enqueue(
                encoder.encode(
                  'id: 2026-11-27T00:00:00Z\nevent: notification\ndata: {"id":"303","contract_id":12,"notification_type":"contract_status_updated","message":"Contract approved","notification_date":"2026-11-27T00:00:00Z"}\n\n'
                )
              )
            }
            controller.close()
          },
        })
        return Promise.resolve(
          new Response(body, {
            status: 200,
            headers: { 'Content-Type': 'text/event-stream' },
          })
        )
      }
      return Promise.resolve(
        new Response(JSON.stringify({ data: [] }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      )
    })

    vi.stubGlobal('fetch', fetchMock)

    const { notifications, startNotificationStream, stopNotificationStream } = useNotifications()
    startNotificationStream()

    await vi.waitFor(() => {
      expect(notifications.value.some(n => n.id === '303')).toBe(true)
    })

    stopNotificationStream()
    vi.unstubAllGlobals()
  })
})
