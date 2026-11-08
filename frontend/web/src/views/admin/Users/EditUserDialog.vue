<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { Pencil } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'
import type { User, Role, Status } from '@/types/user'

const props = defineProps<{
  open: boolean;
  user: User | null;
  roles?: { id: number; name: string }[];
  departments?: { id: number; name: string }[];
}>()
const emit  = defineEmits<{
  'update:open': [v: boolean]
  submit: [data: { id: string; name: string; email: string; role: Role; status: Status; department: string }]
}>()

const showConfirmEdit = ref(false)

const form = reactive({
  id: '', firstName: '', lastName: '', middleName: '',
  email: '', role: '' as Role | '', status: '' as Status | '', department: 'Sales & Marketing',
})
const touched = reactive({ firstName: false, lastName: false, email: false })

const emailValid = computed(() => !form.email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))

function onNameInput(field: 'firstName' | 'lastName' | 'middleName', e: Event) {
  const el = e.target as HTMLInputElement
  const clean = el.value.replace(/[^a-zA-Z\s\-']/g, '')
  form[field] = clean
  el.value = clean
  if (field !== 'middleName') touched[field] = true
}

watch(() => props.user, user => {
  if (!user) return
  const parts = user.name.trim().split(/\s+/)
  Object.assign(form, {
    id:         user.id,
    firstName:  parts[0]  ?? '',
    lastName:   parts.length > 1 ? parts[parts.length - 1] : '',
    middleName: parts.length > 2 ? parts.slice(1, -1).join(' ') : '',
    email:      user.email,
    role:       user.role,
    status:     user.status,
    department: 'Sales & Marketing',
  })
  Object.assign(touched, { firstName: false, lastName: false, email: false })
})

function submit() {
  Object.assign(touched, { firstName: true, lastName: true, email: true })
  if (!form.firstName || !form.lastName || !form.email || !emailValid.value || !form.role || !form.status) return
  showConfirmEdit.value = true
}

function confirmSubmit() {
  showConfirmEdit.value = false
  const fullName = [form.firstName, form.middleName, form.lastName].filter(Boolean).join(' ')
  emit('submit', {
    id: form.id,
    name: fullName,
    email: form.email,
    role: form.role as Role,
    status: form.status as Status,
    department: 'Sales & Marketing',
  })
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-lg" @pointer-down-outside="$emit('update:open', false)">

      <div class="px-6 pt-6 pb-5 border-b border-black/6">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-brand-blue/8 flex items-center justify-center shrink-0">
            <Pencil class="w-4 h-4 text-brand-blue" />
          </div>
          <DialogHeader>
            <DialogTitle>Edit user</DialogTitle>
            <DialogDescription>
              Updating details for <span class="font-semibold text-black/70">{{ [form.firstName, form.lastName].filter(Boolean).join(' ') || 'this user' }}</span>.
            </DialogDescription>
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
          <input v-model="form.email" @blur="touched.email = true" type="email" placeholder="e.g. sarah.j@sbsi.com" maxlength="254"
            :class="['w-full h-9 rounded-md border bg-white px-3 text-sm placeholder:text-black/25 focus:outline-none focus:ring-2 transition',
              touched.email && (!form.email || !emailValid) ? 'border-red-400 focus:border-red-400 focus:ring-red-400/15' : 'border-black/12 focus:border-brand-blue focus:ring-brand-blue/15']" />
          <p v-if="touched.email && !form.email" class="text-xs text-red-500">Required.</p>
          <p v-else-if="touched.email && !emailValid" class="text-xs text-red-500">Enter a valid email address.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Role <span class="text-red-500">*</span></label>
            <Select v-model="form.role">
              <SelectTrigger class="h-9 rounded-md border-black/12 text-sm"><SelectValue placeholder="Select role" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-black/55 uppercase tracking-wide">Status <span class="text-red-500">*</span></label>
            <Select v-model="form.status">
              <SelectTrigger class="h-9 rounded-md border-black/12 text-sm"><SelectValue placeholder="Select status" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="Active">Active</SelectItem>
                <SelectItem value="Inactive">Inactive</SelectItem>
              </SelectContent>
            </Select>
          </div>
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

        <div class="border-t border-black/6 pt-4">
          <DialogFooter>
            <Button type="button" variant="outline" @click="$emit('update:open', false)"
              class="h-9 px-4 text-sm border-black/15 text-black/60 hover:text-black">Cancel</Button>
            <Button type="submit" class="h-9 px-5 text-sm bg-brand-navy hover:bg-brand-dark text-white">Save changes</Button>
          </DialogFooter>
        </div>

        <ConfirmationDialog
          v-model:open="showConfirmEdit"
          title="Save User Changes"
          description="Are you sure you want to save the changes for this user account? This will update their credentials and permissions."
          confirm-label="Save"
          variant="default"
          @confirm="confirmSubmit"
        />
      </form>
    </DialogContent>
  </Dialog>
</template>
