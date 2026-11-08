<script setup lang="ts">
import type { TimeFilterOption } from '@/composables/useTimeFilter'
import { TIME_FILTER_LABELS } from '@/composables/useTimeFilter'

const props = defineProps<{
  modelValue: TimeFilterOption
}>()

const emit = defineEmits<{ 'update:modelValue': [v: TimeFilterOption] }>()

const options: TimeFilterOption[] = ['today', 'week', 'month']
</script>

<template>
  <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1 w-fit">
    <button
      v-for="opt in options"
      :key="opt"
      type="button"
      @click="emit('update:modelValue', opt)"
      class="px-3 py-1.5 text-xs rounded transition-all font-medium"
      :class="props.modelValue === opt
        ? 'bg-white text-black shadow-sm'
        : 'text-black/40 hover:text-black/60'"
    >
      {{ TIME_FILTER_LABELS[opt] }}
    </button>
  </div>
</template>
