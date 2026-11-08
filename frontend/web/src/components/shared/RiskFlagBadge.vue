<script setup lang="ts">
import { computed } from 'vue'
import { Flag } from 'lucide-vue-next'
import type { RiskLevel } from '@/types/riskAssessment'
import { severityIconColor, severityLabel } from '@/types/riskAssessment'

// AI Risk Assessment is advisory (a recommendation/suggestion), so this badge
// is a lightweight at-a-glance indicator — not a blocking control. Absence of
// this badge means "not yet assessed" (no document uploaded / scan not run
// yet), which is a distinct state from "assessed, low risk" and therefore
// intentionally renders nothing rather than a default/neutral icon.
const props = withDefaults(
  defineProps<{
    riskLevel?: RiskLevel | null
    findingsCount?: number
    size?: 'sm' | 'md'
    clickable?: boolean
  }>(),
  {
    riskLevel: null,
    findingsCount: 0,
    size: 'sm',
    clickable: true,
  }
)

defineEmits<{ click: [] }>()

const visible = computed(() => !!props.riskLevel)

const label = computed(() => {
  if (!props.riskLevel) return ''
  const base = severityLabel[props.riskLevel]
  return props.findingsCount > 0
    ? `${base} — ${props.findingsCount} flagged clause${props.findingsCount === 1 ? '' : 's'}`
    : base
})
</script>

<template>
  <component
    :is="clickable ? 'button' : 'span'"
    v-if="visible"
    :type="clickable ? 'button' : undefined"
    class="inline-flex items-center gap-1.5 font-medium rounded-full whitespace-nowrap transition-colors"
    :class="[
      severityIconColor[riskLevel as RiskLevel],
      size === 'sm' ? 'text-[11px] px-2 py-0.5' : 'text-xs px-2.5 py-1',
      clickable ? 'hover:opacity-75 cursor-pointer' : ''
    ]"
    :title="label"
    :aria-label="`AI Risk Assessment: ${label}`"
    @click="clickable && $emit('click')"
  >
    <Flag :class="size === 'sm' ? 'w-3 h-3' : 'w-3.5 h-3.5'" class="shrink-0 fill-current" />
    <span v-if="size !== 'sm'">{{ severityLabel[riskLevel as RiskLevel] }}</span>
  </component>
</template>
