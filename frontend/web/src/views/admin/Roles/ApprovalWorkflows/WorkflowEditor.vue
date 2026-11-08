<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { Plus, Save, Loader2 } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useWorkflowBuilder } from '@/composables/useWorkflowBuilder'
import type { AssignableRole, ContractTypeWithWorkflow, ExecutionReviewers, ResubmitMode, Workflow, WorkflowStep } from '@/types/workflow'
import WorkflowStepCard from './WorkflowStepCard.vue'
import RolePickerDialog from './RolePickerDialog.vue'
import ExecutionWorkflowSettings from './ExecutionWorkflowSettings.vue'

const props = defineProps<{
  open: boolean
  contractType: ContractTypeWithWorkflow
  workflow: Workflow | null  // null = creating a new workflow
  assignableRoles: AssignableRole[]
}>()

const emit = defineEmits<{
  close: []
  saved: [status: 'draft' | 'active']
}>()

const { error: toastError } = useToast()
const { saveWorkflow } = useWorkflowBuilder()

const isEditing = computed(() => !!props.workflow)
const createsVersion = computed(() => props.workflow?.status === 'active' || props.workflow?.has_runs === true)
const activeVersion = computed(() => props.contractType.active_workflow?.id)

const form = reactive({
  name: props.workflow?.name ?? `${props.contractType.contract_type_name} Approval`,
  resubmitMode: (props.workflow?.resubmit_mode ?? 'restart') as ResubmitMode,
})

// Deep-cloned so editing doesn't mutate the parent's cached workflow object
// until a successful save.
const steps = reactive<WorkflowStep[]>(
  props.workflow ? JSON.parse(JSON.stringify(props.workflow.steps)) : []
)

const saving = ref(false)
const reviewers = ref<ExecutionReviewers>({ ...props.workflow?.execution_reviewers })
const saveError = ref<string | null>(null)

function addStep() {
  steps.push({
    order_index: steps.length + 1,
    mode: 'sequential',
    roles: [],
  })
}

function removeStep(index: number) {
  steps.splice(index, 1)
  steps.forEach((s, i) => { s.order_index = i + 1 })
}

function setStepMode(index: number, mode: 'sequential' | 'parallel') {
  steps[index].mode = mode
}

// ── Role picker (one dialog shared across steps) ──────────────────────
const rolePickerOpenForStep = ref<number | null>(null)

function openRolePicker(stepIndex: number) {
  rolePickerOpenForStep.value = stepIndex
}

function handleRoleSelected(role: AssignableRole) {
  const stepIndex = rolePickerOpenForStep.value
  if (stepIndex === null) return
  const step = steps[stepIndex]
  if (!step.roles.some(r => r.auth_role_id === role.id)) {
    step.roles.push({ auth_role_id: role.id, role_name: role.name })
  }
  rolePickerOpenForStep.value = null
}

function removeRole(stepIndex: number, roleId: number) {
  const step = steps[stepIndex]
  step.roles = step.roles.filter(r => r.auth_role_id !== roleId)
}

// ── Save ──────────────────────────────────────────────────────────────
async function doSave(status: 'draft' | 'active') {
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
      steps: steps.map((s, i) => ({ ...s, order_index: i + 1 })),
    }, savedId)
    emit('saved', status)
  } catch (e: any) {
    saveError.value = e.message ?? 'Failed to save workflow.'
    toastError('Could not save workflow', saveError.value ?? undefined)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v: boolean) => !v && emit('close')">
    <DialogContent class="max-w-2xl max-h-[90dvh] overflow-y-auto p-4 sm:p-6 gap-4">
      <DialogHeader>
        <DialogTitle class="text-sm font-bold text-black">
          {{ isEditing ? 'Edit Workflow' : 'Create Workflow' }} — {{ contractType.contract_type_name }}
        </DialogTitle>
        <DialogDescription class="text-xs text-black/40 mt-1">
          Set the internal approval chain and choose who confirms each execution stage.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <p v-if="activeVersion || createsVersion" class="rounded-lg border border-brand-blue/20 bg-brand-blue/5 p-4 text-sm text-slate-700">
          Activating this version applies it to new contracts. Existing contracts keep their assigned workflow.
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">Workflow Name</label>
            <input v-model="form.name" type="text"
              class="h-9 rounded-lg border border-black/12 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue/15 focus:border-brand-blue transition" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-black/55">On Rejection</label>
            <select v-model="form.resubmitMode"
              class="h-9 rounded-lg border border-black/12 px-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-blue/15 focus:border-brand-blue transition">
              <option value="restart">Restart from the beginning</option>
              <option value="resume_at_rejected">Resume at the rejected step</option>
            </select>
          </div>
        </div>

        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Internal Approval</h3>
            <Button variant="outline" size="sm" @click="addStep" class="h-7 px-2.5 text-xs border-black/15 text-black/60 hover:text-black">
              <Plus class="w-3 h-3" /> Add Step
            </Button>
          </div>

          <p v-if="!steps.length" class="text-xs text-black/35 py-6 text-center bg-black/[0.02] rounded-lg border border-dashed border-black/10">
            No steps yet. This can be saved as a draft, but needs at least one step with a role before it can be activated.
          </p>

          <WorkflowStepCard
            v-for="(step, i) in steps"
            :key="i"
            :step="step"
            :step-index="i"
            @change-mode="(mode) => setStepMode(i, mode)"
            @add-role="openRolePicker(i)"
            @remove-role="(roleId) => removeRole(i, roleId)"
            @remove-step="removeStep(i)"
          />
        </div>
        <p class="text-sm text-slate-600">Replacing a previously approved draft restarts internal approval from the first step.</p>
        <ExecutionWorkflowSettings :reviewers="reviewers" :roles="assignableRoles" @change="reviewers = $event" />

        <p v-if="saveError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ saveError }}
        </p>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4">
        <Button variant="outline" @click="emit('close')" :disabled="saving"
          class="h-9 px-4 text-sm border-black/15 text-black/65 hover:text-black hover:bg-black/4">
          Cancel
        </Button>
        <Button variant="outline" @click="doSave('draft')" :disabled="saving"
          class="h-9 px-4 text-sm border-black/15 text-black/65 hover:text-black hover:bg-black/4">
          <Loader2 v-if="saving" class="w-3.5 h-3.5 animate-spin" />
          {{ createsVersion ? 'Save as New Draft' : 'Save as Draft' }}
        </Button>
        <Button @click="doSave('active')" :disabled="saving"
          class="h-9 px-4 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm">
          <Loader2 v-if="saving" class="w-3.5 h-3.5 animate-spin" />
          <Save v-else class="w-3.5 h-3.5" />
          {{ activeVersion || createsVersion ? 'Activate New Version' : 'Activate' }}
        </Button>
      </div>
    </DialogContent>
  </Dialog>

  <RolePickerDialog
    :open="rolePickerOpenForStep !== null"
    :roles="assignableRoles"
    :already-selected-ids="rolePickerOpenForStep !== null ? steps[rolePickerOpenForStep].roles.map(r => r.auth_role_id) : []"
    @close="rolePickerOpenForStep = null"
    @select="handleRoleSelected"
  />
</template>
