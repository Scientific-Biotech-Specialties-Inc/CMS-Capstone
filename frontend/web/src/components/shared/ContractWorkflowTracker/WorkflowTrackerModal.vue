<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { X, GitBranch, CheckCircle2 } from 'lucide-vue-next'
import type { TrackerStatus, WorkflowProgress } from '@/types/contractWorkflow'
import type { ExecutionAction, ExecutionState } from '@/composables/useContractExecution'
import ContractWorkflowTracker from './index.vue'

const props = withDefaults(defineProps<{
  open: boolean
  workflow: WorkflowProgress | null
  status: TrackerStatus
  onRetry?: () => void
  initialTab?: 'approval' | 'execution'
  executionState?: ExecutionState | null
  executionSubmitLabel?: string
}>(), { initialTab: 'approval' })

const emit = defineEmits<{
  'update:open': [value: boolean]
  executionAction: [action: ExecutionAction]
}>()

const currentTab = ref<'approval' | 'execution'>(props.initialTab)

function close() {
  emit('update:open', false)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') close()
}

function lockScroll() {
  document.addEventListener('keydown', onKeydown)
  document.body.style.overflow = 'hidden'
}

function unlockScroll() {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
}

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    lockScroll()
    currentTab.value = props.initialTab
  } else {
    // Panel leave-transition still plays; overflow restores on after-leave.
    document.removeEventListener('keydown', onKeydown)
  }
})

function onPanelAfterLeave() {
  if (!props.open) unlockScroll()
}

onUnmounted(() => unlockScroll())
</script>

