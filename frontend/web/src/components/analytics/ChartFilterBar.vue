<script setup lang="ts">
export interface FilterOption {
  label: string
  value: string
}

export interface FilterDef {
  key: string
  label: string
  value: string
  options: FilterOption[]
}

const props = defineProps<{
  filters: FilterDef[]
}>()

const emit = defineEmits<{
  (e: 'update:filter', key: string, value: string): void
}>()

function onChange(key: string, event: Event) {
  const target = event.target as HTMLSelectElement
  emit('update:filter', key, target.value)
}
</script>

<template>
  <div v-if="filters.length > 0" class="flex items-center gap-2 flex-wrap">
    <div v-for="f in filters" :key="f.key" class="flex items-center gap-1.5 bg-black/4 border border-black/8 rounded-lg px-2.5 py-1 text-xs">
      <span class="font-medium text-black/45 shrink-0">{{ f.label }}:</span>
      <select
        :value="f.value"
        @change="onChange(f.key, $event)"
        class="bg-transparent font-semibold text-black focus:outline-none cursor-pointer text-xs pr-1"
      >
        <option v-for="opt in f.options" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
    </div>
  </div>
</template>
