<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft, Sparkles, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useVendorSuggestions } from '@/composables/useVendorSuggestions'
import VendorSuggestionCard from './VendorSuggestionCard.vue'

// Feature 3, Page 1: request a suggestion batch (industry/region hint),
// poll until the async Gemini pipeline completes, then let the Admin
// multi-select candidates before proceeding to the review/tabs page.
const router = useRouter()
const { error } = useToast()
const { role } = useAuth()
const { batch, loading, requesting, requestSuggestions, fetchBatch, fetchLatestBatch } = useVendorSuggestions()

function roleBase(): string {
  if (role.value === 'Manager') return '/manager'
  if (['Sales', 'Employee', 'Finance'].includes(role.value ?? '')) return '/sales'
  return '/admin'
}

function goBack() {
  router.push(`${roleBase()}/partners`)
}

const industryHint = ref('')
const regionHint = ref('')
const selectedIds = ref<number[]>([])

let pollTimer: ReturnType<typeof setInterval> | null = null

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (!batch.value) return
    if (batch.value.status === 'pending') {
      await fetchBatch(batch.value.id)
    } else {
      stopPolling()
    }
  }, 2500)
}

onMounted(async () => {
  await fetchLatestBatch()
  if (batch.value?.status === 'pending') {
    startPolling()
  } else if (batch.value?.status === 'failed') {
    // Hide historical failures on initial load for a clean slate
    batch.value = null
  }
})

onUnmounted(stopPolling)

async function handleRequest() {
  selectedIds.value = []
  const ok = await requestSuggestions(industryHint.value.trim(), regionHint.value)
  if (!ok) {
    error('Request failed', 'Could not request AI vendor suggestions. Please try again.')
    return
  }
  startPolling()
}

function toggleCandidate(id: number) {
  const i = selectedIds.value.indexOf(id)
  if (i >= 0) selectedIds.value.splice(i, 1)
  else selectedIds.value.push(id)
}

const pendingCandidates = computed(() =>
  (batch.value?.candidates ?? []).filter(c => c.decision === 'pending')
)

function proceedToReview() {
  if (selectedIds.value.length === 0 || !batch.value) return
  router.push({
    path: `${roleBase()}/vendor-suggestions/review`,
    query: { batchId: String(batch.value.id), candidateIds: selectedIds.value.join(',') },
  })
}

const allSelected = computed(() => 
  pendingCandidates.value.length > 0 &&
  pendingCandidates.value.every(c => selectedIds.value.includes(c.id))
)

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = pendingCandidates.value.map(c => c.id)
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-center gap-4">
      <button @click="goBack"
        class="flex items-center justify-center w-9 h-9 rounded-lg border border-black/10 bg-white hover:bg-black/4 text-black/50 hover:text-black transition shrink-0">
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div class="flex-1">
        <h1 class="text-xl font-semibold text-black flex items-center gap-2">
          <Sparkles class="w-5 h-5 text-brand-blue" /> AI Vendor Suggestions
        </h1>
        <p class="text-sm text-black/40 mt-0.5">Gemini-suggested candidate Philippine business partners/suppliers for SBSI. Review and edit before saving.</p>
      </div>
    </div>

    <!-- Request form -->
    <div class="bg-white rounded-lg border border-black/8 shadow-sm p-6">
      <h2 class="text-xs font-semibold text-black/40 uppercase tracking-widest mb-4">Suggestion Criteria</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/55">Industry Hint</label>
          <input v-model="industryHint" type="text" placeholder="e.g. clinical diagnostics"
            class="h-9 rounded-lg border border-black/12 px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-black/55">Region Hint</label>
          <Select v-model="regionHint">
            <SelectTrigger class="h-9 rounded-lg text-sm border-black/12"><SelectValue placeholder="Any region" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="Luzon">Luzon</SelectItem>
              <SelectItem value="Visayas">Visayas</SelectItem>
              <SelectItem value="Mindanao">Mindanao</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="flex items-end">
          <Button :disabled="requesting" @click="handleRequest" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white w-full">
            <Loader2 v-if="requesting" class="w-4 h-4 animate-spin mr-1" />
            {{ requesting ? 'Requesting…' : 'Get Suggestions' }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Results -->
    <div v-if="loading && !batch" class="text-center py-16 text-sm text-black/35">Loading…</div>

    <div v-else-if="batch?.status === 'pending'" class="flex flex-col items-center gap-3 py-16 text-black/35">
      <Loader2 class="w-8 h-8 animate-spin text-brand-blue" />
      <p class="text-sm font-medium">Gemini is generating candidate suggestions…</p>
    </div>

    <div v-else-if="batch?.status === 'failed'" class="text-center py-16 text-sm text-red-500">
      The suggestion request failed. Please try again.
    </div>

    <template v-else-if="batch && pendingCandidates.length > 0">
      <div class="flex items-center justify-between">
        <div class="flex items-baseline gap-4">
          <h2 class="text-sm font-semibold text-black">Suggested Candidates ({{ pendingCandidates.length }})</h2>
          <button @click="toggleSelectAll" class="text-xs font-medium text-brand-blue hover:text-brand-navy transition-colors">
            {{ allSelected ? 'Deselect All' : 'Select All' }}
          </button>
        </div>
        <Button :disabled="selectedIds.length === 0" @click="proceedToReview" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white disabled:opacity-40">
          Accept Selected ({{ selectedIds.length }})
        </Button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <VendorSuggestionCard
          v-for="c in pendingCandidates"
          :key="c.id"
          :candidate="c"
          :selected="selectedIds.includes(c.id)"
          @toggle="toggleCandidate(c.id)"
        />
      </div>
    </template>

    <div v-else-if="batch" class="text-center py-16 text-sm text-black/35">
      No pending candidates in this batch — request a new suggestion above.
    </div>
  </div>
</template>
