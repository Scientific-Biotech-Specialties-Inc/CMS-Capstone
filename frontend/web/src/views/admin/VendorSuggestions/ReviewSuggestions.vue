<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, X, Building2, Truck } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useVendorSuggestions } from '@/composables/useVendorSuggestions'
import type { VendorSuggestionReviewForm } from '@/types/vendorSuggestion'

// Feature 3, Page 2: tab bar, one tab per selected candidate. Each tab owns
// its own editable form state (pre-filled from the candidate's AI-suggested
// data — a starting point, not verified data, so every field is editable
// before saving). Saving a tab creates the real vendor and removes that tab;
// once all tabs are saved, redirects back to /admin/partners.
const route = useRoute()
const router = useRouter()
const { success, error } = useToast()
const { role } = useAuth()
const { batch, fetchBatch, decideCandidate } = useVendorSuggestions()

function roleBase(): string {
  if (role.value === 'Manager') return '/manager'
  if (['Sales', 'Employee', 'Finance'].includes(role.value ?? '')) return '/sales'
  return '/admin'
}

const batchId = Number(route.query.batchId)
const candidateIds = String(route.query.candidateIds ?? '').split(',').filter(Boolean).map(Number)

const forms = reactive<Record<number, VendorSuggestionReviewForm>>({})
const openTabIds = ref<number[]>([...candidateIds])
const activeTabId = ref<number | null>(candidateIds[0] ?? null)
const savingTabId = ref<number | null>(null)

onMounted(async () => {
  await fetchBatch(batchId)
  if (!batch.value) return

  for (const id of candidateIds) {
    const candidate = batch.value.candidates.find(c => c.id === id)
    if (!candidate) continue

    forms[id] = {
      candidateId:   id,
      vendorType:    'partner',
      name:          candidate.name,
      industry:      candidate.industry ?? '',
      region:        candidate.region ?? '',
      contactPerson: '',
      email:         candidate.contactEmail ?? '',
      phone:         candidate.contactNumber ?? '',
      address:       candidate.address ?? '',
      tinNumber:     '',
      bpCode:        '',
    }
  }
})

const activeForm = computed(() => activeTabId.value !== null ? forms[activeTabId.value] : null)

function closeTab(id: number) {
  openTabIds.value = openTabIds.value.filter(t => t !== id)
  delete forms[id]
  if (activeTabId.value === id) {
    activeTabId.value = openTabIds.value[0] ?? null
  }
  if (openTabIds.value.length === 0) {
    router.push(`${roleBase()}/partners`)
  }
}

const emailValid = computed(() => !activeForm.value?.email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(activeForm.value.email))
const isFormValid = computed(() => {
  const f = activeForm.value
  if (!f) return false
  if (!f.name.trim() || !f.industry || !f.region) return false
  if (f.vendorType === 'partner' && !f.email.trim()) return false
  if (f.vendorType === 'supplier' && !f.tinNumber.trim()) return false
  return emailValid.value
})

