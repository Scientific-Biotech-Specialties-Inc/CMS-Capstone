<script setup lang="ts">
import { reactive, computed, ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowLeft, Loader2, ScanLine, Sparkles } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import { useRiskAssessment } from '@/composables/useRiskAssessment'
import DocumentUpload from './DocumentUpload.vue'
import type { ContractRegion, UploadedDoc } from '@/types/contract'
import { useCreateContractDraft } from '@/composables/useCreateContractDraft'
import OcrImportDialog from '@/views/admin/Contracts/OcrImportDialog.vue'
import type { OcrExtractedData } from '@/types/ocr'
import PrefixedNumberInput from '@/components/shared/PrefixedNumberInput.vue'
import FormSelect from '@/components/shared/FormSelect.vue'
import { useDocumentUpload } from '@/composables/useDocumentUpload'
import { useContractTypes } from '@/composables/useContractTypes'
import { readPrsContractLink, usePrsContractHandoff } from '@/composables/usePrsContractHandoff'

import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const router = useRouter()
const route = useRoute()
const { success, error } = useToast()
const { state: authState, hasPermission } = useAuth()
const { state: cacheState, fetchContracts, invalidateContracts, invalidateRequests } = useApiCache()
const { draft, saveDraft, restoreDraft, clearDraft } = useCreateContractDraft()
const { triggerScan } = useRiskAssessment()
const { contractTypes, fetchContractTypes } = useContractTypes()
const { fetchCatalogProduct } = usePrsContractHandoff()

const canUseOcr = computed(() => hasPermission('cms.ai.ocr'))
const canUseRiskAssessment = computed(() => hasPermission('cms.ai.risk_assessment'))
const canUseContractGenerator = computed(() => hasPermission('cms.ai.contract_generator'))

const ocrOpen = ref(false)
const { detectType: detectOcrDocType, validate: validateOcrDoc, uploadFile: uploadOcrDoc } = useDocumentUpload()

const activePartnerNames = computed(() => {
  return vendorOptions.value.filter(v => v.status !== 'Suspended').map(v => v.name)
})

function attachOcrFile(file: File) {
  // Only PDF/DOCX are supported as contract documents; OCR also accepts
  // images, which can't be represented as an UploadedDoc, so skip those.
  const type = detectOcrDocType(file)
  if (!type) return
  if (validateOcrDoc(file, contractDocs.value)) return

  const previewUrl = type === 'pdf' ? URL.createObjectURL(file) : ''
  const newDoc: UploadedDoc = {
    file,
    name: file.name,
    size: file.size,
    type,
    previewUrl,
    uploadStatus: 'uploading',
  }
  contractDocs.value = [...contractDocs.value, newDoc]
  const index = contractDocs.value.length - 1

  uploadOcrDoc(newDoc, (patch) => {
    if (!contractDocs.value[index]) return
    const updated = [...contractDocs.value]
    updated[index] = { ...updated[index], ...patch }
    contractDocs.value = updated
  })
}

function handleOcrSuccess(data: OcrExtractedData, file: File) {
  if (data.business_partner) {
    form.businessPartner = data.business_partner
    touched.businessPartner = true
  }
  if (data.category) {
    form.category = data.category
    touched.category = true
  }
  if (data.start_date) {
    form.startDate = data.start_date
    touched.startDate = true
  }
  if (data.end_date) {
    form.endDate = data.end_date
    touched.endDate = true
  }
  if (data.description) {
    form.description = data.description
    touched.description = true
  }
  attachOcrFile(file)
}
const loading      = ref(false)
const showConfirm  = ref(false)
const showGenerateConfirm = ref(false)
const generatingContract = ref(false)
const contractDocs = ref<UploadedDoc[]>([])

const today = new Date().toISOString().split('T')[0]
const maxDate = (() => {
  const d = new Date()
  d.setFullYear(d.getFullYear() + 100)
  return d.toISOString().split('T')[0]
})()
const minDate = '1999-01-01'

