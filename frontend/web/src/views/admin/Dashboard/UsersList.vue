<script setup lang="ts">
import { BRAND_PALETTE } from '@/constants/theme'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

type Role   = 'Admin' | 'Manager' | 'Sales'
type Status = 'Active' | 'Inactive'

interface DashUser {
  id: string; name: string; email: string; role: Role; status: Status
}

const router = useRouter()

const props = defineProps<{
  users: DashUser[]
}>()

const userFilter = ref<'All' | Role>('All')

const filteredUsers = computed(() =>
  userFilter.value === 'All'
    ? props.users
    : props.users.filter(u => u.role === userFilter.value)
)

const roleBadge: Record<Role, string> = {
  Admin:   'bg-brand-navy/8 text-brand-navy border-brand-navy/20',
  Manager: 'bg-brand-dark/8 text-brand-dark border-brand-dark/20',
  Sales:   'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
}

const palette = BRAND_PALETTE
function getInitials(name: string) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}
function avatarColor(idx: number) { return palette[idx % palette.length] }
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">
    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-black">
        User list
        <span class="text-black/30 font-normal">({{ filteredUsers.length }})</span>
      </h2>
      <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1">
        <button
          v-for="f in ['All','Admin','Manager','Sales']"
          :key="f"
          @click="userFilter = f as any"
          class="px-3 py-1 text-xs rounded transition-all font-medium"
          :class="userFilter === f ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'"
        >
          {{ f }}
        </button>
      </div>
    </div>

    <div v-if="filteredUsers.length === 0" class="p-8 text-center text-sm text-black/40">
      No users match this filter.
    </div>
    <Table v-else>
      <TableHeader class="bg-black/[0.018]">
        <TableRow class="border-b border-black/4 hover:bg-transparent">
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider pl-6 py-3">Name</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Role</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow
          v-for="(user, index) in filteredUsers.slice(0, 5)"
          :key="user.id"
          class="border-b border-black/4 last:border-0 hover:bg-black/1.2 transition-colors cursor-pointer"
          @click="router.push('/admin/users')"
        >
          <TableCell class="pl-6 py-3.5">
            <div class="flex items-center gap-3">
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 select-none"
                :style="{ backgroundColor: avatarColor(index) }"
              >
                {{ getInitials(user.name) }}
              </div>
              <div>
                <p class="text-sm font-medium text-black leading-snug">{{ user.name }}</p>
                <p class="text-xs text-black/35 mt-0.5">{{ user.email }}</p>
              </div>
            </div>
          </TableCell>
          <TableCell class="py-3.5">
            <Badge variant="outline" class="text-xs font-medium rounded-full px-2.5 py-0.5"
              :class="roleBadge[user.role]">
              {{ user.role }}
            </Badge>
          </TableCell>
          <TableCell class="py-3.5">
            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full border"
              :class="user.status === 'Active'
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                : 'bg-black/4 text-black/35 border-black/8'">
              {{ user.status }}
            </span>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>

    <div class="px-6 py-3 border-t border-black/5">
      <button @click="router.push('/admin/users')" class="flex items-center gap-1.5 text-xs font-semibold text-brand-blue hover:text-brand-navy transition-colors">
        View all users <ArrowRight class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
