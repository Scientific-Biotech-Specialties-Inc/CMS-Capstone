<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, Clock, X, Minus, AlertTriangle, Pencil } from 'lucide-vue-next'
import type { WorkflowNode } from '@/types/contractWorkflow'

const props = defineProps<{
  node: WorkflowNode
  isCurrent: boolean // true when this node is the single "active" one across the whole run
  layout?: 'horizontal' | 'vertical'
  // Nested nodes render inside a parallel group's card: plain rows with
  // no spine dot, no card, no entrance of their own (the parent card
  // animates as one unit).
  nested?: boolean
}>()

const isVertical = computed(() => props.layout === 'vertical')

// Each status gets a distinct icon SHAPE (not just a color) plus an
// accessible text label (never color-only). "Locked" (pending) steps use
// a muted outline circle to read as not-yet-reachable, "unlocked" steps
// (completed/active) use the brand-filled circle.
const statusMeta = computed(() => {
  switch (props.node.status) {
    case 'completed':
      return { icon: Check, label: 'Completed', circle: 'bg-brand-navy border-brand-navy text-white', title: 'text-black', ring: '' }
    case 'active':
      return { icon: Clock, label: 'In progress, current step', circle: 'bg-white border-brand-blue text-brand-blue', title: 'text-black', ring: 'ring-4 ring-brand-blue/15' }
    case 'on_hold':
      return { icon: AlertTriangle, label: 'On hold, no active holder for this role', circle: 'bg-amber-50 border-amber-400 text-amber-600', title: 'text-black', ring: '' }
    case 'rejected':
      return { icon: X, label: 'Rejected', circle: 'bg-red-500 border-red-500 text-white', title: 'text-black', ring: '' }
    case 'canceled':
      return { icon: Minus, label: 'Canceled', circle: 'bg-black/5 border-black/15 text-black/35', title: 'text-black/40', ring: '' }
    default:
      return { icon: Clock, label: 'Not started, locked', circle: 'bg-black/[0.03] border-black/15 text-black/25', title: 'text-black/35', ring: '' }
  }
})

// Vertical card language (mirrors Version History): a status pill on the
// right of the title row plus a spine-dot style, so status is never
// color-only.
const pillMeta = computed(() => {
  switch (props.node.status) {
    case 'completed':
      return { text: 'Done', cls: 'bg-brand-blue/10 text-brand-blue', title: 'text-black', dot: 'bg-brand-blue' }
    case 'active':
      return { text: 'Now', cls: 'bg-white text-brand-navy', title: 'text-white', dot: 'bg-white border-2 border-brand-navy ring-4 ring-brand-navy/15' }
    case 'on_hold':
      return { text: 'On hold', cls: 'bg-amber-50 text-amber-600', title: 'text-black', dot: 'bg-amber-400' }
    case 'rejected':
      return { text: 'Rejected', cls: 'bg-red-50 text-red-600', title: 'text-black', dot: 'bg-red-500' }
    case 'canceled':
      return { text: 'Canceled', cls: 'bg-black/[0.05] text-black/40', title: 'text-black/40', dot: 'bg-black/20' }
    default:
      return { text: 'Pending', cls: 'bg-black/[0.04] text-black/40', title: 'text-black/40', dot: 'bg-white border-[1.5px] border-black/25' }
  }
})

const longDate = computed(() => {
  if (!props.node.actedAt) return null
  return new Date(props.node.actedAt).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
})

const isoDate = computed(() => props.node.actedAt ? new Date(props.node.actedAt).toISOString().slice(0, 10) : null)

const dateOrWaitingText = computed(() => {
  if (longDate.value) return longDate.value
  if (props.node.status === 'active') return 'Awaiting action'
  if (props.node.status === 'on_hold') return 'On hold'
  return null
})

// Delegation sub-text replaces any generic text (spec 4.3/5.4); if a
// comment also exists, it appears on the next line.
const subTextLines = computed(() => {
  const lines: string[] = []
  if (props.node.delegation) {
    lines.push(`Approved by ${props.node.delegation.delegateName} on behalf of ${props.node.delegation.onBehalfOfRole}`)
  }
  if (props.node.subText) {
    lines.push(props.node.subText)
  }
  return lines
})

