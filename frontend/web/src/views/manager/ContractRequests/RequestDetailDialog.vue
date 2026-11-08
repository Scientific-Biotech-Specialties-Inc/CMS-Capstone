<script setup lang="ts">
import { BRAND_PALETTE } from '@/constants/theme'
import { ref, reactive, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ClipboardList, CalendarDays, FileText,
  CheckCircle, XCircle, RefreshCw, ExternalLink, User, FilePenLine,
} from 'lucide-vue-next'
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import FormField from '@/components/shared/FormField.vue'
import { required, minLength } from '@/lib/validation'
import { useFormValidation } from '@/composables/useFormValidation'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import { requestStatusBadge, fmtReqDate } from '@/types/contractRequest'
import type { ContractRequest } from '@/types/contractRequest'
import { safeHref } from '@/utils/sanitize'

const router = useRouter()

const props = defineProps<{ open: boolean; request: ContractRequest | null }>()
const emit  = defineEmits<{
  'update:open': [v: boolean]
  approve:       [id: string]
  reject:        [id: string, reason: string]
  setReviewing:  [id: string]
}>()

const palette = BRAND_PALETTE
function initials(name: string) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}
function avatarColor(name: string) {
  let h = 0
  for (const c of name) h = (h * 31 + c.charCodeAt(0)) & 0xffff
  return palette[h % palette.length]
}

const showRejectInput = ref(false)
const form = reactive({ rejectReason: '' })

const { errors, touched, validateField, validateAll, resetValidation } = useFormValidation(form, {
  rejectReason: [
    required('Reason for rejection is required.'),
    minLength(5, 'Rejection reason must be at least 5 characters.'),
  ],
})

const showApproveConfirm = ref(false)
const showReviewConfirm = ref(false)

watch(() => props.open, open => {
  if (!open) {
    showRejectInput.value = false
    form.rejectReason = ''
    resetValidation()
    showApproveConfirm.value = false
    showReviewConfirm.value = false
  }
})

function confirmReject() {
  if (!props.request) return
  if (!validateAll()) return
  emit('reject', props.request.id, form.rejectReason.trim())
  emit('update:open', false)
}

function confirmApprove() {
  if (!props.request) return
  emit('approve', props.request.id)
  showApproveConfirm.value = false
  emit('update:open', false)
}

