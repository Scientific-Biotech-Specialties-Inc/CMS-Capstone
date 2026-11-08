<script setup lang="ts">
import { Check, X as XIcon, Info, AlertTriangle } from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'

const { toasts, remove, pause, resume } = useToast()

const config = {
  success: {
    icon: Check,
    iconColor: 'text-[#10B981]',
    badgeBg: 'bg-[#ECFDF5]',
    borderClass: 'border-l-4 border-l-[#10B981]',
  },
  error: {
    icon: XIcon,
    iconColor: 'text-[#EF4444]',
    badgeBg: 'bg-[#FEF2F2]',
    borderClass: 'border-l-4 border-l-[#EF4444]',
  },
  warning: {
    icon: AlertTriangle,
    iconColor: 'text-[#F59E0B]',
    badgeBg: 'bg-[#FFFBEB]',
    borderClass: 'border-l-4 border-l-[#F59E0B]',
  },
  info: {
    icon: Info,
    iconColor: 'text-[#2E85D8]',
    badgeBg: 'bg-[#EFF6FF]',
    borderClass: 'border-l-4 border-l-[#2E85D8]',
  },
}
</script>

<template>
  <Teleport to="body">
    <!-- Viewport Positioning: Bottom-Right (bottom: 24px, right: 24px, z-index: 1200) -->
    <div
      class="fixed bottom-6 right-6 z-[1200] flex flex-col gap-2.5 pointer-events-none select-none max-w-sm w-full"
      aria-live="polite"
    >
      <TransitionGroup
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-x-8 opacity-0 scale-95"
        enter-to-class="translate-x-0 opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="translate-x-8 opacity-0 scale-95"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-center gap-3 w-full bg-white border border-slate-200/90 rounded-xl shadow-lg px-4 py-3 transition-all font-poppins"
          :class="config[toast.type].borderClass"
          @mouseenter="pause(toast.id)"
          @mouseleave="resume(toast.id)"
        >
          <!-- Status Icon Badge with soft circular wash -->
          <div
            class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
            :class="config[toast.type].badgeBg"
          >
            <component
              :is="config[toast.type].icon"
              class="w-5 h-5 shrink-0 stroke-[2.5]"
              :class="config[toast.type].iconColor"
            />
          </div>

          <!-- Text Block: Vertically Centered -->
          <div class="flex-1 min-w-0 pr-1 flex flex-col justify-center">
            <p class="text-xs font-bold text-slate-800 leading-snug tracking-wide">
              {{ toast.title }}
            </p>
            <p
              v-if="toast.description"
              class="text-xs text-slate-500 mt-0.5 leading-tight font-normal"
            >
              {{ toast.description }}
            </p>
          </div>

          <!-- Close Button: Vertically Centered -->
          <button
            type="button"
            @click="remove(toast.id)"
            class="text-slate-400 hover:text-slate-700 transition-colors shrink-0 p-1 rounded hover:bg-slate-100 cursor-pointer self-center"
            title="Dismiss notification"
          >
            <XIcon class="w-4 h-4" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
