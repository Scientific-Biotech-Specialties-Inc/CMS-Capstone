<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  title: string
  description: string
  icon: Component
  iconBg?: string
  iconColor?: string
}>()
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-black/4 flex items-center gap-3">
      <div
        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
        :class="iconBg ?? 'bg-brand-navy/8'"
      >
        <component :is="icon" class="w-4 h-4" :class="iconColor ?? 'text-brand-navy'" />
      </div>
      <div>
        <p class="text-sm font-semibold text-black">{{ title }}</p>
        <p class="text-xs text-black/40 mt-0.5">{{ description }}</p>
      </div>
    </div>
    <slot />
  </div>
</template>
