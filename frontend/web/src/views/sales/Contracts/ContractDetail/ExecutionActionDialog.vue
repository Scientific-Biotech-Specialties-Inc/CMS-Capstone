<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ClipboardCheck, Loader2, UploadCloud, AlertCircle, ArrowRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import type { ExecutionAction, ExecutionState } from '@/composables/useContractExecution'

const props = defineProps<{
  contractId: string
  action: ExecutionAction
  execution: ExecutionState
  save: (body: FormData) => Promise<unknown>
  loadDraft: (id: string) => Promise<ArrayBuffer>
  draftUrl: string
}>()

const emit = defineEmits<{ close: []; saved: [] }>()
const router = useRouter()
const route = useRoute()

const portal = computed(() => route.path.split('/')[1] || 'sales')
const reason = ref('')
const file = ref<File | null>(null)
const signingMethod = ref<'scanned_upload' | 'e_signature'>('scanned_upload')
const submitting = ref(false)
const attemptedSubmit = ref(false)
const error = ref('')
const operationId = crypto.randomUUID()

const signing = computed(() => props.action === 'submit' && props.execution.stage === 'For Signing')
const needsFile = computed(() => props.action === 'revise' || (props.action === 'submit' && props.execution.stage === 'Signed') || (signing.value && signingMethod.value === 'scanned_upload'))
const eSigning = computed(() => signing.value && signingMethod.value === 'e_signature')
const needsReason = computed(() => ['return', 'pause', 'resume'].includes(props.action))
const uploadForm = computed(() => needsFile.value)

const fileLabel = computed(() => {
  if (signing.value) return 'Signed PDF Document'
  if (props.execution.stage === 'Signed') return 'Notarized PDF Document'
  if (props.action === 'revise') return 'Revised Draft PDF'
  return 'PDF Document'
})

const title = computed(() => {
  if (props.action === 'submit') {
    if (signing.value) {
      return signingMethod.value === 'e_signature' ? 'Sign Contract in the Application' : 'Submit Signed PDF'
    }
    if (props.execution.stage === 'Signed') return 'Submit Notarized PDF'
    return props.execution.stage === 'SBSI Review' ? 'Submit SBSI Review Completion' : 'Submit Client Acceptance'
  }
  return { confirm: 'Confirm Submission', return: 'Return Submission', pause: 'Request Revision', resume: 'Resume Workflow', revise: 'Resubmit for Approval' }[props.action]
})

