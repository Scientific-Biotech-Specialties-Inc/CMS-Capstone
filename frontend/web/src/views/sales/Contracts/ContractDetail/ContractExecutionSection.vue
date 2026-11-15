<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FileText, GitBranch } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { ExecutionAction, ExecutionEvent, ExecutionState } from '@/composables/useContractExecution'

const props = defineProps<{
  contractId: string
  state: ExecutionState | null
  loading: boolean
  loadError: string
  submitLabel: string
}>()
defineEmits<{ executionAction: [action: ExecutionAction]; retry: [] }>()
const route = useRoute()
const router = useRouter()
const lastReturn = computed(() => {
  const current = props.state
  const latest = current?.history.find(event => event.action === 'submit' && event.stage === current.stage
    && event.draft_document_id === current.draft_document_id)
  return latest?.state === 'returned' ? latest.reason : null
})
const reviewerRoles = computed(() => new Intl.ListFormat(undefined, { type: 'disjunction' })
  .format(props.state?.pending?.reviewer_roles ?? []))

function documentUrl(documentId?: string | null) {
  if (!documentId) return ''
  const portal = route.path.split('/')[1]
  return router.resolve(`/${portal}/contracts/${props.contractId}/documents/${documentId}`).href
}

function eventLabel(event: ExecutionEvent) {
  if (event.action === 'submit') {
    return `${event.stage} · ${{ confirmed: 'Confirmed', pending: 'Awaiting Confirmation', returned: 'Returned', superseded: 'Superseded', recorded: 'Submitted' }[event.state]}`
  }
  return { pause: 'Revision Requested', resume: 'Workflow Resumed', revise: 'Revised Draft Resubmitted', confirm: 'Submission Confirmed', return: 'Submission Returned' }[event.action]
}
</script>

<template>
  <section v-if="loading || loadError || state?.enabled" aria-labelledby="execution-title" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
    <div class="flex items-center gap-2">
      <GitBranch class="size-5 text-brand-navy" />
      <h2 id="execution-title" class="font-semibold text-slate-800">Execution Details</h2>
    </div>
    <div v-if="loading" role="status" aria-label="Loading execution workflow" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
        <div v-for="placeholder in 3" :key="placeholder" class="h-8 animate-pulse rounded-full bg-slate-100" />
    </div>
    <div v-else-if="loadError" class="mt-4 flex flex-wrap items-center gap-4">
      <p role="alert" class="text-sm text-red-700">{{ loadError }}</p>
      <Button variant="outline" size="sm" @click="$emit('retry')">Retry</Button>
    </div>
    <template v-else-if="state">
      <p v-if="!state.stage" class="mt-4 text-sm text-slate-600">Execution starts after final internal approval.</p>
      <div v-if="state.paused || state.awaiting_confirmation || lastReturn" role="status" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p v-if="state.paused" class="font-semibold">Revision Required</p>
        <p v-else-if="state.awaiting_confirmation" class="font-semibold">Awaiting Confirmation</p>
        <p v-else class="whitespace-pre-wrap"><strong>Submission Returned</strong> · {{ lastReturn }}</p>
        <p v-if="state.pending" class="mt-2">
          <span v-if="state.pending.submitted_by_name">Submitted by {{ state.pending.submitted_by_name }} · </span>
          <time :datetime="state.pending.created_at">{{ new Date(state.pending.created_at).toLocaleString() }}</time>
        </p>
        <p v-if="reviewerRoles" class="mt-2">Waiting for {{ reviewerRoles }} to confirm.</p>
        <a v-if="state.pending?.document_id" :href="documentUrl(state.pending.document_id)" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 font-semibold underline"><FileText class="size-4" /> Review submitted PDF</a>
      </div>
      <div class="mt-4 flex flex-wrap gap-2">
        <Button v-if="state.allowed.submit" class="bg-brand-navy text-white hover:bg-brand-dark" @click="$emit('executionAction', 'submit')">{{ submitLabel }}</Button>
        <Button v-if="state.allowed.confirm" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="$emit('executionAction', 'confirm')">Confirm Submission</Button>
        <Button v-if="state.allowed.confirm" variant="outline" @click="$emit('executionAction', 'return')">Return Submission</Button>
        <Button v-if="state.allowed.pause" variant="outline" @click="$emit('executionAction', 'pause')">Request Revision</Button>
        <Button v-if="state.allowed.revise" class="bg-brand-navy text-white hover:bg-brand-dark" @click="$emit('executionAction', 'revise')">Resubmit for Approval</Button>
        <Button v-if="state.allowed.resume" variant="outline" @click="$emit('executionAction', 'resume')">Resume Workflow</Button>
      </div>
      <details v-if="state.history.length" class="mt-4 border-t border-slate-200 pt-4">
        <summary class="cursor-pointer text-sm font-semibold text-brand-navy">Execution History</summary>
        <ol class="mt-4 divide-y divide-slate-200">
          <li v-for="event in state.history" :key="event.id" class="py-2 text-sm text-slate-700">
            <p class="font-semibold">{{ eventLabel(event) }}</p>
            <p class="text-xs text-slate-600">{{ new Date(event.created_at).toLocaleString() }}</p>
            <p v-if="event.reason" class="whitespace-pre-wrap break-words">{{ event.reason }}</p>
            <a v-if="event.document_id" :href="documentUrl(event.document_id)" target="_blank" rel="noopener" class="text-brand-navy underline">View PDF</a>
          </li>
        </ol>
      </details>
    </template>
  </section>
</template>
