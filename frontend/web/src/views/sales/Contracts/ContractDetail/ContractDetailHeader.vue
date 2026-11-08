<script setup lang="ts">
import { ArrowLeft, FileText, FilePenLine, Clock, AlertTriangle, Loader2, CheckCircle, XCircle, Workflow } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { approvalStatusBadge, workflowStatusBadge } from '@/types/contract'
import type { StoredContract } from '@/composables/useContractStore'
import type { RiskLevel } from '@/types/riskAssessment'
import RiskFlagBadge from '@/components/shared/RiskFlagBadge.vue'
import LockedStatusBadge from '@/components/shared/LockedStatusBadge.vue'

const props = defineProps<{
  contract:  StoredContract
  days:      number
  isEditing: boolean
  saving?:   boolean
  disabled?: boolean
  actionInProgress?: boolean
  isManager?: boolean
  showRejectInput?: boolean
  rejectReasonValid?: boolean
  isSnapshot?: boolean
  riskLevel?: RiskLevel | null
  riskFindingsCount?: number
  approvalLocked?: boolean
  isRiskLoading?: boolean
  // Phase 7: generalized nudge label ("Notify Manager" legacy,
  // "Nudge {Role}" / "Nudge Current Step" engine-routed) + whether the
  // contract is engine-routed (relaxes the Pending-only visibility).
  nudgeLabel?: string
  isEngineRouted?: boolean
}>()

defineEmits<{ 
  back: []; edit: []; save: []; cancel: []; notifyStep: [];
  approve: []; toggleReject: []; confirmReject: []; openHistory: []; openRiskAssessment: []; openWorkflowTracker: []
}>()

function daysDisplay(days: number) {
  if (days < 0)   return { text: `Expired ${Math.abs(days)}d ago`, cls: 'bg-red-50 text-red-600 border-red-200',     icon: AlertTriangle }
  if (days <= 30) return { text: `${days} days left`,              cls: 'bg-amber-50 text-amber-600 border-amber-200', icon: Clock }
  return                 { text: `${days} days left`,              cls: 'bg-black/4 text-black/55 border-black/10',   icon: Clock }
}
</script>