const description = computed(() => {
  switch (props.action) {
    case 'submit':
      if (signing.value && signingMethod.value === 'e_signature') {
        return 'Launch the dedicated signing workspace to draw or upload your signature and place it on the contract.'
      }
      return props.execution.allowed.auto_confirm
        ? 'Submitting advances the contract to the next stage.'
        : 'The contract stays at its current stage until an eligible reviewer confirms this submission.'
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

function handleContinue() {
  if (signing.value && signingMethod.value === 'e_signature') {
    if (!props.execution.draft_document_id) {
      error.value = 'In-app signing requires an approved draft PDF. Please select "Upload Signed PDF" instead.'
      return
    }
    emit('close')
    router.push(`/${portal.value}/contracts/${props.contractId}/sign`)
    return
  }
  void confirm()
}

async function confirm() {
  if (submitting.value || !props.execution.stage) return
  error.value = ''
  attemptedSubmit.value = true

  if (needsReason.value && !reason.value.trim()) {
    error.value = 'Please provide a reason to continue.'
    return
  }
  if (needsFile.value && !file.value) {
    error.value = 'Please attach a PDF document to continue.'
    return
  }

  submitting.value = true
  try {
    const body = new FormData()
    body.set('action', props.action)
    body.set('operation_id', operationId)
    body.set('stage', props.execution.stage)
    if (props.execution.draft_document_id) {
      body.set('draft_document_id', props.execution.draft_document_id)
    }
    if (props.execution.pending && ['confirm', 'return'].includes(props.action)) {
      body.set('submission_id', String(props.execution.pending.id))
    }
    if (reason.value.trim()) body.set('reason', reason.value.trim())
    if (signing.value) body.set('method', signingMethod.value)
    if (needsFile.value && file.value) body.set('file', file.value)

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
    <DialogContent class="max-h-[90dvh] w-[calc(100%_-_2rem)] overflow-y-auto rounded-2xl border-0 p-6 shadow-2xl sm:p-8 font-poppins max-w-lg">
      <DialogHeader :class="uploadForm ? 'text-left border-b border-slate-100 pb-5 mb-5' : 'items-center text-center pb-2'">
        <span class="inline-flex size-12 items-center justify-center rounded-full bg-brand-blue/10 text-brand-navy mb-3">
          <ClipboardCheck class="size-6" />
        </span>
        <DialogTitle class="text-xl font-bold text-slate-800 tracking-tight">{{ title }}</DialogTitle>
        <DialogDescription class="text-sm font-montserrat text-slate-500 leading-relaxed max-w-md mx-auto mt-2">
          {{ description }}
        </DialogDescription>
      </DialogHeader>

      <form class="space-y-6 mt-6" @submit.prevent="handleContinue">
        <!-- Signing Method Choice for For Signing stage -->
        <fieldset v-if="signing" class="space-y-2 pb-1" :disabled="submitting">
          <legend class="text-sm font-semibold text-slate-800">
            Signing Method <span class="text-red-500">*</span>
          </legend>
          <div class="flex flex-wrap gap-4 pt-1">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="signingMethod" type="radio" value="scanned_upload" class="accent-brand-navy" />
              Upload Signed PDF
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="signingMethod" type="radio" value="e_signature" class="accent-brand-navy" />
              Sign in the Application
            </label>
          </div>
        </fieldset>

        <!-- Redirect Notice for In-App Signing -->
        <div v-if="eSigning" class="mt-4 rounded-xl border border-blue-200 bg-blue-50/70 p-5 text-xs text-blue-900 space-y-2">
          <p class="font-semibold text-sm text-brand-navy flex items-center gap-1.5">
            Full-Page Signing Workspace
          </p>
          <p class="leading-relaxed text-slate-600">
            Clicking continue will open the dedicated signing workspace with a full PDF viewer where you can draw or upload your signature, resize it to fit signature blocks, and place it directly on the document.
          </p>
        </div>

        <!-- Scanned PDF Upload Box -->
        <div v-if="needsFile" class="mt-4 space-y-1.5">
          <label class="block text-sm font-semibold text-slate-800">
            {{ fileLabel }} <span class="text-red-500">*</span>
          </label>
          <label
            class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition-colors focus-within:border-brand-blue"
            :class="attemptedSubmit && !file ? 'border-red-400 bg-red-50/20' : 'border-slate-300 hover:border-slate-400 bg-slate-50/50'"
            @dragover.prevent
            @drop.prevent="!submitting && selectFile($event.dataTransfer?.files[0] ?? null)"
          >
            <UploadCloud class="size-6 text-brand-navy" />
            <span class="text-sm font-semibold text-slate-800">{{ file?.name ?? 'Click to upload or drag and drop' }}</span>
            <span class="text-xs text-slate-500">PDF document only, up to 10 MB</span>
            <input type="file" accept=".pdf,application/pdf" class="sr-only" :disabled="submitting" @change="selectFile(($event.target as HTMLInputElement).files?.[0] ?? null)" />
          </label>
          <p v-if="attemptedSubmit && !file" class="text-xs text-red-600 flex items-center gap-1 pt-1">
            <AlertCircle class="w-3.5 h-3.5" /> Please attach a PDF document.
          </p>
        </div>

        <!-- Reason Input -->
        <div v-if="needsReason" class="space-y-1.5">
          <label class="block text-sm font-semibold text-slate-800">
            Reason <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="reason"
            rows="3"
            maxlength="2000"
            placeholder="Provide a clear explanation..."
            :disabled="submitting"
            class="block w-full rounded-lg border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-brand-blue"
            :class="attemptedSubmit && !reason.trim() ? 'border-red-400 ring-1 ring-red-200' : 'border-slate-200'"
          />
          <p v-if="attemptedSubmit && !reason.trim()" class="text-xs text-red-600 flex items-center gap-1">
            <AlertCircle class="w-3.5 h-3.5" /> Reason is required.
          </p>
        </div>

        <!-- Error Alert Banner -->
        <div v-if="error" role="alert" class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs font-medium text-red-700 mt-3">
          <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" />
          <span>{{ error }}</span>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-3 pt-3" :class="[uploadForm ? 'justify-end mt-4' : 'justify-center', !uploadForm && !needsFile && !needsReason ? 'mt-8' : '']">
          <Button type="button" variant="outline" :disabled="submitting" @click="emit('close')" class="h-11 px-6 rounded-xl border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium">
            Cancel
          </Button>

          <!-- In-App E-Signing Continue Button -->
          <Button
            v-if="eSigning"
            type="button"
            class="h-11 px-6 rounded-xl font-semibold shadow-sm transition-colors bg-brand-navy text-white hover:bg-brand-dark flex items-center gap-2"
            @click="handleContinue"
          >
            Continue to Sign in the Application
            <ArrowRight class="w-4 h-4" />
          </Button>

          <!-- Form Submit Button -->
          <Button
            v-else
            type="submit"
            :disabled="submitting"
            class="h-11 px-6 rounded-xl font-semibold shadow-sm transition-colors"
            :class="action === 'return' ? 'bg-red-600 text-white hover:bg-red-700' : action === 'confirm' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-brand-navy text-white hover:bg-brand-dark'"
          >
            <Loader2 v-if="submitting" class="size-4 animate-spin" />
            {{ title }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
