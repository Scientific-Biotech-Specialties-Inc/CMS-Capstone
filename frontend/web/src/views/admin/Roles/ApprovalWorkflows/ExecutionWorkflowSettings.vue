<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { AssignableRole, ExecutionReviewers, ExecutionReviewStage } from '@/types/workflow'
import RolePickerDialog from './RolePickerDialog.vue'

const props = defineProps<{ reviewers: ExecutionReviewers; roles: AssignableRole[] }>()
const emit = defineEmits<{ change: [reviewers: ExecutionReviewers] }>()
const activeStage = ref<ExecutionReviewStage | null>(null)
const stages: { stage: ExecutionReviewStage; next: string; fallback: string }[] = [
  { stage: 'SBSI Review', next: 'Client Review', fallback: 'Manager' },
  { stage: 'Client Review', next: 'For Signing', fallback: 'Manager' },
  { stage: 'For Signing', next: 'Signed', fallback: 'Manager or CEO' },
  { stage: 'Signed', next: 'Notarized PDF (optional)', fallback: 'Manager' },
]
const eligibleRoles = computed(() => activeStage.value === 'For Signing'
  ? props.roles.filter(role => ['Manager', 'CEO'].includes(role.name)) : props.roles)

function addRole(role: AssignableRole) {
  if (!activeStage.value) return
  const stage = activeStage.value
  emit('change', { ...props.reviewers, [stage]: [...(props.reviewers[stage] ?? []), { auth_role_id: role.id, role_name: role.name }] })
  activeStage.value = null
}

function removeRole(stage: ExecutionReviewStage, roleId: number) {
  emit('change', { ...props.reviewers, [stage]: props.reviewers[stage]?.filter(role => role.auth_role_id !== roleId) ?? [] })
}
</script>

<template>
  <section class="space-y-4 border-t border-slate-200 pt-6" aria-labelledby="execution-settings-title">
    <div>
      <h3 id="execution-settings-title" class="font-semibold text-slate-800">Execution Workflow</h3>
      <p class="mt-2 text-sm text-slate-600">The owner submits each stage. One eligible reviewer confirms it. The bypass permission can confirm a submission in the same action.</p>
    </div>
    <ol class="divide-y divide-slate-200 rounded-xl border border-slate-200">
      <li v-for="row in stages" :key="row.stage" class="flex flex-wrap items-center justify-between gap-4 p-4">
        <div>
          <p class="text-sm font-semibold text-slate-800">{{ row.stage }} → {{ row.next }}</p>
          <p v-if="!reviewers[row.stage]?.length" class="mt-2 text-sm text-slate-600">{{ row.fallback }} by default</p>
          <div v-else class="mt-2 flex flex-wrap gap-2">
            <Button v-for="role in reviewers[row.stage]" :key="role.auth_role_id" type="button" variant="outline" size="sm"
              :aria-label="`Remove ${role.role_name} from ${row.stage}`" @click="removeRole(row.stage, role.auth_role_id)">
              {{ role.role_name }} <X class="size-4" />
            </Button>
          </div>
        </div>
        <Button type="button" variant="outline" size="sm" @click="activeStage = row.stage">
          <Plus class="size-4" /> Add Reviewer
        </Button>
      </li>
    </ol>
  </section>
  <RolePickerDialog :open="activeStage !== null" :roles="eligibleRoles"
    :already-selected-ids="activeStage ? reviewers[activeStage]?.map(role => role.auth_role_id) ?? [] : []"
    @close="activeStage = null" @select="addRole" />
</template>
