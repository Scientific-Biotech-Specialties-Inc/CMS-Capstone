<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { AlertTriangle, AlertCircle } from 'lucide-vue-next'
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
  sourceWorkflowId: number
  sourceName: string
  contractTypes: ContractTypeWithWorkflow[]
}>()

const emit = defineEmits<{
  close: []
  duplicated: []
}>()

const { error: toastError } = useToast()
const { duplicateWorkflow } = useWorkflowBuilder()

// One-time duplication (decision #10): the copy is a brand new draft with
// no ongoing link back to the source — editing either afterward never
// affects the other.
const form = reactive({
  targetContractTypeId: null as number | null,
  name: `${props.sourceName} (Copy)`,
})

const { errors, touched, validateField, validateAll } = useFormValidation(form, {
  targetContractTypeId: [
    required('Please select a target contract type.'),
  ],
  name: [
    required('Workflow name is required.'),
    minLength(3, 'Workflow name must be at least 3 characters.'),
  ],
})

const saving = ref(false)
const saveError = ref<string | null>(null)

// Pre-flight: warn if the chosen target already has a workflow
const targetHasWorkflow = computed(() => {
  if (!form.targetContractTypeId) return false
  const ct = props.contractTypes.find(c => c.contract_type_id === form.targetContractTypeId)
  return !!(ct && (ct.active_workflow || ct.draft_workflow))
})

async function doDuplicate() {
  if (!validateAll()) return
  if (!form.targetContractTypeId) return
  saveError.value = null
  saving.value = true
  try {
    await duplicateWorkflow(props.sourceWorkflowId, form.targetContractTypeId, form.name.trim())
    emit('duplicated')
  } catch (e: any) {
    saveError.value = e.message ?? 'Failed to duplicate workflow.'
    toastError('Could not duplicate workflow', saveError.value ?? undefined)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v: boolean) => !v && emit('close')">
    <DialogContent class="max-w-md p-6 gap-4">
      <DialogHeader>
        <DialogTitle class="text-sm font-bold text-black">Duplicate Workflow</DialogTitle>
        <DialogDescription class="text-xs text-black/40 mt-1">
          Creates a new draft copy of "{{ sourceName }}" for a different contract type. The copy has no
          ongoing link to the original.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-3">
        <FormField
          label="Target Contract Type"
          :required="true"
          :error="touched.targetContractTypeId ? errors.targetContractTypeId : null"
        >
          <select
            v-model="form.targetContractTypeId"
            @blur="validateField('targetContractTypeId')"
            @change="validateField('targetContractTypeId')"
            class="w-full h-9 rounded-lg border px-3 text-sm bg-white focus:outline-none focus:ring-2 transition"
            :class="touched.targetContractTypeId && errors.targetContractTypeId
              ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15'
              : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
          >
            <option :value="null" disabled>Select a contract type</option>
            <option v-for="ct in contractTypes" :key="ct.contract_type_id" :value="ct.contract_type_id">
              {{ ct.contract_type_name }}
            </option>
          </select>
        </FormField>

        <FormField
          label="New Workflow Name"
          :required="true"
          :error="touched.name ? errors.name : null"
        >
          <input
            v-model="form.name"
            @blur="validateField('name')"
            @input="validateField('name')"
            type="text"
            class="w-full h-9 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 transition"
            :class="touched.name && errors.name
              ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15'
              : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'"
          />
        </FormField>

        <!-- Pre-flight warning: target already has a workflow -->
        <div
          v-if="targetHasWorkflow"
          class="flex items-start gap-2.5 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5"
        >
          <AlertTriangle class="w-3.5 h-3.5 shrink-0 mt-0.5" />
          <span>This contract type already has a workflow. The duplicate will be created as a second draft — only one workflow can be active at a time.</span>
        </div>

        <!-- Save error -->
        <div
          v-if="saveError"
          class="flex items-start gap-2.5 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5"
        >
          <AlertCircle class="w-3.5 h-3.5 shrink-0 mt-0.5" />
          <span>{{ saveError }}</span>
        </div>
      </div>

      <DialogFooter class="flex items-center justify-end gap-3 mt-2">
        <Button variant="outline" @click="emit('close')" :disabled="saving"
          class="h-9 px-4 text-sm border-black/15 text-black/65 hover:text-black hover:bg-black/4">
          Cancel
        </Button>
        <Button @click="doDuplicate" :disabled="saving"
          class="h-9 px-4 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm">
          {{ saving ? 'Duplicating...' : 'Duplicate' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
