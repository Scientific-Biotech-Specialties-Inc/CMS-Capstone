import { ref, readonly } from 'vue'

export type LoaderAction = 'loading' | 'creating' | 'updating' | 'deleting' | 'saving'

const LABELS: Record<LoaderAction, string> = {
  loading:  'Loading…',
  creating: 'Creating…',
  updating: 'Updating…',
  deleting: 'Deleting…',
  saving:   'Saving…',
}

const isLoading = ref(false)
const action    = ref<LoaderAction>('loading')
const label     = ref(LABELS.loading)

/**
 * Show the global loader overlay with custom action/label.
 */
function showLoader(type: LoaderAction = 'loading', customLabel?: string) {
  action.value = type
  label.value = customLabel || LABELS[type]
  isLoading.value = true
}

/**
 * Hide the global loader overlay.
 */
function hideLoader() {
  isLoading.value = false
}

/**
 * Wrap any async operation with a loading state.
 * Usage: await withLoading('creating', () => fetch(...))
 */
async function withLoading<T>(type: LoaderAction, fn: () => Promise<T>): Promise<T> {
  action.value  = type
  label.value   = LABELS[type]
  isLoading.value = true
  try {
    return await fn()
  } finally {
    isLoading.value = false
  }
}

export function useLoader() {
  return {
    isLoading: readonly(isLoading),
    action:    readonly(action),
    label:     readonly(label),
    withLoading,
    showLoader,
    hideLoader,
  }
}
