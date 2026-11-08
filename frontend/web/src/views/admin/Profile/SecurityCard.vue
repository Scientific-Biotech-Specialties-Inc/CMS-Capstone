<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { ShieldCheck, Eye, EyeOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

const emit = defineEmits<{ save: [data: { current: string, next: string }] }>()

const form = reactive({ current: '', next: '', confirm: '' })
const touched = reactive({ current: false, next: false, confirm: false })
const showCurrent = ref(false)
const showNext    = ref(false)
const showConfirm = ref(false)

const pwRules = computed(() => ({
  minLength:  form.next.length >= 8,
  hasUpper:   /[A-Z]/.test(form.next),
  hasNumber:  /\d/.test(form.next),
  hasSpecial: /[!@#$%^&*()\-_=+\[\]{};:'",.<>?/\\|`~]/.test(form.next),
}))
const passwordValid    = computed(() => Object.values(pwRules.value).every(Boolean))
const passwordMismatch = computed(() => form.confirm.length > 0 && form.next !== form.confirm)

function fieldCls(field: keyof typeof touched, invalid: boolean) {
  return touched[field] && invalid
    ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15'
    : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'
}

function save() {
  Object.assign(touched, { current: true, next: true, confirm: true })
  if (!form.current || !passwordValid.value || passwordMismatch.value) return
  emit('save', { current: form.current, next: form.next })
  Object.assign(form, { current: '', next: '', confirm: '' })
  Object.assign(touched, { current: false, next: false, confirm: false })
  showCurrent.value = false; showNext.value = false; showConfirm.value = false
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm">

    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
        <ShieldCheck class="w-4 h-4 text-red-500" />
      </div>
      <div>
        <h3 class="text-sm font-semibold text-black">Security</h3>
        <p class="text-xs text-black/40">Change your password. You'll be asked to log in again after saving.</p>
      </div>
    </div>

    <form @submit.prevent="save" class="px-6 py-5">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- Current password -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Current Password <span class="text-red-500">*</span></label>
          <div class="relative">
            <input v-model="form.current" @blur="touched.current = true"
              :type="showCurrent ? 'text' : 'password'" placeholder="Current password"
              :class="['w-full h-9 rounded-md border bg-white pl-3 pr-10 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', fieldCls('current', !form.current)]" />
            <button type="button" @click="showCurrent = !showCurrent" tabindex="-1"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-black/30 hover:text-black/60 transition-colors">
              <EyeOff v-if="showCurrent" class="w-4 h-4" /><Eye v-else class="w-4 h-4" />
            </button>
          </div>
          <p v-if="touched.current && !form.current" class="text-xs text-red-500">Required.</p>
        </div>

        <!-- New password -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">New Password <span class="text-red-500">*</span></label>
          <div class="relative">
            <input v-model="form.next" @blur="touched.next = true"
              :type="showNext ? 'text' : 'password'" placeholder="New password"
              :class="['w-full h-9 rounded-md border bg-white pl-3 pr-10 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', fieldCls('next', touched.next && !passwordValid)]" />
            <button type="button" @click="showNext = !showNext" tabindex="-1"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-black/30 hover:text-black/60 transition-colors">
              <EyeOff v-if="showNext" class="w-4 h-4" /><Eye v-else class="w-4 h-4" />
            </button>
          </div>
          <div v-if="touched.next || form.next.length > 0" class="flex flex-wrap gap-x-3 gap-y-1 pt-1">
            <span v-for="(ok, rule) in pwRules" :key="rule"
              class="flex items-center gap-1 text-[10px]" :class="ok ? 'text-emerald-600' : 'text-black/35'">
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="ok ? 'bg-emerald-500' : 'bg-black/20'" />
              {{ rule === 'minLength' ? '8+ chars' : rule === 'hasUpper' ? 'Uppercase' : rule === 'hasNumber' ? 'One number' : 'Special char' }}
            </span>
          </div>
        </div>

        <!-- Confirm password -->
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Confirm Password <span class="text-red-500">*</span></label>
          <div class="relative">
            <input v-model="form.confirm" @blur="touched.confirm = true"
              :type="showConfirm ? 'text' : 'password'" placeholder="Repeat new password"
              :class="['w-full h-9 rounded-md border bg-white pl-3 pr-10 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', fieldCls('confirm', passwordMismatch)]" />
            <button type="button" @click="showConfirm = !showConfirm" tabindex="-1"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-black/30 hover:text-black/60 transition-colors">
              <EyeOff v-if="showConfirm" class="w-4 h-4" /><Eye v-else class="w-4 h-4" />
            </button>
          </div>
          <p v-if="passwordMismatch" class="text-xs text-red-500">Passwords do not match.</p>
        </div>

      </div>

      <div class="mt-4">
        <Button type="submit" class="h-9 px-5 text-sm bg-red-600 hover:bg-red-700 text-white">
          Update password
        </Button>
      </div>
    </form>

  </div>
</template>
