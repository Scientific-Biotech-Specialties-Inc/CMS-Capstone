<script setup lang="ts">
import { reactive, computed, ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowLeft, Loader2, ScanLine, Sparkles } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import DocumentUpload from '@/views/sales/Contracts/DocumentUpload.vue'
import type { ContractRegion, UploadedDoc } from '@/types/contract'
import type { OcrExtractedData } from '@/types/ocr'
import { useCreateContractDraft } from '@/composables/useCreateContractDraft'
import OcrImportDialog from '@/views/admin/Contracts/OcrImportDialog.vue'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import PrefixedNumberInput from '@/components/shared/PrefixedNumberInput.vue'
import FormSelect from '@/components/shared/FormSelect.vue'
import { useDocumentUpload } from '@/composables/useDocumentUpload'

const { hasPermission, role } = useAuth()
const canUseOcr = computed(() => hasPermission('cms.ai.ocr') || role.value === 'Admin' || role.value === 'CEO')
const canUseContractGenerator = computed(() => hasPermission('cms.ai.contract_generator') || role.value === 'Admin' || role.value === 'CEO')

const router = useRouter()
const route  = useRoute()
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
  if (data.item_code) {
    form.itemCode = data.item_code
    touched.itemCode = true
  }
  if (data.description) {
    form.description = data.description
    touched.description = true
  }
  if (data.serial_number) {
    form.serialNo = data.serial_number
    touched.serialNo = true
  }
  if (data.sbu_number) {
    form.sbuNumber = data.sbu_number
    touched.sbuNumber = true
  }
  if (data.region && data.region !== 'Unknown') {
    form.region = data.region as ContractRegion
    touched.region = true
  }
  if (data.start_date) {
    form.startDate = data.start_date
    touched.startDate = true
  }
  if (data.end_date) {
    form.endDate = data.end_date
    touched.endDate = true
  }
  attachOcrFile(file)
}
const { success, error } = useToast()
const { state: authState } = useAuth()
const { state: cacheState, fetchContracts, invalidateContracts, invalidateRequests } = useApiCache()
const { draft, saveDraft, restoreDraft, clearDraft } = useCreateContractDraft()


const loading      = ref(false)
const contractDocs = ref<UploadedDoc[]>([])
const showGenerateConfirm = ref(false)
const generatingContract = ref(false)

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
  const name = form.businessPartner.trim().toLowerCase()
  if (!name) return false
  return vendorOptions.value.some(v => v.name.toLowerCase() === name && v.status !== 'Suspended')
})

const errors = computed(() => ({
  businessPartner: touched.businessPartner && !form.businessPartner.trim()
    ? 'Business partner is required.'
    : touched.businessPartner && suspendedVendorMatch.value
    ? 'This vendor is suspended and cannot be assigned to a new contract.'
    : touched.businessPartner && vendorOptions.value.length > 0 && !isVerifiedPartner.value
    ? 'Contract must be created with an existing verified Business Partner.'
    : '',
  category:        touched.category        && !form.category                  ? 'Category is required.' : '',
  itemCode:        touched.itemCode        && !form.itemCode.trim()
    ? 'Item code is required.'
    : touched.itemCode && !/^ITM-\d{4}$/.test(form.itemCode.trim())
    ? 'Item code must start with ITM- followed by 4 digits (e.g., ITM-0041).'
    : serverErrors.itemCode || '',
  description:     touched.description     && !form.description.trim()        ? 'Description is required.' : '',
  serialNo:        touched.serialNo        && !form.serialNo.trim()
    ? 'Serial number is required.'
    : touched.serialNo && !/^SN-\d{4}-\d{4}$/.test(form.serialNo.trim())
    ? 'Serial number must be in the format SN-YYYY-xxxx (e.g., SN-2024-0041).'
    : touched.serialNo && existingSerialNumbers.value.has(form.serialNo.trim().toLowerCase())
    ? 'This serial number is already registered in an existing contract.'
    : serverErrors.serialNo || '',
  sbuNumber:       touched.sbuNumber       && !form.sbuNumber.trim()
    ? 'SBU number is required.'
    : touched.sbuNumber && !/^SBU-\d{3}$/.test(form.sbuNumber.trim())
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

const categories = [
  'Service Agreement',
  'Partnership Agreement',
  'Supply Contract',
  'Equipment Lease',
  'Equipment Maintenance',
]

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
    form.itemCode.trim() && /^ITM-\d{4}$/.test(form.itemCode.trim()) &&
    form.description.trim() &&
    form.serialNo.trim() && /^SN-\d{4}-\d{4}$/.test(form.serialNo.trim()) &&
    !existingSerialNumbers.value.has(form.serialNo.trim().toLowerCase()) &&
    form.sbuNumber.trim() && /^SBU-\d{3}$/.test(form.sbuNumber.trim()) &&
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
  saveDraft(form, contractDocs.value, 'admin')
  await new Promise(resolve => setTimeout(resolve, 250))
  showGenerateConfirm.value = false
  router.push('/admin/contracts/editor')
  generatingContract.value = false
}

async function handleSubmit() {
  touchAll()
  if (!isValid()) return

  loading.value = true
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

    contractDocs.value = []
    clearDraft()
    invalidateContracts()
    invalidateRequests()
    success('Contract created', `${form.businessPartner}'s contract has been saved.`)
    router.push(`/admin/contracts/${data.data.contract_id}`)
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

onMounted(async () => {
  await fetchPartnerNames()
  if (route.query.partner) {
    form.businessPartner = String(route.query.partner)
    touched.businessPartner = true
  }

  if (!cacheState.contracts) {
    fetchContracts().catch(() => {})
  }

  if (draft.active && (draft.role === 'admin' || !draft.role)) {
    const saved = restoreDraft()
    if (saved) {
      Object.assign(form, saved.form)
      contractDocs.value = [...saved.docs]
    }
    draft.active = false
  }
})

function previewDocument(doc: UploadedDoc) {
  if (!doc.id) return
  saveDraft(form, contractDocs.value, 'admin')
  router.push({
    path: `/admin/contracts/preview/documents/${doc.id}`,
    query: {
      from: 'create',
      role: 'admin',
      docName: doc.name,
      docSize: String(doc.size),
      docType: doc.type,
    }
  })
}

const showSuggestions = ref(false)
const suggestionsContainer = ref<HTMLElement | null>(null)

const partnerSuggestions = computed(() => {
  const query = form.businessPartner.trim().toLowerCase()
  const available = vendorOptions.value.filter(v => v.status !== 'Suspended')
  if (!query) return available.map(v => v.name)
  return available.filter(v => v.name.toLowerCase().includes(query)).map(v => v.name)
})

const suspendedVendorMatch = computed(() => {
  const name = form.businessPartner.trim().toLowerCase()
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
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-4">
        <button @click="router.push('/admin/contracts')"
          class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
          <ArrowLeft class="w-4 h-4" />
        </button>
        <div class="flex-1">
          <h1 class="text-xl font-semibold text-black">Create New Contract</h1>
          <p class="text-sm text-black/40 mt-0.5">Fill in the details below to create a new contract.</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2">
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
    </div>

    <!-- Form card -->
    <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

      <!-- Section: Contract Info -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Contract Info</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

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
        <Button variant="outline" @click="router.push('/admin/contracts')"
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

  <!-- OCR Scan Dialog -->
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
