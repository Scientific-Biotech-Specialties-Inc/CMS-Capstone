<script setup lang="ts">
import { watch, onUnmounted } from 'vue'
import { X, Clock, ChevronDown } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import type { ContractVersionSnapshot } from '@/types/contractAmendment'

export interface DiffItem {
  field: string
  old: string
  new: string
}

const props = defineProps<{
  open: boolean
  snapshots: ContractVersionSnapshot[]
  activeVersion: number
  activeSnapForDiff: Record<string, unknown> | null
  expandedVersion: number | null
  getDiffList: (snap: any) => DiffItem[]
}>()

const emit = defineEmits<{
  close: []
  toggle: [version: number]
  viewSnapshot: [version: number, date: string]
}>()

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
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
  } else {
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
    <!-- Overlay: full-viewport coverage, anchored to body. -->
    <Transition name="vh-overlay">
      <div
        v-if="open"
        class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40"
        @click="emit('close')"
      />
    </Transition>

    <!-- Drawer: inset-y-0 + h-dvh guarantees top-to-bottom coverage. -->
    <Transition name="vh-panel" @after-leave="onPanelAfterLeave">
      <aside
        v-if="open"
        class="fixed inset-y-0 right-0 h-dvh w-full sm:w-[480px] bg-white border-l border-black/10 shadow-2xl z-50 flex flex-col font-poppins"
        role="dialog"
        aria-modal="true"
        aria-label="Version History"
      >
      <!-- Drawer Header -->
      <div class="p-6 border-b border-black/5 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Clock class="w-5 h-5 text-brand-blue" />
          <h3 class="text-base font-bold text-black">Version History</h3>
        </div>
        <button
          @click="emit('close')"
          class="p-1.5 hover:bg-black/5 rounded-lg text-black/40 hover:text-black transition-colors"
          aria-label="Close version history"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Drawer Content -->
      <div class="flex-1 overflow-y-auto p-6 space-y-4">
        <!-- Versions Timeline: same continuous-spine principle as the
             workflow tracker (shared vertical-timeline.css). Rails sit
             LAST in DOM so stagger indices align with the steps. -->
        <div :key="String(open)" class="wf-timeline relative mx-auto w-full max-w-[360px] list-none space-y-6 pl-8">
          <!-- Current Active Version -->
          <div class="wf-step relative">
            <!-- Timeline dot -->
            <div class="wf-step-dot absolute -left-[29px] top-[21px] block h-2 w-2 rounded-full bg-white border-2 border-brand-navy ring-4 ring-brand-navy/15" />

            <div
              @click="emit('toggle', activeVersion)"
              class="group cursor-pointer rounded-xl border border-primary bg-primary p-4 shadow-md transition-all duration-200"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white text-brand-navy text-[10px] font-bold uppercase tracking-wider">
                  Version {{ activeVersion }} (Current)
                </span>
                <span class="text-[10px] text-white/75 font-medium">Active</span>
              </div>
              <h4 class="text-sm font-bold text-white mt-1.5">Latest approved state</h4>
              <p class="text-xs text-white/75 mt-0.5">Active details shown on main contract page</p>

              <!-- Expanded Diff Comparison for Current vs Last historical version -->
              <div v-if="expandedVersion === activeVersion && activeSnapForDiff" class="mt-4 pt-4 border-t border-white/15 space-y-3 cursor-default" @click.stop>
                <h5 class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-2">Changes in this version</h5>

                <div v-if="getDiffList(activeSnapForDiff).length === 0" class="text-xs text-white/60 text-center py-2">
                  No detail changes (only documents updated).
                </div>
                <div v-else class="space-y-2.5">
                  <div v-for="diff in getDiffList(activeSnapForDiff)" :key="diff.field" class="text-xs">
                    <span class="font-semibold text-white/70 block capitalize text-[10px]">{{ diff.field }}</span>
                    <div class="flex flex-col gap-1 mt-1 font-mono bg-white/10 p-1.5 rounded border border-white/15">
                      <span class="text-rose-200 line-through whitespace-pre-wrap shrink-0 block">
                        - {{ diff.old }}
                      </span>
                      <span class="text-emerald-200 font-semibold whitespace-pre-wrap shrink-0 block">
                        + {{ diff.new }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Historical Versions -->
          <div v-for="snap in snapshots" :key="snap.version" class="wf-step relative">
            <!-- Timeline dot -->
            <div class="wf-step-dot absolute -left-[29px] top-[21px] block h-2 w-2 rounded-full bg-white border-[1.5px] border-black/25" />

            <div
              @click="emit('toggle', snap.version)"
              class="group cursor-pointer hover:bg-black/[0.02] p-4 rounded-xl border border-black/[0.04] transition-all duration-200"
              :class="expandedVersion === snap.version ? 'bg-black/[0.01] border-brand-blue/30 shadow-sm' : ''"
            >
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-brand-navy uppercase tracking-wide">
                  Version {{ snap.version }}
                </span>
                <span class="text-[10px] text-black/35 font-medium">{{ snap.approvedDate }}</span>
              </div>
              <p class="text-xs text-black/70 mt-1.5 line-clamp-2">{{ snap.reason }}</p>

              <div class="flex items-center justify-between mt-3 text-[10px] text-black/40">
                <span>By: {{ snap.amendedBy }}</span>
                <span class="flex items-center gap-1 font-semibold text-black/60">
                  Approved: {{ snap.approvedBy }}
                  <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="expandedVersion === snap.version ? 'rotate-180' : ''" />
                </span>
              </div>

              <!-- Expanded Diff Comparison -->
              <div v-if="expandedVersion === snap.version" class="mt-4 pt-4 border-t border-black/[0.05] space-y-3 cursor-default" @click.stop>
                <h5 class="text-[10px] font-bold uppercase tracking-widest text-black/40 mb-2">Changes in this version</h5>

                <div v-if="getDiffList(snap).length === 0" class="text-xs text-black/35 text-center py-2">
                  No detail changes (only documents updated).
                </div>
                <div v-else class="space-y-2.5">
                  <div v-for="diff in getDiffList(snap)" :key="diff.field" class="text-xs">
                    <span class="font-semibold text-black/50 block capitalize text-[10px]">{{ diff.field }}</span>
                    <div class="flex flex-col gap-1 mt-1 font-mono bg-black/[0.01] p-1.5 rounded border border-black/[0.03]">
                      <span class="text-red-600 line-through whitespace-pre-wrap shrink-0 block">
                        - {{ diff.old }}
                      </span>
                      <span class="text-emerald-600 font-semibold whitespace-pre-wrap shrink-0 block">
                        + {{ diff.new }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- View Snapshot button -->
                <div class="pt-3 border-t border-black/[0.03] flex justify-end">
                  <Button
                    @click="emit('viewSnapshot', snap.version, snap.approvedDate)"
                    variant="outline"
                    class="h-8 text-xs font-semibold border-brand-blue text-brand-blue hover:bg-brand-blue/5 flex items-center gap-1.5 transition-colors"
                  >
                    <Clock class="w-3.5 h-3.5" />
                    View Snapshot
                  </Button>
                </div>
              </div>
            </div>
          </div>

          <!-- Spine rails LAST in DOM so stagger indices align with steps. -->
          <div class="absolute left-[6px] top-4 bottom-4 w-0.5 bg-black/[0.08]" aria-hidden="true" />
          <div class="wf-spine-fill absolute left-[6px] top-4 bottom-4 w-0.5 origin-top bg-brand-blue" aria-hidden="true" />
        </div>
      </div>
      </aside>
    </Transition>
  </Teleport>
</template>

<style scoped>
.vh-overlay-enter-active,
.vh-overlay-leave-active {
  transition: opacity 0.25s ease;
}

.vh-overlay-enter-from,
.vh-overlay-leave-to {
  opacity: 0;
}

.vh-panel-enter-active {
  transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.vh-panel-leave-active {
  transition: transform 0.24s ease-in;
}

.vh-panel-enter-from,
.vh-panel-leave-to {
  transform: translateX(100%);
}

@media (prefers-reduced-motion: reduce) {
  .vh-overlay-enter-active,
  .vh-overlay-leave-active,
  .vh-panel-enter-active,
  .vh-panel-leave-active {
    transition: none;
  }
}
</style>

<!-- Shared spine + stagger motion, same file the workflow tracker uses. -->
<style scoped src="../../../../components/shared/ContractWorkflowTracker/vertical-timeline.css"></style>
