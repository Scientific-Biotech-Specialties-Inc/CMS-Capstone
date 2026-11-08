<script setup lang="ts">
import { reactive, computed, onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowLeft, Building2, Truck } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useVendorService } from '@/composables/useVendorService'
import type { AddPartnerForm, TabKey } from '@/types/partner'

const router = useRouter()
const route = useRoute()
const { success, error, warning } = useToast()
const { createPartner, createSupplier, fetchPartners, fetchSuppliers } = useVendorService()

const activeTab = computed<TabKey>(() => route.query.type === 'suppliers' ? 'suppliers' : 'partners')
const basePath = computed(() => {
  if (route.path.startsWith('/manager')) return '/manager/partners'
  if (route.path.startsWith('/sales')) return '/sales/partners'
  return '/admin/partners'
})

const form = reactive<AddPartnerForm>({
  name: '', industry: '', region: '', status: 'Active',
  contactPerson: '', email: '', phone: '', address: '', tinNumber: '',
})

const touched = reactive({
  name: false, industry: false, region: false,
  contactPerson: false, email: false, phone: false, address: false, tinNumber: false,
})

const existingNames = ref<string[]>([])
const loadingNames = ref(true)

onMounted(async () => {
  try {
    const [partners, suppliers] = await Promise.all([
      fetchPartners(),
      fetchSuppliers()
    ])
    let names: string[] = []
    names = names.concat(partners.map(p => p.name))
    names = names.concat(suppliers.map(s => s.name))
    existingNames.value = Array.from(new Set(names))
  } catch (err) {
    console.error('Failed to fetch existing names:', err)
  } finally {
    loadingNames.value = false
  }
})

const emailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()))
const phoneValid = computed(() => !form.phone || /^09\d{9}$/.test(form.phone))
const tinValid = computed(() => /^\d{3}-\d{3}-\d{3}(-\d{3,5})?$/.test(form.tinNumber.trim()))

const isDuplicate = computed(() => {
  if (!existingNames.value || !form.name.trim()) return false
  const lower = form.name.trim().toLowerCase()
  return existingNames.value.some(n => n.toLowerCase() === lower)
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

const isSaving = ref(false)

async function handleSubmit() {
  Object.keys(touched).forEach(k => ((touched as Record<string, boolean>)[k] = true))
  
  if (!form.name || form.name.trim().length < 2 || isDuplicate.value || !form.industry || !form.region || !form.contactPerson || !emailValid.value || !phoneValid.value || !form.address || (activeTab.value === 'suppliers' && !tinValid.value)) {
    return
  }

  isSaving.value = true
  try {
    if (activeTab.value === 'partners') {
      const { partner: created, warnings } = await createPartner({ ...form })
      success('Partner added', `${created.name} has been added successfully.`)
      if (warnings.length) warning('Duplicate warning', warnings[0].message)
    } else {
      const { partner: created, warnings } = await createSupplier({ ...form })
      success('Supplier added', `${created.name} has been added successfully.`)
      if (warnings.length) warning('Duplicate warning', warnings[0].message)
    }
    router.push(basePath.value)
  } catch (err: any) {
    const msg = err?.message ?? 'An error occurred. Please try again.'
    error('Save failed', msg)
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-4">
      <button @click="router.push(basePath)"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
          <component :is="activeTab === 'partners' ? Building2 : Truck" class="w-4.5 h-4.5 text-brand-navy" />
        </div>
        <div>
          <h1 class="text-xl font-semibold text-black">Add {{ activeTab === 'partners' ? 'Business Partner' : 'Supplier' }}</h1>
          <p class="text-sm text-black/40 mt-0.5">Fill in the details below to add a new {{ activeTab === 'partners' ? 'partner' : 'supplier' }}.</p>
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
              :class="(touched.name && (!form.name || form.name.trim().length < 2)) || isDuplicate ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'" />
            <p v-if="touched.name && !form.name" class="text-xs text-red-500">Name is required.</p>
            <p v-else-if="touched.name && form.name.trim().length < 2" class="text-xs text-red-500">Name must be at least 2 characters.</p>
            <p v-else-if="isDuplicate" class="text-xs text-red-500">This name is already registered.</p>
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
          @click="router.push(basePath)">Cancel</Button>
        <Button @click="handleSubmit" :disabled="isSaving" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
          {{ isSaving ? 'Saving...' : `Add ${activeTab === 'partners' ? 'Partner' : 'Supplier'}` }}
        </Button>
      </div>

    </div>

  </div>
</template>
