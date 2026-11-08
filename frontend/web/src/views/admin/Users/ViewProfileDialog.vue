<script setup lang="ts">
import { Pencil, Mail, Building2, CalendarDays } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { getInitials, avatarColor, roleBadgeFor } from '@/types/user'
import type { User } from '@/types/user'

const props = defineProps<{ open: boolean; user: User | null; avatarIndex: number }>()
const emit  = defineEmits<{ 'update:open': [v: boolean]; edit: [u: User] }>()
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-sm p-0 overflow-hidden gap-0" aria-describedby="undefined" @pointer-down-outside="$emit('update:open', false)">
      <template v-if="user">

        <!-- ── Header ─────────────────────────────────────────────── -->
        <div class="px-5 pt-5 pb-4 border-b border-black/6">
          <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0 select-none"
              :style="{ backgroundColor: avatarColor(avatarIndex) }">
              {{ getInitials(user.name) }}
            </div>
            <div class="flex-1 min-w-0 pr-6">
              <DialogTitle class="text-sm font-bold text-black leading-snug">{{ user.name }}</DialogTitle>
              <div class="flex items-center gap-2 mt-1.5">
                <span class="text-[10px] font-mono text-black/30 bg-black/4 px-1.5 py-0.5 rounded">{{ user.id }}</span>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full border" :class="roleBadgeFor(user.role)">
                  {{ user.role }}
                </span>
                <span class="flex items-center gap-1 text-[11px] font-medium"
                  :class="user.status === 'Active' ? 'text-emerald-600' : 'text-black/35'">
                  <span class="w-1.5 h-1.5 rounded-full shrink-0"
                    :class="user.status === 'Active' ? 'bg-emerald-500' : 'bg-black/20'" />
                  {{ user.status }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Detail rows ────────────────────────────────────────── -->
        <div class="px-5 py-1 divide-y divide-black/4">

          <div class="flex items-center gap-3 py-2.5">
            <Mail class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Email</span>
              <span class="text-sm text-brand-blue truncate text-right">{{ user.email }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 py-2.5">
            <Building2 class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Department</span>
              <span class="text-sm font-medium text-black text-right">Sales Department</span>
            </div>
          </div>

          <div class="flex items-center gap-3 py-2.5">
            <CalendarDays class="w-3.5 h-3.5 text-black/25 shrink-0" />
            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-4">
              <span class="text-[10px] font-semibold text-black/35 uppercase tracking-wider shrink-0">Date Added</span>
              <span class="text-sm font-medium text-black tabular-nums">{{ user.dateAdded }}</span>
            </div>
          </div>

        </div>

        <!-- ── Footer ────────────────────────────────────────────── -->
        <div class="px-5 py-4 border-t border-black/6 flex justify-end gap-2">
          <Button variant="outline" @click="$emit('update:open', false)"
            class="h-8 px-4 text-sm border-black/15 text-black/60 hover:text-black">
            Close
          </Button>
          <Button @click="$emit('edit', user)"
            class="h-8 px-4 text-sm bg-brand-navy hover:bg-brand-dark text-white">
            <Pencil class="w-3.5 h-3.5 mr-1.5" /> Edit user
          </Button>
        </div>

      </template>
    </DialogContent>
  </Dialog>
</template>
