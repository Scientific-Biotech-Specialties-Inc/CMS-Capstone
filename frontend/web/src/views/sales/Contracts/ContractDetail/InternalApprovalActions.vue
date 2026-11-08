<script setup lang="ts">
import { ref } from 'vue'
import { CheckCircle2, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useToast } from '@/composables/useToast'

type Task = { id: number; role_name: string }
type Decision = 'approved' | 'rejected'
const props = defineProps<{ tasks: Task[]; save: (task: number, decision: Decision, reason?: string) => Promise<void> }>()
const emit = defineEmits<{ changed: [] }>()
const { success } = useToast()
const selected = ref<{ task: Task; decision: Decision } | null>(null)
const reason = ref('')
const error = ref('')
const saving = ref(false)

function open(task: Task, decision: Decision) {
  selected.value = { task, decision }
  reason.value = ''
  error.value = ''
}

async function submit() {
  if (!selected.value || saving.value) return
  const { task, decision } = selected.value
  if (decision === 'rejected' && !reason.value.trim()) { error.value = 'Enter a rejection reason.'; return }
  saving.value = true
  try {
    await props.save(task.id, decision, reason.value.trim() || undefined)
    selected.value = null
    success('Approval decision recorded')
    emit('changed')
  } catch (failure) {
    error.value = failure instanceof Error ? failure.message : 'Approval decision could not be saved.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6" aria-labelledby="approval-actions-title">
    <h2 id="approval-actions-title" class="font-semibold text-slate-800">Your Internal Approval Tasks</h2>
    <ul class="mt-4 divide-y divide-slate-200">
      <li v-for="task in tasks" :key="task.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
        <span class="font-semibold text-slate-800">{{ task.role_name }}</span>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" @click="open(task, 'rejected')">Reject</Button>
          <Button class="bg-emerald-600 text-white hover:bg-emerald-700" @click="open(task, 'approved')">Approve</Button>
        </div>
      </li>
    </ul>
  </section>
  <Dialog :open="selected !== null" @update:open="value => { if (!value && !saving) selected = null }">
    <DialogContent v-if="selected" class="rounded-2xl p-4 sm:p-6">
      <DialogHeader class="items-center text-center">
        <span class="rounded-full bg-brand-blue/10 p-2 text-brand-navy"><CheckCircle2 class="size-5" /></span>
        <DialogTitle class="text-xl font-bold">{{ selected.decision === 'approved' ? 'Approve Draft' : 'Reject Draft' }}</DialogTitle>
        <DialogDescription>Record the decision for {{ selected.task.role_name }} on this draft.</DialogDescription>
      </DialogHeader>
      <form class="space-y-4" @submit.prevent="submit">
        <label v-if="selected.decision === 'rejected'" class="block text-sm font-semibold">Rejection Reason
          <textarea v-model="reason" maxlength="2000" rows="3" class="mt-2 block w-full rounded-lg border border-slate-200 p-2" />
        </label>
        <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
        <div class="flex justify-center gap-2">
          <Button type="button" variant="outline" :disabled="saving" @click="selected = null">Cancel</Button>
          <Button type="submit" :disabled="saving" :class="selected.decision === 'approved' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'">
            <Loader2 v-if="saving" class="size-4 animate-spin" /> {{ selected.decision === 'approved' ? 'Approve' : 'Reject' }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
