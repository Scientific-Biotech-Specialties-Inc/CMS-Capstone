<script setup lang="ts">
import { useRouter } from 'vue-router'

type LogType = 'create' | 'update' | 'approve' | 'delete'

interface AuditLog {
  action: string; user: string; timestamp: string; type: LogType
}

const router = useRouter()

const props = defineProps<{
  logs: AuditLog[]
}>()

const logDot: Record<LogType, string> = {
  create:  'bg-brand-blue',
  update:  'bg-brand-dark',
  approve: 'bg-brand-navy',
  delete:  'bg-black/30',
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden h-full flex flex-col justify-between">
    <div>
      <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-black">Audit log</h3>
        <button @click="router.push('/admin/audit-log')" class="text-xs font-semibold text-brand-blue hover:text-brand-navy transition-colors">
          See all
        </button>
      </div>

      <div v-if="logs.length === 0" class="p-8 text-center text-sm text-black/40">
        No audit logs recorded.
      </div>
      <div v-else class="px-6 py-4 space-y-0 divide-y divide-black/4">
        <div
          v-for="log in logs"
          :key="log.action + log.timestamp"
          class="flex items-start gap-3 py-3.5 first:pt-0 last:pb-0"
        >
          <div class="mt-1.5 w-2 h-2 rounded-full shrink-0" :class="logDot[log.type]" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-black leading-snug">{{ log.action }}</p>
            <p class="text-xs text-black/38 mt-0.5">by {{ log.user }}</p>
          </div>
          <p class="text-[11px] text-black/35 shrink-0 mt-0.5">{{ log.timestamp }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
