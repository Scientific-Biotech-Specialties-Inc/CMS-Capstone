<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { Save, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import { useToast } from '@/composables/useToast'
import { useWorkflowBuilder } from '@/composables/useWorkflowBuilder'
import type { AssignableRole, ContractTypeWithWorkflow, ExecutionReviewers, ResubmitMode, Workflow, WorkflowStep } from '@/types/workflow'
import InternalApprovalSteps from './InternalApprovalSteps.vue'
import ExecutionWorkflowSettings from './ExecutionWorkflowSettings.vue'

const props = defineProps<{
  contractType: ContractTypeWithWorkflow
  workflow: Workflow | null  // null = creating a new workflow
  assignableRoles: AssignableRole[]
}>()

const emit = defineEmits<{
  saved: [status: 'draft' | 'active']
}>()

const { error: toastError } = useToast()
const { saveWorkflow } = useWorkflowBuilder()

const isEditing = computed(() => !!props.workflow)
const createsVersion = computed(() => props.workflow?.status === 'active' || props.workflow?.has_runs === true)
const activeVersion = computed(() => props.contractType.active_workflow?.id)
const activationLabel = computed(() => activeVersion.value || createsVersion.value ? 'Activate New Version' : 'Activate')
const showActivation = ref(false)

const form = reactive({
  name: props.workflow?.name ?? `${props.contractType.contract_type_name} Approval`,
  resubmitMode: (props.workflow?.resubmit_mode ?? 'restart') as ResubmitMode,
})

// Deep-cloned so editing doesn't mutate the parent's cached workflow object
// until a successful save.
const steps = ref<WorkflowStep[]>(
  props.workflow ? JSON.parse(JSON.stringify(props.workflow.steps)) : []
)

const saving = ref(false)
const reviewers = ref<ExecutionReviewers>({ ...props.workflow?.execution_reviewers })
const saveError = ref<string | null>(null)

// ── Save ──────────────────────────────────────────────────────────────
async function doSave(status: 'draft' | 'active') {
  if (saving.value) return
  showActivation.value = false
  saveError.value = null
  saving.value = true
  try {
    const savedId = createsVersion.value ? undefined : props.workflow?.id
    await saveWorkflow({
      contract_type_id: props.contractType.contract_type_id,
      name: form.name,
      status,
      replace_active_workflow_id: status === 'active' && activeVersion.value !== savedId ? activeVersion.value : undefined,
      replace_draft_workflow_id: createsVersion.value && props.workflow?.status === 'draft' ? props.workflow.id : undefined,
      resubmit_mode: form.resubmitMode,
      execution_reviewers: reviewers.value,
      steps: steps.value.map((s, i) => ({ ...s, order_index: i + 1 })),
    }, savedId)
    emit('saved', status)
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Failed to save workflow.'
    toastError('Could not save workflow', saveError.value ?? undefined)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <header>
      <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">
        {{ isEditing ? 'Edit Workflow' : 'Create Workflow' }} · {{ contractType.contract_type_name }}
      </h1>
      <p class="mt-2 font-montserrat text-sm text-slate-600">Set the approval order and choose who confirms each execution stage.</p>
    </header>

    <fieldset :disabled="saving" class="min-w-0 space-y-4">
      <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 sm:p-6">
        <label class="flex flex-col gap-2 text-sm font-semibold text-slate-800">
          Workflow Name
          <input v-model="form.name" type="text" maxlength="255"
            class="h-10 w-full rounded-lg border border-slate-200 px-4 text-sm font-normal focus-visible:outline-2 focus-visible:outline-brand-blue" />
        </label>
        <label class="flex flex-col gap-2 text-sm font-semibold text-slate-800">
          On Rejection
          <select v-model="form.resubmitMode"
            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-4 text-sm font-normal focus-visible:outline-2 focus-visible:outline-brand-blue">
            <option value="restart">Restart from the beginning</option>
            <option value="resume_at_rejected">Resume at the rejected step</option>
          </select>
        </label>
      </div>

      <div class="grid items-start gap-4 xl:grid-cols-2">
        <InternalApprovalSteps v-model="steps" :roles="assignableRoles" />
        <ExecutionWorkflowSettings :reviewers="reviewers" :roles="assignableRoles" @change="reviewers = $event" />
      </div>
    </fieldset>

    <p v-if="saveError" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ saveError }}
    </p>

    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4">
      <Button variant="outline" @click="doSave('draft')" :disabled="saving">
        <Loader2 v-if="saving" class="size-4 animate-spin" />
        {{ createsVersion ? 'Save as New Draft' : 'Save Draft' }}
      </Button>
      <Button @click="showActivation = true" :disabled="saving" class="bg-brand-navy text-white hover:bg-brand-dark">
        <Loader2 v-if="saving" class="size-4 animate-spin" />
        <Save v-else class="size-4" />
        {{ activationLabel }}
      </Button>
    </div>
  </div>

  <ConfirmationDialog
    v-model:open="showActivation"
    :title="activationLabel"
    :confirm-label="activationLabel"
    description="New contracts of this type will use this workflow. In-progress contracts keep their assigned version."
    @confirm="doSave('active')"
  />
</template>
