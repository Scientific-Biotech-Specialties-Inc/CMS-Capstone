<script setup lang="ts">
import { useBreadcrumbs } from '@/composables/useBreadcrumbs'

const { breadcrumbs } = useBreadcrumbs()
</script>

<template>
  <nav
    v-if="breadcrumbs.length > 0"
    aria-label="Breadcrumb"
    class="flex items-center min-w-0 text-xs sm:text-sm select-none font-secondary"
  >
    <ol class="flex items-center gap-1.5 sm:gap-2 min-w-0 flex-wrap">
      <li
        v-for="(item, idx) in breadcrumbs"
        :key="idx"
        class="flex items-center gap-1.5 sm:gap-2 min-w-0"
      >
        <!-- Separator -->
        <span
          v-if="idx > 0"
          class="text-black/30 text-xs font-normal shrink-0"
          aria-hidden="true"
        >/</span>

        <!-- Clickable parent link -->
        <router-link
          v-if="item.to"
          :to="item.to"
          class="font-medium text-black/50 hover:text-brand-blue transition-colors truncate max-w-[130px] sm:max-w-[220px]"
          :title="item.label"
        >
          {{ item.label }}
        </router-link>

        <!-- Current active leaf -->
        <span
          v-else
          class="font-semibold text-black/85 truncate max-w-[160px] sm:max-w-[280px]"
          :title="item.label"
          aria-current="page"
        >
          {{ item.label }}
        </span>
      </li>
    </ol>
  </nav>
</template>
