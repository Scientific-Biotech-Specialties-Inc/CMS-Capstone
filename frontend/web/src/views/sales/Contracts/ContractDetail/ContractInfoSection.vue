<script setup lang="ts">
import { BRAND_PALETTE } from '@/constants/theme'
import { fmtDate } from '@/types/contract'
import type { ContractRegion, ContractWorkflowStatus } from '@/types/contract'
import type { StoredContract } from '@/composables/useContractStore'
import { CalendarCheck, CalendarX } from 'lucide-vue-next'
import { ref, computed } from 'vue'
import FormSelect from '@/components/shared/FormSelect.vue'
import { initialBusinessPartners, initialSuppliersData } from '@/views/admin/Partners/mockPartners'
import { onClickOutside } from '@vueuse/core'

const props = defineProps<{
  contract:   StoredContract
  isEditing:  boolean
  isManager:  boolean
  isOwner:    boolean
  isApproved: boolean
  editForm:   {
    businessPartner: string
    category:        string
    itemCode:        string
    description:     string
    serialNo:        string
    sbuNumber:       string
    region:          ContractRegion | ''
    startDate:       string
    endDate:         string
    workflowStatus:  ContractWorkflowStatus | ''
  }
  touched:   Record<string, boolean>
  dateError: string
}>()

const palette = BRAND_PALETTE

function initials(name: string) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

function avatarColor(name: string) {
  let h = 0
  for (const c of name) h = (h * 31 + c.charCodeAt(0)) & 0xffff
  return palette[h % palette.length]
}

function fieldCls(field: string, invalid: boolean) {
  return props.touched[field] && invalid
    ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
    : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'
}

const categories = [
  'Service Agreement',
  'Partnership Agreement',
  'Supply Contract',
  'Equipment Lease',
  'Equipment Maintenance',
]

const showSuggestions = ref(false)
const suggestionsContainer = ref<HTMLElement | null>(null)

const partnerSuggestions = computed(() => {
  const allNames = [
    ...initialBusinessPartners.map(p => p.name),
    ...initialSuppliersData.map(s => s.name)
  ]
  const uniqueNames = Array.from(new Set(allNames))
  const query = props.editForm.businessPartner.trim().toLowerCase()
  if (!query) return uniqueNames
  return uniqueNames.filter(name => name.toLowerCase().includes(query))
})

function selectSuggestion(name: string) {
  props.editForm.businessPartner = name
  showSuggestions.value = false
  props.touched.businessPartner = true
}

onClickOutside(suggestionsContainer, () => {
  showSuggestions.value = false
})
</script>

