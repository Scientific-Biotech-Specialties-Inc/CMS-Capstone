<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { Upload } from 'lucide-vue-next'
import { exportToExcel } from '@/utils/excelExport'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import AuditLogFilters from './AuditLogFilters.vue'
import AuditLogTable   from './AuditLogTable.vue'
import { formatAction } from '@/types/auditLog'
import type { LogEntry, ActionType } from '@/types/auditLog'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const { error: showError, success: showSuccess } = useToast()

const logs = ref<LogEntry[]>([])
const totalLogs = ref(0)
const totalPages = ref(1)
const isFetching = ref(false)

const searchQuery  = ref('')
const actionFilter = ref<ActionType | 'All'>('All')
const dateFilter   = ref('')
const currentPage  = ref(1)
const PAGE_SIZE    = 15

const contractApiUrl = import.meta.env.VITE_CONTRACT_API_URL || 'http://localhost:8002/api'

async function fetchAuditLogs() {
  const { state } = useAuth()
  if (!state.user) return

  isFetching.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', currentPage.value.toString())
    params.append('per_page', PAGE_SIZE.toString())

    if (actionFilter.value !== 'All') {
      params.append('action', actionFilter.value)
    }
    if (dateFilter.value) {
      params.append('date', dateFilter.value)
    }
    if (searchQuery.value.trim()) {
      params.append('search', searchQuery.value.trim())
    }

    const res = await fetch(`${contractApiUrl}/audit-logs?${params.toString()}`, {
      headers: {
        'Authorization': `Bearer ${state.token || ''}`,
        'X-Session-ID': localStorage.getItem('session_id') || '',
        'Accept': 'application/json'
      }
    })

    const data = await res.json()
    if (res.ok && data.data) {
      logs.value = data.data
      totalLogs.value = data.total
      totalPages.value = data.last_page || 1
    } else {
      showError('Fetch failed', data.message || 'Could not load audit logs from the server.')
    }
  } catch (err) {
    console.error('Failed to fetch audit logs:', err)
    showError('Network Error', 'Could not connect to the contract management service.')
  } finally {
    isFetching.value = false
  }
}

onMounted(() => {
  fetchAuditLogs()
})

// Debounce search input to avoid spamming the backend API
let debounceTimeout: any = null
watch(searchQuery, () => {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchAuditLogs()
  }, 300)
})

// Trigger reload on filter updates
watch([actionFilter, dateFilter], () => {
  if (currentPage.value === 1) {
    fetchAuditLogs()
  } else {
    currentPage.value = 1
  }
})

// Trigger reload on page changes
watch(currentPage, () => {
  fetchAuditLogs()
})

const showExportConfirm = ref(false)

function exportXLSX() {
  showExportConfirm.value = true
}

async function executeExport() {
  showExportConfirm.value = false
  const { state } = useAuth()
  if (!state.user) return

  try {
    const params = new URLSearchParams()
    params.append('page', '1')
    params.append('per_page', '100') // Fetch a larger slice to create a complete report

    if (actionFilter.value !== 'All') {
      params.append('action', actionFilter.value)
    }
    if (dateFilter.value) {
      params.append('date', dateFilter.value)
    }
    if (searchQuery.value.trim()) {
      params.append('search', searchQuery.value.trim())
    }

    const res = await fetch(`${contractApiUrl}/audit-logs?${params.toString()}`, {
      headers: {
        'Authorization': `Bearer ${state.token || ''}`,
        'X-Session-ID': localStorage.getItem('session_id') || '',
        'Accept': 'application/json'
      }
    })

    const data = await res.json()
    if (res.ok && data.data) {
      const rows = data.data.map((l: any) => ({
        'Log ID': l.id,
        'User': l.user_name,
        'Email': l.user_email || 'N/A',
        'Role': l.role || 'Finance',
        'Action': formatAction(l.action),
        'Description': l.description,
        'Timestamp': new Date(l.performed_at).toLocaleString()
      }))
      await exportToExcel('audit-log.xlsx', 'Audit Log', rows)
      showSuccess('Export complete', `${data.data.length} logs exported to audit-log.xlsx`)
    } else {
      showError('Export failed', 'Could not retrieve data for export.')
    }
  } catch (err) {
    console.error('Failed to export audit logs:', err)
    showError('Export Error', 'An error occurred during export.')
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-xl font-semibold text-black">Audit Log</h1>
        <p class="text-sm text-black/40 mt-0.5">
          Monitor all system activity and user actions across SBSI. Records are automatically retained for 90 days in accordance with data retention policy.
        </p>
      </div>
      <Button @click="exportXLSX" variant="outline"
        class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
        <Upload class="w-4 h-4" /> Export XLSX
      </Button>
    </div>

    <AuditLogFilters
      v-model:action-filter="actionFilter"
      v-model:date-filter="dateFilter"
      v-model:search-query="searchQuery"
    />

    <AuditLogTable
      :paginated="logs"
      :filtered="logs"
      :current-page="currentPage"
      :page-size="PAGE_SIZE"
      :total-pages="totalPages"
      :is-loading="isFetching"
      :total-logs="totalLogs"
      @update:current-page="currentPage = $event"
    />

    <ConfirmationDialog
      v-model:open="showExportConfirm"
      title="Export Audit Logs"
      description="Are you sure you want to export the audit logs to an Excel (.xlsx) file?"
      confirm-label="Export"
      variant="default"
      @confirm="executeExport"
    />

  </div>
</template>

