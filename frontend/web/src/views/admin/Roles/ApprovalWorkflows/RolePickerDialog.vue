<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Search, Check } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import type { AssignableRole } from '@/types/workflow'

const props = defineProps<{
  open: boolean
  roles: AssignableRole[]
  alreadySelectedIds: number[]
}>()

const emit = defineEmits<{
  close: []
  select: [role: AssignableRole]
}>()

const search = ref('')

// Reset search state whenever the dialog is reopened for a different step.
watch(() => props.open, (isOpen) => {
  if (isOpen) search.value = ''
})

const filteredRoles = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.roles
  return props.roles.filter(r => r.name.toLowerCase().includes(q))
})

function pick(role: AssignableRole) {
  emit('select', role)
}
</script>

<template>
  <Dialog :open="open" @update:open="(v: boolean) => !v && emit('close')">
    <DialogContent class="max-w-md p-6 gap-4">
      <DialogHeader>
        <DialogTitle class="text-sm font-bold text-black">Select a Role</DialogTitle>
        <DialogDescription class="text-xs text-black/40 mt-1">
          Anyone currently holding this role (or temporarily delegated it) can act on this step.
        </DialogDescription>
      </DialogHeader>

      <div class="relative">
        <Search class="w-3.5 h-3.5 text-black/30 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input v-model="search" type="text" placeholder="Search roles..."
          class="w-full h-9 rounded-lg border border-black/12 bg-white pl-8.5 pr-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/15 transition" />
      </div>

      <div class="max-h-72 overflow-y-auto divide-y divide-black/5 -mx-6 px-6">
        <button
          v-for="role in filteredRoles"
          :key="role.id"
          @click="pick(role)"
          :disabled="alreadySelectedIds.includes(role.id)"
          class="w-full flex items-center justify-between gap-3 py-3 text-left transition-colors disabled:opacity-40 disabled:cursor-not-allowed hover:bg-black/[0.02]"
        >
          <div>
            <p class="text-sm font-medium text-black">{{ role.name }}</p>
            <p v-if="role.description" class="text-xs text-black/40 mt-0.5">{{ role.description }}</p>
          </div>
          <Check v-if="alreadySelectedIds.includes(role.id)" class="w-4 h-4 text-brand-blue shrink-0" />
        </button>

        <p v-if="!filteredRoles.length" class="py-6 text-center text-xs text-black/35">
          No roles match your search.
        </p>
      </div>
    </DialogContent>
  </Dialog>
</template>
