<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, FileText, GitBranch } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useContractExecution } from '@/composables/useContractExecution'
import { useToast } from '@/composables/useToast'
import type { ExecutionAction, ExecutionEvent } from '@/composables/useContractExecution'
import ExecutionActionDialog from './ExecutionActionDialog.vue'

const props = defineProps<{ contractId: string }>()
const emit = defineEmits<{ changed: [] }>()
const route = useRoute()
const router = useRouter()
const { state, loading, loadError, load, submit, loadDraft } = useContractExecution(props.contractId)
const { success } = useToast()
const action = ref<ExecutionAction | null>(null)
const steps = ['SBSI Review', 'Client Review', 'For Signing', 'Signed', 'Notarized PDF']
const currentStep = computed(() => steps.indexOf(state.value?.stage ?? ''))
const submitLabel = computed(() => ({
  'SBSI Review': 'Submit SBSI Review',
  'Client Review': 'Submit Client Acceptance',
  'For Signing': 'Submit Signed PDF',
  Signed: 'Submit Notarized PDF',
}[state.value?.stage as 'SBSI Review' | 'Client Review' | 'For Signing' | 'Signed'] ?? 'Submit Stage'))
const lastReturn = computed(() => {
  const current = state.value
  const latest = current?.history.find(event => event.action === 'submit' && event.stage === current.stage
    && event.draft_document_id === current.draft_document_id)
  return latest?.state === 'returned' ? latest.reason : null
})

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

async function saved() {
  action.value = null
  success('Workflow updated')
  emit('changed')
  await load()
}

onMounted(load)
</script>

<template>
  <section v-if="loading || loadError || state?.enabled" aria-labelledby="execution-title" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
    <div class="flex items-center gap-2">
      <GitBranch class="size-5 text-brand-navy" />
      <h2 id="execution-title" class="font-semibold text-slate-800">Execution Workflow</h2>
    </div>
    <div v-if="loading" role="status" aria-label="Loading execution workflow" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
      <div v-for="step in steps" :key="step" class="h-8 animate-pulse rounded-full bg-slate-100" />
    </div>
    <div v-else-if="loadError" class="mt-4 flex flex-wrap items-center gap-4">
      <p role="alert" class="text-sm text-red-700">{{ loadError }}</p>
      <Button variant="outline" size="sm" @click="load">Retry</Button>
    </div>
    <template v-else-if="state">
      <ol class="mt-4 flex flex-wrap items-center gap-2" aria-label="Execution stages">
        <li v-for="(step, index) in steps" :key="step" class="flex items-center gap-2" :aria-current="step === state.stage ? 'step' : undefined">
          <ArrowRight v-if="index > 0" class="size-4 text-slate-400" />
          <span class="rounded-full border px-3 py-1 text-xs font-semibold"
            :class="step === state.stage ? 'border-brand-navy/20 bg-brand-navy/10 text-brand-navy' : index < currentStep ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600'">{{ step }}</span>
        </li>
      </ol>
      <p v-if="!state.stage" class="mt-4 text-sm text-slate-600">Execution starts after final internal approval.</p>
      <div v-if="state.paused || state.awaiting_confirmation || lastReturn" role="status" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p v-if="state.paused" class="font-semibold">Revision Required</p>
        <p v-else-if="state.awaiting_confirmation" class="font-semibold">Awaiting Confirmation</p>
        <p v-else class="whitespace-pre-wrap"><strong>Submission Returned</strong> · {{ lastReturn }}</p>
        <a v-if="state.pending?.document_id" :href="documentUrl(state.pending.document_id)" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 font-semibold underline"><FileText class="size-4" /> Review submitted PDF</a>
      </div>
      <div class="mt-4 flex flex-wrap gap-2">
        <Button v-if="state.allowed.submit" class="bg-brand-navy text-white hover:bg-brand-dark" @click="action = 'submit'">{{ submitLabel }}</Button>
        <Button v-if="state.allowed.confirm" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="action = 'confirm'">Confirm Submission</Button>
        <Button v-if="state.allowed.confirm" variant="outline" @click="action = 'return'">Return Submission</Button>
        <Button v-if="state.allowed.pause" variant="outline" @click="action = 'pause'">Request Revision</Button>
        <Button v-if="state.allowed.revise" class="bg-brand-navy text-white hover:bg-brand-dark" @click="action = 'revise'">Resubmit for Approval</Button>
        <Button v-if="state.allowed.resume" variant="outline" @click="action = 'resume'">Resume Workflow</Button>
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
      <ExecutionActionDialog v-if="action" :action="action" :execution="state" :save="submit" :load-draft="loadDraft"
        :draft-url="documentUrl(state.draft_document_id)" @close="action = null" @saved="saved" />
    </template>
  </section>
</template>
