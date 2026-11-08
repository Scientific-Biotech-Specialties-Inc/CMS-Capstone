<script setup lang="ts">
import { inject } from 'vue'
import { FileText, ShieldCheck } from 'lucide-vue-next'
import type { SystemCfg } from '@/composables/useSystemConfig'
import SettingCard from '@/components/shared/SettingCard.vue'
import ToggleRow   from '@/components/shared/ToggleRow.vue'

const cfg = inject<SystemCfg>('cfg')!
</script>

<template>
  <div class="space-y-4">

    <SettingCard title="Contract Lifecycle" description="Control how contracts move through the approval pipeline."
      :icon="FileText" icon-bg="bg-brand-blue/8" icon-color="text-brand-blue">
      <div class="divide-y divide-black/4">
        <ToggleRow v-model="cfg.autoRenew"           label="Auto-Renew Contracts"    description="Automatically renew contracts before they expire." />
        <ToggleRow v-model="cfg.dualApproval"         label="Require Dual Approval"   description="Contracts must be approved by two authorized users." />
        <ToggleRow v-model="cfg.allowDraftSubmit"     label="Allow Draft Submissions" description="Users can save contracts as drafts before submitting." />
        <ToggleRow v-model="cfg.requireNotarization"  label="Require Notarization"    description="All finalized contracts must be notarized before activation." />
      </div>
    </SettingCard>

    <SettingCard title="Duration & Expiry" description="Set limits on contract duration and warning thresholds."
      :icon="ShieldCheck">
      <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Max contract duration</label>
          <div class="relative mt-2">
            <input v-model.number="cfg.maxContractDays" type="number" min="30" max="3650"
              class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 pr-12 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-black/35 pointer-events-none">days</span>
          </div>
        </div>
        <div>
          <label class="text-[11px] font-semibold text-black/40 uppercase tracking-wider">Expiry warning threshold</label>
          <div class="relative mt-2">
            <input v-model.number="cfg.expiryWarningDays" type="number" min="1" max="180"
              class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 pr-12 text-sm text-black focus:border-brand-blue focus:outline-none transition-colors" />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-black/35 pointer-events-none">days</span>
          </div>
        </div>
      </div>
    </SettingCard>

  </div>
</template>
