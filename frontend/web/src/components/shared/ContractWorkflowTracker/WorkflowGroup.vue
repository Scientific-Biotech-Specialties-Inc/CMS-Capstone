<script setup lang="ts">
import { computed } from 'vue'
import type { WorkflowGroup } from '@/types/contractWorkflow'
import { groupStatus } from '@/types/contractWorkflow'
import WorkflowNode from './WorkflowNode.vue'

const props = withDefaults(defineProps<{
  group: WorkflowGroup
  activeNodeId: string | null // the single "current" node across the whole run, if any
  orientation?: 'horizontal' | 'vertical'
}>(), { orientation: 'horizontal' })

const parallelLabel = computed(() =>
  `Parallel steps: ${props.group.nodes.map(n => n.title).join(' and ')}`
)

const isVertical = computed(() => props.orientation === 'vertical')

// Spine dot for the parallel card as a whole, driven by the group
// status (spec 5.3), mirroring the single-step dot language.
const groupDot = computed(() => {
  switch (groupStatus(props.group)) {
    case 'completed': return 'bg-brand-blue'
    case 'active':
    case 'on_hold':   return 'bg-white border-2 border-brand-blue ring-4 ring-brand-blue/15'
    case 'rejected':  return 'bg-red-500'
    case 'canceled':  return 'bg-black/20'
    default:          return 'bg-white border-[1.5px] border-black/25'
  }
})
</script>

<template>
  <!-- Sequential: a single step (card in vertical mode, circle in
       horizontal mode — decided inside WorkflowNode). -->
  <template v-if="group.mode === 'sequential'">
    <WorkflowNode :node="group.nodes[0]" :is-current="group.nodes[0].id === activeNodeId" :layout="orientation" />
  </template>

  <!-- Vertical parallel: one card attached to the spine holding the
       stacked sub-steps as plain rows (spec 5.6 grouping preserved in
       the same card language as single steps). -->
  <li v-else-if="isVertical" class="relative list-none" role="group" :aria-label="parallelLabel">
    <span
      class="wf-step-dot absolute -left-[29px] top-[21px] block h-2 w-2 rounded-full"
      :class="groupDot"
      aria-hidden="true"
    />
    <div class="border-2 border-dashed border-black/15 rounded-xl bg-white px-4 py-3">
      <p class="text-[10px] font-semibold text-black/35 uppercase tracking-wide">Parallel</p>
      <ol class="mt-3 space-y-3 list-none">
        <WorkflowNode
          v-for="node in group.nodes"
          :key="node.id"
          :node="node"
          :is-current="node.id === activeNodeId"
          layout="vertical"
          nested
        />
      </ol>
    </div>
  </li>

  <!-- Horizontal parallel: sub-steps stack vertically within this one
       slot under a labeled, bracketed container (spec 5.6). -->
  <li v-else class="list-none shrink-0" role="group" :aria-label="parallelLabel">
    <div class="border-2 border-dashed border-black/15 rounded-xl px-3 py-3 space-y-4">
      <p class="text-[10px] font-semibold text-black/35 uppercase tracking-wide text-center">Parallel</p>
      <ol class="flex flex-col gap-4 list-none">
        <WorkflowNode
          v-for="node in group.nodes"
          :key="node.id"
          :node="node"
          :is-current="node.id === activeNodeId"
        />
      </ol>
    </div>
  </li>
</template>