<template>
  <Teleport to="body">
    <!-- Single static wrapper: two transition roots directly inside a
         Teleport confuse Vue's anchor bookkeeping on unmount (e.g.
         navigating away mid-animation throws "Cannot read properties of
         null (reading 'parentNode')"). One wrapper = one anchor. -->
    <div>
    <Transition name="tracker-overlay">
      <div
        v-if="open"
        class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40"
        @click="close"
      />
    </Transition>

    <!-- Side panel: same shell as the Version History drawer. Teleported
         to body + inset-y-0/h-dvh so top and bottom edges are fully
         covered with no gap. -->
    <Transition name="tracker-panel" @after-leave="onPanelAfterLeave">
      <aside
        v-if="open"
        class="fixed inset-y-0 right-0 h-dvh w-full sm:w-[480px] bg-white border-l border-black/10 shadow-2xl z-50 flex flex-col font-poppins"
        role="dialog"
        aria-modal="true"
        aria-label="Contract Workflow Tracker"
      >
        <!-- Drawer Header -->
        <div class="p-6 border-b border-black/5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <GitBranch class="w-5 h-5 text-brand-blue" />
              <h3 class="text-base font-bold text-black">Contract Workflow Tracker</h3>
            </div>
            <button
              @click="close"
              class="p-1.5 hover:bg-black/5 rounded-lg text-black/40 hover:text-black transition-colors"
              aria-label="Close workflow tracker"
            >
              <X class="w-5 h-5" />
            </button>
          </div>
          <p class="text-xs text-black/40 mt-1.5">
            Track internal approval steps and post-approval execution stages.
          </p>

          <!-- Tab Switcher -->
          <div class="mt-4 flex p-1 bg-black/5 rounded-xl border border-black/5 gap-1 font-poppins">
            <button
              type="button"
              @click="currentTab = 'approval'"
              class="flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5"
              :class="currentTab === 'approval' ? 'bg-white text-brand-navy shadow-sm' : 'text-black/50 hover:text-black hover:bg-black/[0.02]'"
            >
              Internal Approval
            </button>
            <button
              type="button"
              @click="currentTab = 'execution'"
              class="flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold transition-all flex items-center justify-center gap-1.5"
              :class="currentTab === 'execution' ? 'bg-white text-brand-navy shadow-sm' : 'text-black/50 hover:text-black hover:bg-black/[0.02]'"
            >
              Execution Stages
              <span v-if="workflow?.executionGroups?.length" class="inline-flex w-1.5 h-1.5 rounded-full bg-brand-blue" />
            </button>
          </div>
        </div>

        <!-- Drawer Content: vertical timeline, scrolls independently -->
        <!-- :key re-mounts the timeline on each open / tab switch so the stagger
             cascade replays smoothly. -->
        <div class="flex-1 overflow-y-auto p-6">
          <ContractWorkflowTracker
            :key="`${String(open)}-${currentTab}`"
            :workflow="workflow"
            :status="status"
            :on-retry="onRetry"
            :tab="currentTab"
            orientation="vertical"
            bare
          />
        </div>

        <!-- Drawer Footer: Execution Action Trigger when viewing Execution Stages -->
        <div v-if="currentTab === 'execution' && $slots['execution-details']" class="max-h-64 shrink-0 overflow-y-auto border-t border-black/5">
          <slot name="execution-details" />
        </div>
        <div v-else-if="currentTab === 'execution' && executionState?.enabled" class="p-4 border-t border-black/5 bg-slate-50/80 flex flex-col gap-2">
          <div class="flex items-center justify-between text-xs">
            <span class="text-black/50 font-medium">Current Execution Stage:</span>
            <span class="font-bold text-brand-navy">{{ executionState.stage ?? 'Pending' }}</span>
          </div>

          <div class="flex gap-2 empty:hidden">
            <template v-if="executionState.allowed?.confirm">
              <button
                type="button"
                @click="$emit('executionAction', 'confirm')"
                class="flex-1 py-2 px-3 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm flex items-center justify-center gap-1.5 transition-colors"
              >
                <CheckCircle2 class="w-3.5 h-3.5" />
                Confirm Submission
              </button>
              <button
                type="button"
                @click="$emit('executionAction', 'return')"
                class="py-2 px-3 rounded-lg text-xs font-medium border border-black/15 text-black/65 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition-colors"
              >
                Return
              </button>
            </template>

            <template v-else-if="executionState.allowed?.submit">
              <button
                type="button"
                @click="$emit('executionAction', 'submit')"
                class="w-full py-2.5 px-3 rounded-lg text-xs font-semibold bg-brand-navy hover:bg-brand-dark text-white shadow-sm flex items-center justify-center gap-1.5 transition-colors"
              >
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400" />
                {{ executionSubmitLabel ?? 'Update Execution Status' }}
              </button>
            </template>

            <template v-else-if="executionState.allowed?.resume">
              <button
                type="button"
                @click="$emit('executionAction', 'resume')"
                class="w-full py-2.5 px-3 rounded-lg text-xs font-semibold border border-brand-navy text-brand-navy hover:bg-brand-navy/5 transition-colors"
              >
                Resume Workflow
              </button>
            </template>

            <template v-else-if="executionState.allowed?.revise">
              <button
                type="button"
                @click="$emit('executionAction', 'revise')"
                class="w-full py-2.5 px-3 rounded-lg text-xs font-semibold bg-brand-navy hover:bg-brand-dark text-white shadow-sm transition-colors"
              >
                Resubmit for Approval
              </button>
            </template>

            <span v-else-if="executionState.awaiting_confirmation" role="status" class="rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-900">
              Awaiting Confirmation
            </span>
          </div>
        </div>
      </aside>
    </Transition>
    </div>
  </Teleport>
</template>

<style scoped>
.tracker-overlay-enter-active,
.tracker-overlay-leave-active {
  transition: opacity 0.25s ease;
}

.tracker-overlay-enter-from,
.tracker-overlay-leave-to {
  opacity: 0;
}

.tracker-panel-enter-active {
  transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.tracker-panel-leave-active {
  transition: transform 0.24s ease-in;
}

.tracker-panel-enter-from,
.tracker-panel-leave-to {
  transform: translateX(100%);
}

@media (prefers-reduced-motion: reduce) {
  .tracker-overlay-enter-active,
  .tracker-overlay-leave-active,
  .tracker-panel-enter-active,
  .tracker-panel-leave-active {
    transition: none;
  }
}
</style>
