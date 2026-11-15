<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Clock, ArrowRight } from 'lucide-vue-next'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'

const router = useRouter()

const props = defineProps<{
  contracts: (Contract & { days: number })[]
}>()

const expiringContracts = computed(() => {
  return props.contracts
    .filter(c => contractLifecycle(c) === 'expiring')
    .sort((a, b) => a.days - b.days)
    .slice(0, 5)
})

function cls(days: number) {
  if (days <= 7)  return { dot: 'bg-red-500',   badge: 'bg-red-50 text-red-600 border-red-200',     bar: 'bg-red-500'   }
  if (days <= 14) return { dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700 border-amber-200', bar: 'bg-amber-500' }
  return               { dot: 'bg-brand-blue', badge: 'bg-brand-blue/8 text-brand-blue border-brand-blue/20', bar: 'bg-brand-blue' }
}

const urgentCount = computed(() => expiringContracts.value.filter(c => c.days <= 14).length)
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full">
    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <h3 class="text-sm font-semibold text-black">Expiring Soon</h3>
        <span
          v-if="urgentCount > 0"
          class="px-1.5 py-0.5 rounded-full bg-red-50 text-red-600 text-[10px] font-bold border border-red-200"
        >{{ urgentCount }} urgent</span>
      </div>
      <button
        @click="router.push('/manager/contracts')"
        class="flex items-center gap-1 text-xs font-semibold text-brand-blue hover:text-brand-navy transition-colors"
      >
        View all <ArrowRight class="w-3.5 h-3.5" />
      </button>
    </div>

    <div v-if="expiringContracts.length === 0" class="p-8 text-center text-sm text-black/40">
      No expiring contracts.
    </div>
    <div v-else class="divide-y divide-black/4">
      <div
        v-for="c in expiringContracts"
        :key="c.id"
        class="px-6 py-3.5 hover:bg-black/1.2 transition-colors cursor-pointer"
        @click="router.push('/manager/contracts/' + c.id)"
      >
        <div class="flex items-start gap-3">
          <div class="w-2 h-2 rounded-full shrink-0 mt-1.5" :class="cls(c.days).dot" />
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-medium text-black truncate">{{ c.businessPartner }}</p>
                <p class="text-[11px] text-black/35 mt-0.5">{{ c.category }}</p>
              </div>
              <span
                class="flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full border shrink-0"
                :class="cls(c.days).badge"
              >
                <Clock class="w-3 h-3" />
                {{ c.days }}d
              </span>
            </div>
            <!-- Progress bar -->
            <div class="mt-2 h-1 rounded-full bg-black/6 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="cls(c.days).bar"
                :style="{ width: `${Math.min(100, Math.round((30 - c.days) / 30 * 100))}%` }"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
