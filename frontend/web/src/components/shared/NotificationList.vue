<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Bell, Search, Trash2, Check, Minus, ArchiveRestore } from 'lucide-vue-next'
import NotificationItem from './NotificationItem.vue'
import ConfirmationDialog from './ConfirmationDialog.vue'
import TablePagination from './TablePagination.vue'
import type { Notification, TabKey } from '@/types/notification'

const props = defineProps<{
  tabs:         { key: TabKey; label: string; count: number }[]
  activeTab:    TabKey
  searchQuery:  string
  filtered:     Notification[]
  paginated?:   Notification[]
  currentPage?: number
  itemsPerPage?: number
  unreadCount: number
}>()

const emit = defineEmits<{
  'update:activeTab':   [tab: TabKey]
  'update:searchQuery': [q: string]
  'update:currentPage': [page: number]
  'toggle-read':        [id: string]
  'toggle-favorite':    [id: string]
  'delete':             [id: string]
  'delete-selected':    [ids: string[]]
  'restore':            [id: string]
  'restore-selected':   [ids: string[]]
}>()

const displayed = computed(() => props.paginated ?? props.filtered)

const showDeleteConfirm = ref(false)
const notifIdToDelete = ref<string | null>(null)

function triggerDelete(id: string) {
  notifIdToDelete.value = id
  showDeleteConfirm.value = true
}

function confirmDelete() {
  if (notifIdToDelete.value) {
    emit('delete', notifIdToDelete.value)
  }
  showDeleteConfirm.value = false
  notifIdToDelete.value = null
}

const showDeleteAllConfirm = ref(false)
const selectedIds = ref<Set<string>>(new Set())

const allSelected = computed(() => displayed.value.length > 0 && selectedIds.value.size === displayed.value.length)
const someSelected = computed(() => selectedIds.value.size > 0 && selectedIds.value.size < displayed.value.length)

watch(() => props.filtered, () => {
  selectedIds.value.clear()
})

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value.clear()
  } else {
    displayed.value.forEach(n => selectedIds.value.add(n.id))
  }
}

function confirmDeleteAll() {
  emit('delete-selected', Array.from(selectedIds.value))
  selectedIds.value.clear()
  showDeleteAllConfirm.value = false
}

function restoreSelected() {
  emit('restore-selected', Array.from(selectedIds.value))
  selectedIds.value.clear()
}
</script>