<template>
  <div class="space-y-6">

    <!-- Section 1: Contract Info -->
    <div class="bg-white border border-black/[0.08] rounded-xl p-8 shadow-sm">
      <h3 class="text-[10px] font-bold text-brand-navy/60 uppercase tracking-widest border-b border-black/[0.05] pb-3 mb-6">Contract Info</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Business Partner -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Business Partner <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <p v-if="!isEditing" class="text-[15px] font-medium text-black mt-0.5">{{ contract.businessPartner }}</p>
          <template v-else>
            <div class="relative w-full" ref="suggestionsContainer">
              <input
                v-model="editForm.businessPartner"
                @focus="showSuggestions = true"
                @blur="touched.businessPartner = true"
                type="text"
                placeholder="e.g. Globe Telecom"
                class="h-9 rounded-lg border px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition w-full mt-1"
                :class="fieldCls('businessPartner', !editForm.businessPartner)"
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
            <p v-if="touched.businessPartner && !editForm.businessPartner" class="text-xs text-red-500">Business partner is required.</p>
          </template>
        </div>

        <!-- Category -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Category <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <p v-if="!isEditing" class="text-[15px] font-medium text-black mt-0.5">{{ contract.category }}</p>
          <template v-else>
            <FormSelect
              v-model="editForm.category"
              :options="categories"
              placeholder="Select category"
              :error="!!(touched.category && !editForm.category)"
              class="mt-1"
              @blur="touched.category = true"
            />
            <p v-if="touched.category && !editForm.category" class="text-xs text-red-500">Category is required.</p>
          </template>
        </div>

        <!-- Created By -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">Created By</label>
          <div class="flex items-center gap-2 mt-1.5">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[9px] font-bold shrink-0 select-none"
              :style="{ backgroundColor: avatarColor(contract.createdBy) }">
              {{ initials(contract.createdBy) }}
            </div>
            <span class="text-[15px] text-black">{{ contract.createdBy }}</span>
          </div>
        </div>

        <!-- Product Activity (Optional) -->
        <div v-if="contract.prsActivityId && !isEditing" class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">Linked PRS Activity</label>
          <div class="flex items-center gap-2 mt-1.5">
            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 text-xs font-medium">
              #{{ contract.prsActivityId }}
            </span>
          </div>
        </div>

        <!-- Workflow Status (manager or contract owner) - Only shows in Edit Mode and if Approved -->
        <div v-if="!contract.executionRequired && isEditing && (isManager || isOwner) && isApproved" class="flex flex-col gap-1.5 mt-2 md:col-span-3">
          <label class="text-xs font-semibold text-black/50">Workflow Status</label>
          <select
            v-model="editForm.workflowStatus"
            class="h-9 w-full md:w-1/3 rounded-lg border px-3 text-sm bg-white focus:outline-none focus:ring-2 transition border-black/12 focus:border-brand-blue focus:ring-brand-blue/15 mt-1"
            :class="!editForm.workflowStatus ? 'text-black/30' : 'text-black'">
            <option value="">No status</option>
            <option value="SBSI Review">SBSI Review</option>
            <option value="Client Review">Client Review</option>
            <option value="Notarized PDF">Notarized PDF</option>
          </select>
        </div>

      </div>
    </div>

    <!-- Section 2: Item Details -->
    <div class="bg-white border border-black/[0.08] rounded-xl overflow-hidden shadow-sm">
      <div class="p-8 pb-4">
        <h3 class="text-[10px] font-bold text-brand-navy/60 uppercase tracking-widest mb-2">Item Details</h3>
      </div>
      
      <!-- View mode table -->
      <div v-if="!isEditing" class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-black/[0.018] border-y border-black/[0.04]">
              <th class="px-8 py-4 text-[11px] font-bold text-black/50 uppercase tracking-wide">Item Code</th>
              <th class="px-8 py-4 text-[11px] font-bold text-black/50 uppercase tracking-wide">Description</th>
              <th class="px-8 py-4 text-[11px] font-bold text-black/50 uppercase tracking-wide">Serial No</th>
              <th class="px-8 py-4 text-[11px] font-bold text-black/50 uppercase tracking-wide">SBU Number</th>
            </tr>
          </thead>
          <tbody>
            <tr class="hover:bg-black/[0.02] transition-colors">
              <td class="px-8 py-6 text-[15px] text-black font-semibold">{{ contract.itemCode }}</td>
              <td class="px-8 py-6 text-[15px] text-black">{{ contract.description }}</td>
              <td class="px-8 py-6 text-[15px] text-black">{{ contract.serialNo }}</td>
              <td class="px-8 py-6 text-[15px] text-black">{{ contract.sbuNumber || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Edit mode grid -->
      <div v-else class="p-8 pt-0 grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Item Code -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Item Code <span class="text-red-500">*</span>
          </label>
          <input
            v-model="editForm.itemCode"
            @blur="touched.itemCode = true"
            type="text"
            placeholder="e.g. ITM-0041"
            class="h-9 rounded-lg border px-3 text-sm font-mono placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('itemCode', !editForm.itemCode)"
          />
          <p v-if="touched.itemCode && !editForm.itemCode" class="text-xs text-red-500">Item code is required.</p>
        </div>

        <!-- Description -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Description <span class="text-red-500">*</span>
          </label>
          <input
            v-model="editForm.description"
            @blur="touched.description = true"
            type="text"
            placeholder="e.g. Network Infrastructure"
            class="h-9 rounded-lg border px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('description', !editForm.description)"
          />
          <p v-if="touched.description && !editForm.description" class="text-xs text-red-500">Description is required.</p>
        </div>

        <!-- Serial No -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Serial No <span class="text-red-500">*</span>
          </label>
          <input
            v-model="editForm.serialNo"
            @blur="touched.serialNo = true"
            type="text"
            placeholder="e.g. SN-2024-0041"
            class="h-9 rounded-lg border px-3 text-sm font-mono placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('serialNo', !editForm.serialNo)"
          />
          <p v-if="touched.serialNo && !editForm.serialNo" class="text-xs text-red-500">Serial number is required.</p>
        </div>

        <!-- SBU Number -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            SBU Number <span class="text-red-500">*</span>
          </label>
          <input
            v-model="editForm.sbuNumber"
            @blur="touched.sbuNumber = true"
            type="text"
            placeholder="e.g. SBU-001"
            class="h-9 rounded-lg border px-3 text-sm font-mono placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('sbuNumber', !editForm.sbuNumber)"
          />
          <p v-if="touched.sbuNumber && !editForm.sbuNumber" class="text-xs text-red-500">SBU number is required.</p>
        </div>
      </div>
    </div>

    <!-- Section 3: Schedule & Location -->
    <div class="bg-white border border-black/[0.08] rounded-xl p-8 shadow-sm">
      <h3 class="text-[10px] font-bold text-brand-navy/60 uppercase tracking-widest border-b border-black/[0.05] pb-3 mb-6">Schedule & Location</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Region -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Region <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <p v-if="!isEditing" class="text-[15px] font-medium text-black mt-0.5">{{ contract.region }}</p>
          <template v-else>
            <FormSelect
              v-model="editForm.region"
              :options="['Luzon', 'Visayas', 'Mindanao']"
              placeholder="Select region"
              :error="!!(touched.region && !editForm.region)"
              class="mt-1"
              @blur="touched.region = true"
            />
            <p v-if="touched.region && !editForm.region" class="text-xs text-red-500">Region is required.</p>
          </template>
        </div>

        <!-- Start Date -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Start Date <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <div v-if="!isEditing" class="flex items-center gap-2 mt-0.5">
            <CalendarCheck class="w-[18px] h-[18px] text-black/40" />
            <p class="text-[15px] font-medium text-black tabular-nums">{{ fmtDate(contract.startDate) }}</p>
          </div>
          <template v-else>
            <input
              v-model="editForm.startDate"
              @blur="touched.startDate = true"
              type="date"
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition mt-1"
              :class="fieldCls('startDate', !editForm.startDate)"
            />
            <p v-if="touched.startDate && !editForm.startDate" class="text-xs text-red-500">Start date is required.</p>
          </template>
        </div>

        <!-- End Date -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            End Date <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <div v-if="!isEditing" class="flex items-center gap-2 mt-0.5">
            <CalendarX class="w-[18px] h-[18px] text-red-500/80" />
            <p class="text-[15px] font-medium text-black tabular-nums">{{ fmtDate(contract.endDate) }}</p>
          </div>
          <template v-else>
            <input
              v-model="editForm.endDate"
              @blur="touched.endDate = true"
              type="date"
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition mt-1"
              :class="fieldCls('endDate', !editForm.endDate || !!dateError)"
            />
            <p v-if="touched.endDate && !editForm.endDate" class="text-xs text-red-500">End date is required.</p>
            <p v-else-if="dateError" class="text-xs text-red-500">{{ dateError }}</p>
          </template>
        </div>

      </div>
    </div>

  </div>
</template>