interface FormState {
  businessPartner: string
  category:        string
  itemCode:        string
  description:     string
  serialNo:        string
  sbuNumber:       string
  region:          ContractRegion | ''
  startDate:       string
  endDate:         string
  prsActivityId:   number | ''
  productId?: number
  prsInstitutionId?: number
  prsBranchId?: number
}

const form = reactive<FormState>({
  businessPartner: '',
  category:        '',
  itemCode:        '',
  description:     '',
  serialNo:        '',
  sbuNumber:       '',
  region:          '',
  startDate:       '',
  endDate:         '',
  prsActivityId:   '',
})

const touched = reactive<Record<keyof FormState, boolean>>({
  businessPartner: false,
  category:        false,
  itemCode:        false,
  description:     false,
  serialNo:        false,
  sbuNumber:       false,
  region:          false,
  startDate:       false,
  endDate:         false,
  prsActivityId:   false,
  productId:       false,
  prsInstitutionId: false,
  prsBranchId:     false,
})

const serverErrors = reactive({
  serialNo: '',
  itemCode: '',
  sbuNumber: '',
})

watch(() => form.serialNo, () => { serverErrors.serialNo = '' })
watch(() => form.itemCode, () => { serverErrors.itemCode = '' })
watch(() => form.sbuNumber, () => { serverErrors.sbuNumber = '' })

const existingSerialNumbers = computed(() => {
  return new Set(
    (cacheState.contracts || [])
      .map(c => c.serialNo?.trim().toLowerCase())
      .filter(Boolean)
  )
})

const isVerifiedPartner = computed(() => {
  const name = String(form.businessPartner || '').trim().toLowerCase()
  if (!name) return false
  return vendorOptions.value.some(v => v.name.toLowerCase() === name && v.status !== 'Suspended')
})

const errors = computed(() => ({
  businessPartner: touched.businessPartner && !String(form.businessPartner || '').trim()
    ? 'Business partner is required.'
    : touched.businessPartner && suspendedVendorMatch.value
    ? 'This vendor is suspended and cannot be assigned to a new contract.'
    : touched.businessPartner && vendorOptions.value.length > 0 && !isVerifiedPartner.value
    ? 'Contract must be created with an existing verified Business Partner.'
    : '',
  category:        touched.category        && !form.category                  ? 'Category is required.' : '',
  itemCode:        touched.itemCode        && !String(form.itemCode || '').trim()
    ? 'Item code is required.'
    : touched.itemCode && !/^ITM-\d{4}$/.test(String(form.itemCode || '').trim())
    ? 'Item code must start with ITM- followed by 4 digits (e.g., ITM-0041).'
    : serverErrors.itemCode || '',
  description:     touched.description     && !String(form.description || '').trim()        ? 'Description is required.' : '',
  serialNo:        touched.serialNo        && !String(form.serialNo || '').trim()
    ? 'Serial number is required.'
    : touched.serialNo && !/^SN-\d{4}-\d{4}$/.test(String(form.serialNo || '').trim())
    ? 'Serial number must be in the format SN-YYYY-xxxx (e.g., SN-2024-0041).'
    : touched.serialNo && existingSerialNumbers.value.has(String(form.serialNo || '').trim().toLowerCase())
    ? 'This serial number is already registered in an existing contract.'
    : serverErrors.serialNo || '',
  sbuNumber:       touched.sbuNumber       && !String(form.sbuNumber || '').trim()
    ? 'SBU number is required.'
    : touched.sbuNumber && !/^SBU-\d{3}$/.test(String(form.sbuNumber || '').trim())
    ? 'SBU number must start with SBU- followed by 3 digits (e.g., SBU-001).'
    : serverErrors.sbuNumber || '',
  region:          touched.region          && !form.region                    ? 'Region is required.' : '',
  startDate:       touched.startDate       && !form.startDate
    ? 'Start date is required.'
    : touched.startDate && form.startDate < minDate
    ? 'Start date cannot be before 1999.'
    : touched.startDate && form.startDate > maxDate
    ? 'Start date cannot exceed 100 years from today.'
    : '',
  endDate:         touched.endDate && !form.endDate
    ? 'End date is required.'
    : touched.endDate && form.endDate > maxDate
    ? 'End date cannot exceed 100 years from today.'
    : touched.endDate && form.startDate && form.endDate <= form.startDate
    ? 'End date must be after start date.'
    : '',
}))

