<script setup lang="ts">
import { reactive, computed, watch } from 'vue'
import { Building2, Truck } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Partner, TabKey, AddPartnerForm } from '@/types/partner'

const props = defineProps<{
  open:          boolean
  activeTab:     TabKey
  editTarget?:   Partner | null
  existingNames?: string[]
}>()
const emit = defineEmits<{
  'update:open': [v: boolean]
  submit:        [form: AddPartnerForm]
}>()

const isEdit = computed(() => !!props.editTarget)

const form = reactive<AddPartnerForm>({
  name: '', industry: '', region: '', status: 'Active',
  contactPerson: '', email: '', phone: '', address: '', tinNumber: '',
})
const touched = reactive({
  name: false, industry: false, region: false,
  contactPerson: false, email: false, phone: false, address: false,
})

const emailValid = computed(() =>
  !form.email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)
)
const phoneValid = computed(() =>
  !form.phone || /^\d{7,11}$/.test(form.phone)
)
const isDuplicate = computed(() => {
  if (!props.existingNames || !form.name.trim()) return false
  const lower    = form.name.trim().toLowerCase()
  const editName = (props.editTarget?.name ?? '').toLowerCase()
  return lower !== editName && props.existingNames.some(n => n.toLowerCase() === lower)
})

function onNameInput(field: 'contactPerson', e: Event) {
  const el = e.target as HTMLInputElement
  const clean = el.value.replace(/[^a-zA-Z\s\-'.]/g, '')
  form[field] = clean
  el.value = clean
  touched[field] = true
}

function onPhoneInput(e: Event) {
  const el     = e.target as HTMLInputElement
  const digits = el.value.replace(/\D/g, '').slice(0, 11)
  form.phone   = digits
  el.value     = digits
  touched.phone = true
}

function reset() {
  Object.assign(form, { name: '', industry: '', region: '', status: 'Active', contactPerson: '', email: '', phone: '', address: '', tinNumber: '' })
  Object.assign(touched, { name: false, industry: false, region: false, contactPerson: false, email: false, phone: false, address: false })
}

function populate(p: Partner) {
  form.name          = p.name
  form.industry      = p.industry
  form.region        = p.region ?? ''
  form.status        = p.status
  form.contactPerson = p.contactPerson
  form.email         = p.email
  form.phone         = p.phone
  form.address       = p.address
  form.tinNumber     = p.tinNumber ?? ''
}

watch(() => props.open, open => {
  if (open) {
    if (props.editTarget) populate(props.editTarget)
    else reset()
  } else {
    reset()
  }
})

function submit() {
  Object.keys(touched).forEach(k => ((touched as Record<string, boolean>)[k] = true))
  if (!form.name || form.name.trim().length < 2 || isDuplicate.value || !form.industry || !form.region || !form.contactPerson || !emailValid.value || !phoneValid.value || !form.address) return
  emit('submit', { ...form })
  emit('update:open', false)
}

function err(field: keyof typeof touched, extra = true) {
  return touched[field] && extra ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-lg p-0 overflow-y-auto max-h-[90vh] modal-scrollbar" @pointer-down-outside="$emit('update:open', false)">

      <!-- Header -->
      <div class="px-6 pt-6 pb-5 border-b border-black/6">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
            <component :is="activeTab === 'partners' ? Building2 : Truck" class="w-4.5 h-4.5 text-brand-navy" />
          </div>
          <DialogHeader>
            <DialogTitle class="text-base font-semibold text-black">
              {{ isEdit ? 'Edit' : 'Add' }} {{ activeTab === 'partners' ? 'Business Partner' : 'Supplier' }}
            </DialogTitle>
            <DialogDescription class="text-xs text-black/40 mt-0.5">
              {{ isEdit ? 'Update the details below.' : `Fill in the details below to add a new ${activeTab === 'partners' ? 'partner' : 'supplier'}.` }}
            </DialogDescription>
          </DialogHeader>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

        <!-- Name -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" @blur="touched.name = true" type="text" placeholder="Organisation name" maxlength="100"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition',
              (touched.name && (!form.name || form.name.trim().length < 2)) || isDuplicate
                ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15'
                : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15']" />
          <p v-if="touched.name && !form.name" class="text-xs text-red-500">Name is required.</p>
          <p v-else-if="touched.name && form.name.trim().length < 2" class="text-xs text-red-500">Name must be at least 2 characters.</p>
          <p v-else-if="isDuplicate" class="text-xs text-red-500">This name is already registered.</p>
        </div>

        <!-- Industry + Region -->
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Industry <span class="text-red-500">*</span></label>
            <input v-model="form.industry" @blur="touched.industry = true" type="text" placeholder="e.g. Healthcare" maxlength="100"
              :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', err('industry', !form.industry)]" />
            <p v-if="touched.industry && !form.industry" class="text-xs text-red-500">Required.</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Region <span class="text-red-500">*</span></label>
            <Select v-model="form.region" @update:model-value="touched.region = true">
              <SelectTrigger class="h-9 rounded-md text-sm"
                :class="touched.region && !form.region ? 'border-red-400' : 'border-black/12 focus:border-brand-blue'">
                <SelectValue placeholder="Select region" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Luzon">Luzon</SelectItem>
                <SelectItem value="Visayas">Visayas</SelectItem>
                <SelectItem value="Mindanao">Mindanao</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="touched.region && !form.region" class="text-xs text-red-500">Required.</p>
          </div>
        </div>

        <!-- Contact Person + Phone -->
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Contact Person <span class="text-red-500">*</span></label>
            <input :value="form.contactPerson" @input="onNameInput('contactPerson', $event)" @blur="touched.contactPerson = true"
              type="text" placeholder="Full name" maxlength="100"
              :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', err('contactPerson', !form.contactPerson)]" />
            <p v-if="touched.contactPerson && !form.contactPerson" class="text-xs text-red-500">Required.</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Phone <span class="text-red-500">*</span></label>
            <input :value="form.phone" @input="onPhoneInput" @blur="touched.phone = true"
              type="text" inputmode="numeric" placeholder="09xxxxxxxxx"
              :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', err('phone', !form.phone || !phoneValid)]" />
            <p v-if="touched.phone && !form.phone" class="text-xs text-red-500">Required.</p>
            <p v-else-if="touched.phone && !phoneValid" class="text-xs text-red-500">Phone number must be 7–11 digits.</p>
          </div>
        </div>

        <!-- Email -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Email</label>
          <input v-model="form.email" @blur="touched.email = true" type="email" placeholder="contact@company.com"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', err('email', !!form.email && !emailValid)]" />
          <p v-if="touched.email && form.email && !emailValid" class="text-xs text-red-500">Enter a valid email address.</p>
        </div>

        <!-- Address -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Address <span class="text-red-500">*</span></label>
          <input v-model="form.address" @blur="touched.address = true" type="text" placeholder="Street, City" maxlength="200"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', err('address', !form.address)]" />
          <p v-if="touched.address && !form.address" class="text-xs text-red-500">Required.</p>
        </div>

        <!-- TIN (suppliers only) -->
        <div v-if="activeTab === 'suppliers'" class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">TIN Number</label>
          <input v-model="form.tinNumber" type="text" placeholder="000-000-000-000" maxlength="100"
            class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
        </div>

        <!-- Status -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Status</label>
          <Select v-model="form.status">
            <SelectTrigger class="h-9 rounded-md border-black/12 text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="Active">Active</SelectItem>
              <SelectItem value="Inactive">Inactive</SelectItem>
              <SelectItem value="Suspended">Suspended</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Footer -->
        <div class="border-t border-black/6 pt-4">
          <DialogFooter>
            <Button type="button" variant="outline" class="h-9 px-4 text-sm border-black/15 text-black/60 hover:text-black"
              @click="$emit('update:open', false)">Cancel</Button>
            <Button type="submit" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white">
              {{ isEdit ? 'Save Changes' : `Add ${activeTab === 'partners' ? 'Partner' : 'Supplier'}` }}
            </Button>
          </DialogFooter>
        </div>

      </form>
    </DialogContent>
  </Dialog>
</template>