function confirmReview() {
  if (!props.request) return
  emit('setReviewing', props.request.id)
  showReviewConfirm.value = false
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-md p-0 overflow-hidden gap-0" @pointer-down-outside="$emit('update:open', false)">
      <template v-if="request">

        <!-- Header -->
        <div class="px-5 pt-5 pb-4 border-b border-black/6">
          <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0 bg-brand-navy">
              <ClipboardList class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0 pr-6">
              <DialogTitle class="text-sm font-bold text-black leading-snug truncate">{{ request.businessPartner }}</DialogTitle>
              <DialogDescription class="text-xs text-black/45 mt-0.5">{{ request.category }}</DialogDescription>
              <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                <span class="text-[10px] font-mono text-black/30 bg-black/4 px-1.5 py-0.5 rounded">{{ request.id }}</span>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full border"
                  :class="requestStatusBadge[request.status]">
                  {{ request.status }}
                </span>

              </div>
            </div>
          </div>
        </div>

        <!-- Stats strip -->
        <div class="grid grid-cols-3 divide-x divide-black/6 border-b border-black/6">
          <div class="px-4 py-3">
            <p class="text-[9px] font-bold text-black/30 uppercase tracking-widest mb-1">Region</p>
            <p class="text-sm font-bold text-black">{{ request.region }}</p>
          </div>
          <div class="px-4 py-3">
            <p class="text-[9px] font-bold text-black/30 uppercase tracking-widest mb-1">Requested</p>
            <p class="text-xs font-bold text-black tabular-nums">{{ fmtReqDate(request.requestDate) }}</p>
          </div>
          <div class="px-4 py-3">
            <p class="text-[9px] font-bold text-black/30 uppercase tracking-widest mb-1">Duration</p>
            <p class="text-sm font-bold text-black tabular-nums">
              {{ Math.round((new Date(request.endDate).getTime() - new Date(request.startDate).getTime()) / 86_400_000 / 30) }}mo
            </p>
          </div>
        </div>

        <!-- Detail rows -->
        <div class="px-5 py-1 divide-y divide-black/4">

          <div class="flex items-start gap-3 py-2.5">
            <FileText class="w-3.5 h-3.5 text-black/25 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Item Code</span>
              <span class="text-xs font-mono font-bold text-black">{{ request.itemCode }}</span>
            </div>
          </div>

          <div class="flex items-start gap-3 py-2.5">
            <FileText class="w-3.5 h-3.5 text-black/25 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Serial No</span>
              <span class="text-xs font-mono text-black">{{ request.serialNo }}</span>
            </div>
          </div>

          <div class="flex items-start gap-3 py-2.5">
            <FileText class="w-3.5 h-3.5 text-black/25 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">SBU Number</span>
              <span class="text-xs font-mono text-black">{{ request.sbuNumber }}</span>
            </div>
          </div>

          <div class="flex items-start gap-3 py-2.5">
            <CalendarDays class="w-3.5 h-3.5 text-black/25 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Period</span>
              <span class="text-xs text-black tabular-nums">{{ fmtReqDate(request.startDate) }} &rarr; {{ fmtReqDate(request.endDate) }}</span>
            </div>
          </div>

          <div v-if="request.description" class="py-2.5">
            <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider block mb-1">Description</span>
            <p class="text-xs text-black/65 leading-relaxed bg-black/2 rounded-lg px-3 py-2 border border-black/4">
              {{ request.description }}
            </p>
          </div>

          <div class="flex items-center gap-3 py-2.5">
            <User class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Sales Rep</span>
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[9px] font-bold shrink-0 select-none"
                  :style="{ backgroundColor: avatarColor(request.createdBy) }">
                  {{ initials(request.createdBy) }}
                </div>
                <span class="text-sm font-medium text-black">{{ request.createdBy }}</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Reject reason input (shown when about to reject) -->
        <div v-if="showRejectInput" class="px-5 pb-3">
          <FormField
            label="Reason for rejection"
            :required="true"
            :error="touched.rejectReason ? errors.rejectReason : null"
          >
            <textarea
              v-model="form.rejectReason"
              @blur="validateField('rejectReason')"
              @input="validateField('rejectReason')"
              rows="2"
              placeholder="Provide a reason (minimum 5 characters)..."
              class="w-full rounded-lg border bg-white px-3 py-2 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition resize-none"
              :class="touched.rejectReason && errors.rejectReason
                ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15'
                : 'border-black/10 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
          </FormField>
        </div>

        <!-- Footer: contract link left, actions right -->
        <div class="px-5 py-4 border-t border-black/6">

          <!-- Contract link -->
          <a :href="safeHref(request.contractLink)" target="_blank" rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-blue hover:underline mb-3">
            <ExternalLink class="w-3.5 h-3.5" /> View Contract PDF
          </a>

          <!-- Action buttons -->
          <div class="flex items-center justify-between gap-2">

            <div v-if="request.status === 'Pending' || request.status === 'Under Review'"
              class="flex items-center gap-2">

              <Button v-if="!showRejectInput" variant="outline"
                @click="router.push(`/manager/contract-requests/${request.id}`); $emit('update:open', false)"
                class="h-8 px-3.5 text-xs font-semibold border-brand-navy/25 text-brand-navy hover:bg-brand-navy/5 hover:border-brand-navy/40 gap-1.5">
                <FilePenLine class="w-3.5 h-3.5" /> Edit Request
              </Button>

              <Button v-if="!showRejectInput" variant="outline"
                @click="showRejectInput = true"
                class="h-8 px-3.5 text-xs font-semibold border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700 gap-1.5">
                <XCircle class="w-3.5 h-3.5" /> Reject
              </Button>

              <template v-if="showRejectInput">
                <Button variant="outline" @click="showRejectInput = false; form.rejectReason = ''; resetValidation()"
                  class="h-8 px-3.5 text-xs font-semibold border-black/15 text-black/60 hover:text-black">
                  Cancel
                </Button>
                <Button @click="confirmReject" :disabled="!form.rejectReason.trim()"
                  class="h-8 px-3.5 text-xs font-semibold bg-red-600 hover:bg-red-700 text-white gap-1.5 disabled:opacity-40">
                  <XCircle class="w-3.5 h-3.5" /> Confirm Reject
                </Button>
              </template>

              <template v-if="!showRejectInput">
                <Button v-if="request.status === 'Pending'" variant="outline"
                  @click="showReviewConfirm = true"
                  class="h-8 px-3.5 text-xs font-semibold border-brand-blue/30 text-brand-blue hover:bg-brand-blue/8 gap-1.5">
                  <RefreshCw class="w-3.5 h-3.5" /> Set Reviewing
                </Button>
                <Button
                  @click="showApproveConfirm = true"
                  class="h-8 px-3.5 text-xs font-semibold bg-brand-navy hover:bg-brand-dark text-white gap-1.5">
                  <CheckCircle class="w-3.5 h-3.5" /> Approve
                </Button>
              </template>
            </div>

            <div v-else class="flex-1" />

            <Button variant="outline" @click="$emit('update:open', false)"
              class="h-8 px-4 text-sm border-black/15 text-black/60 hover:text-black ml-auto">
              Close
            </Button>
          </div>
        </div>

        <!-- Confirmations -->
        <ConfirmationDialog
          v-model:open="showApproveConfirm"
          title="Approve Contract Request"
          description="Are you sure you want to approve this contract request? An active contract will be generated in the system."
          confirm-label="Approve"
          @confirm="confirmApprove"
        />

        <ConfirmationDialog
          v-model:open="showReviewConfirm"
          title="Mark Request Under Review"
          description="Are you sure you want to change this request status to 'Under Review'?"
          confirm-label="Confirm"
          @confirm="confirmReview"
        />

      </template>
    </DialogContent>
  </Dialog>
</template>
