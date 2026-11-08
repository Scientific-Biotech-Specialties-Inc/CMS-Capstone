<script setup lang="ts">
import type { ApiRole, RoleMeta } from '@/types/role'

defineProps<{
  roles:         ApiRole[]
  activeRole:    ApiRole | null
  roleMetaFn:    (role: ApiRole) => RoleMeta
  enabledCounts: Record<number, number>
}>()

defineEmits<{ 'update:activeRole': [role: ApiRole] }>()
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <button
      v-for="role in roles"
      :key="role.id"
      @click="$emit('update:activeRole', role)"
      class="text-left p-5 rounded-xl border transition-all duration-200"
      :class="activeRole?.id === role.id
        ? 'bg-brand-navy/5 border-brand-navy/30 ring-1 ring-brand-navy/20'
        : 'bg-white border-black/8 hover:border-black/15'"
    >
      <div class="flex items-center gap-3 mb-3">
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
          :class="activeRole?.id === role.id ? 'bg-brand-navy/10' : 'bg-black/5'"
        >
          <component
            :is="roleMetaFn(role).icon"
            class="w-4.5 h-4.5"
            :class="activeRole?.id === role.id ? 'text-brand-navy' : 'text-black/40'"
          />
        </div>
        <span
          class="text-xs font-semibold px-2.5 py-1 rounded-full border"
          :class="activeRole?.id === role.id
            ? 'bg-brand-navy/8 text-brand-navy border-brand-navy/20'
            : 'bg-black/4 text-black/50 border-black/8'"
        >
          {{ role.name.toUpperCase() }}
        </span>
      </div>

      <p class="text-xs text-black/50 leading-relaxed mb-3">{{ roleMetaFn(role).description }}</p>
      <p
        class="text-xs font-semibold"
        :class="activeRole?.id === role.id ? 'text-brand-navy' : 'text-black/40'"
      >
        {{ enabledCounts[role.id] ?? 0 }} permissions enabled
      </p>
    </button>
  </div>
</template>
