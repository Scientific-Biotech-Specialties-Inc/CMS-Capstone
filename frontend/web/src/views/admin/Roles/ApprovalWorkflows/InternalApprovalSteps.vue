<script setup lang="ts">
import { ref } from 'vue'
import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { AssignableRole, WorkflowStep } from '@/types/workflow'
import WorkflowStepCard from './WorkflowStepCard.vue'
import RolePickerDialog from './RolePickerDialog.vue'

defineProps<{ roles: AssignableRole[] }>()
const steps = defineModel<WorkflowStep[]>({ required: true })
const selectedStep = ref<number | null>(null)

function addStep() {
  steps.value = [...steps.value, { order_index: steps.value.length + 1, mode: 'sequential', roles: [] }]
}

function updateStep(index: number, patch: Partial<WorkflowStep>) {
  steps.value = steps.value.map((step, i) => i === index ? { ...step, ...patch } : step)
}

function removeStep(index: number) {
  steps.value = steps.value.filter((_, i) => i !== index).map((step, i) => ({ ...step, order_index: i + 1 }))
}

function addRole(role: AssignableRole) {
  const index = selectedStep.value
  if (index === null) return
  const step = steps.value[index]
  if (step && !step.roles.some(item => item.auth_role_id === role.id)) {
    updateStep(index, { roles: [...step.roles, { auth_role_id: role.id, role_name: role.name }] })
  }
  selectedStep.value = null
}
</script>

<template>
  <section aria-labelledby="internal-approval-title" class="min-w-0 space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 id="internal-approval-title" class="font-semibold text-slate-900">Internal Approval</h2>
      <Button variant="outline" size="sm" @click="addStep"><Plus class="size-4" /> Add Step</Button>
    </div>
    <p v-if="!steps.length" class="rounded-lg border border-dashed border-slate-200 p-4 text-sm text-slate-600">
      Add at least one step with a role before activating this workflow.
    </p>
    <WorkflowStepCard
      v-for="(step, index) in steps"
      :key="step.id === undefined ? 'new-' + index : 'saved-' + step.id"
      :step="step"
      :step-index="index"
      @change-mode="mode => updateStep(index, { mode })"
      @add-role="selectedStep = index"
      @remove-role="roleId => updateStep(index, { roles: step.roles.filter(role => role.auth_role_id !== roleId) })"
      @remove-step="removeStep(index)"
    />
    <p class="font-montserrat text-xs text-slate-600">Replacing a previously approved draft restarts internal approval from the first step.</p>
  </section>
  <RolePickerDialog
    :open="selectedStep !== null"
    :roles="roles"
    :already-selected-ids="selectedStep === null ? [] : steps[selectedStep]?.roles.map(role => role.auth_role_id) ?? []"
    @close="selectedStep = null"
    @select="addRole"
  />
</template>
