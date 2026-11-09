<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useWorkflowBuilder } from '@/composables/useWorkflowBuilder'
import ContractTypeWorkflowRow from './ContractTypeWorkflowRow.vue'
import WorkflowDraftList from './WorkflowDraftList.vue'
import DuplicateWorkflowDialog from './DuplicateWorkflowDialog.vue'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const { success, error: toastError } = useToast()
const router = useRouter()

const {
  loading,
  error,
  contractTypes,
  fetchContractTypesWithWorkflows,
  fetchWorkflow,
  deleteWorkflow,
  saveWorkflow,
} = useWorkflowBuilder()

const drafts = computed(() => contractTypes.value.filter(type => type.draft_workflow))

function openEditor(contractTypeId: number, workflowId?: number) {
  router.push({ name: 'admin-workflow-editor', params: { contractTypeId, workflowId } })
}

// ── Duplicate dialog state ───────────────────────────────────────────────
const showDuplicateDialog = ref(false)
const duplicateSource = ref<{ workflowId: number; name: string } | null>(null)

function openDuplicate(workflowId: number, name: string) {
  duplicateSource.value = { workflowId, name }
  showDuplicateDialog.value = true
}

async function handleDuplicated() {
  showDuplicateDialog.value = false
  duplicateSource.value = null
  success('Workflow duplicated', 'A new draft has been created from the copy.')
  await refresh()
}

// ── Delete ────────────────────────────────────────────────────────────
const showDeleteConfirm = ref(false)
const deleteTargetId = ref<number | null>(null)
const isDeleting = ref(false)

function confirmDelete(workflowId: number) {
  deleteTargetId.value = workflowId
  showDeleteConfirm.value = true
}

async function executeDelete() {
  if (!deleteTargetId.value) return
  isDeleting.value = true
  try {
    await deleteWorkflow(deleteTargetId.value)
    success('Workflow deleted', 'The workflow has been removed.')
    showDeleteConfirm.value = false
    await refresh()
  } catch (e: any) {
    toastError('Could not delete workflow', e.message ?? 'Something went wrong.')
  } finally {
    isDeleting.value = false
  }
}

// ── Deactivate ────────────────────────────────────────────────────────
const showDeactivateConfirm = ref(false)
const deactivateTargetId = ref<number | null>(null)
const isDeactivating = ref(false)

function confirmDeactivate(workflowId: number) {
  deactivateTargetId.value = workflowId
  showDeactivateConfirm.value = true
}

async function executeDeactivate() {
  if (!deactivateTargetId.value) return
  isDeactivating.value = true
  try {
    const existing = await fetchWorkflow(deactivateTargetId.value)
    if (!existing) {
      throw new Error('Workflow not found.')
    }
    await saveWorkflow(
      {
        contract_type_id: existing.contract_type_id,
        name: existing.name,
        status: 'draft',
        resubmit_mode: existing.resubmit_mode,
        execution_reviewers: existing.execution_reviewers ?? undefined,
        steps: existing.steps,
      },
      deactivateTargetId.value
    )
    success('Workflow deactivated', 'The workflow has been set back to draft.')
    showDeactivateConfirm.value = false
    await refresh()
  } catch (e: any) {
    toastError('Could not deactivate workflow', e.message ?? 'Something went wrong.')
  } finally {
    isDeactivating.value = false
  }
}

// ── Load ──────────────────────────────────────────────────────────────
const refresh = fetchContractTypesWithWorkflows
onMounted(refresh)
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-sm font-semibold text-black">Approval Workflows</h2>
      <p class="mt-2 font-montserrat text-xs text-slate-600">
        Manage internal approval and execution reviewers for each contract type.
      </p>
    </div>

    <!-- Loading skeleton -->
    <ul v-if="loading && !contractTypes.length" aria-busy="true" aria-label="Loading approval workflows" class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
      <li v-for="i in 4" :key="i" class="flex gap-4 p-4">
        <span class="h-8 flex-1 animate-pulse rounded-md bg-slate-100" />
        <span class="h-8 flex-1 animate-pulse rounded-md bg-slate-100" />
      </li>
    </ul>

    <!-- Error state -->
    <div v-else-if="error"
      class="flex items-center gap-3 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm">
      <AlertCircle class="w-4 h-4 shrink-0" />
      <span>{{ error }}</span>
      <Button variant="outline" size="sm" class="ml-auto" @click="refresh">Retry</Button>
    </div>

    <!-- Loaded state -->
    <template v-else>
      <WorkflowDraftList :contract-types="drafts" @edit="openEditor" @delete="confirmDelete" />
      <section aria-labelledby="workflows-by-type" class="rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-4">
          <h3 id="workflows-by-type" class="font-semibold text-slate-900">Workflows by Contract Type</h3>
          <p class="font-montserrat text-xs text-slate-600">Each type can have one active workflow. Otherwise, new contracts use manager approval.</p>
        </div>
        <p v-if="!contractTypes.length" class="p-4 text-sm text-slate-600">No contract types are available.</p>
        <ul v-else class="divide-y divide-slate-200">
          <ContractTypeWorkflowRow
            v-for="ct in contractTypes"
            :key="ct.contract_type_id"
            :contract-type="ct"
            @create="openEditor(ct.contract_type_id)"
            @edit="(workflowId: number) => openEditor(ct.contract_type_id, workflowId)"
            @duplicate="openDuplicate"
            @deactivate="confirmDeactivate"
          />
        </ul>
      </section>
    </template>

    <DuplicateWorkflowDialog
      v-if="showDuplicateDialog && duplicateSource"
      :open="showDuplicateDialog"
      :source-workflow-id="duplicateSource.workflowId"
      :source-name="duplicateSource.name"
      :contract-types="contractTypes"
      @close="showDuplicateDialog = false"
      @duplicated="handleDuplicated"
    />

    <ConfirmationDialog
      v-model:open="showDeleteConfirm"
      title="Delete Workflow"
      description="Are you sure you want to delete this workflow? This cannot be undone. Workflows that have already been used by a contract cannot be deleted."
      confirm-label="Delete"
      variant="destructive"
      :loading="isDeleting"
      @confirm="executeDelete"
    />

    <ConfirmationDialog
      v-model:open="showDeactivateConfirm"
      title="Deactivate Workflow"
      description="New contracts of this type will use manager approval. Existing contracts keep their assigned workflow."
      confirm-label="Deactivate"
      variant="destructive"
      :loading="isDeactivating"
      @confirm="executeDeactivate"
    />
  </div>
</template>
