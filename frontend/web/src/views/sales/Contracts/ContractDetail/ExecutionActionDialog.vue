<script setup lang="ts">
import { computed, onUnmounted, ref, shallowRef, watch } from 'vue'
import { ClipboardCheck, Loader2, UploadCloud } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import type { ExecutionAction, ExecutionState } from '@/composables/useContractExecution'
import { signDraftPdf, type SignaturePlacement } from './signDraftPdf'
import SignatureInput from './SignatureInput.vue'
import SignaturePlacementPreview from './SignaturePlacementPreview.vue'

const props = defineProps<{
  action: ExecutionAction
  execution: ExecutionState
  save: (body: FormData) => Promise<unknown>
  loadDraft: (id: string) => Promise<ArrayBuffer>
  draftUrl: string
}>()
const emit = defineEmits<{ close: []; saved: [] }>()
const reason = ref('')
const file = ref<File | null>(null)
const signingMethod = ref<'scanned_upload' | 'e_signature'>('scanned_upload')
const signerName = ref('')
const signature = ref<string | null>(null)
const placement = ref<SignaturePlacement | null>(null)
const draft = shallowRef<ArrayBuffer | null>(null)
const preview = ref('')
const loadingDraft = ref(false)
let closed = false
const consent = ref(false)
const submitting = ref(false)
const error = ref('')
const operationId = crypto.randomUUID()
const signing = computed(() => props.action === 'submit' && props.execution.stage === 'For Signing')
const needsFile = computed(() => props.action === 'revise' || (props.action === 'submit' && props.execution.stage === 'Signed') || (signing.value && signingMethod.value === 'scanned_upload'))
const eSigning = computed(() => signing.value && signingMethod.value === 'e_signature')
const needsReason = computed(() => ['return', 'pause', 'resume'].includes(props.action))
const uploadForm = computed(() => needsFile.value || eSigning.value)

async function fetchDraft() {
  if (!props.execution.draft_document_id || draft.value || loadingDraft.value) return
  loadingDraft.value = true
  error.value = ''
  try {
    const bytes = await props.loadDraft(props.execution.draft_document_id)
    if (closed) return
    draft.value = bytes
    preview.value = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }))
  } catch (failure) {
    error.value = failure instanceof Error ? failure.message : 'The approved draft could not be opened.'
  } finally {
    loadingDraft.value = false
  }
}

onUnmounted(() => {
  closed = true
  if (preview.value) URL.revokeObjectURL(preview.value)
})

watch(eSigning, enabled => {
  signature.value = null
  placement.value = null
  consent.value = false
  if (enabled) void fetchDraft()
})
watch(signature, () => { placement.value = null })
const title = computed(() => {
  if (props.action === 'submit') {
    if (signing.value) return 'Submit Signed PDF'
    if (props.execution.stage === 'Signed') return 'Submit Notarized PDF'
    return props.execution.stage === 'SBSI Review' ? 'Submit SBSI Review Completion' : 'Submit Client Acceptance'
  }
  return { confirm: 'Confirm Submission', return: 'Return Submission', pause: 'Request Revision', resume: 'Resume Workflow', revise: 'Resubmit for Approval' }[props.action]
})
const description = computed(() => {
  switch (props.action) {
    case 'submit': return props.execution.allowed.auto_confirm ? 'Submitting advances the contract to the next stage.' : 'The contract stays at its current stage until an eligible reviewer confirms this submission.'
    case 'confirm': return 'Confirm this submission and advance the contract to the next stage.'
    case 'return': return 'Return this submission to the owner with a reason. The contract stays at its current stage.'
    case 'pause': return 'Pause review and signing while the owner prepares a revised draft.'
    case 'resume': return 'Resume at the same stage with the existing approved draft.'
    case 'revise': return 'Attach the revised PDF to restart internal approval from the first step.'
  }
})

function selectFile(value: File | null) {
  error.value = ''
  file.value = null
  if (!value) return
  if (!value.name.toLowerCase().endsWith('.pdf') || value.size > 10 * 1024 * 1024) {
    error.value = 'Select a PDF of 10 MB or less.'
    return
  }
  file.value = value
}