// Sourced from contract-management's contract_categories lookup table
// (fetched on mount) rather than hardcoded, so new contract types become
// selectable without a frontend code change.
const categories = computed(() => contractTypes.value)

const regions: ContractRegion[] = ['Luzon', 'Visayas', 'Mindanao']

function touchAll() {
  (Object.keys(touched) as (keyof FormState)[]).forEach(k => { touched[k] = true })
}

function isValid() {
  return (
    String(form.businessPartner || '').trim() &&
    !suspendedVendorMatch.value &&
    (vendorOptions.value.length === 0 || isVerifiedPartner.value) &&
    form.category &&
    String(form.itemCode || '').trim() && /^ITM-\d{4}$/.test(String(form.itemCode || '').trim()) &&
    String(form.description || '').trim() &&
    String(form.serialNo || '').trim() && /^SN-\d{4}-\d{4}$/.test(String(form.serialNo || '').trim()) &&
    !existingSerialNumbers.value.has(String(form.serialNo || '').trim().toLowerCase()) &&
    String(form.sbuNumber || '').trim() && /^SBU-\d{3}$/.test(String(form.sbuNumber || '').trim()) &&
    form.region &&
    form.startDate && form.startDate >= minDate && form.startDate <= maxDate &&
    form.endDate && form.endDate <= maxDate &&
    form.endDate > form.startDate
  )
}

const isUploadingOrScanFailed = computed(() => {
  return contractDocs.value.some(d => d.uploadStatus === 'uploading' || d.uploadStatus === 'scanning' || d.uploadStatus === 'error')
})

function getApiErrorMessage(data: any) {
  if (data?.errors && typeof data.errors === 'object') {
    const firstError = Object.values(data.errors).flat().find(Boolean)
    if (firstError) return String(firstError)
  }

  return data?.message ?? 'Something went wrong.'
}

function handleGenerateContract() {
  touchAll()
  if (!isValid()) {
    error('Missing Required Information', 'Please complete all required fields with valid values before generating a contract.')
    return
  }
  showGenerateConfirm.value = true
}

async function confirmGenerateContract() {
  generatingContract.value = true
  saveDraft(form, contractDocs.value, 'sales')
  await new Promise(resolve => setTimeout(resolve, 250))
  showGenerateConfirm.value = false
  router.push('/sales/contracts/editor')
  generatingContract.value = false
}

function handleSubmit() {
  touchAll()
  if (!isValid()) return
  showConfirm.value = true
}

