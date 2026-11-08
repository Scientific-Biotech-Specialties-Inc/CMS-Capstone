<script setup lang="ts">
import { Plus, Pencil, Copy, Trash2, FileText, PowerOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { ContractTypeWithWorkflow } from '@/types/workflow'

const props = defineProps<{
  contractType: ContractTypeWithWorkflow
}>()

const emit = defineEmits<{
  create: []
  edit: [workflowId: number]
  duplicate: [workflowId: number, name: string]
  delete: [workflowId: number]
  deactivate: [workflowId: number]
}>()
</script>

<template>
  <div class="bg-white rounded-xl border border-black/8 p-5 space-y-3">
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
          <FileText class="w-4 h-4 text-brand-navy" />
        </div>
        <div>
          <p class="text-sm font-semibold text-black">{{ contractType.contract_type_name }}</p>
          <p v-if="!contractType.is_active" class="text-xs text-black/35 mt-0.5">Contract type inactive</p>
        </div>
      </div>

      <span v-if="contractType.active_workflow"
        class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/60 shrink-0">
        Active
      </span>
      <span v-else-if="contractType.draft_workflow"
        class="text-xs font-semibold px-2.5 py-1 rounded-full bg-black/4 text-black/50 border border-black/8 shrink-0">
        Draft only
      </span>
      <span v-else
        class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200/60 shrink-0">
        No workflow
      </span>
    </div>

    <!-- Active workflow summary -->
    <div v-if="contractType.active_workflow" class="bg-black/[0.02] rounded-lg border border-black/6 p-3">
      <p class="text-sm font-medium text-black">{{ contractType.active_workflow.name }}</p>
      <p class="mt-2 text-sm text-slate-600">{{ contractType.active_workflow.step_count }} internal approval step(s) · Option 1B execution</p>
      <div class="flex items-center gap-2 mt-2">
        <Button v-if="!contractType.draft_workflow" variant="outline" size="sm" @click="emit('edit', contractType.active_workflow!.id)"
          class="h-7 px-2.5 text-xs border-black/15 text-black/60 hover:text-black">
          <Pencil class="w-3 h-3" /> Edit
        </Button>
        <Button variant="outline" size="sm" @click="emit('duplicate', contractType.active_workflow!.id, contractType.active_workflow!.name)"
          class="h-7 px-2.5 text-xs border-black/15 text-black/60 hover:text-black">
          <Copy class="w-3 h-3" /> Duplicate
        </Button>
        <Button variant="outline" size="sm" @click="emit('deactivate', contractType.active_workflow!.id)"
          class="h-7 px-2.5 text-xs border-red-200 text-red-600 hover:bg-red-50 ml-auto">
          <PowerOff class="w-3 h-3" /> Deactivate
        </Button>
      </div>
    </div>

    <!-- A replacement draft can be edited while the active workflow serves new contracts. -->
    <div v-if="contractType.draft_workflow" class="bg-black/[0.02] rounded-lg border border-black/6 p-3">
      <p class="text-sm font-medium text-black">{{ contractType.draft_workflow.name }}</p>
      <p class="mt-2 text-sm text-slate-600">Draft · {{ contractType.draft_workflow.step_count }} internal approval step(s)</p>
      <div class="flex items-center gap-2 mt-2">
        <Button variant="outline" size="sm" @click="emit('edit', contractType.draft_workflow!.id)"
          class="h-7 px-2.5 text-xs border-black/15 text-black/60 hover:text-black">
          <Pencil class="w-3 h-3" /> Continue editing
        </Button>
        <Button variant="outline" size="sm" @click="emit('delete', contractType.draft_workflow!.id)"
          class="h-7 px-2.5 text-xs border-red-200 text-red-600 hover:bg-red-50">
          <Trash2 class="w-3 h-3" /> Delete
        </Button>
      </div>
    </div>

    <!-- No workflow at all -->
    <div v-if="!contractType.active_workflow && !contractType.draft_workflow" class="bg-black/[0.02] rounded-lg border border-black/6 p-3">
      <p class="text-xs text-black/45">Contracts of this type use the default manager approval flow.</p>
    </div>

    <Button v-if="!contractType.active_workflow && !contractType.draft_workflow" @click="emit('create')"
      class="w-full h-9 bg-brand-navy hover:bg-brand-dark text-white text-sm font-medium">
      <Plus class="w-4 h-4" /> Create Workflow
    </Button>
  </div>
</template>
