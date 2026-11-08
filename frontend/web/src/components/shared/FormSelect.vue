<script setup lang="ts">
import { computed } from 'vue'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export interface SelectOption {
  label: string
  value: string | number
}

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    options: (string | SelectOption)[]
    placeholder?: string
    disabled?: boolean
    error?: boolean
    size?: 'default' | 'sm'
    class?: string
  }>(),
  {
    modelValue: '',
    placeholder: 'Select an option',
    disabled: false,
    error: false,
    size: 'default',
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'change', value: string): void
  (e: 'blur'): void
}>()

const normalizedOptions = computed<SelectOption[]>(() => {
  return props.options.map((opt) => {
    if (typeof opt === 'string') {
      return { label: opt, value: opt }
    }
    return opt
  })
})

const stringValue = computed<string>(() => {
  if (props.modelValue === null || props.modelValue === undefined) return ''
  return String(props.modelValue)
})

function handleValueChange(val: any) {
  emit('update:modelValue', val)
  emit('change', val)
}
</script>

<template>
  <Select
    :model-value="stringValue"
    :disabled="disabled"
    @update:model-value="handleValueChange"
  >
    <SelectTrigger
      :size="size"
      :class="[
        props.class,
        error ? 'border-red-400 focus-visible:border-red-400 ring-red-200/50' : '',
        !stringValue ? 'text-black/30' : 'text-slate-800 font-medium',
      ]"
      @blur="$emit('blur')"
    >
      <SelectValue :placeholder="placeholder" />
    </SelectTrigger>
    <SelectContent>
      <SelectItem
        v-for="opt in normalizedOptions"
        :key="String(opt.value)"
        :value="String(opt.value)"
      >
        {{ opt.label }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>
