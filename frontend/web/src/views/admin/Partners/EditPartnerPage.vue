<script setup lang="ts">
import { reactive, computed, onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowLeft, Pencil } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useVendorService } from '@/composables/useVendorService'
import { setBreadcrumbTitle } from '@/composables/useBreadcrumbs'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import type { AddPartnerForm, TabKey } from '@/types/partner'

const router = useRouter()
const route = useRoute()
const { success, error, warning } = useToast()
const { updatePartner, updateSupplier, fetchPartnerById, fetchSupplierById } = useVendorService()

const rawCode = route.params.code as string
const code = rawCode ? rawCode.replace(/^(BP-)+/i, 'BP-').replace(/^(SP-)+/i, 'SP-') : ''
if (rawCode && rawCode !== code) {
  const currentPath = route.path.replace(`/${rawCode}`, `/${code}`)
  router.replace(currentPath)
}
const type = code.startsWith('SP') ? 'sp' : 'bp'
const activeTab = computed<TabKey>(() => type === 'bp' ? 'partners' : 'suppliers')

const basePath = computed(() => {
  if (route.path.startsWith('/manager')) return '/manager/partners'
  if (route.path.startsWith('/sales')) return '/sales/partners'
  return '/admin/partners'
})

const loading = ref(true)
const isSaving = ref(false)
const showSaveConfirm = ref(false)

const form = reactive<AddPartnerForm>({
  name: '', industry: '', region: '', status: 'Active',
  contactPerson: '', email: '', phone: '', address: '', tinNumber: '',
})

const touched = reactive({
  name: false, industry: false, region: false,
  contactPerson: false, email: false, phone: false, address: false, tinNumber: false,
})

const originalBpCode = ref<string | null>(null)
const targetDbId = ref<number | null>(null)

const emailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()))
const phoneValid = computed(() => !form.phone || /^09\d{9}$/.test(form.phone))
const tinValid = computed(() => /^\d{3}-\d{3}-\d{3}(-\d{3,5})?$/.test(form.tinNumber.trim()))

onMounted(async () => {
  loading.value = true
  try {
    const partner = type === 'bp'
      ? await fetchPartnerById(code)
      : await fetchSupplierById(parseInt(code.replace(/\D/g, '')) || 0)
    if (partner) {
      setBreadcrumbTitle(partner.name)
      targetDbId.value = partner.db_id ?? null
      originalBpCode.value = partner.bpCode ?? null

      form.name = partner.name
      form.industry = partner.industry
      form.region = partner.region ?? ''
      form.status = partner.status
      form.contactPerson = partner.contactPerson
      form.email = partner.email
      form.phone = partner.phone
      form.address = partner.address
      form.tinNumber = partner.tinNumber ?? ''
    } else {
      error('Not Found', 'The requested record could not be found.')
      router.push(basePath.value)
    }
  } catch (err: any) {
    error('Error', 'Failed to load details.')
    router.push(basePath.value)
  } finally {
    loading.value = false
  }
})

