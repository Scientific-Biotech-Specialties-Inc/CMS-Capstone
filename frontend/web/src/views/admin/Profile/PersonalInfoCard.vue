<script setup lang="ts">
import { reactive, watch, computed, ref } from 'vue'
import { UserRound } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const props = defineProps<{
  profile: {
    firstName: string; lastName: string; middleName: string
    email: string; phone: string; role: string; department: string
  }
}>()

const emit = defineEmits<{ save: [data: Partial<typeof props.profile>] }>()

const form = reactive({ ...props.profile, department: 'Sales & Marketing' })
watch(() => props.profile, p => {
  Object.assign(form, p)
  form.department = 'Sales & Marketing'
}, { deep: true })

const touched = reactive({
  firstName: false, lastName: false, email: false, phone: false,
})

const emailValid = computed(() => !form.email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))
const phoneValid = computed(() => !form.phone || /^09\d{9}$/.test(form.phone))

function onNameInput(field: 'firstName' | 'lastName' | 'middleName', e: Event) {
  const el = e.target as HTMLInputElement
  const clean = el.value.replace(/[^a-zA-Z\s\-'.]/g, '')
  form[field] = clean
  el.value = clean
  if (field !== 'middleName') touched[field as keyof typeof touched] = true
}

function fieldCls(field: keyof typeof touched, invalid: boolean) {
  return touched[field] && invalid
    ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15'
    : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15'
}

const showConfirm = ref(false)

function confirmSave() {
  Object.assign(touched, { firstName: true, lastName: true, email: true, phone: true })
  if (!form.firstName || !form.lastName || !form.email || !emailValid.value || !form.phone || !phoneValid.value) return
  showConfirm.value = true
}

function save() {
  showConfirm.value = false
  emit('save', { ...form, department: 'Sales & Marketing' })
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm">

    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
        <UserRound class="w-4 h-4 text-brand-navy" />
      </div>
      <div>
        <h3 class="text-sm font-semibold text-black">Personal Information</h3>
        <p class="text-xs text-black/40">Update your name, contact details and department.</p>
      </div>
    </div>

    <form @submit.prevent="confirmSave" class="px-6 py-5 space-y-4">

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">First name <span class="text-red-500">*</span></label>
          <input :value="form.firstName" @input="onNameInput('firstName', $event)" @blur="touched.firstName = true"
            type="text" placeholder="e.g. Shadrack" maxlength="50"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', fieldCls('firstName', !form.firstName)]" />
          <p v-if="touched.firstName && !form.firstName" class="text-xs text-red-500">Required.</p>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Last name <span class="text-red-500">*</span></label>
          <input :value="form.lastName" @input="onNameInput('lastName', $event)" @blur="touched.lastName = true"
            type="text" placeholder="e.g. Castro" maxlength="50"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', fieldCls('lastName', !form.lastName)]" />
          <p v-if="touched.lastName && !form.lastName" class="text-xs text-red-500">Required.</p>
        </div>
      </div>

      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">
          Middle name <span class="normal-case font-normal text-black/30 ml-1">(optional)</span>
        </label>
        <input :value="form.middleName" @input="onNameInput('middleName', $event)" type="text" placeholder="e.g. Miguel" maxlength="50"
          class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/15 transition" />
      </div>

      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Email <span class="text-red-500">*</span></label>
        <input v-model="form.email" @blur="touched.email = true" type="email" placeholder="you@sbsi.com" maxlength="254"
          :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', fieldCls('email', !form.email || !emailValid)]" />
        <p v-if="touched.email && !form.email" class="text-xs text-red-500">Required.</p>
        <p v-else-if="touched.email && !emailValid" class="text-xs text-red-500">Enter a valid email address.</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Phone <span class="text-red-500">*</span></label>
          <input v-model="form.phone" @blur="touched.phone = true" type="tel" placeholder="e.g. 09123456789" maxlength="20"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition', fieldCls('phone', !form.phone || !phoneValid)]" />
          <p v-if="touched.phone && !form.phone" class="text-xs text-red-500">Required.</p>
          <p v-else-if="touched.phone && !phoneValid" class="text-xs text-red-500">Must be an 11-digit mobile number starting with 09.</p>
        </div>
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Department</label>
          <input
            type="text"
            disabled
            value="Sales & Marketing"
            class="w-full h-9 rounded-md border border-black/12 bg-black/[0.02] px-3 text-sm text-black/50 cursor-not-allowed"
          />
        </div>
      </div>

      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Role</label>
        <input :value="form.role" disabled type="text"
          class="w-full h-9 rounded-md border border-black/8 bg-black/2.5 px-3 text-sm text-black/40 cursor-not-allowed" />
        <p class="text-[11px] text-black/30">Role is managed by system administrators.</p>
      </div>

      <div class="pt-1">
        <Button type="submit" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white">
          Save changes
        </Button>
      </div>

    </form>

    <ConfirmationDialog
      v-model:open="showConfirm"
      title="Save Profile Details"
      description="Are you sure you want to save these changes to your personal information?"
      confirm-text="Save changes"
      @confirm="save"
    />
  </div>
</template>
