<script setup lang="ts">
import { inject } from 'vue'
import { Globe, Database, Mail } from 'lucide-vue-next'
import type { SystemCfg } from '@/composables/useSystemConfig'
import SettingCard from '@/components/shared/SettingCard.vue'
import ToggleRow   from '@/components/shared/ToggleRow.vue'

const cfg = inject<SystemCfg>('cfg')!
</script>

<template>
  <div class="space-y-4">

    <SettingCard title="General Settings" description="Basic system identity and preferences."
      :icon="Globe" icon-bg="bg-brand-blue/8" icon-color="text-brand-blue">
      <div class="px-6 py-5 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">System name</label>
            <input v-model="cfg.systemName" type="text" maxlength="100"
              class="w-full mt-2 rounded-lg border border-black/10 bg-white px-3 py-2 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
          </div>
          <div>
            <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Support email</label>
            <div class="relative mt-2">
              <Mail class="w-3.5 h-3.5 text-black/30 absolute left-3 top-1/2 -translate-y-1/2" />
              <input v-model="cfg.supportEmail" type="email"
                class="w-full rounded-lg border border-black/10 bg-white pl-8 pr-3 py-2 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
            </div>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Timezone</label>
            <div class="relative mt-2">
              <select v-model="cfg.timezone"
                class="w-full appearance-none rounded-lg border border-black/10 bg-white px-3 py-2 pr-8 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors cursor-pointer">
                <option>Asia/Manila</option>
                <option>Asia/Singapore</option>
                <option>UTC</option>
                <option>America/New_York</option>
                <option>Europe/London</option>
              </select>
              <svg class="w-3.5 h-3.5 text-black/35 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
          <div>
            <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Date format</label>
            <div class="relative mt-2">
              <select v-model="cfg.dateFormat"
                class="w-full appearance-none rounded-lg border border-black/10 bg-white px-3 py-2 pr-8 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors cursor-pointer">
                <option>MM/DD/YYYY</option>
                <option>DD/MM/YYYY</option>
                <option>YYYY-MM-DD</option>
              </select>
              <svg class="w-3.5 h-3.5 text-black/35 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
        </div>
      </div>
    </SettingCard>

    <SettingCard title="Maintenance & Logging" description="System health, audit trails, and data management."
      :icon="Database">
      <div class="divide-y divide-black/4">
        <ToggleRow v-model="cfg.auditLogging" label="Audit Logging" description="Record all user actions and system events." />

        <div class="px-6 py-4 space-y-3">
          <ToggleRow v-model="cfg.autoBackups" label="Automatic Backups" description="Schedule regular database backups." />
          <div v-if="cfg.autoBackups" class="bg-black/2 rounded-lg border border-black/6 p-4 space-y-4">
            <div>
              <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Backup frequency</label>
              <div class="relative mt-2">
                <select v-model="cfg.backupFrequency"
                  class="w-full appearance-none rounded-lg border border-black/10 bg-white px-3 py-2 pr-8 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors cursor-pointer">
                  <option value="hourly">Hourly</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
                <svg class="w-3.5 h-3.5 text-black/35 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
            <div>
              <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Data retention period</label>
              <div class="relative mt-2">
                <input v-model.number="cfg.dataRetentionDays" type="number" min="30" max="3650"
                  class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 pr-12 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-black/35 pointer-events-none">days</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </SettingCard>

  </div>
</template>
