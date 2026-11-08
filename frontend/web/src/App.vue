<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue'
import { useAuth } from '@/composables/useAuth'
import Toaster from '@/components/ui/toast/Toaster.vue'
import LoaderOverlay from '@/components/shared/LoaderOverlay.vue'

const { logout, isAuthenticated } = useAuth()

let timeoutId: number | null = null
const TIMEOUT_MS = 3 * 60 * 60 * 1000 // 3 hours

const resetIdleTimer = () => {
  if (timeoutId) {
    window.clearTimeout(timeoutId)
  }
  if (isAuthenticated.value) {
    timeoutId = window.setTimeout(() => {
      logout()
    }, TIMEOUT_MS)
  }
}

watch(isAuthenticated, (newVal) => {
  if (newVal) {
    resetIdleTimer()
  } else if (timeoutId) {
    window.clearTimeout(timeoutId)
  }
})

onMounted(() => {
  document.body.style.overflow = ''
  document.body.style.pointerEvents = 'auto'
  window.addEventListener('mousemove', resetIdleTimer)
  window.addEventListener('keydown', resetIdleTimer)
  window.addEventListener('click', resetIdleTimer)
  window.addEventListener('scroll', resetIdleTimer)
  resetIdleTimer()
})

onUnmounted(() => {
  window.removeEventListener('mousemove', resetIdleTimer)
  window.removeEventListener('keydown', resetIdleTimer)
  window.removeEventListener('click', resetIdleTimer)
  window.removeEventListener('scroll', resetIdleTimer)
  if (timeoutId) {
    window.clearTimeout(timeoutId)
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <main>
      <router-view />
    </main>
    <Toaster />
    <LoaderOverlay />
  </div>
</template>