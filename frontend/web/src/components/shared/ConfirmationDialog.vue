<script setup lang="ts">
import { computed } from 'vue'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Trash2, Check, FileText, AlertTriangle, Loader2, Sparkles } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  open: boolean
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'default' | 'destructive' | 'warning' | 'ai'
  loading?: boolean
}>(), {
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'default',
  loading: false,
})

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

// Determine semantic type based on variant or title keywords to support modal-reference styles
const semanticType = computed<'destructive' | 'approve' | 'warning' | 'ai' | 'default'>(() => {
  if (props.variant === 'destructive') return 'destructive'
  if (props.variant === 'warning') return 'warning'
  if (props.variant === 'ai') return 'ai'
  const titleLower = props.title.toLowerCase()
  const labelLower = props.confirmLabel.toLowerCase()
  if (titleLower.includes('delete') || labelLower.includes('delete')) return 'destructive'
  if (titleLower.includes('approve') || labelLower.includes('approve')) return 'approve'
  if (titleLower.includes('reject') || labelLower.includes('reject')) return 'destructive'
  if (titleLower.includes('generate') || labelLower.includes('generate') || titleLower.includes('ai')) return 'ai'
  return 'default'
})

const badgeConfig = computed(() => {
  switch (semanticType.value) {
    case 'destructive':
      return {
        icon: Trash2,
        bgClass: 'bg-[#FEF2F2]',
        colorClass: 'text-[#EF4444]',
        buttonClass: 'bg-[#EF4444] hover:bg-[#DC2626] text-white',
      }
    case 'approve':
      return {
        icon: Check,
        bgClass: 'bg-[#ECFDF5]',
        colorClass: 'text-[#10B981]',
        buttonClass: 'bg-[#10B981] hover:bg-[#059669] text-white',
      }
    case 'warning':
      return {
        icon: AlertTriangle,
        bgClass: 'bg-[#FFFBEB]',
        colorClass: 'text-[#F59E0B]',
        buttonClass: 'bg-[#F59E0B] hover:bg-[#D97706] text-white',
      }
    case 'ai':
      return {
        icon: Sparkles,
        bgClass: 'bg-[#EEF2FF]',
        colorClass: 'text-[#252578]',
        buttonClass: 'bg-[#252578] hover:bg-[#1E1E66] text-white',
      }
    default:
      return {
        icon: FileText,
        bgClass: 'bg-[#EEF2FF]',
        colorClass: 'text-[#252578]',
        buttonClass: 'bg-[#252578] hover:bg-[#1E1E66] text-white',
      }
  }
})

function handleOpenUpdate(val: boolean) {
  emit('update:open', val)
}

function handleConfirm() {
  emit('confirm')
}

function handleCancel() {
  emit('cancel')
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenUpdate">
    <DialogContent class="max-w-[420px] p-8 gap-0 rounded-2xl shadow-xl border border-black/5" @pointer-down-outside="loading ? undefined : handleCancel">
      
      <!-- Top Centered Icon Badge -->
      <div class="flex justify-center mb-5">
        <div 
          class="w-14 h-14 rounded-full flex items-center justify-center transition-transform duration-200"
          :class="badgeConfig.bgClass"
        >
          <component
            :is="badgeConfig.icon"
            class="w-7 h-7 stroke-[2.2]"
            :class="badgeConfig.colorClass"
          />
        </div>
      </div>

      <!-- Centered Header -->
      <DialogHeader class="text-center sm:text-center space-y-2 mb-6">
        <DialogTitle class="text-lg font-bold text-slate-900 tracking-tight font-poppins">
          {{ title }}
        </DialogTitle>
        <DialogDescription class="text-xs sm:text-[13px] text-slate-500 leading-relaxed max-w-[340px] mx-auto font-poppins">
          {{ description }}
        </DialogDescription>
      </DialogHeader>

      <!-- Optional slot for inputs (like reason textarea or password) -->
      <slot />

      <!-- Centered Action Buttons -->
      <DialogFooter class="flex flex-row items-center justify-center gap-3 sm:justify-center mt-2">
        <Button
          type="button"
          variant="outline"
          @click="handleCancel"
          :disabled="loading"
          class="h-10 px-6 text-xs sm:text-sm font-semibold rounded-lg border border-slate-200 bg-[#F8F9FA] text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all font-poppins min-w-[100px]"
        >
          {{ cancelLabel }}
        </Button>
        <Button
          type="button"
          @click="handleConfirm"
          :disabled="loading"
          class="h-10 px-6 text-xs sm:text-sm font-semibold rounded-lg shadow-sm transition-all font-poppins min-w-[100px]"
          :class="badgeConfig.buttonClass"
        >
          <Loader2 v-if="loading" class="w-4 h-4 mr-1.5 animate-spin" />
          {{ loading ? 'Processing...' : confirmLabel }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
