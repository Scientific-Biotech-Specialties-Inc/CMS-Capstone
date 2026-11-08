<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import type { Partner, TabKey } from '@/types/partner'

defineProps<{ open: boolean; partner: Partner | null; activeTab: TabKey }>()
defineEmits<{ 'update:open': [v: boolean]; confirm: [] }>()
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-sm px-6 py-6" @pointer-down-outside="$emit('update:open', false)">
      <DialogHeader class="space-y-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center shrink-0">
            <AlertTriangle class="w-5 h-5 text-red-500" />
          </div>
          <DialogTitle class="text-base font-semibold text-black">Delete {{ activeTab === 'partners' ? 'Partner' : 'Supplier' }}</DialogTitle>
        </div>
        <DialogDescription class="text-sm text-black/55 leading-relaxed pl-1">
          Are you sure you want to remove
          <span class="font-semibold text-black">{{ partner?.name }}</span>?
          This action cannot be undone.
        </DialogDescription>
      </DialogHeader>
      <DialogFooter class="gap-2 mt-6">
        <Button variant="outline" class="border-black/12 text-black/60 hover:text-black"
          @click="$emit('update:open', false)">
          Cancel
        </Button>
        <Button class="bg-red-500 hover:bg-red-600 text-white" @click="$emit('confirm')">
          Delete
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
