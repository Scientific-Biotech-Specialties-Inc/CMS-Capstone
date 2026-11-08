<script setup lang="ts">
import { inject } from 'vue'
import { UserPlus, Lock } from 'lucide-vue-next'
import type { SystemCfg } from '@/composables/useSystemConfig'
import SettingCard from '@/components/shared/SettingCard.vue'
import ToggleRow   from '@/components/shared/ToggleRow.vue'

const cfg = inject<SystemCfg>('cfg')!
</script>

<template>
  <div class="space-y-4">

    <SettingCard title="Registration & Onboarding" description="Control how new users join the system."
      :icon="UserPlus" icon-bg="bg-brand-blue/8" icon-color="text-brand-blue">
      <div class="divide-y divide-black/4">
        <ToggleRow v-model="cfg.allowSelfReg"       label="Allow Self-Registration"    description="Users can create their own accounts without admin invite." />
        <ToggleRow v-model="cfg.requireEmailVerify" label="Require Email Verification" description="New accounts must verify their email before access." />
      </div>
    </SettingCard>

    <SettingCard title="Security & Authentication" description="Password policies and session management."
      :icon="Lock">
      <div class="px-6 py-5 space-y-5">

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div>
            <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Session timeout</label>
            <div class="relative mt-2">
              <input v-model.number="cfg.sessionTimeout" type="number" min="5"
                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 pr-10 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-black/35 pointer-events-none">min</span>
            </div>
          </div>
          <div>
            <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Max login attempts</label>
            <div class="mt-2">
              <input v-model.number="cfg.maxLoginAttempts" type="number" min="1" max="20"
                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
              <p class="text-[11px] text-black/30 mt-1">Account locks after this many failed attempts.</p>
            </div>
          </div>
          <div>
            <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Min password length</label>
            <div class="relative mt-2">
              <input v-model.number="cfg.minPasswordLength" type="number" min="6" max="32"
                class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 pr-12 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-black/35 pointer-events-none">chars</span>
            </div>
          </div>
        </div>

        <div class="divide-y divide-black/4 border border-black/6 rounded-lg overflow-hidden">
          <ToggleRow v-model="cfg.requireStrongPass" label="Require Strong Password"       description="Enforce uppercase, numbers, and special characters." />
          <ToggleRow v-model="cfg.twoFactorAdmins"   label="Two-Factor Auth for Admins"    description="Require 2FA for all admin accounts." />
          <ToggleRow v-model="cfg.twoFactorAll"      label="Two-Factor Auth for All Users" description="Require 2FA for every account in the system." />
        </div>

      </div>
    </SettingCard>

  </div>
</template>
