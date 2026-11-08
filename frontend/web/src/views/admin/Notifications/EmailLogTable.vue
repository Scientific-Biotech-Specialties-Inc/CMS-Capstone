<script setup lang="ts">
import { Mail, RefreshCw, ChevronLeft, ChevronRight, XCircle } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import type { EmailSendLog } from '@/types/notification'

defineProps<{
  logs: EmailSendLog[]
  loading: boolean
  currentPage: number
  lastPage: number
}>()

const emit = defineEmits<{
  changePage: [page: number]
  refresh: []
}>()

function formatDate(dateString: string | null): string {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
    
    <div class="px-6 py-4.5 border-b border-black/5 flex items-center justify-between">
      <div>
        <h2 class="text-sm font-semibold text-black">Email Reminders Log</h2>
        <p class="text-xs text-black/40 mt-0.5">Audit history of email notifications sent to users.</p>
      </div>
      <button @click="emit('refresh')" :disabled="loading"
        class="h-8 px-3 rounded-lg border border-black/10 hover:bg-black/2 active:bg-black/4 text-xs font-semibold text-black/70 flex items-center gap-1.5 transition disabled:opacity-50 select-none">
        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" /> Refresh
      </button>
    </div>

    <div class="relative">
      <div v-if="loading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
        <RefreshCw class="w-8 h-8 text-brand-blue animate-spin" />
      </div>

      <Table>
        <TableHeader class="bg-black/[0.018]">
          <TableRow class="border-b border-black/4 hover:bg-transparent">
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3 pl-6">Recipient</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Subject</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Date Dispatched</TableHead>
            <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3 pr-6">Details</TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          <TableRow v-for="log in logs" :key="log.id" class="border-b border-black/4 last:border-0 hover:bg-black/1.2 transition-colors">
            
            <TableCell class="py-4 pl-6">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-brand-blue/8 flex items-center justify-center shrink-0">
                  <Mail class="w-4 h-4 text-brand-blue" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-black leading-snug">{{ log.recipientEmail }}</p>
                  <p class="text-[11px] text-black/35 mt-0.5">User #{{ log.userId }}</p>
                </div>
              </div>
            </TableCell>

            <TableCell class="text-sm font-medium text-black py-4">
              {{ log.subject }}
            </TableCell>

            <TableCell class="py-4">
              <Badge variant="outline" class="text-xs font-semibold rounded-full px-2.5 py-0.5 border select-none"
                :class="{
                  'bg-emerald-50 text-emerald-700 border-emerald-200': log.status === 'sent',
                  'bg-red-50 text-red-700 border-red-200': log.status === 'failed',
                  'bg-black/4 text-black/35 border-black/8': log.status === 'skipped',
                }">
                <span class="capitalize">{{ log.status }}</span>
              </Badge>
            </TableCell>

            <TableCell class="text-sm text-black/45 py-4">
              {{ formatDate(log.sentAt || log.createdAt) }}
            </TableCell>

            <TableCell class="text-xs text-black/40 py-4 pr-6 max-w-xs truncate">
              <div v-if="log.status === 'failed'" class="flex items-center gap-1.5 text-red-600">
                <XCircle class="w-3.5 h-3.5 shrink-0" />
                <span class="truncate" :title="log.errorMessage || 'Unknown Error'">{{ log.errorMessage || 'Mail Send Failed' }}</span>
              </div>
              <span v-else-if="log.status === 'skipped'" class="italic text-black/30">
                {{ log.errorMessage || 'Skipped' }}
              </span>
              <span v-else class="text-emerald-600">
                Delivered
              </span>
            </TableCell>

          </TableRow>

          <TableRow v-if="logs.length === 0">
            <TableCell colspan="5" class="text-center py-16">
              <Mail class="w-8 h-8 text-black/15 mx-auto mb-2" />
              <p class="text-sm font-semibold text-black/28">No email logs found</p>
              <p class="text-xs text-black/20 mt-1">Check back later once reminder notifications are sent.</p>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Custom Pagination adhering strictly to pagination layout config: previous left, page centers, next right -->
    <div v-if="lastPage > 1" class="grid grid-cols-3 items-center px-6 py-4 border-t border-black/5 bg-black/[0.005]">
      <div class="flex justify-start">
        <button @click="emit('changePage', currentPage - 1)" :disabled="currentPage === 1"
          class="h-9 px-4 rounded-md border border-black/10 hover:bg-black/2 active:bg-black/4 font-medium text-sm text-black flex items-center gap-1.5 transition disabled:opacity-30 disabled:pointer-events-none select-none">
          <ChevronLeft class="w-4 h-4" /> Previous
        </button>
      </div>

      <div class="flex justify-center text-sm font-medium text-black/40">
        Page {{ currentPage }} of {{ lastPage }}
      </div>

      <div class="flex justify-end">
        <button @click="emit('changePage', currentPage + 1)" :disabled="currentPage === lastPage"
          class="h-9 px-4 rounded-md border border-black/10 hover:bg-black/2 active:bg-black/4 font-medium text-sm text-black flex items-center gap-1.5 transition disabled:opacity-30 disabled:pointer-events-none select-none">
          Next <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </div>

  </div>
</template>
