<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Building2, Check, Loader2, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useVendorService } from '@/composables/useVendorService'
import { useToast } from '@/composables/useToast'
import type { SuggestedVendor } from '@/types/partner'

const { fetchSuggestedVendors, approveSuggestedVendor, rejectSuggestedVendor } = useVendorService()
const { success, error } = useToast()

const loading = ref(true)
const actingId = ref<number | null>(null)
const suggestions = ref<SuggestedVendor[]>([])

async function load() {
  loading.value = true
  try {
    suggestions.value = await fetchSuggestedVendors('Pending')
  } catch {
    suggestions.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function handleApprove(item: SuggestedVendor) {
  actingId.value = item.suggestedVendorId
  try {
    await approveSuggestedVendor(item.suggestedVendorId, true)
    suggestions.value = suggestions.value.filter(s => s.suggestedVendorId !== item.suggestedVendorId)
    success('Supplier onboarded', `${item.vendorName} has been added as an accredited supplier.`)
  } catch {
    error('Action failed', 'Could not approve suggested vendor.')
  } finally {
    actingId.value = null
  }
}

async function handleReject(item: SuggestedVendor) {
  actingId.value = item.suggestedVendorId
  try {
    await rejectSuggestedVendor(item.suggestedVendorId)
    suggestions.value = suggestions.value.filter(s => s.suggestedVendorId !== item.suggestedVendorId)
    success('Suggestion rejected', `${item.vendorName} was dismissed.`)
  } catch {
    error('Action failed', 'Could not reject suggested vendor.')
  } finally {
    actingId.value = null
  }
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-xs p-6 space-y-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <Building2 class="w-4 h-4 text-brand-navy" />
        <div>
          <h2 class="text-sm font-semibold text-black">SERMS Suggested Vendors</h2>
          <p class="text-xs text-black/50">Merchants verified via SERMS receipt OCR and submitted for CMS supplier onboarding.</p>
        </div>
      </div>
      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-blue/10 text-brand-navy border border-brand-blue/20">
        {{ suggestions.length }} Pending
      </span>
    </div>

    <div v-if="loading" class="space-y-3 animate-pulse">
      <div v-for="i in 2" :key="i" class="h-16 bg-black/5 rounded-lg"></div>
    </div>

    <div v-else-if="suggestions.length === 0" class="py-6 text-center text-xs text-black/45">
      No pending vendor onboarding suggestions from SERMS.
    </div>

    <div v-else class="divide-y divide-black/6">
      <div
        v-for="item in suggestions"
        :key="item.suggestedVendorId"
        class="py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
      >
        <div class="space-y-0.5">
          <div class="flex items-center gap-2">
            <p class="text-sm font-semibold text-black">{{ item.vendorName }}</p>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-black/5 text-black/65">
              {{ item.sourceSystem }}
            </span>
          </div>
          <p class="text-xs text-black/55">
            TIN: {{ item.tinNumber || 'Not provided' }}
            <span v-if="item.defaultExpenseCategory"> · Category: {{ item.defaultExpenseCategory }}</span>
            <span v-if="item.sourceReceiptId"> · Receipt #{{ item.sourceReceiptId }}</span>
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <Button
            size="sm"
            variant="outline"
            :disabled="actingId === item.suggestedVendorId"
            @click="handleReject(item)"
            class="h-8 px-3 text-xs border-black/15 text-black/70 hover:text-red-600"
          >
            <X class="w-3.5 h-3.5 mr-1" />
            Reject
          </Button>
          <Button
            size="sm"
            :disabled="actingId === item.suggestedVendorId"
            @click="handleApprove(item)"
            class="h-8 px-3 text-xs bg-brand-navy hover:bg-brand-dark text-white"
          >
            <Loader2 v-if="actingId === item.suggestedVendorId" class="w-3.5 h-3.5 mr-1 animate-spin" />
            <Check v-else class="w-3.5 h-3.5 mr-1" />
            Onboard Supplier
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
