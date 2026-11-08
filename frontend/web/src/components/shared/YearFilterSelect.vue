<script setup lang="ts">
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select'

// Independent override sitting next to TimeFilterBar: picking a year shows
// that full calendar year's monthly trend regardless of the active
// Today/This Week/Last Month filter. Selecting "By Year" again (__none__)
// returns control to the time filter.
const props = defineProps<{
  modelValue: number | null
  availableYears: number[]
}>()

const emit = defineEmits<{ 'update:modelValue': [v: number | null] }>()

// The underlying Select component's `AcceptableValue` generic is broader
// than the string values we pass into it; narrow it here since we always
// control the value set (years as strings, or the '__none__' sentinel).
function handleSelect(v: unknown) {
  const str = v == null ? '__none__' : String(v)
  emit('update:modelValue', str === '__none__' ? null : Number(str))
}
</script>

<template>
  <Select :model-value="props.modelValue !== null ? String(props.modelValue) : '__none__'" @update:model-value="handleSelect">
    <SelectTrigger class="w-28 h-9 rounded-md border-black/10 bg-white text-xs text-black/70 focus:ring-brand-blue/15">
      <SelectValue placeholder="By Year" />
    </SelectTrigger>
    <SelectContent class="bg-white border border-black/10 shadow-lg">
      <SelectItem value="__none__">By Year</SelectItem>
      <SelectItem v-for="yr in props.availableYears" :key="yr" :value="String(yr)">{{ yr }}</SelectItem>
    </SelectContent>
  </Select>
</template>
