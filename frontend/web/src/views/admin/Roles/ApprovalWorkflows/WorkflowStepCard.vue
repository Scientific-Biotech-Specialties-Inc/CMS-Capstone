<script setup lang="ts">
import { Plus, X, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { WorkflowStep, WorkflowStepMode } from '@/types/workflow'

const props = defineProps<{
  step: WorkflowStep
  stepIndex: number
}>()

defineEmits<{
  'change-mode': [mode: WorkflowStepMode]
  'add-role': []
  'remove-role': [roleId: number]
  'remove-step': []
}>()
</script>

<template>
  <div class="bg-black/[0.02] rounded-lg border border-black/8 p-4 space-y-3">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <span class="w-6 h-6 rounded-full bg-brand-navy/10 text-brand-navy text-xs font-bold flex items-center justify-center shrink-0">
          {{ stepIndex + 1 }}
        </span>
        <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-0.5">
          <button @click="$emit('change-mode', 'sequential')"
            class="px-2.5 py-1 text-xs rounded font-medium transition-all"
            :class="step.mode === 'sequential' ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
            Sequential
          </button>
          <button @click="$emit('change-mode', 'parallel')"
            class="px-2.5 py-1 text-xs rounded font-medium transition-all"
            :class="step.mode === 'parallel' ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
            Parallel
          </button>
        </div>
      </div>
      <button @click="$emit('remove-step')" class="text-black/30 hover:text-red-500 transition-colors p-1">
        <Trash2 class="w-3.5 h-3.5" />
      </button>
    </div>

    <p v-if="step.mode === 'parallel'" class="text-xs text-black/35">
      All assigned roles must act. If one rejects, the step fails immediately and the others are canceled.
    </p>

    <div class="flex flex-wrap items-center gap-2">
      <span v-for="role in step.roles" :key="role.auth_role_id"
        class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 rounded-full bg-brand-navy/6 border border-brand-navy/20 text-brand-navy text-xs font-medium">
        {{ role.role_name }}
        <button @click="$emit('remove-role', role.auth_role_id)" class="hover:text-red-500 transition-colors">
          <X class="w-3 h-3" />
        </button>
      </span>

      <Button variant="outline" size="sm" @click="$emit('add-role')"
        class="h-7 px-2.5 text-xs border-dashed border-black/20 text-black/50 hover:text-black hover:border-black/35">
        <Plus class="w-3 h-3" /> Add Role
      </Button>
    </div>

    <p v-if="!step.roles.length" class="text-xs text-amber-600">
      This step needs at least one role before the workflow can be activated.
    </p>
  </div>
</template>
