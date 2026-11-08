<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    prefix: string
    placeholder?: string
    error?: string | boolean
    disabled?: boolean
    prefixWidth?: string
    numericOnly?: boolean
    autoDashAfter?: number
    maxLength?: number
  }>(),
  {
    placeholder: '',
    error: false,
    disabled: false,
    prefixWidth: 'w-16',
    numericOnly: false,
    autoDashAfter: undefined,
    maxLength: undefined,
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', val: string): void
  (e: 'blur', evt: FocusEvent): void
}>()

const suffix = computed(() => {
  if (!props.modelValue) return ''
  const p = props.prefix.trim()
  if (props.modelValue.startsWith(p)) {
    return props.modelValue.slice(p.length)
  }
  return props.modelValue
})

function formatValue(val: string, isDeleting = false): string {
  // 1. Strip prefix if user typed or pasted it
  const escapedPrefix = props.prefix.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')
  const re = new RegExp(`^${escapedPrefix}\\s*`, 'i')
  let cleaned = val.replace(re, '')

  // 2. If numericOnly is enabled
  if (props.numericOnly) {
    if (props.autoDashAfter) {
      let digits = cleaned.replace(/\D/g, '')

      const maxDigits = props.maxLength ? props.maxLength - 1 : 12
      if (digits.length > maxDigits) {
        digits = digits.slice(0, maxDigits)
      }

      if (digits.length < props.autoDashAfter) {
        return digits
      }

      if (digits.length === props.autoDashAfter) {
        return isDeleting ? digits : `${digits}-`
      }

      const p1 = digits.slice(0, props.autoDashAfter)
      const p2 = digits.slice(props.autoDashAfter)
      return `${p1}-${p2}`
    } else {
      let digits = cleaned.replace(/\D/g, '')
      if (props.maxLength && digits.length > props.maxLength) {
        digits = digits.slice(0, props.maxLength)
      }
      return digits
    }
  }

  if (props.maxLength && cleaned.length > props.maxLength) {
    cleaned = cleaned.slice(0, props.maxLength)
  }

  return cleaned
}

function onInput(e: Event) {
  const inputEvent = e as InputEvent
  const isDeleting = inputEvent.inputType === 'deleteContentBackward' || inputEvent.inputType === 'deleteContentForward'
  const target = e.target as HTMLInputElement
  let raw = target.value

  const formatted = formatValue(raw, isDeleting)

  // Force DOM input value to reflect formatted state immediately
  target.value = formatted

  if (!formatted) {
    emit('update:modelValue', '')
  } else {
    emit('update:modelValue', `${props.prefix}${formatted}`)
  }
}

function onBlur(e: FocusEvent) {
  if (props.modelValue) {
    const p = props.prefix.trim()
    if (props.modelValue.startsWith(p)) {
      const s = props.modelValue.slice(p.length).trim()
      emit('update:modelValue', s ? `${p}${s}` : '')
    }
  }
  emit('blur', e)
}
</script>

<template>
  <div class="flex items-center gap-1.5">
    <!-- Disabled Prefix Field -->
    <input
      type="text"
      :value="prefix"
      disabled
      tabindex="-1"
      :class="[
        prefixWidth,
        'h-9 rounded-lg border border-black/12 bg-black/[0.04] text-black/60 font-mono text-sm font-semibold text-center select-none cursor-not-allowed focus:outline-none shrink-0'
      ]"
    />

    <!-- Adjacent Editable Suffix Field -->
    <input
      :value="suffix"
      @input="onInput"
      @blur="onBlur"
      type="text"
      :inputmode="numericOnly ? 'numeric' : undefined"
      :placeholder="placeholder"
      :disabled="disabled"
      class="flex-1 min-w-0 h-9 rounded-lg border px-3 text-sm font-mono placeholder:text-black/25 focus:outline-none focus:ring-2 transition"
      :class="error
        ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
        : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
    />
  </div>
</template>
