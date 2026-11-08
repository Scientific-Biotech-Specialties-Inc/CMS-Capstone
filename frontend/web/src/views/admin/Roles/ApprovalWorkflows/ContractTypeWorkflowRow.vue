<script setup lang="ts">
import { Plus, Pencil, Copy, Ellipsis, PowerOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import type { ContractTypeWithWorkflow } from '@/types/workflow'

const props = defineProps<{
  contractType: ContractTypeWithWorkflow
}>()

const emit = defineEmits<{
  create: []
  edit: [workflowId: number]
  duplicate: [workflowId: number, name: string]
  deactivate: [workflowId: number]
}>()
</script>

<template>
  <li class="grid gap-4 p-4 sm:grid-cols-2 sm:items-center lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_12rem]">
    <div class="min-w-0">
      <p class="break-words text-sm font-semibold text-slate-900">{{ contractType.contract_type_name }}</p>
      <p v-if="!contractType.is_active" class="text-xs text-slate-600">Inactive Contract Type</p>
    </div>
    <div v-if="contractType.active_workflow" class="min-w-0">
      <p class="break-words text-sm font-medium text-slate-900">{{ contractType.active_workflow.name }}</p>
      <p class="font-montserrat text-xs text-slate-600">
        {{ contractType.active_workflow.step_count }} internal approval {{ contractType.active_workflow.step_count === 1 ? 'step' : 'steps' }} · Option 1B
      </p>
    </div>
    <p v-else class="font-montserrat text-sm text-slate-600">Uses Manager Approval</p>
    <div v-if="contractType.active_workflow || !contractType.draft_workflow" class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-1 lg:justify-end">
      <template v-if="contractType.active_workflow">
        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Active</span>
        <Button v-if="!contractType.draft_workflow" variant="outline" size="sm" @click="emit('edit', contractType.active_workflow.id)">
          <Pencil class="size-4" /> Edit
        </Button>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" :aria-label="'Actions for ' + contractType.contract_type_name">
              <Ellipsis class="size-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @select="emit('duplicate', contractType.active_workflow!.id, contractType.active_workflow!.name)">
              <Copy class="size-4" /> Duplicate
            </DropdownMenuItem>
            <DropdownMenuItem class="text-red-700" @select="emit('deactivate', contractType.active_workflow!.id)">
              <PowerOff class="size-4" /> Deactivate
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>
      <Button v-else-if="!contractType.draft_workflow" variant="outline" size="sm" @click="emit('create')">
        <Plus class="size-4" /> Create Workflow
      </Button>
    </div>
  </li>
</template>