<template>
  <div class="bg-white rounded-lg border border-black/8 shadow-sm overflow-hidden">

    <div class="px-6 pt-5 pb-4 border-b border-black/5 flex items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-semibold text-black">List Notifications</h2>
        <span v-if="unreadCount > 0"
          class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-brand-blue text-white tabular-nums">
          {{ unreadCount }} unread
        </span>
      </div>
      <div class="relative w-64">
        <Search class="w-3.5 h-3.5 text-black/35 absolute left-3 top-1/2 -translate-y-1/2" />
        <input :value="searchQuery" @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value.trim())"
          type="text" placeholder="Search notifications..."
          class="w-full rounded-lg border border-black/10 bg-black/2 py-2 pl-8 pr-3 text-sm text-black placeholder:text-black/35 focus:border-brand-blue focus:outline-none transition-colors" />
      </div>
    </div>

    <div class="px-6 py-3 border-b border-black/5 flex items-center justify-between">
      <div class="flex items-center gap-0.5 bg-black/4 rounded-md p-1">
        <button v-for="tab in tabs" :key="tab.key"
          @click="emit('update:activeTab', tab.key); emit('update:searchQuery', '')"
          class="flex items-center gap-1.5 px-3 py-1 text-xs rounded transition-all font-medium"
          :class="activeTab === tab.key ? 'bg-white text-black shadow-sm' : 'text-black/40 hover:text-black/60'">
          {{ tab.label }}
          <span class="text-[10px] font-semibold tabular-nums px-1.5 py-0.5 rounded-full min-w-4.5 text-center"
            :class="activeTab === tab.key ? 'bg-brand-navy/8 text-brand-navy' : 'bg-black/8 text-black/40'">
            {{ tab.count }}
          </span>
        </button>
      </div>
      <div class="flex items-center gap-4">
        <button type="button" v-if="filtered.length > 0 && activeTab !== 'email_logs'" @click="toggleSelectAll"
          class="flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-black/[0.04] active:bg-black/[0.06] transition-colors group select-none -ml-2">
          <div class="w-4 h-4 rounded-[4px] flex items-center justify-center border transition-all duration-200"
            :class="allSelected || someSelected ? 'bg-brand-blue border-brand-blue text-white shadow-sm' : 'border-black/20 bg-white group-hover:border-black/40 text-transparent'">
            <Check v-if="allSelected" class="w-3 h-3" stroke-width="3.5" />
            <Minus v-else-if="someSelected" class="w-3 h-3" stroke-width="3.5" />
          </div>
          <span class="text-xs font-semibold text-black/60 group-hover:text-black/80 transition-colors">
            Select All
          </span>
        </button>
        <template v-if="selectedIds.size > 0 && activeTab !== 'email_logs'">
          <button v-if="activeTab === 'archive'" @click="restoreSelected"
            class="flex items-center gap-1.5 text-xs font-semibold text-brand-blue hover:text-brand-navy transition-colors">
            <ArchiveRestore class="w-3.5 h-3.5" />
            Restore Selected ({{ selectedIds.size }})
          </button>
          <button @click="showDeleteAllConfirm = true"
            class="flex items-center gap-1.5 text-xs font-semibold text-red-500 hover:text-red-700 transition-colors">
            <Trash2 class="w-3.5 h-3.5" />
            {{ activeTab === 'archive' ? 'Permanently Delete' : 'Archive' }} Selected ({{ selectedIds.size }})
          </button>
        </template>
        <p class="text-xs text-black/35">{{ filtered.length }} notification{{ filtered.length !== 1 ? 's' : '' }}</p>
      </div>
    </div>

    <div class="divide-y divide-black/4">
      <slot v-if="activeTab === 'email_logs'" name="email-logs" />
      <template v-else>
        <div v-if="filtered.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
          <div class="w-12 h-12 rounded-full bg-black/4 flex items-center justify-center mb-3">
            <Bell class="w-5 h-5 text-black/30" />
          </div>
          <p class="text-sm font-medium text-black/50">No notifications</p>
          <p class="text-xs text-black/30 mt-0.5">You're all caught up!</p>
        </div>

        <NotificationItem
          v-for="notif in displayed"
          :key="notif.id"
          :notif="notif"
          :selected="selectedIds.has(notif.id)"
          :is-archived="activeTab === 'archive'"
          @update:selected="$event ? selectedIds.add(notif.id) : selectedIds.delete(notif.id)"
          @toggle-read="emit('toggle-read', $event)"
          @toggle-favorite="emit('toggle-favorite', $event)"
          @delete="triggerDelete"
          @restore="emit('restore', $event)"
        />
      </template>
    </div>

    <div v-if="activeTab !== 'email_logs' && paginated && currentPage !== undefined && itemsPerPage !== undefined"
      class="flex justify-center px-6 py-4 border-t border-black/5">
      <TablePagination
        :current-page="currentPage"
        :total-items="filtered.length"
        :items-per-page="itemsPerPage"
        :current-page-items-count="paginated.length"
        @update:current-page="emit('update:currentPage', $event)"
      />
    </div>

    <ConfirmationDialog
      v-model:open="showDeleteConfirm"
      :title="activeTab === 'archive' ? 'Permanently Delete Notification' : 'Archive Notification'"
      :description="activeTab === 'archive'
        ? 'Are you sure you want to permanently delete this notification? This action cannot be undone.'
        : 'Are you sure you want to delete this notification? It will be moved to your Archive tab.'"
      :confirm-label="activeTab === 'archive' ? 'Permanently Delete' : 'Archive'"
      variant="destructive"
      @confirm="confirmDelete"
    />

    <ConfirmationDialog
      v-model:open="showDeleteAllConfirm"
      :title="activeTab === 'archive' ? 'Permanently Delete Selected Notifications' : 'Archive Selected Notifications'"
      :description="activeTab === 'archive'
        ? `Are you sure you want to permanently delete the ${selectedIds.size} selected notification(s)? This action cannot be undone.`
        : `Are you sure you want to archive the ${selectedIds.size} selected notification(s)? They will be moved to your Archive tab.`"
      :confirm-label="activeTab === 'archive' ? 'Permanently Delete' : 'Archive Selected'"
      variant="destructive"
      @confirm="confirmDeleteAll"
    />
  </div>
</template>
