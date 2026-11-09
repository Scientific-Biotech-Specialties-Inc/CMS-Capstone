<script setup lang="ts">
import { Ellipsis, Pencil, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import type { ContractTypeWithWorkflow } from '@/types/workflow'

defineProps<{ contractTypes: ContractTypeWithWorkflow[] }>()
const emit = defineEmits<{
  edit: [contractTypeId: number, workflowId: number]
  delete: [workflowId: number]
}>()
</script>

<template>
  <section v-if="contractTypes.length" aria-labelledby="workflow-drafts" class="rounded-xl border border-slate-200 bg-white">
    <div class="border-b border-slate-200 p-4">
      <h3 id="workflow-drafts" class="font-semibold text-slate-900">Drafts</h3>
      <p class="font-montserrat text-xs text-slate-600">Saved changes waiting to be activated.</p>
    </div>
    <ul class="divide-y divide-slate-200">
      <template v-for="type in contractTypes" :key="type.contract_type_id">
        <li v-if="type.draft_workflow" class="flex flex-wrap items-center justify-between gap-4 p-4">
          <div class="min-w-0 flex-1 basis-48">
            <p class="break-words text-sm font-semibold text-slate-900">{{ type.draft_workflow.name }}</p>
            <p class="font-montserrat text-xs text-slate-600">
              {{ type.contract_type_name }} · {{ type.active_workflow ? 'Replaces ' + type.active_workflow.name : 'Replaces default manager approval' }} when activated
            </p>
          </div>
          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="emit('edit', type.contract_type_id, type.draft_workflow!.id)">
              <Pencil class="size-4" /> Continue Editing
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" :aria-label="'Actions for draft ' + type.draft_workflow.name">
                  <Ellipsis class="size-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem class="text-red-700" @select="emit('delete', type.draft_workflow!.id)">
                  <Trash2 class="size-4" /> Delete Draft
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </li>
      </template>
    </ul>
  </section>
</template>