function onNameInput(field: 'contactPerson', e: Event) {
  const el = e.target as HTMLInputElement
  const clean = el.value.replace(/[^a-zA-Z\s\-'.]/g, '')
  form[field] = clean
  el.value = clean
  touched[field] = true
}

function onPhoneInput(e: Event) {
  const el = e.target as HTMLInputElement
  const digits = el.value.replace(/\D/g, '').slice(0, 11)
  form.phone = digits
  el.value = digits
  touched.phone = true
}

function err(field: keyof typeof touched, extra = true) {
  return touched[field] && extra ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'
}

function handleSubmit() {
  Object.keys(touched).forEach(k => ((touched as Record<string, boolean>)[k] = true))
  
  if (!form.name || form.name.trim().length < 2 || !form.industry || !form.region || !form.contactPerson || !emailValid.value || !phoneValid.value || !form.address || (activeTab.value === 'suppliers' && !tinValid.value)) {
    return
  }

  if (!targetDbId.value) return
  showSaveConfirm.value = true
}

async function confirmSubmit() {
  isSaving.value = true
  try {
    if (activeTab.value === 'partners') {
      const { partner: updated, warnings } = await updatePartner(targetDbId.value!, { ...form }, originalBpCode.value)
      success('Partner updated', `${updated.name} has been updated successfully.`)
      if (warnings.length) warning('Duplicate warning', warnings[0].message)
    } else {
      const { partner: updated, warnings } = await updateSupplier(targetDbId.value!, { ...form })
      success('Supplier updated', `${updated.name} has been updated successfully.`)
      if (warnings.length) warning('Duplicate warning', warnings[0].message)
    }
    router.push(`${basePath.value}/${code}`)
  } catch (err: any) {
    const msg = err?.message ?? 'An error occurred. Please try again.'
    error('Save failed', msg)
  } finally {
    isSaving.value = false
    showSaveConfirm.value = false
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Loading skeleton -->
    <template v-if="loading">
      <div class="flex items-center gap-4">
        <div class="w-9 h-9 bg-black/5 animate-pulse rounded-lg shrink-0" />
        <div class="space-y-2 flex-1">
          <div class="h-6 w-48 bg-black/5 animate-pulse rounded" />
          <div class="h-4 w-64 bg-black/5 animate-pulse rounded" />
        </div>
      </div>
      <div class="bg-white rounded-lg border border-black/8 shadow-sm p-6 space-y-6">
        <div class="space-y-4">
          <div class="h-4 w-32 bg-black/5 animate-pulse rounded" />
          <div class="grid grid-cols-2 gap-4">
            <div class="h-14 bg-black/5 animate-pulse rounded-md" />
            <div class="h-14 bg-black/5 animate-pulse rounded-md" />
          </div>
        </div>
        <div class="space-y-4">
          <div class="h-4 w-32 bg-black/5 animate-pulse rounded" />
          <div class="grid grid-cols-2 gap-4">
            <div class="h-14 bg-black/5 animate-pulse rounded-md" />
            <div class="h-14 bg-black/5 animate-pulse rounded-md" />
          </div>
        </div>
      </div>
    </template>

    <template v-else>
      <!-- Header -->
      <div class="flex items-center gap-4">
        <button @click="router.push(`${basePath}/${code}`)"
          class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
          <ArrowLeft class="w-4 h-4" />
        </button>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
            <Pencil class="w-4.5 h-4.5 text-brand-navy" />
          </div>
          <div>
            <h1 class="text-xl font-semibold text-black">Edit {{ activeTab === 'partners' ? 'Business Partner' : 'Supplier' }}</h1>
            <p class="text-sm text-black/40 mt-0.5">Update the details below.</p>
          </div>
        </div>
      </div>

      <!-- Form card -->
      <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
        
        <!-- Section: Organization Info -->
        <div class="px-6 py-5 border-b border-black/6">
          <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Organization Info</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" @blur="touched.name = true" type="text" placeholder="Organization name" maxlength="100"
                class="w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
                :class="(touched.name && (!form.name || form.name.trim().length < 2)) ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'" />
              <p v-if="touched.name && !form.name" class="text-xs text-red-500">Name is required.</p>
              <p v-else-if="touched.name && form.name.trim().length < 2" class="text-xs text-red-500">Name must be at least 2 characters.</p>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Industry <span class="text-red-500">*</span></label>
              <input v-model="form.industry" @blur="touched.industry = true" type="text" placeholder="e.g. Healthcare" maxlength="100"
                class="w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
                :class="err('industry', !form.industry)" />
              <p v-if="touched.industry && !form.industry" class="text-xs text-red-500">Required.</p>
            </div>

          </div>
        </div>

        <!-- Section: Location & Status -->
        <div class="px-6 py-5 border-b border-black/6">
          <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Location & Status</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Region <span class="text-red-500">*</span></label>
              <select v-model="form.region" @blur="touched.region = true"
                class="h-9 rounded-md border px-3 text-sm bg-white focus:outline-none focus:ring-2 transition"
                :class="[!form.region ? 'text-black/30' : 'text-black', touched.region && !form.region ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15']">
                <option value="" disabled>Select region</option>
                <option value="Luzon">Luzon</option>
                <option value="Visayas">Visayas</option>
                <option value="Mindanao">Mindanao</option>
              </select>
              <p v-if="touched.region && !form.region" class="text-xs text-red-500">Required.</p>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Status <span class="text-red-500">*</span></label>
              <select v-model="form.status"
                class="h-9 rounded-md border border-black/12 bg-white px-3 text-sm text-black focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Suspended">Suspended</option>
              </select>
            </div>

          </div>
        </div>

        <!-- Section: Contact Details -->
        <div class="px-6 py-5 border-b border-black/6">
          <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Contact Details</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Contact Person <span class="text-red-500">*</span></label>
              <input :value="form.contactPerson" @input="onNameInput('contactPerson', $event)" @blur="touched.contactPerson = true" type="text" placeholder="Full name" maxlength="100"
                class="w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
                :class="err('contactPerson', !form.contactPerson)" />
              <p v-if="touched.contactPerson && !form.contactPerson" class="text-xs text-red-500">Required.</p>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Phone <span class="text-red-500">*</span></label>
              <input :value="form.phone" @input="onPhoneInput" @blur="touched.phone = true" type="text" inputmode="numeric" placeholder="e.g. 09123456789"
                class="w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
                :class="err('phone', !form.phone || !phoneValid)" />
              <p v-if="touched.phone && !form.phone" class="text-xs text-red-500">Required.</p>
              <p v-else-if="touched.phone && !phoneValid" class="text-xs text-red-500">Must be an 11-digit mobile number starting with 09.</p>
            </div>
          </div>

          <div class="flex flex-col gap-1.5 mb-4">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Email <span class="text-red-500">*</span></label>
            <input v-model="form.email" @blur="touched.email = true" type="email" placeholder="contact@company.com"
              class="w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
              :class="err('email', !emailValid)" />
            <p v-if="touched.email && !form.email" class="text-xs text-red-500">Email is required.</p>
            <p v-else-if="touched.email && !emailValid" class="text-xs text-red-500">Enter a valid email address.</p>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Address <span class="text-red-500">*</span></label>
            <input v-model="form.address" @blur="touched.address = true" type="text" placeholder="Street, City" maxlength="200"
              class="w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
              :class="err('address', !form.address)" />
            <p v-if="touched.address && !form.address" class="text-xs text-red-500">Required.</p>
          </div>

        </div>

        <!-- Section: Additional (Suppliers only) -->
        <div v-if="activeTab === 'suppliers'" class="px-6 py-5 border-b border-black/6">
          <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Additional Information</h2>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">TIN Number <span class="text-red-500">*</span></label>
            <input v-model="form.tinNumber" @blur="touched.tinNumber = true" type="text" placeholder="000-000-000-000" maxlength="100"
              class="w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
              :class="err('tinNumber', !tinValid)" />
            <p v-if="touched.tinNumber && !form.tinNumber" class="text-xs text-red-500">TIN Number is required.</p>
            <p v-else-if="touched.tinNumber && !tinValid" class="text-xs text-red-500">Enter a valid TIN (format: 000-000-000 or 000-000-000-0000).</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 flex items-center justify-end gap-3 bg-black/[0.015]">
          <Button type="button" variant="outline" class="h-9 px-4 text-sm border-black/15 text-black/60 hover:text-black"
            @click="router.push(`${basePath}/${code}`)">Cancel</Button>
          <Button @click="handleSubmit" :disabled="isSaving" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
            {{ isSaving ? 'Saving...' : 'Save Changes' }}
          </Button>
        </div>

      </div>
    </template>

    <ConfirmationDialog
      v-model:open="showSaveConfirm"
      title="Confirm Changes"
      :description="`Are you sure you want to save the changes for this ${activeTab === 'partners' ? 'business partner' : 'supplier'}?`"
      confirm-label="Save"
      variant="default"
      :loading="isSaving"
      @confirm="confirmSubmit"
    />
  </div>
</template>
