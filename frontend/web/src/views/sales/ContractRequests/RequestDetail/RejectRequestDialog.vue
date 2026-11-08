<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { XCircle, Loader2 } from 'lucide-vue-next'

const props = defineProps<{
  open: boolean
  businessPartner?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: [reason: string]
}>()

const reason = ref('')
const errorMsg = ref('')

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    reason.value = ''
    errorMsg.value = ''
  }
})

function handleClose() {
  if (props.loading) return
  emit('update:open', false)
}

function handleConfirm() {
  const trimmed = reason.value.trim()
  if (!trimmed) {
    errorMsg.value = 'Please provide a reason for rejection.'
    return
  }
  if (trimmed.length < 5) {
    errorMsg.value = 'Rejection reason must be at least 5 characters.'
    return
  }
  errorMsg.value = ''
  emit('confirm', trimmed)
}
</script>

<template>
  <Dialog :open="open" @update:open="val => { if (!val) handleClose() }">
    <DialogContent class="max-w-md p-6 gap-4 font-sans" @pointer-down-outside="handleClose">
      <DialogHeader class="space-y-2">
        <div class="flex items-center gap-2.5 text-red-600">
          <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
            <XCircle class="w-5 h-5 text-red-600" />
          </div>
          <DialogTitle class="text-base font-bold text-black font-poppins">Reject Contract Request</DialogTitle>
        </div>
        <DialogDescription class="text-xs text-black/60 leading-relaxed font-montserrat pt-1">
          Are you sure you want to reject this contract request<span v-if="businessPartner"> from <strong class="text-black font-semibold">{{ businessPartner }}</strong></span>?
          Please provide a reason below. The requester will be notified.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-1.5 mt-2">
        <div class="flex items-center justify-between">
          <label class="text-xs font-semibold text-black/70 font-poppins">
            Rejection Reason <span class="text-red-500">*</span>
          </label>
          <span class="text-[11px] text-black/40 font-montserrat">{{ reason.trim().length }}/500</span>
        </div>
        <textarea
          v-model="reason"
          rows="3"
          maxlength="500"
          placeholder="State why this contract request cannot be approved..."
          :disabled="loading"
          class="w-full rounded-lg border border-black/15 bg-white px-3 py-2 text-sm font-montserrat placeholder:text-black/30 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200/50 transition resize-none disabled:opacity-50"
          :class="{ 'border-red-400 focus:ring-red-200': errorMsg }"
        />
        <p v-if="errorMsg" class="text-xs text-red-500 font-montserrat">{{ errorMsg }}</p>
      </div>

      <DialogFooter class="flex items-center justify-end gap-3 mt-3">
        <Button
          type="button"
          variant="outline"
          @click="handleClose"
          :disabled="loading"
          class="h-9 px-4 text-xs font-medium border-black/15 text-black/70 hover:text-black hover:bg-black/5"
        >
          Cancel
        </Button>
        <Button
          type="button"
          @click="handleConfirm"
          :disabled="loading || !reason.trim()"
          class="h-9 px-4 text-xs bg-red-600 hover:bg-red-700 text-white font-semibold shadow-sm gap-1.5 disabled:opacity-40"
        >
          <Loader2 v-if="loading" class="w-3.5 h-3.5 animate-spin" />
          <XCircle v-else class="w-3.5 h-3.5" />
          <span>{{ loading ? 'Rejecting...' : 'Reject Request' }}</span>
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
