<script setup lang="ts">
import { provide, onMounted } from 'vue'
import { Save } from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'
import NotificationsTab  from './NotificationsTab.vue'

import { useSystemConfig } from '@/composables/useSystemConfig'

const { success, error: errorToast } = useToast()

const { cfg, loading, fetchConfig, saveConfig } = useSystemConfig()

onMounted(async () => {
  await fetchConfig()
})

provide('cfg', cfg)

async function saveChanges() {
  const successResult = await saveConfig(cfg)
  if (successResult) {
    success('Settings saved', 'System configuration has been updated.')
  } else {
    errorToast('Error', 'Failed to update system configuration.')
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-xl font-semibold text-black">System Configuration</h1>
        <p class="text-sm text-black/40 mt-0.5">Manage system-wide settings, notifications, and access controls.</p>
      </div>
      <button
        @click="saveChanges"
        :disabled="loading"
        class="flex items-center gap-2 bg-brand-navy hover:bg-brand-dark text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors disabled:opacity-50"
      >
        <Save class="w-4 h-4" />
        {{ loading ? 'Saving...' : 'Save Changes' }}
      </button>
    </div>

    <NotificationsTab />

  </div>
</template>
