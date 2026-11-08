<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { AlertCircle } from 'lucide-vue-next'

defineProps<{
  open: boolean
  reason: string
}>()

defineEmits<{
  'update:open': [val: boolean]
}>()
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-md p-6 border-0">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2 text-red-600">
          <AlertCircle class="w-5 h-5" />
          Contract Rejected
        </DialogTitle>
        <DialogDescription class="text-black/60 pt-2">
          This contract was rejected by the manager. The reason provided is:
        </DialogDescription>
      </DialogHeader>
      
      <div class="mt-4 p-4 bg-red-50 border border-red-100 rounded-lg">
        <p class="text-sm font-medium text-red-800 leading-relaxed">{{ reason || 'No reason provided.' }}</p>
      </div>
    </DialogContent>
  </Dialog>
</template>
