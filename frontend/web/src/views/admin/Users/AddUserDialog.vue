<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { UserPlus } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import type { Role } from '@/types/user'

const props = defineProps<{
  open: boolean;
  roles?: { id: number; name: string }[];
  departments?: { id: number; name: string }[];
}>()
const emit  = defineEmits<{ 'update:open': [v: boolean]; submit: [data: any] }>()

const showConfirmCreate = ref(false)

const form = reactive({
  firstName: '', lastName: '', middleName: '',
  email: '', role: '' as Role | '', department: 'Sales & Marketing',
})
const touched = reactive({
  firstName: false, lastName: false, email: false,
})

// const ALLOWED_DOMAIN = 'sbsi.com'
const emailFormatValid = computed(() => !form.email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))
// const emailDomainValid = computed(() => !form.email || form.email.toLowerCase().endsWith(`@${ALLOWED_DOMAIN}`))
const emailValid       = computed(() => emailFormatValid.value /* && emailDomainValid.value */)

function onNameInput(field: 'firstName' | 'lastName' | 'middleName', e: Event) {
  const el = e.target as HTMLInputElement
  const clean = el.value.replace(/[^a-zA-Z\s\-']/g, '')
  form[field] = clean
  el.value = clean
  if (field !== 'middleName') touched[field] = true
}

function reset() {
  Object.assign(form, { firstName: '', lastName: '', middleName: '', email: '', role: '', department: 'Sales & Marketing' })
  Object.assign(touched, { firstName: false, lastName: false, email: false })
}

watch(() => props.open, open => { if (!open) reset() })

function submit() {
  Object.assign(touched, { firstName: true, lastName: true, email: true })
  if (!form.firstName || !form.lastName || !form.email || !emailFormatValid.value /* || !emailDomainValid.value */ ||
      !form.role || !form.department) return
  
  showConfirmCreate.value = true
}

function confirmSubmit() {
  showConfirmCreate.value = false
  emit('submit', {
    first_name: form.firstName,
    middle_name: form.middleName,
    last_name: form.lastName,
    email: form.email,
    role_name: form.role,
    department_name: form.department
  })
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-xl" @pointer-down-outside="$emit('update:open', false)">

      <div class="px-6 pt-6 pb-5 border-b border-black/6">
        <div class="flex items-center gap-3 mb-1">
          <div class="w-9 h-9 rounded-lg bg-brand-navy/8 flex items-center justify-center shrink-0">
            <UserPlus class="w-4.5 h-4.5 text-brand-navy" />
          </div>
          <DialogHeader>
            <DialogTitle>Add new user</DialogTitle>
            <DialogDescription>Fill in the details below to create a new team member account.</DialogDescription>
          </DialogHeader>
        </div>
      </div>

      <form @submit.prevent="submit" class="px-6 py-5 space-y-4">

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">First name <span class="text-red-500">*</span></label>
            <input :value="form.firstName" @input="onNameInput('firstName', $event)" @blur="touched.firstName = true"
              type="text" placeholder="e.g. Sarah" maxlength="50"
              :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition',
                touched.firstName && !form.firstName ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15']" />
            <p v-if="touched.firstName && !form.firstName" class="text-xs text-red-500">Required.</p>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Last name <span class="text-red-500">*</span></label>
            <input :value="form.lastName" @input="onNameInput('lastName', $event)" @blur="touched.lastName = true"
              type="text" placeholder="e.g. Jenkins" maxlength="50"
              :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition',
                touched.lastName && !form.lastName ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15']" />
            <p v-if="touched.lastName && !form.lastName" class="text-xs text-red-500">Required.</p>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">
            Middle name <span class="normal-case font-normal text-black/30 ml-1">(optional)</span>
          </label>
          <input :value="form.middleName" @input="onNameInput('middleName', $event)"
            type="text" placeholder="e.g. Anne" maxlength="50"
            class="w-full h-9 rounded-md border border-black/12 bg-white px-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/15 transition" />
          <p class="text-[11px] text-black/30">Letters only — no numbers or special characters.</p>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Email address <span class="text-red-500">*</span></label>
          <input v-model="form.email" @blur="touched.email = true" type="text" placeholder="e.g. sarah.j@sbsi.com" maxlength="254"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition',
              touched.email && (!form.email || !emailValid) ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15']" />
          <p v-if="touched.email && !form.email" class="text-xs text-red-500">Required.</p>
          <p v-else-if="touched.email && !emailFormatValid" class="text-xs text-red-500">Enter a valid email address.</p>
          <!-- <p v-else-if="touched.email && !emailDomainValid" class="text-xs text-red-500">Email must use the company domain <span class="font-semibold">@sbsi.com</span></p> -->
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-md p-3.5 flex gap-3 text-blue-800">
          <div class="w-5 h-5 shrink-0 mt-0.5 opacity-80">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
          </div>
          <div class="text-sm">
            <p class="font-medium mb-0.5">Automated Credentials</p>
            <p class="text-blue-700/80 leading-relaxed">A secure temporary password will be automatically generated and sent to this employee's email. The account will be inactive until the employee logs in and changes their password.</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Role <span class="text-red-500">*</span></label>
            <Select v-model="form.role">
              <SelectTrigger class="h-9 rounded-md border-black/12 text-sm focus:ring-brand-blue/15 focus:border-brand-blue">
                <SelectValue placeholder="Select role" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Department <span class="text-red-500">*</span></label>
            <input
              type="text"
              disabled
              value="Sales & Marketing"
              class="w-full h-9 rounded-md border border-black/12 bg-black/[0.02] px-3 text-sm text-black/50 cursor-not-allowed"
            />
          </div>
        </div>

        <div class="border-t border-black/6 pt-4">
          <DialogFooter>
            <Button type="button" variant="outline" @click="$emit('update:open', false)"
              class="h-9 px-4 text-sm border-black/15 text-black/60 hover:text-black">Cancel</Button>
            <Button type="submit" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white">Add user</Button>
          </DialogFooter>
        </div>

        <ConfirmationDialog
          v-model:open="showConfirmCreate"
          title="Create User Account"
          description="Are you sure you want to create this user account? A temporary password will be sent to their email."
          confirm-label="Create"
          variant="default"
          @confirm="confirmSubmit"
        />
      </form>
    </DialogContent>
  </Dialog>
</template>
