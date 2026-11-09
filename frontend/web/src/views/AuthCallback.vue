<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const { setAuth } = useAuth()

const AUTH_API = import.meta.env.VITE_AUTH_API_URL as string

onMounted(async () => {
  // Read the intended destination from the ?state= query param
  const params = new URLSearchParams(window.location.search)
  let state = params.get('state') || '/admin/dashboard'
  // Strip /cms prefix since Vue Router base already handles it
  state = state.replace(/^\/cms/, '') || '/admin/dashboard'

  console.log('[AuthCallback] Starting auth handshake. Target:', state)

  try {
    // Call the Auth Module API to get the logged-in user's profile
    // The session cookie set by the Auth Module will be sent automatically
    const res = await fetch(`${AUTH_API}/user`, {
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    })

    if (!res.ok) {
      console.error('[AuthCallback] Auth API returned error:', res.status)
      window.location.href = '/'
      return
    }

    const data = await res.json()
    console.log('[AuthCallback] Auth API response:', data)

    // The auth module may wrap the user in a `data` key or return it directly
    const user = data.data ?? data
    const token = data.token ?? data.access_token ?? localStorage.getItem('access_token') ?? ''

    if (!user || !user.id) {
      console.error('[AuthCallback] No valid user in response. Redirecting to login.')
      window.location.href = '/'
      return
    }

    // Store the user in our CMS auth state
    setAuth(user, token)
    console.log('[AuthCallback] User authenticated! Redirecting to:', state)

    // Navigate to the intended destination
    router.replace(state)

  } catch (err) {
    console.error('[AuthCallback] Network error during auth handshake:', err)
    window.location.href = '/'
  }
})
</script>

<template>
  <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: #f9fafb;">
    <div style="font-family: 'Poppins', sans-serif; text-align: center;">
      <div style="width: 48px; height: 48px; border: 4px solid rgba(37,37,120,0.1); border-top-color: #252578; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 24px;"></div>
      <h2 style="color: #252578; font-size: 20px; font-weight: 600; margin: 0 0 8px;">Logging you in...</h2>
      <p style="color: #6b7280; font-size: 14px; margin: 0;">Setting up your session</p>
    </div>
    <style>
      @keyframes spin { to { transform: rotate(360deg); } }
    </style>
  </div>
</template>
