<script setup lang="ts">
import type { SelectItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { Check } from "lucide-vue-next"
import {
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  useForwardProps,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <SelectItem
    data-slot="select-item"
    v-bind="forwardedProps"
    :class="
      cn(
        'relative flex w-full cursor-pointer items-center justify-between rounded-md py-2 px-3 text-sm font-poppins text-[#1E293B] outline-none select-none transition-colors',
        'hover:bg-slate-100 hover:text-[#0F172A] focus:bg-slate-100 focus:text-[#0F172A]',
        'data-[state=checked]:bg-[#2E85D8] data-[state=checked]:text-[#FFFFFF] data-[state=checked]:font-medium',
        'data-[disabled]:pointer-events-none data-[disabled]:opacity-40',
        props.class,
      )
    "
  >
    <SelectItemText>
      <slot />
    </SelectItemText>

    <span class="flex size-4 items-center justify-center">
      <SelectItemIndicator>
        <slot name="indicator-icon">
          <Check class="size-4 text-white" />
        </slot>
      </SelectItemIndicator>
    </span>
  </SelectItem>
</template>
