<script setup lang="ts">
import { Search, ChevronDown } from 'lucide-vue-next'
import type { ActionType } from '@/types/auditLog'
import { actionOptions, formatAction } from '@/types/auditLog'

defineProps<{
  actionFilter: ActionType | 'All'
  dateFilter:   string
  searchQuery:  string
}>()

const emit = defineEmits<{
  'update:actionFilter': [v: ActionType | 'All']
  'update:dateFilter':   [v: string]
  'update:searchQuery':  [v: string]
}>()
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm px-6 py-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

      <div class="space-y-1.5">
        <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Action</label>
        <div class="relative">
          <select :value="actionFilter" @change="emit('update:actionFilter', ($event.target as HTMLSelectElement).value as ActionType | 'All')"
            class="w-full appearance-none rounded-lg border border-black/10 bg-white px-3 py-2 pr-8 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors cursor-pointer">
            <option v-for="opt in actionOptions" :key="opt" :value="opt">
              {{ opt === 'All' ? 'All Actions' : formatAction(opt) }}
            </option>
          </select>
          <ChevronDown class="w-3.5 h-3.5 text-black/35 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        </div>
      </div>

      <div class="space-y-1.5">
        <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Date</label>
        <input :value="dateFilter" @change="emit('update:dateFilter', ($event.target as HTMLInputElement).value)"
          type="date"
          class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors cursor-pointer" />
      </div>

      <div class="space-y-1.5">
        <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Search</label>
        <div class="relative">
          <Search class="w-3.5 h-3.5 text-black/35 absolute left-3 top-1/2 -translate-y-1/2" />
          <input :value="searchQuery" @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
            type="text" placeholder="Search logs..."
            class="w-full rounded-lg border border-black/10 bg-white py-2 pl-8 pr-3 text-sm text-black placeholder:text-black/35 focus:border-brand-blue focus:outline-none transition-colors" />
        </div>
      </div>

    </div>
  </div>
</template>
