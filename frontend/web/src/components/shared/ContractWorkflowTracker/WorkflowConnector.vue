<script setup lang="ts">
import { computed } from 'vue'
import { ArrowRight } from 'lucide-vue-next'
import type { NodeStatus } from '@/types/contractWorkflow'

// Connector style is driven by the LOWER/next group's status (spec 5.3),
// not the current one — the parent passes that status in directly.
const props = withDefaults(defineProps<{
  nextGroupStatus: NodeStatus
  orientation?: 'horizontal' | 'vertical'
}>(), { orientation: 'horizontal' })

const solidStatuses: NodeStatus[] = ['completed', 'active', 'on_hold']

const isSolid = computed(() => solidStatuses.includes(props.nextGroupStatus))
</script>

<template>
  <!-- Horizontal arrow between step circles. Solid colored for
       completed/active paths; dashed muted for future (locked) paths
       (spec 5.3) — the dash pattern itself is the second signal, never
       color-only. -->
  <div
    v-if="orientation === 'horizontal'"
    class="flex items-center justify-center px-1 shrink-0 pt-6"
    aria-hidden="true"
  >
    <div
      class="h-0.5 w-6 sm:w-10"
      :class="solidStatuses.includes(nextGroupStatus) ? 'bg-brand-blue' : 'border-t-2 border-dashed border-black/20'"
    />
    <ArrowRight
      class="w-3.5 h-3.5 -ml-1 shrink-0"
      :class="solidStatuses.includes(nextGroupStatus) ? 'text-brand-blue' : 'text-black/25'"
    />
  </div>

  <!-- Vertical connector for drawer mode: a line-and-dot link
       (-----o-----) in a fixed 20px column — the exact width of the
       dot column — so the spine sits precisely under the dot
       center. Each segment is a light track with a brand-blue fill
       that wipes top-to-bottom like a loading bar; pending paths keep
       the dashed muted treatment and only fade in (spec 5.3). -->
  <div
    v-else
    class="tracker-link flex w-5 flex-col items-center shrink-0 -my-0.5"
    aria-hidden="true"
  >
    <div
      class="tracker-link-seg relative w-0.5 h-6 overflow-hidden"
      :class="isSolid ? 'bg-black/10' : ''"
    >
      <div v-if="isSolid" class="tracker-link-fill absolute inset-0 origin-top bg-brand-blue" />
      <div v-else class="tracker-link-dashed absolute inset-0 border-l-2 border-dashed border-black/20" />
    </div>
    <div
      class="tracker-link-dot my-0.5 h-2 w-2 rounded-full"
      :class="isSolid ? 'tracker-link-dot--solid bg-brand-blue' : 'bg-white border-[1.5px] border-black/25'"
    />
    <div
      class="tracker-link-seg relative w-0.5 h-6 overflow-hidden"
      :class="isSolid ? 'bg-black/10' : ''"
    >
      <div v-if="isSolid" class="tracker-link-fill tracker-link-fill--bottom absolute inset-0 origin-top bg-brand-blue" />
      <div v-else class="tracker-link-dashed absolute inset-0 border-l-2 border-dashed border-black/20" />
    </div>
  </div>
</template>

<style scoped>
/* Loading-fill: light track sits underneath while the blue fill wipes
   top-to-bottom. Runs after the step above has entered (150ms offset)
   via the inherited --tracker-delay cascade. */
@keyframes tracker-link-fill {
  from { transform: scaleY(0); }
  to   { transform: scaleY(1); }
}

@keyframes tracker-link-fade {
  from { opacity: 0; }
  to   { opacity: 1; }
}

@keyframes tracker-link-pop {
  0%   { transform: scale(0); opacity: 0; }
  60%  { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}

.tracker-link-fill {
  animation: tracker-link-fill 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
  animation-delay: calc(var(--tracker-delay, 0ms) + 150ms);
}

.tracker-link-fill--bottom {
  animation-delay: calc(var(--tracker-delay, 0ms) + 400ms);
}

.tracker-link-dashed {
  animation: tracker-link-fade 0.4s ease-out both;
  animation-delay: var(--tracker-delay, 0ms);
}

.tracker-link-dot {
  animation: tracker-link-fade 0.3s ease-out both;
  animation-delay: var(--tracker-delay, 0ms);
}

.tracker-link-dot--solid {
  animation-name: tracker-link-pop;
  animation-duration: 0.35s;
  animation-delay: calc(var(--tracker-delay, 0ms) + 550ms);
}

@media (prefers-reduced-motion: reduce) {
  .tracker-link-fill,
  .tracker-link-dashed,
  .tracker-link-dot {
    animation: none;
  }
}
</style>
