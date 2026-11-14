<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { AlertCircle, Loader2, Plus } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import FormField from '@/components/shared/FormField.vue'
import { required, minLength } from '@/lib/validation'
import { useFormValidation } from '@/composables/useFormValidation'
import { useToast } from '@/composables/useToast'
import { useWorkflowBuilder } from '@/composables/useWorkflowBuilder'
import type { ContractTypeWithWorkflow } from '@/types/workflow'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
  created: [contractType: ContractTypeWithWorkflow]
}>()

const { error: toastError } = useToast()
const { createContractType } = useWorkflowBuilder()

const form = reactive({
  name: '',
})

const { errors, touched, validateField, validateAll, resetValidation } = useFormValidation(form, {
  name: [
    required('Contract type name is required.'),
    minLength(2, 'Contract type name must be at least 2 characters.'),
  ],
})

const saving = ref(false)
const saveError = ref<string | null>(null)

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    form.name = ''
    saveError.value = null
    resetValidation()
  }
})

async function handleSubmit() {
  if (!validateAll()) return
  saveError.value = null
  saving.value = true
  try {
    const created = await createContractType(form.name.trim())
    emit('created', created)
  } catch (e: unknown) {
    saveError.value = e instanceof Error ? e.message : 'Failed to add contract type.'
    toastError('Could not add contract type', saveError.value)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v: boolean) => !v && emit('close')">
    <DialogContent class="max-w-md p-6 gap-4">
      <DialogHeader>
        <DialogTitle class="text-sm font-bold text-black">Add Contract Type</DialogTitle>
        <DialogDescription class="text-xs text-black/40 mt-1">
          Create a new contract type so it appears in contract creation forms and can be assigned an approval workflow.
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <FormField
          label="Contract Type Name"
          :required="true"
          :error="touched.name ? errors.name : null"
        >
          <input
            v-model="form.name"
            @blur="validateField('name')"
            @input="validateField('name')"
            type="text"
            placeholder="e.g., Field Service"
            class="w-full h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition"
            :class="touched.name && errors.name
              ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15'
              : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
          />
        </FormField>

        <div
          v-if="saveError"
          class="flex items-start gap-2.5 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5"
        >
          <AlertCircle class="w-3.5 h-3.5 shrink-0 mt-0.5" />
          <span>{{ saveError }}</span>
        </div>

        <DialogFooter class="flex items-center justify-end gap-3 pt-2">
          <Button type="button" variant="outline" @click="emit('close')" :disabled="saving"
            class="h-9 px-4 text-sm border-black/15 text-black/65 hover:text-black hover:bg-black/4">
            Cancel
          </Button>
          <Button type="submit" :disabled="saving"
            class="h-9 px-4 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm">
            <Loader2 v-if="saving" class="w-3.5 h-3.5 animate-spin" />
            <Plus v-else class="w-3.5 h-3.5" />
            Add Type
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
