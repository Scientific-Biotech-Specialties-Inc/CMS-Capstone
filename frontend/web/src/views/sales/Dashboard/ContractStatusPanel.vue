<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, FileText, AlertTriangle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/composables/useAuth'
import { contractLifecycle } from '@/types/contract'
import type { Contract } from '@/types/contract'

const props = defineProps<{
  contracts: (Contract & { days: number })[]
  loading?: boolean
}>()

const router = useRouter()
const { hasPermission } = useAuth()

const expiringSoon = computed(() =>
  props.contracts.filter(c => contractLifecycle(c) === 'expiring').slice(0, 4)
)
</script>

<template>
  <div class="space-y-4">

    <!-- Expiring Soon (only shown when loading, or when relevant) -->
    <div v-if="loading || expiringSoon.length > 0" class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
      <div class="px-5 pt-4 pb-3 border-b border-black/5 flex items-center gap-2">
        <AlertTriangle class="w-3.5 h-3.5 text-black/40 shrink-0" />
        <h2 class="text-sm font-semibold text-black">Expiring Soon</h2>
      </div>
      <div class="divide-y divide-black/4">
        <template v-if="loading">
          <div v-for="i in 3" :key="i" class="px-5 py-3 flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="h-3.5 w-32 bg-black/5 animate-pulse rounded mb-1"></div>
              <div class="h-3 w-16 bg-black/5 animate-pulse rounded"></div>
            </div>
            <div class="h-4 w-12 bg-black/5 animate-pulse rounded shrink-0"></div>
          </div>
        </template>
        <template v-else>
          <div v-for="c in expiringSoon" :key="c.id"
            @click="router.push('/sales/contracts/' + c.id)"
            class="px-5 py-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-black/1.5 transition-colors">
            <div class="min-w-0">
              <p class="text-xs font-medium text-black truncate">{{ c.businessPartner }}</p>
              <p class="text-[10px] text-black/35 mt-0.5">{{ c.id }}</p>
            </div>
            <span class="text-xs font-semibold text-black/50 shrink-0 tabular-nums">{{ c.days }}d left</span>
          </div>
        </template>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
      <div class="px-5 pt-5 pb-4 border-b border-black/5">
        <h2 class="text-sm font-semibold text-black">Quick Actions</h2>
      </div>
      <div class="px-5 py-4 space-y-2.5">
        <Button v-if="hasPermission('cms.contracts.create')" @click="router.push('/sales/contracts/create')"
          class="w-full h-9 justify-start gap-2.5 text-sm bg-brand-navy hover:bg-brand-dark text-white">
          <Plus class="w-4 h-4" /> New Contract Request
        </Button>
        <Button @click="router.push('/sales/contracts')" variant="outline"
          class="w-full h-9 justify-start gap-2.5 text-sm border-black/12 text-black/65 hover:text-black">
          <FileText class="w-4 h-4" /> View My Contracts
        </Button>
      </div>
    </div>

  </div>
</template>
