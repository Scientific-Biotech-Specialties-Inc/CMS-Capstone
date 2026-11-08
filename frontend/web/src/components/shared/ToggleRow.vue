<script setup lang="ts">
defineProps<{
  label: string
  description?: string
  modelValue: boolean
}>()

defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<template>
  <div class="flex items-center justify-between px-6 py-4">
    <div>
      <p class="text-sm font-medium text-black">{{ label }}</p>
      <p v-if="description" class="text-xs text-black/40 mt-0.5">{{ description }}</p>
    </div>
    <button
      @click="$emit('update:modelValue', !modelValue)"
      class="relative w-11 h-6 rounded-full transition-colors duration-200 shrink-0"
      :class="modelValue ? 'bg-brand-blue' : 'bg-black/15'"
    >
      <span
        class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200"
        :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
      />
    </button>
  </div>
</template>
