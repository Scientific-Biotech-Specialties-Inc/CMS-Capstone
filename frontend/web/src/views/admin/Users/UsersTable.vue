<script setup lang="ts">
import { MoreHorizontal, Pencil, Trash2, Eye, Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import TablePagination from '@/components/shared/TablePagination.vue'
import { getInitials, avatarColor, roleBadgeFor } from '@/types/user'
import type { User, RoleTab } from '@/types/user'

type TabValue = string

const props = defineProps<{
  paginatedUsers:  User[]
  filteredUsers:   User[]
  selectedIds:     string[]
  allPageSelected: boolean
  currentPage:     number
  itemsPerPage:    number
  activeTab:       TabValue
  searchQuery:     string
  // Dynamic role tabs (built by the parent from auth role systems).
  tabs:            RoleTab[]
}>()

const emit = defineEmits<{
  openViewProfile:    [u: User]
  openEditUser:       [u: User]
  openDeleteConfirm:  [u: User]
  toggleRow:          [id: string]
  toggleSelectAll:    []
  'update:currentPage': [page: number]
  'update:activeTab':   [tab: TabValue]
  'update:searchQuery': [q: string]
}>()
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

    <div class="px-6 pt-5 pb-4 border-b border-black/5">
      <h2 class="text-sm font-semibold text-black">
        All users <span class="text-black/30 font-normal">({{ filteredUsers.length }})</span>
      </h2>
    </div>

    <div class="flex items-center justify-between px-6 py-3 border-b border-black/5">
      <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1">
        <button v-for="tab in props.tabs" :key="tab.value"
          @click="emit('update:activeTab', tab.value)"
          class="px-4 py-1.5 text-sm rounded transition-all font-medium"
          :class="activeTab === tab.value ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
          {{ tab.label }}
        </button>
      </div>
      <div class="relative w-52">
        <Search class="w-3.5 h-3.5 text-black/30 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input :value="searchQuery" @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value.trim())"
          type="text" placeholder="Search..."
          class="w-full h-9 rounded-lg border border-black/10 bg-white pl-8.5 pr-3 text-sm placeholder:text-black/25 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue/15 transition" />
      </div>
    </div>

    <Table>
      <TableHeader class="bg-black/[0.018]">
        <TableRow class="border-b border-black/4 hover:bg-transparent">
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3 pl-6">Name</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Role</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Status</TableHead>
          <TableHead class="text-[11px] font-semibold text-black/40 uppercase tracking-wider py-3">Date added</TableHead>
          <TableHead class="w-14 py-3" />
        </TableRow>
      </TableHeader>

      <TableBody>
        <TableRow v-for="(user, index) in paginatedUsers" :key="user.id"
          class="border-b border-black/4 last:border-0 transition-colors"
          :class="selectedIds.includes(user.id) ? 'bg-brand-navy/2.5' : 'hover:bg-black/1.2'">

          <TableCell class="py-4 pl-6">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 select-none"
                :style="{ backgroundColor: avatarColor(index) }">
                {{ getInitials(user.name) }}
              </div>
              <div>
                <p class="text-sm font-medium text-black leading-snug">{{ user.name }}</p>
                <p class="text-xs text-black/35 mt-0.5">{{ user.email }} &bull; <span class="font-medium opacity-75">{{ user.department }}</span></p>
              </div>
            </div>
          </TableCell>

          <TableCell class="py-4">
            <Badge variant="outline" class="text-xs font-medium rounded-full px-2.5 py-0.5" :class="roleBadgeFor(user.role)">
              {{ user.role }}
            </Badge>
          </TableCell>

          <TableCell class="py-4">
            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full border"
              :class="user.status === 'Active'
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                : 'bg-black/4 text-black/35 border-black/8'">
              {{ user.status }}
            </span>
          </TableCell>

          <TableCell class="text-sm text-black/40 py-4">{{ user.dateAdded }}</TableCell>

          <TableCell class="py-4 pr-4">
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon"
                  class="h-8 w-8 text-black/60 hover:text-black hover:bg-black/5 data-[state=open]:bg-black/5 data-[state=open]:text-black">
                  <MoreHorizontal class="w-4 h-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-44">
                <DropdownMenuLabel class="text-xs font-semibold text-black/38 pb-1">Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="emit('openViewProfile', user)" class="gap-2.5 text-sm cursor-pointer">
                  <Eye class="w-3.5 h-3.5 text-black/40" /> View profile
                </DropdownMenuItem>
                <DropdownMenuItem @click="emit('openEditUser', user)" class="gap-2.5 text-sm cursor-pointer">
                  <Pencil class="w-3.5 h-3.5 text-black/40" /> Edit user
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="emit('openDeleteConfirm', user)"
                  class="gap-2.5 text-sm cursor-pointer text-red-600 focus:text-red-600 focus:bg-red-50">
                  <Trash2 class="w-3.5 h-3.5" /> Delete user
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </TableCell>
        </TableRow>

        <TableRow v-if="paginatedUsers.length === 0">
          <TableCell colspan="5" class="text-center py-16">
            <p class="text-sm font-semibold text-black/28">No users found</p>
            <p class="text-xs text-black/20 mt-1">Try a different search or tab</p>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>

    <div class="flex justify-center px-6 py-4 border-t border-black/5">
      <TablePagination :current-page="currentPage" :total-items="filteredUsers.length"
        :items-per-page="itemsPerPage" :current-page-items-count="paginatedUsers.length" @update:current-page="emit('update:currentPage', $event)" />
    </div>

  </div>
</template>
