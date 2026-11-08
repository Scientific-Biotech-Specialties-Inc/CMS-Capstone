<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { getInitials, avatarColor, roleBadgeFor } from '@/types/user'
import type { User } from '@/types/user'

defineProps<{ open: boolean; user: User | null; avatarIndex: number }>()
defineEmits<{ 'update:open': [v: boolean]; confirm: [] }>()
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-sm" @pointer-down-outside="$emit('update:open', false)">
      <template v-if="user">
        <div class="px-6 pt-6 pb-5 text-center">

          <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
            <AlertTriangle class="w-5 h-5 text-red-500" />
          </div>

          <DialogTitle class="text-base font-bold text-black">Delete user?</DialogTitle>
          <DialogDescription class="mt-2 text-sm text-black/50 leading-relaxed">
            You're about to permanently delete
            <span class="font-semibold text-black/70">{{ user.name }}</span>.
            This action cannot be undone.
          </DialogDescription>

          <div class="flex items-center gap-2.5 mt-4 p-3 rounded-lg bg-black/3 border border-black/6 text-left">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
              :style="{ backgroundColor: avatarColor(avatarIndex) }">
              {{ getInitials(user.name) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-black truncate">{{ user.name }}</p>
              <p class="text-xs text-black/40 truncate">{{ user.email }}</p>
            </div>
            <Badge variant="outline" class="text-xs font-semibold rounded-full px-2 py-0.5 shrink-0"
              :class="roleBadgeFor(user.role)">{{ user.role }}</Badge>
          </div>

          <div class="flex gap-3 mt-5">
            <Button variant="outline" @click="$emit('update:open', false)"
              class="flex-1 h-9 text-sm border-black/15 text-black/60 hover:text-black">Cancel</Button>
            <Button @click="$emit('confirm')"
              class="flex-1 h-9 text-sm bg-red-600 hover:bg-red-700 text-white">Yes, delete</Button>
          </div>

        </div>
      </template>
    </DialogContent>
  </Dialog>
</template>
