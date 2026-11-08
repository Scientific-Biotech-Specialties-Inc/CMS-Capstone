<script setup lang="ts">
import { ShieldAlert, ShieldCheck } from 'lucide-vue-next'
import type { RiskAssessmentSummary } from '@/types/riskAssessment'
import { severityBadge, severityLabel, isHighRisk } from '@/types/riskAssessment'

const props = defineProps<{
  summary: RiskAssessmentSummary
  contractName?: string
}>()
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm p-6">
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-start gap-3">
        <div
          class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
          :class="isHighRisk(summary.riskLevel) ? 'bg-red-50' : 'bg-brand-navy/8'"
        >
          <component
            :is="isHighRisk(summary.riskLevel) ? ShieldAlert : ShieldCheck"
            class="w-5 h-5"
            :class="isHighRisk(summary.riskLevel) ? 'text-red-600' : 'text-brand-navy'"
          />
        </div>
        <div>
          <h2 class="text-base font-semibold text-black">AI Risk Assessment Summary</h2>
          <p class="text-xs text-black/40 mt-0.5" v-if="contractName">{{ contractName }}</p>
        </div>
      </div>

      <span
        v-if="summary.riskLevel"
        class="px-3 py-1.5 text-xs font-semibold rounded-full border shrink-0"
        :class="severityBadge[summary.riskLevel]"
      >
        {{ severityLabel[summary.riskLevel] }}
      </span>
    </div>

    <div v-if="summary.status === 'failed'" class="mt-4 bg-red-50 border border-red-200 rounded-lg px-4 py-4">
      <div class="flex items-start gap-3">
        <ShieldAlert class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
        <div>
          <h3 class="text-sm font-semibold text-red-800">Scan Failed</h3>
          <p class="text-xs text-red-700 mt-1 leading-relaxed">
            The AI risk assessment encountered an error and could not complete. This may happen if the contract document is missing or corrupted. Please click "Re-run Scan" to try again.
          </p>
        </div>
      </div>
    </div>

    <template v-else>
      <div class="mt-4 grid grid-cols-3 gap-4 pt-4 border-t border-black/5">
        <div>
          <p class="text-[10px] font-semibold text-black/35 uppercase tracking-widest">Risk Score</p>
          <p class="text-lg font-bold text-black mt-0.5">{{ summary.riskScore ?? '—' }}</p>
        </div>
        <div>
          <p class="text-[10px] font-semibold text-black/35 uppercase tracking-widest">Flagged Clauses</p>
          <p class="text-lg font-bold text-black mt-0.5">{{ summary.findings.length }}</p>
        </div>
        <div>
          <p class="text-[10px] font-semibold text-black/35 uppercase tracking-widest">Scanned</p>
          <p class="text-sm font-medium text-black/70 mt-1.5">
            {{ summary.scannedAt ? new Date(summary.scannedAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}
          </p>
        </div>
      </div>

      <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800 leading-relaxed">
        This assessment is a recommendation to support your review — it is not an
        automatic verdict. Every finding below is grounded in a specific retrieved
        playbook clause and a structured judgment, not a free-floating model claim.
      </div>
    </template>
  </div>
</template>
