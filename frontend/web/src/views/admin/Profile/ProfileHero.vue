<script setup lang="ts">
import { computed } from 'vue'
import { CalendarDays, Mail, Briefcase } from 'lucide-vue-next'

const props = defineProps<{
  profile: {
    firstName: string; middleName?: string; lastName: string; role: string
    email: string; department: string; dateJoined: string
  }
}>()

const initials = computed(() =>
  [props.profile.firstName[0], props.profile.lastName[0]].join('').toUpperCase()
)
const fullName = computed(() =>
  [props.profile.firstName, props.profile.middleName, props.profile.lastName].filter(Boolean).join(' ')
)
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
    <div class="h-24 bg-gradient-to-r from-brand-navy to-brand-blue" />
    <div class="px-6 pb-6">
      <div class="flex items-end justify-between -mt-10 mb-4">
        <div class="w-20 h-20 rounded-2xl bg-brand-navy border-4 border-white flex items-center justify-center text-white text-2xl font-bold select-none shadow-md">
          {{ initials }}
        </div>
        <span class="mb-1 text-xs font-semibold px-3 py-1 rounded-full border bg-emerald-50 text-emerald-700 border-emerald-200">
          ● Active
        </span>
      </div>

      <h2 class="text-lg font-bold text-black">{{ fullName }}</h2>

      <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-2">
        <span class="flex items-center gap-1.5 text-xs text-black/50">
          <Briefcase class="w-3.5 h-3.5 text-black/30" />
          {{ profile.role }} · {{ profile.department }}
        </span>
        <span class="flex items-center gap-1.5 text-xs text-black/50">
          <Mail class="w-3.5 h-3.5 text-black/30" />
          {{ profile.email }}
        </span>
        <span class="flex items-center gap-1.5 text-xs text-black/50">
          <CalendarDays class="w-3.5 h-3.5 text-black/30" />
          Member since {{ profile.dateJoined }}
        </span>
      </div>
    </div>
  </div>
</template>
