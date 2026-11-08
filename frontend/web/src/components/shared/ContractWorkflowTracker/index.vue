<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, Inbox } from 'lucide-vue-next'
import type { TrackerStatus, WorkflowProgress, WorkflowGroup as WorkflowGroupType } from '@/types/contractWorkflow'
import { groupStatus } from '@/types/contractWorkflow'
import WorkflowGroup from './WorkflowGroup.vue'
import WorkflowConnector from './WorkflowConnector.vue'
import RunHistoryToggle from './RunHistoryToggle.vue'

// Presentational only (per the workflow engine plan, Phase 6): this
// component renders whatever data it's given. It does not fetch, does not
// check permissions, and exposes no approve/reject actions — those stay
// in ContractDetailHeader.vue / the rejection-input section already on
// the contract detail page.
const props = withDefaults(defineProps<{
  workflow: WorkflowProgress | null
  status: TrackerStatus
  onRetry?: () => void
  // When true, skips this component's own card chrome (border/shadow/
  // padding) — used when it's already hosted inside another card-like
  // container, e.g. WorkflowTrackerModal's drawer panel.
  bare?: boolean
  orientation?: 'horizontal' | 'vertical'
  tab?: 'approval' | 'execution'
}>(), { orientation: 'vertical', tab: 'approval' })

const latestRun = computed(() => {
  if (!props.workflow || !props.workflow.runs.length) return null
  return props.workflow.runs[props.workflow.runs.length - 1]
})

const previousRuns = computed(() => {
  if (!props.workflow || props.workflow.runs.length < 2) return []
  return props.workflow.runs.slice(0, -1)
})

// The single node currently "on someone's desk" across the latest run —
// used to apply the emphasized "Current" treatment (spec 5.1's distinction
// between active and merely-pending). on_hold counts as current too: the
// node keeps its amber empty-role badge (decision #12) via its own
// status, while still getting the current-step emphasis here.
const activeNodeId = computed<string | null>(() => {
  if (!latestRun.value) return null
  for (const group of latestRun.value.groups) {
    const active = group.nodes.find(n => n.status === 'active' || n.status === 'on_hold')
    if (active) return active.id
  }
  return null
})

const activeExecutionNodeId = computed<string | null>(() => {
  if (!props.workflow?.executionGroups?.length) return null
  for (const group of props.workflow.executionGroups) {
    const active = group.nodes.find(n => n.status === 'active' || n.status === 'on_hold')
    if (active) return active.id
  }
  return null
})

function nextGroupStatus(groups: WorkflowGroupType[], index: number) {
  const next = groups[index + 1]
  return next ? groupStatus(next) : null
}
</script>

<template>
  <div :class="bare ? 'space-y-4' : 'bg-white rounded-xl border border-black/[0.08] shadow-sm p-6 space-y-4'">
    <h2 v-if="!bare" class="text-xs font-semibold text-black/40 uppercase tracking-widest">
      {{ tab === 'execution' ? 'Execution Pipeline' : 'Approval Progress' }}
    </h2>

    <!-- Loading skeleton -->
    <div v-if="status === 'loading'" class="space-y-4">
      <div v-for="i in 4" :key="i" class="flex gap-3">
        <div class="w-8 h-8 rounded-full bg-black/5 animate-pulse shrink-0" />
        <div class="flex-1 space-y-1.5 pt-1">
          <div class="h-3 w-32 bg-black/5 animate-pulse rounded" />
          <div class="h-2.5 w-24 bg-black/5 animate-pulse rounded" />
        </div>
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="status === 'error'" class="flex flex-col items-center gap-3 py-10">
      <AlertCircle class="w-8 h-8 text-red-400" />
      <p class="text-sm text-black/50">Couldn't load workflow progress</p>
      <button v-if="onRetry" @click="onRetry"
        class="text-xs font-semibold text-brand-blue hover:text-brand-navy underline">
        Retry
      </button>
    </div>

    <!-- Execution tab view -->
    <template v-else-if="tab === 'execution'">
      <div v-if="!workflow?.executionGroups?.length" class="flex flex-col items-center gap-2 py-10 text-black/35">
        <Inbox class="w-8 h-8" />
        <p class="text-sm">Execution workflow begins after final internal approval.</p>
      </div>
      <ol v-else class="wf-timeline relative mx-auto w-full max-w-[360px] list-none space-y-6 pl-8">
        <WorkflowGroup
          v-for="group in workflow.executionGroups"
          :key="group.id"
          :group="group"
          :active-node-id="activeExecutionNodeId"
          orientation="vertical"
        />
        <div class="absolute bottom-3 left-[6px] top-3 w-0.5 bg-black/[0.08]" aria-hidden="true" />
        <div class="wf-spine-fill absolute bottom-3 left-[6px] top-3 w-0.5 origin-top bg-brand-blue" aria-hidden="true" />
      </ol>
    </template>

    <!-- Approval tab empty state -->
    <div v-else-if="status === 'empty' || !latestRun" class="flex flex-col items-center gap-2 py-10 text-black/35">
      <Inbox class="w-8 h-8" />
      <p class="text-sm">No approval workflow is set for this contract.</p>
    </div>

    <!-- Ready state: vertical mode is one continuous spine (same
         principle as Version History) with cards attached to it;
         horizontal only when explicitly requested. -->
    <template v-else-if="status === 'ready' && latestRun">
      <RunHistoryToggle v-for="run in previousRuns" :key="run.runNumber" :run="run" :orientation="orientation">
        <template v-for="(group, i) in run.groups" :key="group.id">
          <WorkflowGroup :group="group" :active-node-id="null" :orientation="orientation" />
          <WorkflowConnector v-if="orientation === 'horizontal' && i < run.groups.length - 1" :next-group-status="nextGroupStatus(run.groups, i)!" />
        </template>
      </RunHistoryToggle>

      <ol v-if="orientation === 'vertical'" class="wf-timeline relative mx-auto w-full max-w-[360px] list-none space-y-6 pl-8">
        <WorkflowGroup
          v-for="group in latestRun.groups"
          :key="group.id"
          :group="group"
          :active-node-id="activeNodeId"
          orientation="vertical"
        />
        <!-- Spine rails LAST in DOM so the nth-child stagger indices
             align with the steps. Absolute positioning keeps them
             visually behind the cards. -->
        <div class="absolute bottom-3 left-[6px] top-3 w-0.5 bg-black/[0.08]" aria-hidden="true" />
        <div class="wf-spine-fill absolute bottom-3 left-[6px] top-3 w-0.5 origin-top bg-brand-blue" aria-hidden="true" />
      </ol>

      <ol v-else class="flex items-start overflow-x-auto pb-2 list-none">
        <template v-for="(group, i) in latestRun.groups" :key="group.id">
          <WorkflowGroup :group="group" :active-node-id="activeNodeId" orientation="horizontal" />
          <WorkflowConnector v-if="i < latestRun.groups.length - 1" :next-group-status="nextGroupStatus(latestRun.groups, i)!" orientation="horizontal" />
        </template>
      </ol>
    </template>
  </div>
</template>

<style scoped src="./vertical-timeline.css"></style>
