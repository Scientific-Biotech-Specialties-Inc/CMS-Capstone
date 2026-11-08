<script setup lang="ts">
import { BRAND_PALETTE } from '@/constants/theme'
import { fmtReqDate } from '@/types/contractRequest'
import type { ContractRequest } from '@/types/contractRequest'
import { CalendarCheck, CalendarX } from 'lucide-vue-next'

const props = defineProps<{
  request:   ContractRequest
  isEditing: boolean
  editForm: {
    businessPartner: string
    category:        string
    itemCode:        string
    description:     string
    serialNo:        string
    sbuNumber:       string
    region:          string
    startDate:       string
    endDate:         string
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

import { ref, computed } from 'vue'
import { initialBusinessPartners, initialSuppliersData } from '@/views/admin/Partners/mockPartners'
import { onClickOutside } from '@vueuse/core'

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

    <!-- Section: Contract Info -->
    <div class="bg-white border border-black/[0.08] rounded-xl overflow-hidden shadow-sm">
      <div class="p-8 pb-4 border-b border-black/[0.04]">
        <h3 class="text-[10px] font-bold text-brand-navy/60 uppercase tracking-widest mb-2">Contract Info</h3>
      </div>
      <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8 items-start">

        <!-- Business Partner -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Business Partner <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <p v-if="!isEditing" class="text-[15px] font-medium text-black mt-0.5">{{ request.businessPartner }}</p>
          <template v-else>
            <div class="relative w-full" ref="suggestionsContainer">
              <input v-model="editForm.businessPartner"
                @focus="showSuggestions = true"
                @blur="touched.businessPartner = true"
                type="text" placeholder="e.g. Globe Telecom"
                class="h-9 rounded-lg border px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition w-full mt-1"
                :class="fieldCls('businessPartner', !editForm.businessPartner)" />
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
          <p v-if="!isEditing" class="text-[15px] font-medium text-black mt-0.5">{{ request.category }}</p>
          <template v-else>
            <select v-model="editForm.category" @blur="touched.category = true"
              class="h-9 rounded-lg border px-3 text-sm bg-white focus:outline-none focus:ring-2 transition mt-1"
              :class="[!editForm.category ? 'text-black/30' : 'text-black', fieldCls('category', !editForm.category)]">
              <option value="" disabled>Select category</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
            <p v-if="touched.category && !editForm.category" class="text-xs text-red-500">Category is required.</p>
          </template>
        </div>

        <!-- Created By -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">Created By</label>
          <div class="flex items-center gap-2 mt-1.5">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[9px] font-bold shrink-0 select-none"
              :style="{ backgroundColor: avatarColor(request.createdBy) }">
              {{ initials(request.createdBy) }}
            </div>
            <span class="text-[15px] text-black">{{ request.createdBy }}</span>
          </div>
        </div>

        <!-- Product Activity (Optional) -->
        <div v-if="request.prsActivityId && !isEditing" class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">Linked PRS Activity</label>
          <div class="flex items-center gap-2 mt-1.5">
            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 text-xs font-medium">
              #{{ request.prsActivityId }}
            </span>
          </div>
        </div>

      </div>
    </div>

    <!-- Section: Item Details -->
    <div class="bg-white border border-black/[0.08] rounded-xl overflow-hidden shadow-sm">
      <div class="p-8 pb-4 border-b border-black/[0.04]">
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
              <td class="px-8 py-6 text-[15px] text-black font-semibold">{{ request.itemCode }}</td>
              <td class="px-8 py-6 text-[15px] text-black">{{ request.description }}</td>
              <td class="px-8 py-6 text-[15px] text-black">{{ request.serialNo }}</td>
              <td class="px-8 py-6 text-[15px] text-black">{{ request.sbuNumber || '—' }}</td>
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
          <input v-model="editForm.itemCode" @blur="touched.itemCode = true"
            type="text" placeholder="e.g. ITM-0041"
            class="h-9 rounded-lg border px-3 text-sm font-mono placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('itemCode', !editForm.itemCode)" />
          <p v-if="touched.itemCode && !editForm.itemCode" class="text-xs text-red-500">Item code is required.</p>
        </div>

        <!-- Description -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Description <span class="text-red-500">*</span>
          </label>
          <input v-model="editForm.description" @blur="touched.description = true"
            type="text" placeholder="e.g. Network Infrastructure"
            class="h-9 rounded-lg border px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('description', !editForm.description)" />
          <p v-if="touched.description && !editForm.description" class="text-xs text-red-500">Description is required.</p>
        </div>

        <!-- Serial No -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Serial No <span class="text-red-500">*</span>
          </label>
          <input v-model="editForm.serialNo" @blur="touched.serialNo = true"
            type="text" placeholder="e.g. SN-2024-0041"
            class="h-9 rounded-lg border px-3 text-sm font-mono placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('serialNo', !editForm.serialNo)" />
          <p v-if="touched.serialNo && !editForm.serialNo" class="text-xs text-red-500">Serial number is required.</p>
        </div>

        <!-- SBU Number -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            SBU Number <span class="text-red-500">*</span>
          </label>
          <input v-model="editForm.sbuNumber" @blur="touched.sbuNumber = true"
            type="text" placeholder="e.g. SBU-001"
            class="h-9 rounded-lg border px-3 text-sm font-mono placeholder:text-black/25 focus:outline-none focus:ring-2 transition mt-1"
            :class="fieldCls('sbuNumber', !editForm.sbuNumber)" />
          <p v-if="touched.sbuNumber && !editForm.sbuNumber" class="text-xs text-red-500">SBU number is required.</p>
        </div>

      </div>
    </div>

    <!-- Section: Schedule & Location -->
    <div class="bg-white border border-black/[0.08] rounded-xl overflow-hidden shadow-sm">
      <div class="p-8 pb-4 border-b border-black/[0.04]">
        <h3 class="text-[10px] font-bold text-brand-navy/60 uppercase tracking-widest mb-2">Schedule & Location</h3>
      </div>
      <div class="p-8 grid grid-cols-1 md:grid-cols-3 gap-8 items-start">

        <!-- Region -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Region <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <p v-if="!isEditing" class="text-[15px] font-medium text-black mt-0.5">{{ request.region }}</p>
          <template v-else>
            <select v-model="editForm.region" @blur="touched.region = true"
              class="h-9 rounded-lg border px-3 text-sm bg-white focus:outline-none focus:ring-2 transition mt-1"
              :class="[!editForm.region ? 'text-black/30' : 'text-black', fieldCls('region', !editForm.region)]">
              <option value="" disabled>Select region</option>
              <option value="Luzon">Luzon</option>
              <option value="Visayas">Visayas</option>
              <option value="Mindanao">Mindanao</option>
            </select>
            <p v-if="touched.region && !editForm.region" class="text-xs text-red-500">Region is required.</p>
          </template>
        </div>

        <!-- Start Date -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            Start Date <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <div v-if="!isEditing" class="flex items-center gap-2 mt-0.5 text-[15px] text-black">
            <CalendarCheck class="w-4 h-4 text-brand-blue/70" />
            <span>{{ fmtReqDate(request.startDate) }}</span>
          </div>
          <template v-else>
            <input v-model="editForm.startDate" @blur="touched.startDate = true"
              type="date"
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition mt-1"
              :class="fieldCls('startDate', !editForm.startDate)" />
            <p v-if="touched.startDate && !editForm.startDate" class="text-xs text-red-500">Start date is required.</p>
          </template>
        </div>

        <!-- End Date -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/50">
            End Date <span v-if="isEditing" class="text-red-500">*</span>
          </label>
          <div v-if="!isEditing" class="flex items-center gap-2 mt-0.5 text-[15px] text-black">
            <CalendarX class="w-4 h-4 text-red-400/70" />
            <span>{{ fmtReqDate(request.endDate) }}</span>
          </div>
          <template v-else>
            <input v-model="editForm.endDate" @blur="touched.endDate = true"
              type="date"
              class="h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition mt-1"
              :class="fieldCls('endDate', !editForm.endDate || !!dateError)" />
            <p v-if="touched.endDate && !editForm.endDate" class="text-xs text-red-500">End date is required.</p>
            <p v-else-if="dateError" class="text-xs text-red-500">{{ dateError }}</p>
          </template>
        </div>

      </div>
      
      <!-- Footer: Created by -->
      <div class="px-8 py-4 bg-black/[0.015] border-t border-black/[0.04] flex items-center gap-3">
        <span class="text-[11px] font-bold text-brand-navy/50 uppercase tracking-wide">Created by</span>
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[9px] font-bold shrink-0 select-none shadow-sm"
            :style="{ backgroundColor: avatarColor(request.createdBy) }">
            {{ initials(request.createdBy) }}
          </div>
          <span class="text-[13px] font-semibold text-brand-navy">{{ request.createdBy }}</span>
        </div>
      </div>
    </div>

  </div>
</template>
