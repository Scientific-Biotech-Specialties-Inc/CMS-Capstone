<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    label?: string
    required?: boolean
    error?: string | null
    hint?: string
    id?: string
  }>(),
  {
    required: false,
    error: null,
  }
)
</script>

<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      :for="id"
      class="block text-xs font-semibold text-black/55 uppercase tracking-wide"
    >
      {{ label }}
      <span v-if="required" class="text-red-500 font-bold">*</span>
    </label>

    <slot :id="id" :has-error="!!error" />

    <p v-if="hint && !error" class="text-xs text-black/40">
      {{ hint }}
    </p>

    <p v-if="error" class="text-xs text-red-500 flex items-center gap-1">
      <AlertCircle class="w-3.5 h-3.5 shrink-0" />
      <span>{{ error }}</span>
    </p>
  </div>
</template>
