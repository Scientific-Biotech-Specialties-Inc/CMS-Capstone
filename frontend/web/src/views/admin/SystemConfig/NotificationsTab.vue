<script setup lang="ts">
import { inject } from 'vue'
import { Send, FileText, Clock } from 'lucide-vue-next'
import type { SystemCfg } from '@/composables/useSystemConfig'
import SettingCard from '@/components/shared/SettingCard.vue'
import ToggleRow   from '@/components/shared/ToggleRow.vue'

const cfg = inject<SystemCfg>('cfg')!

const expiryThresholds = [
  { label: '3 months (90 days) before expiry', sublabel: 'First warning — far-in-advance notice', type: 'expiry_90' },
  { label: '1 month (30 days) before expiry',  sublabel: 'Second warning — action window approaching', type: 'expiry_30' },
  { label: '1 day before expiry',               sublabel: 'Final warning — urgent action required', type: 'expiry_1' },
]
</script>

<template>
  <div class="space-y-4">

    <SettingCard title="Delivery Channels" description="Choose how notifications are sent to users."
      :icon="Send" icon-bg="bg-brand-blue/8" icon-color="text-brand-blue">
      <div class="divide-y divide-black/4">
        <ToggleRow v-model="cfg.emailNotifs"  label="Email Notifications"  description="Send alerts via email to relevant users." />
        <ToggleRow v-model="cfg.inAppNotifs"  label="In-App Notifications" description="Show notification bell alerts inside the system." />
      </div>
    </SettingCard>

    <SettingCard title="Contract Alerts" description="Configure when contract-related notifications are triggered."
      :icon="FileText">
      <div class="divide-y divide-black/4">

        <div class="px-6 py-4 space-y-3">
          <ToggleRow v-model="cfg.contractExpiryAlerts"
            label="Contract Expiry Alerts"
            description="Notify Admin, Manager, and Sales users when contracts are approaching expiry." />

          <div v-if="cfg.contractExpiryAlerts" class="bg-black/2 rounded-lg border border-black/6 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-black/6 flex items-center gap-2">
              <Clock class="w-3.5 h-3.5 text-black/40" />
              <span class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Notification Schedule</span>
            </div>
            <ul class="divide-y divide-black/4">
              <li v-for="t in expiryThresholds" :key="t.type"
                class="flex items-center gap-3 px-4 py-3">
                <span class="w-2 h-2 rounded-full bg-brand-blue shrink-0" />
                <div>
                  <p class="text-sm font-medium text-black">{{ t.label }}</p>
                  <p class="text-xs text-black/40 mt-0.5">{{ t.sublabel }}</p>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <ToggleRow v-model="cfg.approvalAlerts"   label="Approval Request Alerts" description="Notify managers when a contract request is submitted." />
        <ToggleRow v-model="cfg.renewalReminders" label="Renewal Reminders"       description="Send reminders when contracts are due for renewal." />
      </div>
    </SettingCard>

  </div>
</template>
