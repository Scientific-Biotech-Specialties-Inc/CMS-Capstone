<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ClipboardList, MapPin, CalendarDays, FileText,
  Bell, CheckCircle, AlertCircle, ExternalLink, FilePenLine,
} from 'lucide-vue-next'
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import { requestStatusBadge, fmtReqDate } from '@/types/contractRequest'
import type { ContractRequest } from '@/types/contractRequest'
import { safeHref } from '@/utils/sanitize'

const router = useRouter()

const props = defineProps<{
  open:         boolean
  request:      ContractRequest | null
  isFollowedUp: boolean
}>()

const emit = defineEmits<{
  'update:open': [v: boolean]
  followUp:      [id: string]
}>()

const showFollowUpConfirm = ref(false)

function confirmFollowUp() {
  if (!props.request) return
  emit('followUp', props.request.id)
  showFollowUpConfirm.value = false
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
            <div class="flex-1 min-w-0">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider block mb-0.5">Description</span>
              <span class="text-sm text-black">{{ request.description }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 py-2.5">
            <CalendarDays class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Proposed Start</span>
              <span class="text-sm font-medium text-black tabular-nums">{{ fmtReqDate(request.startDate) }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 py-2.5">
            <CalendarDays class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Proposed End</span>
              <span class="text-sm font-medium text-black tabular-nums">{{ fmtReqDate(request.endDate) }}</span>
            </div>
          </div>

          <div class="flex items-start gap-3 py-2.5">
            <MapPin class="w-3.5 h-3.5 text-black/25 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Region</span>
              <span class="text-sm font-medium text-black">{{ request.region }}</span>
            </div>
          </div>

          <div v-if="request.notes" class="flex items-start gap-3 py-2.5">
            <ClipboardList class="w-3.5 h-3.5 text-black/25 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider block mb-0.5">Notes</span>
              <span class="text-sm text-black/70">{{ request.notes }}</span>
            </div>
          </div>

          <div v-if="request.status === 'Rejected' && request.rejectionReason"
            class="flex items-start gap-3 py-2.5">
            <AlertCircle class="w-3.5 h-3.5 text-red-400 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0">
              <span class="text-[10px] font-semibold text-red-400 uppercase tracking-wider block mb-0.5">Rejection Reason</span>
              <span class="text-sm text-red-600">{{ request.rejectionReason }}</span>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <div class="px-5 py-4 border-t border-black/6">

          <a :href="safeHref(request.contractLink)" target="_blank" rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-blue hover:underline mb-3">
            <ExternalLink class="w-3.5 h-3.5" /> View Contract PDF
          </a>

          <div class="flex items-center justify-between gap-2">

            <!-- Edit + follow up actions (pending only) -->
            <div v-if="request.status === 'Pending'" class="flex items-center gap-2">
              <Button variant="outline"
                @click="router.push(`/sales/contract-requests/${request.id}`); $emit('update:open', false)"
                class="h-8 px-3.5 text-xs font-semibold border-brand-navy/25 text-brand-navy hover:bg-brand-navy/5 hover:border-brand-navy/40 gap-1.5">
                <FilePenLine class="w-3.5 h-3.5" /> Edit Request
              </Button>
              <Button v-if="!isFollowedUp"
                @click="showFollowUpConfirm = true"
                variant="outline"
                class="h-8 px-3.5 text-xs font-semibold border-amber-200 text-amber-600 hover:bg-amber-50 hover:text-amber-700 gap-1.5">
                <Bell class="w-3.5 h-3.5" /> Follow Up Manager
              </Button>
              <span v-else
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                <CheckCircle class="w-3.5 h-3.5" /> Follow-up sent
              </span>
            </div>
            <div v-else class="flex-1" />

            <Button variant="outline" @click="$emit('update:open', false)"
              class="h-8 px-4 text-sm border-black/15 text-black/60 hover:text-black ml-auto">
              Close
            </Button>
          </div>
        </div>

        <!-- Confirmation -->
        <ConfirmationDialog
          v-model:open="showFollowUpConfirm"
          title="Follow Up Manager"
          description="Are you sure you want to send a follow-up notification to the manager for this request?"
          confirm-label="Send Follow-up"
          @confirm="confirmFollowUp"
        />

      </template>
    </DialogContent>
  </Dialog>
</template>
