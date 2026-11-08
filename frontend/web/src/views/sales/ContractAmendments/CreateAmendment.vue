<script setup lang="ts">
import { reactive, computed, ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowLeft, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useApiCache } from '@/composables/useApiCache'
import { useAmendmentStore } from '@/composables/useAmendmentStore'
import DocumentUpload from '../Contracts/DocumentUpload.vue'
import type { ContractRegion, UploadedDoc } from '@/types/contract'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const router = useRouter()
const route = useRoute()
const { success, error } = useToast()
const { state: authState } = useAuth()
const { state: cacheState, fetchContracts } = useApiCache()
const amendmentStore = useAmendmentStore()

const id = route.params.id as string

// Derive role prefix from current URL path
const rolePrefix = computed(() => {
  if (route.path.startsWith('/admin')) return '/admin'
  if (route.path.startsWith('/manager')) return '/manager'
  return '/sales'
})

const loading = ref(false)
const showConfirm = ref(false)
const contractDocs = ref<UploadedDoc[]>([])
const contract = ref<any | null>(null)
const loadingContract = ref(true)

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
  reason:          string
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
  reason:          '',
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
  reason:          false,
})

const errors = computed(() => ({
  businessPartner: touched.businessPartner && !form.businessPartner.trim() ? 'Business partner is required.' : '',
  category:        touched.category        && !form.category               ? 'Category is required.' : '',
  itemCode:        touched.itemCode        && !form.itemCode.trim()
    ? 'Item code is required.'
    : touched.itemCode && !/^ITM-\d{4}$/.test(form.itemCode.trim())
    ? 'Item code must start with ITM- followed by 4 digits (e.g., ITM-0041).'
    : '',
  description:     touched.description     && !form.description.trim()     ? 'Description is required.' : '',
  serialNo:        touched.serialNo        && !form.serialNo.trim()
    ? 'Serial number is required.'
    : touched.serialNo && !/^SN-\d{4}-\d{4}$/.test(form.serialNo.trim())
    ? 'Serial number must be in the format SN-YYYY-xxxx (e.g., SN-2024-0041).'
    : '',
  sbuNumber:       touched.sbuNumber       && !form.sbuNumber.trim()
    ? 'SBU number is required.'
    : touched.sbuNumber && !/^SBU-\d{3}$/.test(form.sbuNumber.trim())
    ? 'SBU number must start with SBU- followed by 3 digits (e.g., SBU-001).'
    : '',
  region:          touched.region          && !form.region                 ? 'Region is required.' : '',
  startDate:       touched.startDate       && !form.startDate              ? 'Start date is required.' : '',
  endDate:         touched.endDate         && !form.endDate
    ? 'End date is required.'
    : touched.endDate && form.startDate && form.endDate <= form.startDate
    ? 'End date must be after start date.'
    : '',
  reason:          touched.reason          && !form.reason.trim()          ? 'Reason for amendment is required.' : '',
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
    form.businessPartner.trim() &&
    form.category &&
    form.itemCode.trim() && /^ITM-\d{4}$/.test(form.itemCode.trim()) &&
    form.description.trim() &&
    form.serialNo.trim() && /^SN-\d{4}-\d{4}$/.test(form.serialNo.trim()) &&
    form.sbuNumber.trim() && /^SBU-\d{3}$/.test(form.sbuNumber.trim()) &&
    form.region &&
    form.startDate &&
    form.endDate && form.endDate > form.startDate &&
    form.reason.trim()
  )
}

const isUploadingOrScanFailed = computed(() => {
  return contractDocs.value.some(d => d.uploadStatus === 'uploading' || d.uploadStatus === 'scanning' || d.uploadStatus === 'error')
})

function handleSubmit() {
  touchAll()
  if (!isValid()) return
  showConfirm.value = true
}

async function confirmSubmit() {
  loading.value = true
  showConfirm.value = false
  try {
    const payload = {
      businessPartner: form.businessPartner,
      category:        form.category,
      itemCode:        form.itemCode,
      description:     form.description,
      serialNo:        form.serialNo,
      sbuNumber:       form.sbuNumber,
      region:          form.region as ContractRegion,
      startDate:       form.startDate,
      endDate:         form.endDate,
      docs:            [...contractDocs.value]
    }

    await amendmentStore.createAmendment(contract.value, payload, form.reason)

    success('Amendment Submitted', 'Contract amendment request has been sent for review.')
    // Redirect back to the contract detail page
    router.push(`${rolePrefix.value}/contracts/${id}`)
  } catch (err) {
    error('Submission failed', 'Could not create amendment request.')
  } finally {
    loading.value = false
  }
}

function handlePreviewDoc(doc: UploadedDoc) {
  if (!doc.id) return
  const role = route.path.startsWith('/admin') ? 'admin' : route.path.startsWith('/manager') ? 'manager' : 'sales'
  router.push(`/${role}/contracts/${id}/documents/${doc.id}?from=amend&docName=${encodeURIComponent(doc.name)}&docSize=${doc.size}&docType=${doc.type}&role=${role}`)
}

onMounted(async () => {
  let c = (cacheState.contracts || []).find(item => item.id === id)
  if (!c) {
    loadingContract.value = true
    try {
      // Managers and Admins can see all contracts; Sales can only see their own
      const isPrivileged = route.path.startsWith('/manager') || route.path.startsWith('/admin')
      const userId = isPrivileged ? undefined : authState.user?.id
      await fetchContracts(userId)
      c = (cacheState.contracts || []).find(item => item.id === id)
    } catch (e) {
      console.error(e)
    } finally {
      loadingContract.value = false
    }
  } else {
    loadingContract.value = false
  }

  if (c) {
    if (c.approvalStatus !== 'Approved') {
      error('Error', 'Cannot create amendment for an unapproved contract.')
      router.push(`${rolePrefix.value}/contracts/${id}`)
      return
    }
    contract.value = c
    Object.assign(form, {
      businessPartner: c.businessPartner,
      category:        c.category,
      itemCode:        c.itemCode,
      description:     c.description,
      serialNo:        c.serialNo,
      sbuNumber:       c.sbuNumber || '',
      region:          c.region,
      startDate:       c.startDate,
      endDate:         c.endDate,
      reason:          '',
    })
    contractDocs.value = [...(c.docs || [])]
  } else {
    error('Error', 'Contract details could not be loaded.')
    router.push(`${rolePrefix.value}/contracts`)
  }
})
</script>

<template>
  <div class="p-8 space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button @click="router.push(`${rolePrefix}/contracts/${id}`)"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1">
        <h1 class="text-xl font-semibold text-black">Create Amendment</h1>
        <p class="text-sm text-black/40 mt-0.5">Propose changes to contract and submit for approval.</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loadingContract" class="flex flex-col items-center justify-center py-20 gap-2">
      <Loader2 class="w-8 h-8 animate-spin text-brand-navy" />
      <span class="text-sm text-black/40 font-medium">Loading contract details...</span>
    </div>

    <!-- Form card -->
    <div v-else-if="contract" class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden font-poppins">
      
      <!-- Section: Amendment Reason -->
      <div class="px-6 py-5 border-b border-black/6 bg-brand-blue/5">
        <h2 class="text-xs font-bold text-brand-navy uppercase tracking-widest mb-3">Amendment Reason</h2>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/55">Why are you amending this contract? <span class="text-red-500">*</span></label>
          <textarea
            v-model="form.reason"
            @blur="touched.reason = true"
            rows="3"
            placeholder="Provide a detailed reason for this amendment (e.g. Extending end date by 1 year to cover phase 2 support)..."
            class="w-full rounded-lg border px-3 py-2 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
            :class="errors.reason
              ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
              : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
          />
          <p v-if="errors.reason" class="text-xs text-red-500 font-medium">{{ errors.reason }}</p>
        </div>
      </div>

      <!-- Section: Contract Info -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Contract Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Business Partner -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Business Partner <span class="text-red-500">*</span></label>
            <input
              v-model="form.businessPartner"
              @blur="touched.businessPartner = true"
              type="text"
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition w-full"
              :class="errors.businessPartner
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
            <p v-if="errors.businessPartner" class="text-xs text-red-500">{{ errors.businessPartner }}</p>
          </div>

          <!-- Category -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Category <span class="text-red-500">*</span></label>
            <select
              v-model="form.category"
              @blur="touched.category = true"
              class="h-9 rounded-lg border px-3 text-sm bg-white focus:outline-none focus:ring-2 transition"
              :class="[
                !form.category ? 'text-black/30' : 'text-black',
                errors.category
                  ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                  : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'
              ]">
              <option value="" disabled>Select category</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
            <p v-if="errors.category" class="text-xs text-red-500">{{ errors.category }}</p>
          </div>

          <!-- SBU Number -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">SBU Number <span class="text-red-500">*</span></label>
            <input
              v-model="form.sbuNumber"
              @blur="touched.sbuNumber = true"
              type="text"
              placeholder="e.g. SBU-001"
              class="h-9 rounded-lg border px-3 text-sm font-mono focus:outline-none focus:ring-2 transition"
              :class="errors.sbuNumber
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
            <p v-if="errors.sbuNumber" class="text-xs text-red-500">{{ errors.sbuNumber }}</p>
          </div>
        </div>
      </div>

      <!-- Section: Item Details -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Item Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Item Code -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Item Code <span class="text-red-500">*</span></label>
            <input
              v-model="form.itemCode"
              @blur="touched.itemCode = true"
              type="text"
              placeholder="e.g. ITM-0041"
              class="h-9 rounded-lg border px-3 text-sm font-mono focus:outline-none focus:ring-2 transition"
              :class="errors.itemCode
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
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
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition"
              :class="errors.description
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
            <p v-if="errors.description" class="text-xs text-red-500">{{ errors.description }}</p>
          </div>

          <!-- Serial No -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Serial No <span class="text-red-500">*</span></label>
            <input
              v-model="form.serialNo"
              @blur="touched.serialNo = true"
              type="text"
              placeholder="e.g. SN-2024-0041"
              class="h-9 rounded-lg border px-3 text-sm font-mono focus:outline-none focus:ring-2 transition"
              :class="errors.serialNo
                ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
            />
            <p v-if="errors.serialNo" class="text-xs text-red-500">{{ errors.serialNo }}</p>
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
            <select
              v-model="form.region"
              @blur="touched.region = true"
              class="h-9 rounded-lg border px-3 text-sm bg-white focus:outline-none focus:ring-2 transition"
              :class="[
                !form.region ? 'text-black/30' : 'text-black',
                errors.region
                  ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
                  : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'
              ]">
              <option value="" disabled>Select region</option>
              <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
            </select>
            <p v-if="errors.region" class="text-xs text-red-500">{{ errors.region }}</p>
          </div>

          <!-- Start Date -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Start Date <span class="text-red-500">*</span></label>
            <input
              v-model="form.startDate"
              @blur="touched.startDate = true"
              type="date"
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
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-1">New Documents (Optional)</h2>
        <p class="text-xs text-black/35 mb-4">Upload replacement or supporting documents. Accepted formats: PDF, DOCX · Max 10 MB per file.</p>
        <DocumentUpload v-model="contractDocs" :onPreview="handlePreviewDoc" :hide-success-badge="true" />
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 flex items-center justify-end gap-3 bg-black/[0.005]">
        <Button variant="outline" @click="router.push(`/sales/contracts/${id}`)"
          class="h-9 px-5 text-sm border-black/15 text-black/60 hover:text-black">
          Cancel
        </Button>
        <Button @click="handleSubmit" :disabled="loading || isUploadingOrScanFailed"
          class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white font-medium shadow-sm">
          Submit Amendment Request
        </Button>
      </div>

    </div>
  </div>

  <ConfirmationDialog
    v-model:open="showConfirm"
    title="Submit Amendment"
    description="Are you sure you want to submit this amendment request? It will be sent to the manager for approval."
    confirm-label="Submit"
    variant="default"
    :loading="loading"
    @confirm="confirmSubmit"
  />
</template>
