<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { AlertCircle, Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useWorkflowBuilder } from '@/composables/useWorkflowBuilder'
import type { ContractTypeWithWorkflow, Workflow } from '@/types/workflow'
import ContractTypeWorkflowCard from './ContractTypeWorkflowCard.vue'
import WorkflowEditor from './WorkflowEditor.vue'
import DuplicateWorkflowDialog from './DuplicateWorkflowDialog.vue'
import AddContractTypeDialog from './AddContractTypeDialog.vue'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const { success, error: toastError } = useToast()

const {
  loading,
  error,
  contractTypes,
  assignableRoles,
  fetchContractTypesWithWorkflows,
  fetchAssignableRoles,
  fetchWorkflow,
  deleteWorkflow,
  saveWorkflow,
} = useWorkflowBuilder()

// ── Editor state ───────────────────────────────────────────────────────
const showEditor = ref(false)
const editingWorkflow = ref<Workflow | null>(null)
const editingContractType = ref<ContractTypeWithWorkflow | null>(null)

async function openCreate(contractType: ContractTypeWithWorkflow) {
  editingContractType.value = contractType
  editingWorkflow.value = null
  showEditor.value = true
}

async function openEdit(contractType: ContractTypeWithWorkflow, workflowId: number) {
  const workflow = await fetchWorkflow(workflowId)
  if (!workflow) {
    toastError('Failed to load workflow', 'Please try again.')
    return
  }
  editingContractType.value = contractType
  editingWorkflow.value = workflow
  showEditor.value = true
}

function closeEditor() {
  showEditor.value = false
  editingWorkflow.value = null
  editingContractType.value = null
}

async function handleSaved() {
  closeEditor()
  success('Workflow saved', 'The approval workflow has been saved.')
  await refresh()
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
const showAddContractTypeDialog = ref(false)

async function handleContractTypeCreated(created: ContractTypeWithWorkflow) {
  showAddContractTypeDialog.value = false
  success('Contract type added', `"${created.contract_type_name}" is now available.`)
  await refresh()
}

async function refresh() {
  await Promise.all([fetchContractTypesWithWorkflows(), fetchAssignableRoles()])
}

onMounted(refresh)
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 class="text-sm font-semibold text-black">Approval Workflows</h2>
        <p class="text-xs text-black/40 mt-0.5">
          Define the approval chain for each contract type. Only one active workflow applies per type —
          types without one fall back to the standard manager approval.
        </p>
      </div>
      <Button @click="showAddContractTypeDialog = true"
        class="h-9 px-3.5 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm shrink-0">
        <Plus class="w-4 h-4" /> Add Contract Type
      </Button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && !contractTypes.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="i in 4" :key="i" class="h-32 rounded-xl border border-black/8 bg-black/3 animate-pulse" />
    </div>

    <!-- Error state -->
    <div v-else-if="error"
      class="flex items-center gap-3 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm">
      <AlertCircle class="w-4 h-4 shrink-0" />
      <span>{{ error }}</span>
      <button @click="refresh" class="ml-auto font-semibold underline text-xs">Retry</button>
    </div>

    <!-- Loaded state -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <ContractTypeWorkflowCard
        v-for="ct in contractTypes"
        :key="ct.contract_type_id"
        :contract-type="ct"
        @create="openCreate(ct)"
        @edit="(workflowId: number) => openEdit(ct, workflowId)"
        @duplicate="(workflowId: number, name: string) => openDuplicate(workflowId, name)"
        @delete="confirmDelete"
        @deactivate="confirmDeactivate"
      />
    </div>

    <WorkflowEditor
      v-if="showEditor && editingContractType"
      :open="showEditor"
      :contract-type="editingContractType"
      :workflow="editingWorkflow"
      :assignable-roles="assignableRoles"
      @close="closeEditor"
      @saved="handleSaved"
    />

    <DuplicateWorkflowDialog
      v-if="showDuplicateDialog && duplicateSource"
      :open="showDuplicateDialog"
      :source-workflow-id="duplicateSource.workflowId"
      :source-name="duplicateSource.name"
      :contract-types="contractTypes"
      @close="showDuplicateDialog = false"
      @duplicated="handleDuplicated"
    />

    <AddContractTypeDialog
      :open="showAddContractTypeDialog"
      @close="showAddContractTypeDialog = false"
      @created="handleContractTypeCreated"
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
      description="Are you sure you want to deactivate this workflow? Contracts of this type will revert to the default manager approval flow. You can re-activate the workflow at any time."
      confirm-label="Deactivate"
      variant="destructive"
      :loading="isDeactivating"
      @confirm="executeDeactivate"
    />
  </div>
</template>
