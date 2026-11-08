<script setup lang="ts">
import { Download, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

const props = defineProps<{
  exporting?: boolean
}>()

const emit = defineEmits<{ export: [] }>()
</script>

<template>
  <Button variant="outline" :disabled="exporting" @click="emit('export')"
    class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
    <Loader2 v-if="exporting" class="w-4 h-4 animate-spin" />
    <Download v-else class="w-4 h-4" />
    {{ exporting ? 'Exporting…' : 'Export PDF' }}
  </Button>
</template>