async function confirmSubmit() {
  loading.value = true
  showConfirm.value = false
  try {
    // Ensure all attached documents are uploaded to backend before creating the contract
    for (const doc of contractDocs.value) {
      const isMongoId = doc.id && /^[0-9a-fA-F]{24}$/.test(doc.id)
      if (!isMongoId && doc.file) {
        await uploadOcrDoc(doc, (patch) => {
          Object.assign(doc, patch)
        })
        if (doc.uploadStatus === 'error' || !doc.id) {
          error('Document upload failed', doc.errorMessage || `Could not upload document "${doc.name}".`)
          loading.value = false
          return
        }
      }
    }

    const validDocIds = contractDocs.value
      .filter(d => d.id && /^[0-9a-fA-F]{24}$/.test(d.id))
      .map(d => d.id as string)

    const draftId = contractDocs.value.find(doc => doc.type === 'pdf' && doc.id && validDocIds.includes(doc.id))?.id
    if (!draftId) {
      error('Draft PDF required', 'Upload a draft PDF before submitting the contract.')
      return
    }

    const payload = {
      bp_name:       form.businessPartner,
      category:      form.category,
      item_code:     form.itemCode,
      description:   form.description,
      serial_number: form.serialNo,
      sbu_number:    form.sbuNumber,
      region:        form.region,
      start_date:    form.startDate,
      end_date:      form.endDate,
      document_ids:  validDocIds,
      draft_document_id: draftId,
      prs_activity_id: form.prsActivityId ? Number(form.prsActivityId) : null,
      product_id: form.productId,
      prs_institution_id: form.prsInstitutionId,
      prs_branch_id: form.prsBranchId,
    }

    const res = await fetch(`${import.meta.env.VITE_CONTRACT_API_URL}/contracts`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authState.token}`,
      },
      body: JSON.stringify(payload),
    })

    const data = await res.json().catch(() => ({}))

    if (!res.ok) {
      if (data?.errors?.serial_number) {
        serverErrors.serialNo = Array.isArray(data.errors.serial_number) ? data.errors.serial_number[0] : String(data.errors.serial_number)
      }
      if (data?.errors?.item_code) {
        serverErrors.itemCode = Array.isArray(data.errors.item_code) ? data.errors.item_code[0] : String(data.errors.item_code)
      }
      if (data?.errors?.sbu_number) {
        serverErrors.sbuNumber = Array.isArray(data.errors.sbu_number) ? data.errors.sbu_number[0] : String(data.errors.sbu_number)
      }
      error('Failed to create contract', getApiErrorMessage(data))
      return
    }

    const newContractId = String(data.data.contract_id)

    // US-026: trigger the AI Risk Assessment RAG pipeline only if the user
    // uploaded a document and has the feature enabled via permissions.
    // Advisory only — never blocks contract creation itself.
    const hasUploadedDocument = contractDocs.value.some(d => !!d.id)
    if (hasUploadedDocument && canUseRiskAssessment.value) {
      triggerScan(newContractId).catch(() => {
        // Best-effort: a failed scan trigger shouldn't block the user from
        // proceeding to their newly created contract.
      })
    }

    contractDocs.value = []
    clearDraft()
    invalidateContracts()
    invalidateRequests()
    success('Contract Created', 'Contract has been saved successfully.')
    router.push(`/sales/contracts/${newContractId}`)
  } catch {
    error('Network error', 'Could not reach the server. Please try again.')
  } finally {
    loading.value = false
  }
}

import { onClickOutside } from '@vueuse/core'

interface VendorOption { name: string; status: string }
const vendorOptions = ref<VendorOption[]>([])

async function fetchPartnerNames() {
  try {
    const apiBase = import.meta.env.VITE_VENDOR_API_URL || 'http://localhost:8001/api'
    const headers = {
      'Authorization': `Bearer ${authState.token || ''}`,
      'Accept': 'application/json'
    }
    const [resP, resS] = await Promise.all([
      fetch(`${apiBase}/partners?per_page=100`, { headers }),
      fetch(`${apiBase}/suppliers?per_page=100`, { headers })
    ])
    const vendors: VendorOption[] = []
    if (resP.ok) {
      const json = await resP.json()
      vendors.push(...(json.data || []).map((p: any) => ({ name: p.partner_name, status: p.status })))
    }
    if (resS.ok) {
      const json = await resS.json()
      vendors.push(...(json.data || []).map((s: any) => ({ name: s.supplier_name, status: s.status })))
    }
    const seen = new Set<string>()
    vendorOptions.value = vendors.filter(v => {
      const key = v.name.toLowerCase()
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })
  } catch (err) {
    console.error('Failed to fetch partner names:', err)
  }
}

const prefilledFromPrs = ref(false)

onMounted(() => {
  document.body.style.pointerEvents = 'auto'
  document.body.style.overflow = ''

  fetchPartnerNames()
  fetchPrsActivities()
  fetchContractTypes()
  if (!cacheState.contracts) {
    fetchContracts().catch(() => {})
  }

  // 1. Restore draft if active
  if (draft.active && (draft.role === 'sales' || !draft.role)) {
    const saved = restoreDraft()
    if (saved) {
      Object.assign(form, saved.form)
      contractDocs.value = [...saved.docs]
      if (saved.form.prsActivityId) prefilledFromPrs.value = true
    }
    // Deactivate active draft state but preserve draft.generatedDoc for re-editing
    draft.active = false
  }

  // 2. Otherwise auto-populate fields from PRS integration query params
  const link = readPrsContractLink(route.query)
  if (link.prsActivityId) {
    form.prsActivityId = link.prsActivityId
    prefilledFromPrs.value = true
  }
  const partnerParam = route.query.partner
  if (partnerParam) {
    form.businessPartner = String(partnerParam)
  }
  const bpNameParam = route.query.bp_name
  if (bpNameParam) {
    form.businessPartner = String(bpNameParam)
  }
  if (link.sbuNumber) form.sbuNumber = link.sbuNumber
  const itemCodeParam = route.query.item_code
  if (typeof itemCodeParam === 'string' && /^ITM-\d{4}$/.test(itemCodeParam)) {
    form.itemCode = String(itemCodeParam)
  }
  const descParam = route.query.description
  if (descParam) {
    form.description = String(descParam)
  }
  form.prsInstitutionId = link.prsInstitutionId ?? form.prsInstitutionId
  form.prsBranchId = link.prsBranchId ?? form.prsBranchId
  const productId = link.productId ?? form.productId
  if (productId) void selectCatalogProduct(productId)
})

async function selectCatalogProduct(productId: number) {
  form.productId = productId
  try {
    const product = await fetchCatalogProduct(productId)
    if (form.productId !== productId) return
    Object.assign(form, product)
  } catch {
    if (form.productId === productId) {
      form.productId = undefined
      error('Could not load the PRS product.')
    }
  }
}

function previewDocument(doc: UploadedDoc) {
  if (!doc.id) return
  saveDraft(form, contractDocs.value, 'sales')
  router.push({
    path: `/sales/contracts/preview/documents/${doc.id}`,
    query: {
      from: 'create',
      role: 'sales',
      docName: doc.name,
      docSize: String(doc.size),
      docType: doc.type,
    }
  })
}

const showSuggestions = ref(false)
const suggestionsContainer = ref<HTMLElement | null>(null)

const partnerSuggestions = computed(() => {
  const query = String(form.businessPartner || '').trim().toLowerCase()
  const available = vendorOptions.value.filter(v => v.status !== 'Suspended')
  if (!query) return available.map(v => v.name)
  return available.filter(v => v.name.toLowerCase().includes(query)).map(v => v.name)
})

const suspendedVendorMatch = computed(() => {
  const name = String(form.businessPartner || '').trim().toLowerCase()
  if (!name) return false
  return vendorOptions.value.some(v => v.name.toLowerCase() === name && v.status === 'Suspended')
})

function selectSuggestion(name: string) {
  form.businessPartner = name
  showSuggestions.value = false
  touched.businessPartner = true
}

onClickOutside(suggestionsContainer, () => {
  showSuggestions.value = false
})

interface PrsActivityOption {
  id: number
  institution: string
  sbu: string | null
  description: string | null
  activity_date: string | null
  cms_product_id: number | null
  institution_id: number | null
  branch_id: number | null
  status: string
}

const prsActivities = ref<PrsActivityOption[]>([])
const loadingPrsActivities = ref(false)
const showPrsDropdown = ref(false)
const prsContainer = ref<HTMLElement | null>(null)

async function fetchPrsActivities() {
  loadingPrsActivities.value = true
  try {
    const apiBase = import.meta.env.VITE_PRS_API_URL || '/api/prs'
    const headers = {
      'Authorization': `Bearer ${authState.token || ''}`,
      'Accept': 'application/json'
    }
    const res = await fetch(`${apiBase}/analytics/submissions?activity_type=Closing&per_page=100`, { headers })
    if (res.ok) {
      const json = await res.json()
      prsActivities.value = (json.data || []).map((item: any) => ({
        id: item.id,
        institution: item.institution,
        sbu: item.sbu,
        description: item.description,
        activity_date: item.activity_date,
        cms_product_id: item.cms_product_id ?? item.demo_submissions?.[0]?.product?.cms_product_id ?? null,
        institution_id: item.institution_id ?? null,
        branch_id: item.branch_id ?? null,
        status: item.status
      }))
    }
  } catch (err) {
    console.error('Failed to fetch PRS activities:', err)
  } finally {
    loadingPrsActivities.value = false
  }
}

function selectPrsActivity(activity: PrsActivityOption) {
  form.prsActivityId = activity.id
  form.productId = undefined
  form.itemCode = ''
  form.prsInstitutionId = activity.institution_id ?? undefined
  form.prsBranchId = activity.branch_id ?? undefined
  if (activity.institution) {
    form.businessPartner = String(activity.institution)
  }
  if (activity.sbu) {
    const number = String(activity.sbu).replace(/^SBU-/i, '')
    form.sbuNumber = /^\d{1,3}$/.test(number) ? `SBU-${number.padStart(3, '0')}` : ''
  }
  if (activity.description) {
    form.description = String(activity.description)
  }
  if (activity.cms_product_id) {
    void selectCatalogProduct(activity.cms_product_id)
  }
  showPrsDropdown.value = false
}

onClickOutside(prsContainer, () => {
  showPrsDropdown.value = false
})
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-4">
      <button @click="router.push('/sales/contracts')"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1">
        <h1 class="text-xl font-semibold text-black">Create New Contract</h1>
        <p class="text-sm text-black/40 mt-0.5">Fill in the details below to create a new contract.</p>
      </div>

      <Button v-if="canUseOcr" @click="ocrOpen = true" variant="outline"
        class="h-9 px-4 text-sm border-black/15 text-brand-navy hover:text-brand-dark hover:bg-black/2 flex items-center gap-2">
        <ScanLine class="w-4 h-4 text-brand-navy" />
        Fill with OCR
      </Button>

      <Button v-if="canUseContractGenerator" @click="handleGenerateContract" variant="outline"
        :disabled="generatingContract"
        class="h-9 px-4 text-sm border-brand-navy/30 text-brand-navy hover:text-brand-dark hover:bg-brand-navy/5 flex items-center gap-2 font-medium shadow-xs disabled:opacity-60 disabled:cursor-not-allowed">
        <Loader2 v-if="generatingContract" class="w-4 h-4 animate-spin text-brand-navy" />
        <Sparkles v-else class="w-4 h-4 text-brand-navy" />
        {{ draft.generatedDoc ? 'Edit Generated Contract' : 'Generate Contract' }}
      </Button>
    </div>

    <!-- Form card -->
    <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

      <!-- Section: Contract Info -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Contract Info</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

          <!-- Business Partner -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Business Partner <span class="text-red-500">*</span></label>
            <div class="relative w-full" ref="suggestionsContainer">
              <input
                v-model="form.businessPartner"
                @focus="showSuggestions = true"
                @blur="touched.businessPartner = true"
                type="text"
                placeholder="e.g. Globe Telecom"
                class="h-9 rounded-lg border px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition w-full"
                :class="errors.businessPartner
                  ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                  : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
              />
              <div
                v-if="showSuggestions && partnerSuggestions.length > 0"
                class="absolute left-0 right-0 top-[calc(100%+4px)] z-50 max-h-48 overflow-y-auto bg-white border border-black/8 rounded-lg shadow-lg py-1 font-poppins divide-y divide-black/[0.04]"
              >
                <button
                  v-for="name in partnerSuggestions"
                  :key="name"
                  type="button"
                  @click="selectSuggestion(name)"
                  class="w-full text-left px-3.5 py-2 text-xs text-black/75 hover:bg-black/[0.03] hover:text-brand-blue font-medium transition-colors"
                >
                  {{ name }}
                </button>
              </div>
            </div>
            <p v-if="errors.businessPartner" class="text-xs text-red-500">{{ errors.businessPartner }}</p>
          </div>

          <!-- Category -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Category <span class="text-red-500">*</span></label>
            <FormSelect
              v-model="form.category"
              :options="categories"
              placeholder="Select category"
              :error="!!errors.category"
              @blur="touched.category = true"
            />
            <p v-if="errors.category" class="text-xs text-red-500">{{ errors.category }}</p>
          </div>

          <!-- Connected PRS Activity ID (Optional) -->
          <div class="flex flex-col gap-1.5 relative" ref="prsContainer">
            <label class="text-xs font-semibold text-black/55">Connected PRS Activity ID (Optional)</label>
            <div class="relative w-full">
              <input
                v-model="form.prsActivityId"
                @focus="!prefilledFromPrs && (showPrsDropdown = true)"
                type="number"
                :readonly="prefilledFromPrs"
                placeholder="e.g. 12 (Select or type)"
                class="h-9 rounded-lg border px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition w-full border-black/12 focus:border-brand-blue focus:ring-brand-blue/15"
                :class="prefilledFromPrs ? 'bg-black/[0.02] border-black/8 cursor-not-allowed' : ''"
              />
              <p v-if="prefilledFromPrs" class="text-xs text-emerald-600 mt-1 font-medium">
                ✓ Linked from PRS Activity #{{ form.prsActivityId }}
              </p>
              <div
                v-if="showPrsDropdown && prsActivities.length > 0"
                class="absolute left-0 right-0 top-[calc(100%+4px)] z-50 max-h-48 overflow-y-auto bg-white border border-black/8 rounded-lg shadow-lg py-1 font-poppins divide-y divide-black/[0.04]"
              >
                <button
                  v-for="act in prsActivities"
                  :key="act.id"
                  type="button"
                  @click="selectPrsActivity(act)"
                  class="w-full text-left px-3.5 py-2 text-xs text-black/75 hover:bg-black/[0.03] hover:text-brand-blue font-medium transition-colors"
                >
                  <div class="flex items-center justify-between font-semibold">
                    <span>ID: {{ act.id }} - {{ act.institution }}</span>
                    <span :class="[
                      'text-[9px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider',
                      act.status === 'Approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                      act.status === 'Pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                      'bg-red-50 text-red-700 border border-red-200'
                    ]">
                      {{ act.status }}
                    </span>
                  </div>
                  <div class="text-[10px] text-black/40">{{ act.description || 'No description' }} | {{ act.sbu || 'No SBU' }}</div>
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Section: Item Details -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Item Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

          <!-- Item Code -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Item Code <span class="text-red-500">*</span></label>
            <PrefixedNumberInput
              v-model="form.itemCode"
              prefix="ITM-"
              placeholder="0041"
              numeric-only
              :max-length="4"
              :error="!!errors.itemCode"
              @blur="touched.itemCode = true"
            />
            <p v-if="errors.itemCode" class="text-xs text-red-500">{{ errors.itemCode }}</p>
          </div>

          <!-- Description -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Description <span class="text-red-500">*</span></label>
            <input
              v-model="form.description"
              @blur="touched.description = true"
              type="text"
              placeholder="e.g. Network Infrastructure"
              class="h-9 rounded-lg border px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
              :class="errors.description
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
            <p v-if="errors.description" class="text-xs text-red-500">{{ errors.description }}</p>
          </div>

          <!-- Serial No -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Serial No <span class="text-red-500">*</span></label>
            <PrefixedNumberInput
              v-model="form.serialNo"
              prefix="SN-"
              placeholder="2026-0041"
              numeric-only
              :auto-dash-after="4"
              :max-length="9"
              :error="!!errors.serialNo"
              @blur="touched.serialNo = true"
            />
            <p v-if="errors.serialNo" class="text-xs text-red-500">{{ errors.serialNo }}</p>
          </div>

          <!-- SBU Number -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">SBU Number <span class="text-red-500">*</span></label>
            <PrefixedNumberInput
              v-model="form.sbuNumber"
              prefix="SBU-"
              placeholder="001"
              numeric-only
              :max-length="3"
              :error="!!errors.sbuNumber"
              @blur="touched.sbuNumber = true"
            />
            <p v-if="errors.sbuNumber" class="text-xs text-red-500">{{ errors.sbuNumber }}</p>
          </div>

        </div>
      </div>

      <!-- Section: Schedule & Location -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Schedule & Location</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

          <!-- Region -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Region <span class="text-red-500">*</span></label>
            <FormSelect
              v-model="form.region"
              :options="regions"
              placeholder="Select region"
              :error="!!errors.region"
              @blur="touched.region = true"
            />
            <p v-if="errors.region" class="text-xs text-red-500">{{ errors.region }}</p>
          </div>

          <!-- Start Date -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Start Date <span class="text-red-500">*</span></label>
            <input
              v-model="form.startDate"
              @blur="touched.startDate = true"
              type="date"
              :min="minDate"
              :max="maxDate"
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition"
              :class="errors.startDate
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
            <p v-if="errors.startDate" class="text-xs text-red-500">{{ errors.startDate }}</p>
          </div>

          <!-- End Date -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">End Date <span class="text-red-500">*</span></label>
            <input
              v-model="form.endDate"
              @blur="touched.endDate = true"
              type="date"
              :min="form.startDate || today"
              :max="maxDate"
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition"
              :class="errors.endDate
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
            <p v-if="errors.endDate" class="text-xs text-red-500">{{ errors.endDate }}</p>
          </div>

        </div>
      </div>

      <!-- Section: Documents -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-1">Documents</h2>
        <p class="text-xs text-black/35 mb-4">Attach all documents. Accepted formats: PDF, DOCX · Max 10 MB per file.</p>
        <p class="mb-4 text-sm text-slate-600">Upload the contract draft as a PDF. The first PDF will be sent for approval.</p>
        <DocumentUpload v-model="contractDocs" :on-preview="previewDocument" />
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 flex items-center justify-end gap-3">
        <Button variant="outline" @click="router.push('/sales/contracts')"
          class="h-9 px-5 text-sm border-black/15 text-black/60 hover:text-black">
          Cancel
        </Button>
        <Button @click="handleSubmit" :disabled="loading || isUploadingOrScanFailed"
          class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm disabled:opacity-50 disabled:cursor-not-allowed gap-2">
          <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
          {{ loading ? 'Creating…' : 'Create Contract' }}
        </Button>
      </div>

    </div>
  </div>



  <ConfirmationDialog
    v-model:open="showConfirm"
    title="Create Contract"
    description="Are you sure you want to create this contract? This will save the contract record to the system."
    confirm-label="Create"
    variant="default"
    :loading="loading"
    @confirm="confirmSubmit"
  />

  <OcrImportDialog
    v-model:open="ocrOpen"
    :candidate-partners="activePartnerNames"
    @success="handleOcrSuccess"
  />


  <ConfirmationDialog
    v-model:open="showGenerateConfirm"
    title="Generate Contract with AI"
    :description="draft.generatedDoc
      ? `Open the Contract Editor to view and customize the generated agreement for ${form.businessPartner || 'this partner'}?`
      : `Generate a formal agreement draft for ${form.businessPartner || 'this partner'} (${form.category || 'Standard'}) using AI? You will be navigated to the contract editor to review and customize the clauses.`"
    :confirm-label="draft.generatedDoc ? 'Open Editor' : 'Generate Contract'"
    variant="ai"
    :loading="generatingContract"
    @confirm="confirmGenerateContract"
  />
</template>
