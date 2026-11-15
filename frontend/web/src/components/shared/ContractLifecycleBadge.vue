<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle2, Clock, AlertTriangle } from 'lucide-vue-next'
import type { ContractLifecycleStatus } from '@/types/contract'

const props = withDefaults(
  defineProps<{
    status: ContractLifecycleStatus
    days?: number
    size?: 'sm' | 'md'
  }>(),
  {
    size: 'sm'
  }
)

const config = computed(() => {
  switch (props.status) {
    case 'pending_execution':
      return { icon: Clock, classes: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Pending Execution' }
    case 'inactive':
      return {
        icon: Clock,
        classes: 'bg-brand-navy/5 text-brand-navy/70 border-brand-navy/15',
        label: 'Inactive'
      }
    case 'expired':
      return {
        icon: AlertTriangle,
        classes: 'bg-red-50 text-red-700 border-red-200/60',
        label: 'Expired'
      }
    case 'expiring':
      return {
        icon: Clock,
        classes: 'bg-amber-50 text-amber-700 border-amber-200/60',
        label: 'Expiring Soon'
      }
    case 'active':
    default:
      return {
        icon: CheckCircle2,
        classes: 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
        label: 'Active'
      }
  }
})

const displayText = computed(() => {
  if (props.days === undefined) {
    return config.value.label
  }

  if (props.status === 'expiring') {
    return `${config.value.label} (${props.days}d left)`
  }

  if (props.status === 'expired') {
    const absDays = Math.abs(props.days)
    return `${config.value.label} (${absDays}d overdue)`
  }

  return config.value.label
})

const ariaLabel = computed(() => {
  if (props.days === undefined) {
    return `Contract status: ${config.value.label}`
  }
  if (props.status === 'expiring') {
    return `Contract status: ${config.value.label}, ${props.days} days left`
  }
  if (props.status === 'expired') {
    const absDays = Math.abs(props.days)
    return `Contract status: ${config.value.label}, ${absDays} days overdue`
  }
  return `Contract status: ${config.value.label}`
})
</script>

<template>
  <span
    role="status"
    :aria-label="ariaLabel"
    class="inline-flex items-center gap-1.5 font-medium border rounded-full whitespace-nowrap"
    :class="[
      config.classes,
      size === 'sm' ? 'text-[11px] px-2 py-0.5' : 'text-xs px-2.5 py-1'
    ]"
  >
    <component
      :is="config.icon"
      :class="size === 'sm' ? 'w-3 h-3' : 'w-3.5 h-3.5'"
      class="shrink-0"
    />
    <span>{{ displayText }}</span>
  </span>
</template>