async function saveActiveTab() {
  const f = activeForm.value
  if (!f || !isFormValid.value) return

  savingTabId.value = f.candidateId
  try {
    const result = await decideCandidate(f.candidateId, 'accepted', f.vendorType, {
      name:           f.name,
      industry:       f.industry,
      region:         f.region,
      contact_person: f.contactPerson,
      email:          f.email,
      phone:          f.phone,
      address:        f.address,
      tin_number:     f.tinNumber,
      bp_code:        f.bpCode,
    })

    if (!result.ok) {
      error('Failed to save', result.message ?? 'Something went wrong.')
      return
    }

    success('Vendor added', `${f.name} has been saved as a ${f.vendorType === 'supplier' ? 'supplier' : 'business partner'}.`)
    closeTab(f.candidateId)
  } finally {
    savingTabId.value = null
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-center gap-4">
      <button @click="router.push(`${roleBase()}/vendor-suggestions`)"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div>
        <h1 class="text-xl font-semibold text-black">Review Suggested Vendors</h1>
        <p class="text-sm text-black/40 mt-0.5">Edit and confirm each candidate's details before saving.</p>
      </div>
    </div>

    <!-- Tab bar -->
    <div v-if="openTabIds.length > 0" class="flex items-center gap-1 border-b border-black/8 overflow-x-auto">
      <button
        v-for="id in openTabIds" :key="id"
        @click="activeTabId = id"
        class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors shrink-0"
        :class="activeTabId === id ? 'border-brand-navy text-brand-navy' : 'border-transparent text-black/40 hover:text-black/60'"
      >
        {{ forms[id]?.name || `Candidate ${id}` }}
        <X class="w-3.5 h-3.5 text-black/30 hover:text-black/60" @click.stop="closeTab(id)" />
      </button>
    </div>

    <div v-if="activeForm" class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

      <!-- Vendor type toggle -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Save As</h2>
        <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1 w-fit">
          <button @click="activeForm.vendorType = 'partner'"
            class="flex items-center gap-2 px-4 py-1.5 text-sm rounded transition-all font-medium"
            :class="activeForm.vendorType === 'partner' ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
            <Building2 class="w-3.5 h-3.5" /> Business Partner
          </button>
          <button @click="activeForm.vendorType = 'supplier'"
            class="flex items-center gap-2 px-4 py-1.5 text-sm rounded transition-all font-medium"
            :class="activeForm.vendorType === 'supplier' ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
            <Truck class="w-3.5 h-3.5" /> Supplier
          </button>
        </div>
      </div>

      <!-- Organization Info -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Organization Info</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Name <span class="text-red-500">*</span></label>
            <input v-model="activeForm.name" type="text" maxlength="255"
              class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Industry <span class="text-red-500">*</span></label>
            <input v-model="activeForm.industry" type="text" maxlength="150"
              class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
          </div>
        </div>
      </div>

      <!-- Location -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Location</h2>
        <div class="flex flex-col gap-1.5 max-w-xs">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Region <span class="text-red-500">*</span></label>
          <Select v-model="activeForm.region">
            <SelectTrigger class="h-9 rounded-md text-sm border-black/12"><SelectValue placeholder="Select region" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="Luzon">Luzon</SelectItem>
              <SelectItem value="Visayas">Visayas</SelectItem>
              <SelectItem value="Mindanao">Mindanao</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <!-- Contact Details -->
      <div class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Contact Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Contact Person</label>
            <input v-model="activeForm.contactPerson" type="text" maxlength="255"
              class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Phone</label>
            <input v-model="activeForm.phone" type="text" inputmode="numeric" maxlength="20"
              class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
          </div>
        </div>
        <div class="flex flex-col gap-1.5 mb-4">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">
            Email <span v-if="activeForm.vendorType === 'partner'" class="text-red-500">*</span>
          </label>
          <input v-model="activeForm.email" type="email"
            class="w-full h-9 rounded-md border bg-white px-3 text-sm focus:outline-none focus:ring-2 transition"
            :class="!emailValid ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'" />
          <p v-if="!emailValid" class="text-xs text-red-500">Enter a valid email address.</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Address</label>
          <input v-model="activeForm.address" type="text" maxlength="200"
            class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
        </div>
      </div>

      <!-- TIN (suppliers only) -->
      <div v-if="activeForm.vendorType === 'supplier'" class="px-6 py-5 border-b border-black/6">
        <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Additional Information</h2>
        <div class="flex flex-col gap-1.5 max-w-xs">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">TIN Number <span class="text-red-500">*</span></label>
          <input v-model="activeForm.tinNumber" type="text" placeholder="000-000-000-000" maxlength="100"
            class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 flex items-center justify-end gap-3 bg-black/[0.015]">
        <Button type="button" variant="outline" class="h-9 px-4 text-sm border-black/15 text-black/60 hover:text-black"
          @click="closeTab(activeForm.candidateId)">Discard</Button>
        <Button :disabled="!isFormValid || savingTabId === activeForm.candidateId" @click="saveActiveTab"
          class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white disabled:opacity-50">
          {{ savingTabId === activeForm.candidateId ? 'Saving…' : `Save ${activeForm.vendorType === 'supplier' ? 'Supplier' : 'Partner'}` }}
        </Button>
      </div>
    </div>

    <div v-else class="text-center py-16 text-sm text-black/35">
      No candidates left to review.
    </div>

  </div>
</template>
