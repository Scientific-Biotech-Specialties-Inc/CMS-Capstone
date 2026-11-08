<script setup lang="ts">
import type { SelectTriggerProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ChevronDown } from "lucide-vue-next"
import { SelectIcon, SelectTrigger, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = withDefaults(
  defineProps<SelectTriggerProps & { class?: HTMLAttributes["class"], size?: "sm" | "default" }>(),
  { size: "default" },
)

const delegatedProps = reactiveOmit(props, "class", "size")
const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <SelectTrigger
    data-slot="select-trigger"
    :data-size="size"
    v-bind="forwardedProps"
    :class="cn(
      'flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm text-slate-800 shadow-xs transition-all outline-none font-poppins',
      'hover:border-slate-300',
      'focus-visible:border-2 focus-visible:border-[#2E85D8] focus-visible:ring-0',
      'data-[state=open]:border-2 data-[state=open]:border-[#2E85D8] data-[state=open]:rounded-b-none',
      'aria-invalid:border-red-400 focus-visible:aria-invalid:border-red-400',
      'disabled:cursor-not-allowed disabled:opacity-50',
      'data-[size=default]:h-9 data-[size=sm]:h-8',
      '*:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2',
      '[&_svg]:pointer-events-none [&_svg]:shrink-0',
      props.class,
    )"
  >
    <slot />
    <SelectIcon as-child>
      <ChevronDown class="size-4 text-slate-400 transition-transform duration-200 group-data-[state=open]:rotate-180" />
    </SelectIcon>
  </SelectTrigger>
</template>
