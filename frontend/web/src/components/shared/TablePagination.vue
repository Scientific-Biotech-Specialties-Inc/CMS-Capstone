<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
  currentPage:  number
  totalItems:   number
  itemsPerPage: number
  currentPageItemsCount?: number
}>()

const emit = defineEmits<{
  'update:currentPage': [page: number]
}>()

const WINDOW_SIZE = 5

const totalPages = computed(() => {
  const calculated = Math.max(1, Math.ceil(props.totalItems / props.itemsPerPage))
  if (props.currentPageItemsCount !== undefined && props.currentPageItemsCount < props.itemsPerPage) {
    return Math.min(calculated, props.currentPage)
  }
  return calculated
})

const pageNumbers = computed(() => {
  const start = Math.max(1, props.currentPage - 2)
  return Array.from({ length: WINDOW_SIZE }, (_, i) => start + i)
})

function goTo(page: number) {
  if (page < 1 || page > totalPages.value || page === props.currentPage) return
  emit('update:currentPage', page)
}
</script>

<template>
  <div class="flex items-center justify-center gap-1">
    <button type="button" :disabled="currentPage === 1" @click="goTo(currentPage - 1)"
      class="h-8 w-8 flex items-center justify-center rounded-md text-black/50 hover:bg-black/5 hover:text-black transition disabled:opacity-30 disabled:pointer-events-none">
      <ChevronLeft class="w-4 h-4" />
    </button>

    <button v-for="p in pageNumbers" :key="p" type="button"
      :disabled="p > totalPages"
      @click="goTo(p)"
      class="h-8 w-8 flex items-center justify-center rounded-md text-sm font-medium transition disabled:opacity-30 disabled:pointer-events-none"
      :class="p === currentPage
        ? 'bg-brand-navy text-white hover:bg-brand-dark'
        : 'text-black/50 hover:bg-black/5 hover:text-black'">
      {{ p }}
    </button>

    <button type="button" :disabled="currentPage >= totalPages" @click="goTo(currentPage + 1)"
      class="h-8 w-8 flex items-center justify-center rounded-md text-black/50 hover:bg-black/5 hover:text-black transition disabled:opacity-30 disabled:pointer-events-none">
      <ChevronRight class="w-4 h-4" />
    </button>
  </div>
</template>
