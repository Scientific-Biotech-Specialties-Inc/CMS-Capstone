<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useWorkflowBuilder } from '@/composables/useWorkflowBuilder'
import type { AssignableRole, ContractTypeWithWorkflow, Workflow } from '@/types/workflow'
import WorkflowEditor from './WorkflowEditor.vue'

const route = useRoute()
const router = useRouter()
const back = { name: 'admin-roles', query: { tab: 'workflows' } }
const { success } = useToast()
const { fetchContractTypesWithWorkflows, fetchAssignableRoles, fetchWorkflow } = useWorkflowBuilder()
const assignableRoles = ref<AssignableRole[]>([])
const contractType = ref<ContractTypeWithWorkflow>()
const workflow = ref<Workflow | null>(null)
const loading = ref(true)
const loadError = ref('')
const attempt = ref(0)
const contractTypeId = computed(() => Number(route.params.contractTypeId))
const workflowId = computed(() => route.params.workflowId ? Number(route.params.workflowId) : null)

watch([contractTypeId, workflowId, attempt], async ([typeId, id], _, onCleanup) => {
  let cancelled = false
  onCleanup(() => { cancelled = true })
  loading.value = true
  loadError.value = ''

  const [types, roles, selected] = await Promise.all([
    fetchContractTypesWithWorkflows(),
    fetchAssignableRoles(),
    id === null || !Number.isSafeInteger(id) || id < 1 ? null : fetchWorkflow(id),
  ])
  if (cancelled) return

  contractType.value = types.find(type => type.contract_type_id === typeId)
  workflow.value = selected
  assignableRoles.value = roles ?? []
  if (!contractType.value || (id !== null && selected?.contract_type_id !== typeId)) {
    loadError.value = 'Could not load this workflow. Check the link or try again.'
  } else if (roles === null) {
    loadError.value = 'Could not load assignable roles. Try again.'
  }
  loading.value = false
}, { immediate: true })

async function saved(status: 'draft' | 'active') {
  if (status === 'active') success('Workflow activated', 'New contracts of this type will use this version.')
  else success('Workflow draft saved')
  await router.replace(back)
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 p-4 sm:p-8">
    <Button as-child variant="outline" size="sm" class="self-start">
      <RouterLink :to="back"><ArrowLeft class="size-4" /> Back to Approval Workflows</RouterLink>
    </Button>
    <div v-if="loading" aria-label="Loading workflow editor" aria-busy="true" class="space-y-4">
      <div class="h-12 w-2/3 animate-pulse rounded-lg bg-slate-200" />
      <div class="grid gap-4 xl:grid-cols-2">
        <div v-for="column in 2" :key="column" class="h-64 animate-pulse rounded-xl bg-slate-200" />
      </div>
    </div>
    <div v-else-if="loadError" role="alert" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      <p>{{ loadError }}</p>
      <Button variant="outline" size="sm" @click="attempt++">Retry</Button>
    </div>
    <WorkflowEditor
      v-else-if="contractType"
      :contract-type="contractType"
      :workflow="workflow"
      :assignable-roles="assignableRoles"
      @saved="saved"
    />
  </div>
</template>
