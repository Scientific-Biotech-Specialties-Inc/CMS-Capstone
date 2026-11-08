<script setup lang="ts">
import type { RiskFinding } from '@/types/riskAssessment'
import { severityBadge, severityIconColor } from '@/types/riskAssessment'
import { AlertTriangle, Info, ShieldAlert, XCircle, CheckCircle, FileText } from 'lucide-vue-next'

defineProps<{
  findings: RiskFinding[]
}>()

function getIconForSeverity(severity: string) {
  if (severity === 'critical') return ShieldAlert
  if (severity === 'high') return AlertTriangle
  return Info
}
</script>

<template>
  <div class="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden">
    <div class="px-6 pt-5 pb-4 border-b border-black/5 bg-black/[0.015]">
      <h3 class="text-base font-semibold text-black">Flagged Clauses</h3>
      <p class="text-xs text-black/40 mt-0.5">Each finding cites the playbook clause it deviates from.</p>
    </div>

    <div v-if="findings.length === 0" class="px-6 py-10 text-center text-sm text-black/35">
      No flagged clauses were identified in this assessment.
    </div>

    <div v-else class="divide-y divide-black/[0.04] bg-black/[0.005]">
      <div v-for="finding in findings" :key="finding.id" class="px-6 py-6 transition-colors hover:bg-white">
        
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-5">
          <div class="flex items-start gap-3 w-full">
            <div class="flex-shrink-0 mt-0.5">
              <component :is="getIconForSeverity(finding.severity)" class="w-5 h-5" :class="severityIconColor[finding.severity]" />
            </div>
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2 mb-1.5">
                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded uppercase tracking-widest" :class="severityBadge[finding.severity]">
                  {{ finding.severity }} Risk
                </span>
                <span v-if="finding.playbookClauseCode" class="text-[10px] font-mono px-2 py-0.5 rounded bg-black/5 text-black/50 border border-black/5">
                  {{ finding.playbookClauseCode }}
                </span>
              </div>
              <h4 class="text-sm font-bold text-black">
                {{ finding.playbookClauseTitle || 'Uncategorized Finding' }}
              </h4>
            </div>
          </div>
        </div>

        <!-- Content Grid -->
        <div class="space-y-4 pl-[32px] md:pl-[36px]">
          
          <!-- Excerpt -->
          <div v-if="finding.clauseReference && finding.clauseReference !== 'N/A'" class="bg-white border border-black/5 rounded-lg overflow-hidden shadow-sm">
            <div class="bg-black/[0.02] px-3 py-1.5 border-b border-black/5 flex items-center gap-1.5">
              <FileText class="w-3.5 h-3.5 text-black/40" />
              <span class="text-[10px] font-bold text-black/40 uppercase tracking-wider">Contract Excerpt</span>
            </div>
            <div class="p-3">
              <p class="text-xs text-black/60 font-serif italic leading-relaxed whitespace-pre-wrap">"{{ finding.clauseReference }}"</p>
            </div>
          </div>

          <!-- Reason & Remediation grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Deviation Reason -->
            <div class="bg-red-50/40 border border-red-100 rounded-lg overflow-hidden">
              <div class="bg-red-50 px-3 py-1.5 border-b border-red-100/60 flex items-center gap-1.5">
                <XCircle class="w-3.5 h-3.5 text-red-500" />
                <span class="text-[10px] font-bold text-red-800/70 uppercase tracking-wider">Deviation Reason</span>
              </div>
              <div class="p-3">
                <p class="text-[13px] text-red-950/80 leading-relaxed">{{ finding.deviationReason }}</p>
              </div>
            </div>

            <!-- Recommended Remediation -->
            <div class="bg-emerald-50/40 border border-emerald-100 rounded-lg overflow-hidden">
              <div class="bg-emerald-50 px-3 py-1.5 border-b border-emerald-100/60 flex items-center gap-1.5">
                <CheckCircle class="w-3.5 h-3.5 text-emerald-600" />
                <span class="text-[10px] font-bold text-emerald-800/70 uppercase tracking-wider">Recommended Remediation</span>
              </div>
              <div class="p-3">
                <p class="text-[13px] text-emerald-950/80 leading-relaxed">{{ finding.recommendedRemediation }}</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>