<template>
  <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
    
    <div class="flex items-start gap-4">
      <!-- Back button -->
      <button @click="$emit('back')"
        class="w-10 h-10 bg-white rounded-lg border border-black/10 flex items-center justify-center hover:bg-black/5 transition-colors shadow-sm shrink-0">
        <ArrowLeft class="w-5 h-5 text-brand-navy" />
      </button>

      <div>
        <div class="flex items-center gap-3 mb-1">
          <!-- Icon box -->
          <div class="p-2 bg-brand-navy/10 rounded-lg text-brand-navy flex items-center justify-center">
            <FileText class="w-5 h-5" />
          </div>
          <!-- Title -->
          <h2 class="text-2xl font-bold text-black">{{ contract.businessPartner }}</h2>
        </div>
        
        <!-- Subtitle -->
        <p class="text-black/60 text-sm flex items-center gap-2">
          {{ contract.category }} • <span class="text-[11px] font-bold tracking-wide text-black/40 uppercase">ID: {{ contract.id }}</span>
        </p>

        <!-- Badges -->
        <div class="flex flex-wrap gap-2 mt-3">
          <span class="px-3 py-1 text-xs font-semibold rounded-full border" :class="approvalStatusBadge[contract.approvalStatus]">
            {{ contract.approvalStatus }}
          </span>
          <span v-if="contract.workflowStatus" class="px-3 py-1 text-xs font-semibold rounded-full border" :class="workflowStatusBadge[contract.workflowStatus]">
            {{ contract.workflowStatus }}
          </span>
          <span class="px-3 py-1 text-xs font-medium rounded-full border flex items-center gap-1.5"
            :class="daysDisplay(days).cls">
            <component :is="daysDisplay(days).icon" class="w-3.5 h-3.5" />
            {{ daysDisplay(days).text }}
          </span>
          <div v-if="isRiskLoading" class="h-6 w-28 bg-black/5 animate-pulse rounded-full border border-transparent"></div>
          <RiskFlagBadge
            v-else-if="riskLevel"
            :risk-level="riskLevel"
            :findings-count="riskFindingsCount ?? 0"
            size="md"
            @click="$emit('openRiskAssessment')"
          />
          <LockedStatusBadge v-if="approvalLocked" size="md" />
        </div>
      </div>
    </div>

    <!-- Actions: two-row cluster. Top row = the primary decision
         (visible at first glance, right-aligned with the title);
         bottom row = quieter secondary tools. -->
    <div class="flex flex-col items-end gap-2.5 shrink-0">
      <!-- Edit Mode (Save/Cancel) -->
      <template v-if="isEditing">
        <div class="flex flex-wrap items-center justify-end gap-3">
          <button @click="$emit('cancel')" :disabled="actionInProgress"
            class="px-6 py-2.5 bg-white border border-black/15 text-black/60 rounded-lg text-sm font-medium hover:text-black hover:bg-black/5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            Cancel
          </button>
          <button @click="$emit('save')" :disabled="saving || disabled || actionInProgress"
            class="px-6 py-2.5 bg-brand-navy text-white rounded-lg text-sm font-medium flex items-center gap-2 hover:bg-brand-dark transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
            <Loader2 v-if="saving" class="w-4 h-4 animate-spin" />
            {{ saving ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </template>

      <!-- View Mode -->
      <template v-else>
        <!-- Primary row: the approve/reject decision -->
        <div class="flex flex-wrap items-center justify-end gap-3">
          <!-- Manager: Approve / Reject -->
          <template v-if="isManager && contract.hasPendingApprovalWorkflow === false && !isEngineRouted && contract.approvalStatus === 'Pending' && !isSnapshot">
            <template v-if="!showRejectInput">
              <Button @click="$emit('toggleReject')" :disabled="actionInProgress" variant="outline"
                class="h-11 px-6 border-black/15 text-black/65 hover:text-red-600 hover:border-red-200 hover:bg-red-50 font-semibold">
                <XCircle class="w-4 h-4" /> Reject Contract
              </Button>
              <Button @click="$emit('approve')" :disabled="actionInProgress"
                class="h-11 px-7 text-[15px] bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-md">
                <CheckCircle class="w-5 h-5" /> Approve Contract
              </Button>
            </template>
            <template v-else>
              <Button variant="outline" @click="$emit('toggleReject')" :disabled="actionInProgress"
                class="h-11 px-6 border-black/15 text-black/60 hover:text-black hover:bg-black/5 font-semibold">
                Cancel Reject
              </Button>
              <Button @click="$emit('confirmReject')" :disabled="!rejectReasonValid || actionInProgress"
                class="h-11 px-7 text-[15px] bg-red-600 hover:bg-red-700 text-white font-semibold shadow-md">
                Confirm Reject
              </Button>
            </template>
          </template>

          <!-- Notify responsible role (Phase 7: manager-targeted legacy,
               role-aware "Nudge {Role}" for engine-routed contracts) -->
          <button v-if="!isManager && !disabled && !isSnapshot && (contract.approvalStatus === 'Pending' || isEngineRouted)" @click="$emit('notifyStep')"
            class="px-6 py-2.5 bg-white border border-brand-navy text-brand-navy rounded-lg text-sm font-semibold flex items-center gap-2 hover:bg-brand-navy/5 transition-colors shadow-sm">
            {{ nudgeLabel ?? 'Notify Manager' }}
          </button>

          <!-- Create Amendment Button — only for Approved contracts -->
          <button v-if="!showRejectInput && !isSnapshot && contract.approvalStatus === 'Approved'" @click="$emit('edit')"
            class="px-6 py-2.5 bg-brand-navy text-white rounded-lg text-sm font-semibold flex items-center gap-2 hover:opacity-90 transition-opacity shadow-sm">
            <FilePenLine class="w-4 h-4" />
            Create Amendment
          </button>
        </div>

        <!-- Secondary row: quieter timeline tools -->
        <div v-if="!showRejectInput" class="flex flex-wrap items-center justify-end gap-2">
          <!-- Version History Button -->
          <button @click="$emit('openHistory')"
            class="px-3.5 py-2 bg-white border border-black/10 text-black/55 hover:text-black hover:bg-black/5 rounded-lg text-[13px] font-medium flex items-center gap-1.5 transition-colors">
            <Clock class="w-3.5 h-3.5 text-brand-blue" />
            Version History
          </button>

          <!-- View Workflow Tracker Button -->
          <button @click="$emit('openWorkflowTracker')"
            class="px-3.5 py-2 bg-white border border-black/10 text-black/55 hover:text-black hover:bg-black/5 rounded-lg text-[13px] font-medium flex items-center gap-1.5 transition-colors">
            <Workflow class="w-3.5 h-3.5 text-brand-navy" />
            View Workflow Tracker
          </button>

          <!-- AI Risk Assessment Button -->
          <button v-if="!isSnapshot" @click="$emit('openRiskAssessment')"
            class="px-3.5 py-2 bg-white border border-black/10 text-black/55 hover:text-black hover:bg-black/5 rounded-lg text-[13px] font-medium flex items-center gap-1.5 transition-colors">
            <AlertTriangle class="w-3.5 h-3.5 text-brand-dark" />
            Risk Assessment
          </button>
        </div>
      </template>
    </div>

  </div>
</template>
