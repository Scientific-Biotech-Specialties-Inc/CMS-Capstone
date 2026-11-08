import { reactive } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id:           number
  type:         ToastType
  title:        string
  description?: string
  duration:     number
  remaining:    number
  timerId?:     ReturnType<typeof setTimeout>
  startTime?:   number
}

let _id = 0
const toasts = reactive<Toast[]>([])

export function useToast() {
  function add(type: ToastType, title: string, description?: string, duration = 4000) {
    const id = ++_id
    const toast: Toast = {
      id,
      type,
      title,
      description,
      duration,
      remaining: duration,
      startTime: Date.now(),
    }

    toast.timerId = setTimeout(() => remove(id), duration)
    toasts.push(toast)
  }

  function pause(id: number) {
    const toast = toasts.find(t => t.id === id)
    if (!toast || !toast.timerId || !toast.startTime) return
    clearTimeout(toast.timerId)
    toast.timerId = undefined
    const elapsed = Date.now() - toast.startTime
    toast.remaining = Math.max(1000, toast.remaining - elapsed)
  }

  function resume(id: number) {
    const toast = toasts.find(t => t.id === id)
    if (!toast || toast.timerId) return
    toast.startTime = Date.now()
    toast.timerId = setTimeout(() => remove(id), toast.remaining)
  }

  function remove(id: number) {
    const idx = toasts.findIndex(t => t.id === id)
    if (idx >= 0) {
      if (toasts[idx].timerId) {
        clearTimeout(toasts[idx].timerId)
      }
      toasts.splice(idx, 1)
    }
  }

  return {
    toasts,
    success: (title: string, description?: string) => add('success', title, description),
    error:   (title: string, description?: string) => add('error',   title, description),
    info:    (title: string, description?: string) => add('info',    title, description),
    warning: (title: string, description?: string) => add('warning', title, description),
    pause,
    resume,
    remove,
  }
}
