<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'
import type { EquipmentItem } from '@/types/contract'

export interface EquipmentItemError {
  brand?: string
  model?: string
}

const props = withDefaults(defineProps<{
  modelValue: EquipmentItem[]
  errors?: EquipmentItemError[]
  required?: boolean
  size?: 'sm' | 'md'
  maxItems?: number
}>(), {
  errors: () => [],
  required: true,
  size: 'sm',
  maxItems: 20,
})

const emit = defineEmits<{
  'update:modelValue': [items: EquipmentItem[]]
  'blur-field': [index: number, field: keyof EquipmentItem]
  'add-row': []
  'remove-row': [index: number]
}>()

const inputHeightCls = props.size === 'md' ? 'h-10 px-3.5' : 'h-9 px-3'
const actionBtnSizeCls = props.size === 'md' ? 'h-10 w-10' : 'h-9 w-9'

function updateField(index: number, field: keyof EquipmentItem, value: string) {
  const next = props.modelValue.map((item, idx) =>
    idx === index ? { ...item, [field]: value } : item
  )
  emit('update:modelValue', next)
}

function addRow() {
  if (props.modelValue.length >= props.maxItems) return
  emit('update:modelValue', [
    ...props.modelValue,
    { equipmentName: '', brand: '', model: '' },
  ])
  emit('add-row')
}

function removeRow(index: number) {
  if (props.modelValue.length <= 1) return
  emit(
    'update:modelValue',
    props.modelValue.filter((_, idx) => idx !== index)
  )
  emit('remove-row', index)
}
</script>

<template>
  <div class="md:col-span-4 space-y-3">
    <div
      v-for="(item, index) in modelValue"
      :key="index"
      class="grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_80px] gap-4 items-start"
    >
      <!-- Equipment / Machine Name -->
      <div class="flex flex-col gap-1.5">
        <label
          class="text-xs font-semibold text-black/55"
          :class="index > 0 ? 'md:hidden' : ''"
        >
          Equipment / Machine Name
          <span class="font-normal text-black/40">(Optional for software products)</span>
        </label>
        <input
          :value="item.equipmentName"
          type="text"
          placeholder="e.g., Sysmex XN-1000 Hematology Analyzer"
          class="rounded-lg border border-black/12 bg-white text-sm text-black placeholder:text-black/30 focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition"
          :class="inputHeightCls"
          @input="updateField(index, 'equipmentName', ($event.target as HTMLInputElement).value)"
          @blur="emit('blur-field', index, 'equipmentName')"
        />
      </div>

      <!-- Brand -->
      <div class="flex flex-col gap-1.5">
        <label
          class="text-xs font-semibold text-black/55"
          :class="index > 0 ? 'md:hidden' : ''"
        >
          Brand
          <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
          :value="item.brand"
          type="text"
          placeholder="e.g., Sysmex"
          class="rounded-lg border bg-white text-sm text-black placeholder:text-black/30 focus:outline-none focus:ring-2 transition"
          :class="[
            inputHeightCls,
            errors[index]?.brand
              ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
              : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15',
          ]"
          @input="updateField(index, 'brand', ($event.target as HTMLInputElement).value)"
          @blur="emit('blur-field', index, 'brand')"
        />
        <p v-if="errors[index]?.brand" class="text-xs text-red-500">{{ errors[index]?.brand }}</p>
      </div>

      <!-- Model -->
      <div class="flex flex-col gap-1.5">
        <label
          class="text-xs font-semibold text-black/55"
          :class="index > 0 ? 'md:hidden' : ''"
        >
          Model
          <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
          :value="item.model"
          type="text"
          placeholder="e.g., XN-1000"
          class="rounded-lg border bg-white text-sm text-black placeholder:text-black/30 focus:outline-none focus:ring-2 transition"
          :class="[
            inputHeightCls,
            errors[index]?.model
              ? 'border-red-400 focus:border-red-400 focus:ring-red-200/50'
              : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15',
          ]"
          @input="updateField(index, 'model', ($event.target as HTMLInputElement).value)"
          @blur="emit('blur-field', index, 'model')"
        />
        <p v-if="errors[index]?.model" class="text-xs text-red-500">{{ errors[index]?.model }}</p>
      </div>

      <!-- Row Actions (+ / Trash) -->
      <div
        class="flex items-center gap-2"
        :class="index === 0 ? 'md:pt-[22px]' : ''"
      >
        <button
          v-if="modelValue.length > 1"
          type="button"
          :title="`Remove equipment row ${index + 1}`"
          :aria-label="`Remove equipment row ${index + 1}`"
          class="inline-flex items-center justify-center rounded-lg border border-black/12 bg-white text-black/45 hover:text-[#EF4444] hover:border-red-300 hover:bg-red-50 transition cursor-pointer shrink-0"
          :class="actionBtnSizeCls"
          @click="removeRow(index)"
        >
          <Trash2 class="w-4 h-4" />
        </button>

        <button
          v-if="index === modelValue.length - 1 && modelValue.length < maxItems"
          type="button"
          title="Add another equipment item"
          aria-label="Add another equipment item"
          class="inline-flex items-center justify-center rounded-lg border border-[#252578]/20 bg-[#EFF6FF] text-[#252578] hover:bg-[#252578] hover:text-white transition cursor-pointer shrink-0"
          :class="actionBtnSizeCls"
          @click="addRow"
        >
          <Plus class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>
