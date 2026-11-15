<script setup lang="ts">
import { AlertCircle, Loader2, Sparkles } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import SignatureInput from '../ContractDetail/SignatureInput.vue'

defineProps<{
  signerName: string
  signature: string | null
  signatureWidth: number
  consent: boolean
  hasPlacement: boolean
  submitting: boolean
  disabled: boolean
  attemptedSubmit: boolean
  error: string
}>()

defineEmits<{
  'update:signerName': [value: string]
  'update:signature': [value: string | null]
  'update:signatureWidth': [value: number]
  'update:consent': [value: boolean]
  submit: []
}>()

const sizePresets = [
  { label: 'Compact', value: 0.10 },
  { label: 'Small', value: 0.15 },
  { label: 'Standard', value: 0.20 },
  { label: 'Large', value: 0.28 },
]
</script>

<template>
  <div class="flex min-h-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 font-poppins shadow-sm">
    <!-- Header title -->
    <div class="shrink-0 border-b border-slate-100 pb-2">
      <h3 class="text-base font-bold text-slate-800">Signer Credentials</h3>
    </div>

    <div class="min-h-0 space-y-4 lg:-mx-2 lg:overflow-y-auto lg:px-2">
      <!-- Signer Full Name -->
      <div class="space-y-1.5">
        <label class="block text-xs font-semibold text-slate-700">
          Full Legal Name <span class="text-red-500">*</span>
        </label>
        <input
          :value="signerName"
          type="text"
          maxlength="160"
          placeholder="Enter your full name"
          :disabled="disabled || submitting"
          class="block w-full rounded-lg border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-brand-blue"
          :class="attemptedSubmit && !signerName.trim() ? 'border-red-400 ring-1 ring-red-200' : 'border-slate-200'"
          @input="$emit('update:signerName', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="attemptedSubmit && !signerName.trim()" class="text-xs text-red-600 flex items-center gap-1">
          <AlertCircle class="w-3.5 h-3.5" /> Full name is required.
        </p>
      </div>

      <!-- Signature Pad / Upload -->
      <div class="space-y-1.5">
        <SignatureInput
          :disabled="disabled || submitting"
          :invalid="attemptedSubmit && !signature"
          required
          compact
          @change="$emit('update:signature', $event)"
        />
        <p v-if="attemptedSubmit && !signature" class="text-xs text-red-600 flex items-center gap-1">
          <AlertCircle class="w-3.5 h-3.5" /> Please draw or upload a signature.
        </p>
      </div>

      <!-- Signature Size Controls -->
      <div v-if="signature" class="space-y-2 rounded-xl border border-slate-100 bg-slate-50/80 p-2">
        <div class="flex items-center justify-between text-xs">
          <span class="font-semibold text-slate-700 flex items-center gap-1.5">
            <Sparkles class="size-3.5 text-brand-blue" /> Signature Size
          </span>
          <span class="font-mono text-brand-navy font-bold">{{ Math.round(signatureWidth * 100) }}% width</span>
        </div>
        <!-- Preset pills -->
        <div class="grid grid-cols-4 gap-1.5">
          <button
            v-for="preset in sizePresets"
            :key="preset.value"
            type="button"
            :disabled="disabled || submitting"
            class="py-1 px-2 text-[11px] font-semibold rounded-lg border transition-all"
            :class="Math.abs(signatureWidth - preset.value) < 0.02 ? 'bg-brand-navy text-white border-brand-navy shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'"
            @click="$emit('update:signatureWidth', preset.value)"
          >
            {{ preset.label }}
          </button>
        </div>
        <!-- Range slider -->
        <input
          type="range"
          min="0.08"
          max="0.35"
          step="0.01"
          :value="signatureWidth"
          :disabled="disabled || submitting"
          aria-label="Signature size"
          class="block w-full cursor-pointer accent-brand-navy"
          @input="$emit('update:signatureWidth', Number(($event.target as HTMLInputElement).value))"
        />
      </div>

      <div v-if="!signature && !hasPlacement" class="flex items-start gap-2 rounded-xl border p-2" :class="attemptedSubmit ? 'border-red-200 bg-red-50/50 text-red-800' : 'border-amber-200 bg-amber-50/50 text-amber-800'">
        <AlertCircle class="size-4 shrink-0 mt-0.5" />
        <div class="text-xs">
          <span class="block font-semibold">Placement Required <span class="text-red-500">*</span></span>
          <span class="text-[11px] opacity-80">Add a signature, then click the PDF to place it.</span>
        </div>
      </div>
    </div>

    <div class="mt-auto shrink-0 space-y-2 border-t border-slate-100 pt-2">
      <!-- Intent Consent Checkbox -->
      <div class="space-y-1">
        <label class="flex items-start gap-2.5 text-xs text-slate-700 cursor-pointer">
          <input
            type="checkbox"
            :checked="consent"
            :disabled="disabled || submitting"
            class="mt-0.5 size-4 accent-brand-navy rounded cursor-pointer"
            @change="$emit('update:consent', ($event.target as HTMLInputElement).checked)"
          />
          <span>I confirm my intent to sign this approved draft at the position shown. <span class="text-red-500">*</span></span>
        </label>
        <p v-if="attemptedSubmit && !consent" class="text-xs text-red-600 flex items-center gap-1 pl-6">
          <AlertCircle class="w-3.5 h-3.5" /> Consent confirmation is required.
        </p>
      </div>

      <!-- Error Banner -->
      <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 flex items-start gap-2">
        <AlertCircle class="size-4 shrink-0 mt-0.5" />
        <span>{{ error }}</span>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end">
        <Button
          type="button"
          :disabled="submitting || disabled"
          class="bg-brand-navy hover:bg-brand-dark text-white font-semibold flex items-center gap-2"
          @click="$emit('submit')"
        >
          <Loader2 v-if="submitting" class="size-4 animate-spin" />
          Submit Signed PDF
        </Button>
      </div>
    </div>
  </div>
</template>