const expanded = ref(false)
const isLong = computed(() => subTextLines.value.join(' ').length > 90)
</script>

<template>
  <!-- Vertical: version-history-style card attached to the continuous
       spine (dot positioned over the rail at the timeline's left edge).
       Entrance + dot-pop animations come from the shared
       vertical-timeline.css via the parent .wf-timeline. -->
  <li
    v-if="isVertical && !nested"
    class="relative list-none"
    :aria-current="isCurrent ? 'step' : undefined"
  >
    <span
      class="wf-step-dot absolute -left-[29px] top-[21px] block h-2 w-2 rounded-full"
      :class="pillMeta.dot"
      aria-hidden="true"
    />
    <span class="sr-only">{{ statusMeta.label }}</span>
    <div
      class="rounded-xl border p-4 transition-colors"
      :class="isCurrent
        ? 'bg-primary border-primary shadow-md'
        : node.status === 'completed'
          ? 'bg-brand-blue/[0.05] border-brand-blue/30'
          : 'bg-white border-black/[0.08]'"
    >
      <div class="flex items-center justify-between gap-2">
        <span class="flex min-w-0 items-center gap-1 text-sm font-bold leading-snug" :class="pillMeta.title">
          <span class="truncate">{{ node.title }}</span>
          <span v-if="node.roleDeleted" class="inline-flex shrink-0" title="This role has been deleted">
            <AlertTriangle class="w-3 h-3 text-amber-500 shrink-0" />
            <span class="sr-only">Role deleted</span>
          </span>
          <span v-else-if="node.roleRenamedFrom" class="inline-flex shrink-0" :title="`Previously named: ${node.roleRenamedFrom}`">
            <Pencil class="w-2.5 h-2.5 shrink-0" :class="isCurrent ? 'text-white/70' : 'text-black/35'" />
            <span class="sr-only">Role renamed from {{ node.roleRenamedFrom }}</span>
          </span>
        </span>
        <span
          class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
          :class="pillMeta.cls"
        >
          {{ pillMeta.text }}
        </span>
      </div>
      <time v-if="dateOrWaitingText" :datetime="isoDate ?? undefined" class="block mt-0.5 text-xs" :class="isCurrent ? 'text-white/75' : 'text-black/40'">
        {{ dateOrWaitingText }}
      </time>
      <div v-if="subTextLines.length" class="mt-1 text-xs" :class="[isCurrent ? 'text-white/85' : 'text-black/55', !expanded && isLong ? 'line-clamp-2' : '']">
        <p v-for="(line, i) in subTextLines" :key="i">{{ line }}</p>
      </div>
      <button v-if="isLong" @click="expanded = !expanded" class="mt-0.5 text-[11px] font-medium" :class="isCurrent ? 'text-white hover:text-white underline underline-offset-2' : 'text-brand-blue hover:text-brand-navy'">
        {{ expanded ? 'Show less' : 'Show more' }}
      </button>
    </div>
  </li>

  <!-- Vertical nested (inside a parallel group's card): plain row, no
       dot, no card, no entrance of its own. -->
  <li v-else-if="isVertical && nested" class="list-none">
    <span class="sr-only">{{ statusMeta.label }}</span>
    <div class="flex items-center justify-between gap-2">
      <span class="flex min-w-0 items-center gap-1 text-xs font-semibold" :class="pillMeta.title">
        <span class="truncate">{{ node.title }}</span>
        <span v-if="node.roleDeleted" class="inline-flex shrink-0" title="This role has been deleted">
          <AlertTriangle class="w-3 h-3 text-amber-500 shrink-0" />
        </span>
        <span v-else-if="node.roleRenamedFrom" class="inline-flex shrink-0" :title="`Previously named: ${node.roleRenamedFrom}`">
          <Pencil class="w-2.5 h-2.5 text-black/35 shrink-0" />
        </span>
      </span>
      <span
        class="shrink-0 rounded-full px-1.5 py-px text-[9px] font-bold uppercase tracking-wide"
        :class="pillMeta.cls"
      >
        {{ pillMeta.text }}
      </span>
    </div>
    <time v-if="dateOrWaitingText" :datetime="isoDate ?? undefined" class="text-[11px] text-black/40 block mt-0.5">
      {{ dateOrWaitingText }}
    </time>
    <div v-if="subTextLines.length" class="text-[11px] text-black/55 mt-0.5">
      <p v-for="(line, i) in subTextLines" :key="i">{{ line }}</p>
    </div>
  </li>

  <!-- Horizontal: centered step under its circle (unchanged). -->
  <li
    v-else
    class="tracker-node flex flex-col items-center text-center shrink-0 w-28"
    :aria-current="isCurrent ? 'step' : undefined"
  >
    <!-- Circle -->
    <div class="relative w-12 shrink-0">
      <div
        class="w-12 h-12 rounded-full border-2 flex items-center justify-center shrink-0 transition-all"
        :class="[statusMeta.circle, statusMeta.ring, isCurrent ? 'tracker-current' : '']"
      >
        <component :is="statusMeta.icon" class="w-5 h-5" />
      </div>
      <span v-if="isCurrent"
        class="tracker-now absolute -top-2 -right-2 text-[9px] font-bold uppercase tracking-wide text-white bg-brand-blue px-1.5 py-0.5 rounded-full shadow-sm">
        Now
      </span>
    </div>
    <span class="sr-only">{{ statusMeta.label }}</span>

    <!-- Title -->
    <div class="mt-2.5 flex items-center justify-center gap-1">
      <span class="text-xs font-semibold leading-tight" :class="statusMeta.title">{{ node.title }}</span>

      <!-- Decision #14: deleted role warning -->
      <span v-if="node.roleDeleted" class="inline-flex" title="This role has been deleted">
        <AlertTriangle class="w-3 h-3 text-amber-500 shrink-0" />
        <span class="sr-only">Role deleted</span>
      </span>
      <!-- Decision #14: renamed role indicator, previous name on hover -->
      <span v-else-if="node.roleRenamedFrom" class="inline-flex" :title="`Previously named: ${node.roleRenamedFrom}`">
        <Pencil class="w-2.5 h-2.5 text-black/35 shrink-0" />
        <span class="sr-only">Role renamed from {{ node.roleRenamedFrom }}</span>
      </span>
    </div>

    <!-- Date -->
    <time v-if="dateOrWaitingText" :datetime="isoDate ?? undefined" class="text-[11px] text-black/40 block mt-0.5">
      {{ dateOrWaitingText }}
    </time>

    <!-- Sub-text -->
    <div v-if="subTextLines.length" class="text-[11px] text-black/55 mt-1" :class="!expanded && isLong ? 'line-clamp-2' : ''">
      <p v-for="(line, i) in subTextLines" :key="i">{{ line }}</p>
    </div>
    <button v-if="isLong" @click="expanded = !expanded" class="text-[11px] font-medium text-brand-blue hover:text-brand-navy mt-0.5">
      {{ expanded ? 'Show less' : 'Show more' }}
    </button>
  </li>
</template>

<style scoped>
@keyframes tracker-now-pop {
  0%   { opacity: 0; transform: scale(0.4); }
  60%  { opacity: 1; transform: scale(1.15); }
  100% { opacity: 1; transform: scale(1); }
}

@keyframes tracker-current-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(46, 133, 216, 0.35); }
  50%      { box-shadow: 0 0 0 6px rgba(46, 133, 216, 0); }
}

@keyframes tracker-node-in {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Horizontal entrance (vertical cards animate via the shared
   vertical-timeline.css on the parent .wf-timeline instead). */
.tracker-node {
  animation: tracker-node-in 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.tracker-now {
  animation: tracker-now-pop 0.35s ease-out both;
  animation-delay: 0.2s;
}

.tracker-current {
  animation: tracker-current-pulse 2.4s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
  .tracker-node,
  .tracker-now,
  .tracker-current {
    animation: none;
  }
}
</style>
