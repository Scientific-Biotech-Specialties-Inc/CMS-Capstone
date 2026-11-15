<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, shallowRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, FileSignature, AlertCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useApiCache } from '@/composables/useApiCache'
import { useContractExecution } from '@/composables/useContractExecution'
import { signDraftPdf, type SignaturePlacement } from '../ContractDetail/signDraftPdf'
import SignerDetailsPanel from './SignerDetailsPanel.vue'
import PdfSignatureWorkspace from './PdfSignatureWorkspace.vue'

const route = useRoute()
const router = useRouter()
const { success, error: toastError } = useToast()
const { state: cacheState, fetchContracts, invalidateRequests } = useApiCache()

const contractId = route.params.id as string
const portal = computed(() => {
  if (route.path.startsWith('/admin')) return 'admin'
  if (route.path.startsWith('/manager')) return 'manager'
  return 'sales'
})
const backUrl = computed(() => `/${portal.value}/contracts/${contractId}`)

const { state: executionState, load: loadExecution, submit, loadDraft } = useContractExecution(contractId)

const draftBytes = shallowRef<ArrayBuffer | null>(null)
const previewUrl = ref('')
const loadingPdf = ref(true)
const contract = computed(() => cacheState.contracts?.find(c => String(c.id) === String(contractId)))

const signerName = ref('')
const signature = ref<string | null>(null)
const signatureWidth = ref(0.20)
const placement = ref<SignaturePlacement | null>(null)
const consent = ref(false)
const submitting = ref(false)
const attemptedSubmit = ref(false)
const submitError = ref('')

async function initDraft() {
  await Promise.all([fetchContracts(), loadExecution()])
  const draftId = executionState.value?.draft_document_id
  if (!draftId) {
    loadingPdf.value = false
    return
  }
  try {
    const bytes = await loadDraft(draftId)
    draftBytes.value = bytes
    previewUrl.value = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }))
  } catch (err) {
    toastError('Failed to load contract draft PDF')
  } finally {
    loadingPdf.value = false
  }
}

onMounted(initDraft)
onUnmounted(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})

async function handleSubmit() {
  submitError.value = ''
  attemptedSubmit.value = true

  if (!signerName.value.trim() || !signature.value || !placement.value || !consent.value) {
    submitError.value = 'Please complete all required fields (*) and place your signature on the PDF.'
    return
  }
  if (!draftBytes.value) {
    submitError.value = 'The draft PDF is not loaded. Please try again.'
    return
  }

  submitting.value = true
  try {
    const signedFile = await signDraftPdf(draftBytes.value, signature.value, placement.value)
    const body = new FormData()
    body.set('action', 'submit')
    body.set('operation_id', crypto.randomUUID())
    body.set('stage', 'For Signing')
    body.set('method', 'e_signature')
    body.set('signer_name', signerName.value.trim())
    body.set('signature_page', String(placement.value.page))
    body.set('signature_x', String(placement.value.x))
    body.set('signature_y', String(placement.value.y))
    body.set('signature_width', String(signatureWidth.value))
    body.set('file', signedFile)

    await submit(body)
    invalidateRequests()
    success('Contract signed successfully and submitted for review')
    router.push(backUrl.value)
  } catch (err) {
    submitError.value = err instanceof Error ? err.message : 'Failed to save signed contract.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto flex max-w-7xl flex-col gap-4 p-4 font-poppins sm:px-8 lg:h-full">
    <div class="flex shrink-0 flex-col justify-between gap-4 border-b border-black/5 pb-4 sm:flex-row sm:items-center">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <span class="shrink-0 rounded-lg bg-brand-navy/10 p-2 text-brand-navy"><FileSignature class="size-4" /></span>
          <h1 class="min-w-0 break-words text-xl font-bold text-slate-800 sm:text-2xl">
            Sign Contract: {{ contract?.businessPartner ?? `Contract #${contractId}` }}
          </h1>
        </div>
      </div>
      <Button variant="outline" size="sm" class="shrink-0 self-start sm:self-auto" :disabled="submitting" @click="router.push(backUrl)">
        <ArrowLeft class="size-4" />
        Back to Contract
      </Button>
    </div>

    <!-- Missing draft or invalid stage warning -->
    <div v-if="!loadingPdf && !draftBytes" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center space-y-3">
      <AlertCircle class="size-8 text-red-500 mx-auto" />
      <h2 class="text-base font-bold text-red-800">No Approved Draft PDF Found</h2>
      <p class="text-xs text-red-600 max-w-md mx-auto">
        In-app e-signing requires an approved draft PDF document. Please ensure a draft has been uploaded or use "Upload Signed PDF" instead.
      </p>
    </div>

    <!-- Main Workspace Layout -->
    <div v-else class="grid grid-cols-1 gap-4 lg:min-h-0 lg:flex-1 lg:grid-cols-12 lg:grid-rows-1">
      <SignerDetailsPanel
        class="min-w-0 lg:col-span-5"
        :signer-name="signerName"
        :signature="signature"
        :signature-width="signatureWidth"
        :consent="consent"
        :has-placement="!!placement"
        :submitting="submitting"
        :disabled="loadingPdf"
        :attempted-submit="attemptedSubmit"
        :error="submitError"
        @update:signer-name="signerName = $event"
        @update:signature="signature = $event"
        @update:signature-width="signatureWidth = $event"
        @update:consent="consent = $event"
        @submit="handleSubmit"
      />

      <!-- Right Column: PDF Workspace & Interactive Placement (7 cols) -->
      <PdfSignatureWorkspace
        v-if="previewUrl"
        class="min-w-0 lg:col-span-7"
        :pdf-source="previewUrl"
        :signature="signature"
        :signature-width="signatureWidth"
        :placement="placement"
        :disabled="submitting"
        :attempted-submit="attemptedSubmit"
        @update:placement="placement = $event"
        @update:signature-width="signatureWidth = $event"
      />
    </div>
  </div>
</template>

