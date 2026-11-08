<script setup lang="ts">
import { reactive, watch } from 'vue'
import { SlidersHorizontal } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import ToggleRow from '@/components/shared/ToggleRow.vue'

const props = defineProps<{
  preferences: {
    emailNotifications: boolean; systemAlerts: boolean
    contractExpiry: boolean
    loginAlerts: boolean
  }
  role?: string
}>()

const emit = defineEmits<{ save: [data: typeof props.preferences] }>()

const form = reactive({ ...props.preferences })
watch(() => props.preferences, p => Object.assign(form, p), { deep: true })

function save() {
  emit('save', { ...form })
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm">

    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg bg-brand-blue/8 flex items-center justify-center shrink-0">
        <SlidersHorizontal class="w-4 h-4 text-brand-blue" />
      </div>
      <div>
        <h3 class="text-sm font-semibold text-black">Preferences</h3>
        <p class="text-xs text-black/40">Notifications, language and display settings.</p>
      </div>
    </div>

    <!-- Notifications -->
    <div class="px-6 pt-4 pb-1">
      <p class="text-[10px] font-bold text-black/35 uppercase tracking-widest mb-1">Notifications</p>
    </div>
    <div class="divide-y divide-black/4">
      <ToggleRow label="Email notifications" description="Receive alerts via email"
        :model-value="form.emailNotifications" @update:model-value="form.emailNotifications = $event" />
      <ToggleRow label="System alerts" description="In-app notifications and banners"
        :model-value="form.systemAlerts" @update:model-value="form.systemAlerts = $event" />

      <ToggleRow label="Contract expiry alerts" description="Notify 15 days before expiry"
        :model-value="form.contractExpiry" @update:model-value="form.contractExpiry = $event" />
      <ToggleRow v-if="props.role === 'Admin'" label="Login alerts" description="Email me when a new login is detected"
        :model-value="form.loginAlerts" @update:model-value="form.loginAlerts = $event" />
    </div>


      <div class="px-6 pb-5 pt-2">
        <Button @click="save" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white">
          Save preferences
        </Button>
      </div>

  </div>
</template>
