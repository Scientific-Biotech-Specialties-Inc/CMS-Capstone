<script setup lang="ts">
import { ArrowRight } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { requestStatusBadge, fmtReqDate } from '@/types/contractRequest'
import type { ContractRequest } from '@/types/contractRequest'

defineProps<{
  requests: ContractRequest[]
  loading?: boolean
}>()

const router = useRouter()
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

    <!-- Header -->
    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between">
      <div>
        <h2 class="text-sm font-semibold text-black">Recent Contract Requests</h2>
        <p class="text-xs text-black/40 mt-0.5">
          <span v-if="loading" class="inline-block h-3 w-32 bg-black/5 animate-pulse rounded"></span>
          <span v-else>Your latest {{ requests.length }} submissions</span>
        </p>
      </div>
      <button @click="router.push('/sales/contracts')"
        class="inline-flex items-center gap-1 text-xs font-semibold text-brand-blue hover:underline">
        View all <ArrowRight class="w-3 h-3" />
      </button>
    </div>

    <!-- List -->
    <div class="divide-y divide-black/4">
      <template v-if="loading">
        <div v-for="i in 6" :key="i"
          class="px-6 py-3.5 flex items-center justify-between gap-4">
          <!-- Partner + meta -->
          <div class="min-w-0 flex-1">
            <div class="h-4 w-40 bg-black/5 animate-pulse rounded mb-2"></div>
            <div class="flex items-center gap-2">
              <div class="h-3 w-24 bg-black/5 animate-pulse rounded"></div>
            </div>
          </div>
          <!-- Status + date -->
          <div class="shrink-0 text-right">
            <div class="h-5 w-20 bg-black/5 animate-pulse rounded-full ml-auto mb-1.5"></div>
            <div class="h-3 w-16 bg-black/5 animate-pulse rounded ml-auto"></div>
          </div>
        </div>
      </template>
      <template v-else>
        <div v-for="r in requests" :key="r.id"
          @click="router.push('/sales/contract-requests/' + r.id)"
          class="px-6 py-3.5 flex items-center justify-between gap-4 hover:bg-black/1.5 transition-colors cursor-pointer">

          <!-- Partner + meta -->
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-black truncate leading-snug">{{ r.businessPartner }}</p>
            <div class="flex items-center gap-2 mt-0.5">
              <span class="text-[11px] text-black/40">{{ r.category }}</span>
            </div>
          </div>

          <!-- Status + date -->
          <div class="shrink-0 text-right">
            <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full border whitespace-nowrap"
              :class="requestStatusBadge[r.status]">
              {{ r.status }}
            </span>
            <p class="text-[10px] text-black/35 mt-1 tabular-nums">{{ fmtReqDate(r.requestDate) }}</p>
          </div>

        </div>

        <div v-if="requests.length === 0" class="px-6 py-12 text-center">
          <p class="text-sm text-black/30">No requests submitted yet.</p>
        </div>
      </template>
    </div>

  </div>
</template>