async function confirm() {
  if (submitting.value || !props.execution.stage || !props.execution.draft_document_id) return
  error.value = ''
  if (needsReason.value && !reason.value.trim()) { error.value = 'Enter a reason.'; return }
  if (needsFile.value && !file.value) { error.value = 'Attach a PDF to continue.'; return }
  if (eSigning.value && (!signerName.value.trim() || !signature.value || !consent.value || !placement.value || !draft.value)) {
    error.value = 'Enter your name, position your signature on the PDF, and confirm your intent to sign.'
    return
  }

  submitting.value = true
  try {
    const body = new FormData()
    body.set('action', props.action)
    body.set('operation_id', operationId)
    body.set('stage', props.execution.stage)
    body.set('draft_document_id', props.execution.draft_document_id)
    if (props.execution.pending && ['confirm', 'return'].includes(props.action)) body.set('submission_id', String(props.execution.pending.id))
    if (reason.value.trim()) body.set('reason', reason.value.trim())
    if (signing.value) body.set('method', signingMethod.value)
    if (needsFile.value && file.value) body.set('file', file.value)
    if (eSigning.value && signature.value && placement.value && draft.value) {
      body.set('file', await signDraftPdf(draft.value, signature.value, placement.value))
      body.set('signer_name', signerName.value.trim())
      body.set('signature_page', String(placement.value.page))
      body.set('signature_x', String(placement.value.x))
      body.set('signature_y', String(placement.value.y))
    }
    await props.save(body)
    emit('saved')
  } catch (failure) {
    error.value = failure instanceof Error ? failure.message : 'The action could not be saved.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Dialog :open="true" @update:open="open => { if (!open && !submitting) emit('close') }">
    <DialogContent class="max-h-[90dvh] w-[calc(100%_-_2rem)] overflow-y-auto rounded-2xl border-0 p-4 shadow-2xl sm:p-6" :class="eSigning ? 'max-w-3xl' : 'max-w-lg'">
      <DialogHeader :class="uploadForm ? 'text-left border-b border-slate-100 pb-4' : 'items-center text-center'">
        <span class="inline-flex size-10 items-center justify-center rounded-full bg-brand-blue/10 text-brand-navy"><ClipboardCheck class="size-5" /></span>
        <DialogTitle class="text-xl font-bold text-slate-800">{{ title }}</DialogTitle>
        <DialogDescription class="text-sm text-slate-600">{{ description }}</DialogDescription>
      </DialogHeader>
      <form class="space-y-4" @submit.prevent="confirm">
        <fieldset v-if="signing" class="flex flex-wrap gap-4" :disabled="submitting">
          <legend class="mb-2 text-sm font-semibold text-slate-800">Signing Method</legend>
          <label class="flex items-center gap-2 text-sm"><input v-model="signingMethod" type="radio" value="scanned_upload" class="accent-brand-navy" /> Upload Signed PDF</label>
          <label class="flex items-center gap-2 text-sm"><input v-model="signingMethod" type="radio" value="e_signature" class="accent-brand-navy" /> Sign in App</label>
        </fieldset>
        <label v-if="needsFile" class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center focus-within:border-brand-blue"
          @dragover.prevent @drop.prevent="!submitting && selectFile($event.dataTransfer?.files[0] ?? null)">
          <UploadCloud class="size-5 text-brand-navy" />
          <span class="text-sm font-semibold text-slate-800">{{ file?.name ?? 'Click to upload or drag and drop' }}</span>
          <span class="text-xs text-slate-600">PDF, up to 10 MB</span>
          <input type="file" accept=".pdf,application/pdf" class="sr-only" :disabled="submitting" @change="selectFile(($event.target as HTMLInputElement).files?.[0] ?? null)" />
        </label>
        <template v-if="eSigning">
          <a :href="draftUrl" target="_blank" rel="noopener" class="text-sm font-semibold text-brand-navy underline">Review approved draft PDF</a>
          <label class="block text-sm font-semibold text-slate-800">Full Name
            <input v-model="signerName" maxlength="160" :disabled="submitting" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 focus-visible:outline-2 focus-visible:outline-brand-blue" />
          </label>
          <SignatureInput :disabled="submitting" @change="signature = $event" />
          <div v-if="loadingDraft" role="status" aria-label="Loading approved draft" class="h-48 animate-pulse rounded-lg bg-slate-100" />
          <SignaturePlacementPreview v-else-if="preview && signature" :source="preview" :signature="signature" :disabled="submitting" @change="placement = $event" />
          <Button v-else-if="!preview" type="button" variant="outline" @click="fetchDraft">Retry Loading Draft</Button>
          <label class="flex items-start gap-2 text-sm text-slate-700"><input v-model="consent" type="checkbox" :disabled="submitting" class="mt-1 accent-brand-navy" /> I intend to sign this approved draft at the position shown.</label>
        </template>
        <label v-if="needsReason" class="block text-sm font-semibold text-slate-800">Reason
          <textarea v-model="reason" rows="3" maxlength="2000" :disabled="submitting" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 focus-visible:outline-2 focus-visible:outline-brand-blue" />
        </label>
        <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
        <div class="flex flex-wrap gap-2" :class="uploadForm ? 'justify-end' : 'justify-center'">
          <Button type="button" variant="outline" :disabled="submitting" @click="emit('close')">Cancel</Button>
          <Button type="submit" :disabled="submitting" :class="action === 'return' ? 'bg-red-600 text-white hover:bg-red-700' : action === 'confirm' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-brand-navy text-white hover:bg-brand-dark'">
            <Loader2 v-if="submitting" class="size-4 animate-spin" /> {{ title }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
