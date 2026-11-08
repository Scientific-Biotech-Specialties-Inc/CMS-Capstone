<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { CheckCheck } from 'lucide-vue-next'
import NotificationList from '@/components/shared/NotificationList.vue'
import { useNotifications } from '@/composables/useNotifications'
import { useToast } from '@/composables/useToast'
import type { TabKey } from '@/types/notification'

const { notifications, unreadCount, fetchNotifications, markRead, markAllRead, updateState, deletePermanent, deletePermanentBatch } = useNotifications()
const { success } = useToast()

const activeTab   = ref<TabKey>('all')
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = 15

const allCount      = computed(() => notifications.value.filter(n => !n.isArchived && n.type !== 'system').length)
const systemCount   = computed(() => notifications.value.filter(n => !n.isArchived && n.type === 'system').length)
const archiveCount  = computed(() => notifications.value.filter(n => n.isArchived).length)
const favoriteCount = computed(() => notifications.value.filter(n => n.isFavorite && !n.isArchived).length)

const tabs = computed(() => [
  { key: 'all'      as TabKey, label: 'All',       count: allCount.value      },
  { key: 'system'   as TabKey, label: 'System',    count: systemCount.value   },
  { key: 'archive'  as TabKey, label: 'Archive',   count: archiveCount.value  },
  { key: 'favorite' as TabKey, label: 'Favorite',  count: favoriteCount.value },
])

const filtered = computed(() => {
  let list = notifications.value
  if (activeTab.value === 'all')      list = list.filter(n => !n.isArchived && n.type !== 'system')
  if (activeTab.value === 'system')   list = list.filter(n => !n.isArchived && n.type === 'system')
  if (activeTab.value === 'archive')  list = list.filter(n =>  n.isArchived)
  if (activeTab.value === 'favorite') list = list.filter(n =>  n.isFavorite && !n.isArchived)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(n => n.message.toLowerCase().includes(q))
  }
  return list
})

watch([activeTab, searchQuery], () => { currentPage.value = 1 })

const paginated = computed(() =>
  filtered.value.slice((currentPage.value - 1) * itemsPerPage, currentPage.value * itemsPerPage)
)

function toggleRead(id: string)     { markRead(id) }
function toggleFavorite(id: string) { const n = notifications.value.find(x => x.id === id); if (n) updateState(id, { isFavorite: !n.isFavorite }) }

async function deleteNotif(id: string) {
  if (activeTab.value === 'archive') {
    await deletePermanent(id)
    success('Notification deleted', 'The notification has been permanently removed.')
  } else {
    await updateState(id, { isArchived: true })
    success('Notification archived', 'The notification has been moved to Archive.')
  }
}

async function deleteSelected(ids: string[]) {
  if (ids.length === 0) return
  if (activeTab.value === 'archive') {
    await deletePermanentBatch(ids)
    success('Notifications deleted', `${ids.length} selected notification(s) have been permanently removed.`)
  } else {
    for (const id of ids) {
      await updateState(id, { isArchived: true })
    }
    success('Notifications archived', `${ids.length} selected notification(s) have been moved to Archive.`)
  }
}

async function restoreNotif(id: string) {
  await updateState(id, { isArchived: false })
  success('Notification restored', 'The notification has been restored to your inbox.')
}

async function restoreSelected(ids: string[]) {
  if (ids.length === 0) return
  for (const id of ids) {
    await updateState(id, { isArchived: false })
  }
  success('Notifications restored', `${ids.length} selected notification(s) have been restored to your inbox.`)
}

onMounted(async () => {
  await fetchNotifications()
})
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-xl font-semibold text-black">Notifications</h1>
        <p class="text-sm text-black/40 mt-0.5">View and manage all system notifications.</p>
      </div>
      <button v-if="unreadCount > 0" @click="markAllRead"
        class="flex items-center gap-2 text-sm font-medium text-brand-blue hover:text-brand-navy transition-colors">
        <CheckCheck class="w-4 h-4" /> Mark all as read
      </button>
    </div>

    <NotificationList
      :tabs="tabs"
      :filtered="filtered"
      :paginated="paginated"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      :unread-count="unreadCount"
      v-model:active-tab="activeTab"
      v-model:search-query="searchQuery"
      @update:current-page="currentPage = $event"
      @toggle-read="toggleRead"
      @toggle-favorite="toggleFavorite"
      @delete="deleteNotif"
      @delete-selected="deleteSelected"
      @restore="restoreNotif"
      @restore-selected="restoreSelected"
    />

  </div>
</template>
