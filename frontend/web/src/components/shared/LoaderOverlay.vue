<script setup lang="ts">
import { useLoader } from '@/composables/useLoader'

const { isLoading, label } = useLoader()
</script>

<template>
  <Transition name="loader">
    <div v-if="isLoading"
      class="fixed inset-0 z-[9999] flex items-center justify-center"
      style="background: rgba(249, 250, 251, 0.6); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);">

      <div class="flex flex-col items-center text-center px-8 py-12 max-w-sm w-full">
        <!-- Brand Title -->
        <p class="text-[22px] font-bold text-brand-navy tracking-tight mb-10"
          style="letter-spacing: -0.025em;">
          SBSI Contract Management
        </p>

        <!-- Circular Spinner -->
        <div class="flex flex-col items-center gap-6">
          <div class="loader-circular-spinner"></div>

          <!-- Dynamic Status Label -->
          <p class="text-[15px] font-semibold text-black/60">{{ label }}</p>
        </div>
      </div>

    </div>
  </Transition>
</template>

<style scoped>
.loader-enter-active,
.loader-leave-active { transition: opacity 0.2s ease; }
.loader-enter-from,
.loader-leave-to    { opacity: 0; }

.loader-circular-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid color-mix(in srgb, var(--brand-navy) 10%, transparent);
  border-top-color: var(--brand-navy);
  border-radius: 9999px;
  animation: loader-spin 0.9s linear infinite;
}

@keyframes loader-spin {
  to { transform: rotate(360deg); }
}
</style>
