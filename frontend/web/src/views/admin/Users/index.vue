<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { Plus, Upload, CheckCircle2, XCircle } from 'lucide-vue-next'
import { exportToExcel } from '@/utils/excelExport'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useLoader } from '@/composables/useLoader'
import { useRoleSystems } from '@/composables/useRoleSystems'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import UsersTable       from './UsersTable.vue'
import ViewProfileDialog from './ViewProfileDialog.vue'
import AddUserDialog    from './AddUserDialog.vue'
import EditUserDialog   from './EditUserDialog.vue'
import DeleteUserDialog from './DeleteUserDialog.vue'
import type { User, Role, Status } from '@/types/user'
import { buildRoleTabs, pluralRoleName } from '@/types/user'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const { success, error } = useToast()
const { withLoading }    = useLoader()

const showResultDialog = ref(false)
const resultStatus = ref<'success' | 'error'>('success')
const resultTitle = ref('')
const resultMessage = ref('')

// true while the initial table fetch is in flight
const isFetching = ref(false)

const users = ref<User[]>([])

// Dynamic role tabs: one per role holding CMS access in auth (IT Admin
// governs via Role Builder). Granting a role (e.g. CEO) any cms
// permission adds its tab here with no CMS change.
const { cmsRoleNames, fetchRoleSystems } = useRoleSystems()
const roleTabs = computed(() => buildRoleTabs(cmsRoleNames.value))

const rolesOptions = ref<{ id: number; name: string }[]>([])
const departmentsOptions = ref<{ id: number; name: string }[]>([])

async function fetchOptions() {
  const { state } = useAuth()
  try {
    const authApiUrl = import.meta.env.VITE_AUTH_API_URL || '/api'
    const headers = {
      'Authorization': `Bearer ${state.token}`,
      'X-Session-ID': localStorage.getItem('session_id') || '',
      'Accept': 'application/json'
    }
    const [resRoles, resDepts] = await Promise.all([
      fetch(`${authApiUrl}/admin/role-options`, { headers }),
      fetch(`${authApiUrl}/admin/department-options`, { headers })
    ])
    if (resRoles.ok) {
      rolesOptions.value = await resRoles.json()
    }
    if (resDepts.ok) {
      departmentsOptions.value = await resDepts.json()
    }
  } catch (err) {
    console.error('Failed to fetch user creation options:', err)
  }
}

const rolesList = computed(() => {
  if (rolesOptions.value.length > 0) return rolesOptions.value
  return [
    { id: 1, name: 'Super Admin' },
    { id: 2, name: 'IT Admin' },
    { id: 3, name: 'Admin' },
    { id: 4, name: 'Manager' },
    { id: 5, name: 'Sales' },
    { id: 6, name: 'Finance' },
    { id: 7, name: 'Employee' },
  ]
})

const departmentsList = computed(() => {
  if (departmentsOptions.value.length > 0) return departmentsOptions.value
  return [
    { id: 1, name: 'IT' },
    { id: 2, name: 'Operations' },
    { id: 3, name: 'Sales & Marketing' },
  ]
})

async function fetchUsers() {
  const { state } = useAuth()
  isFetching.value = true
  try {
    const authApiUrl = import.meta.env.VITE_AUTH_API_URL || '/api'
    const res = await fetch(`${authApiUrl}/admin/users?per_page=100`, {
      headers: {
        'Authorization': `Bearer ${state.token}`,
        'X-Session-ID': localStorage.getItem('session_id') || '',
        'Accept': 'application/json'
      }
    })
    const data = await res.json()
    if (res.ok && data.data) {
      users.value = data.data.map((u: any) => ({
        id: u.id.toString(),
        name: [u.profile?.first_name, u.profile?.middle_name, u.profile?.last_name].filter(Boolean).join(' '),
        email: u.email,
        role: u.profile?.role?.name || 'Unknown',
        department: u.profile?.department?.name || 'Unknown',
        status: (u.is_active ? 'Active' : 'Inactive') as Status,
        dateAdded: new Date(u.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
      }))
    }
  } catch (err) {
    console.error('Failed to fetch users:', err)
    error('Fetch failed', 'Could not load users from the server.')
  } finally {
    isFetching.value = false
  }
}

onMounted(() => {
  fetchUsers()
  fetchOptions()
  fetchRoleSystems()
})

// Stat cards stay in sync with the dynamic tabs: All users plus one per
// CMS-accessible role. Known roles keep their historic delta pills;
// newly granted roles show a neutral "new" pill.
const KNOWN_CARD_DELTAS: Record<string, { change: string; positive: boolean }> = {
  Admin:    { change: '+2.1%', positive: true  },
  Manager:  { change: '-0.3%', positive: false },
  Employee: { change: '+5.2%', positive: true  },
}

// Canonical role display order to match the Role Cards hierarchy.
const ROLE_SORT_ORDER: Record<string, number> = {
  Admin: 0, Manager: 1, Sales: 2, Employee: 3,
}

const statCards = computed(() => [
  { label: 'All users', value: users.value.length, change: '+4.0%', positive: true },
  ...[...cmsRoleNames.value]
    .sort((a, b) => (ROLE_SORT_ORDER[a] ?? 99) - (ROLE_SORT_ORDER[b] ?? 99))
    .map(name => ({
      label: pluralRoleName(name),
      value: users.value.filter(u => u.role === name).length,
      ...(KNOWN_CARD_DELTAS[name] ?? { change: 'new', positive: true }),
    })),
])

type TabValue = string
const activeTab   = ref<TabValue>('all')
const searchQuery = ref('')

const filteredUsers = computed(() =>
  users.value.filter(u => {
    const byTab    = activeTab.value === 'all' || u.role === activeTab.value
    const bySearch = !searchQuery.value ||
      u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      u.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    return byTab && bySearch
  })
)

const currentPage  = ref(1)
const itemsPerPage = 15
watch([activeTab, searchQuery], () => { currentPage.value = 1 })

const paginatedUsers = computed(() =>
  filteredUsers.value.slice((currentPage.value - 1) * itemsPerPage, currentPage.value * itemsPerPage)
)

const selectedIds = ref<string[]>([])
const allPageSelected = computed(() =>
  paginatedUsers.value.length > 0 &&
  paginatedUsers.value.every(u => selectedIds.value.includes(u.id))
)
function toggleSelectAll() {
  const ids = paginatedUsers.value.map(u => u.id)
  if (allPageSelected.value) selectedIds.value = selectedIds.value.filter(id => !ids.includes(id))
  else ids.forEach(id => { if (!selectedIds.value.includes(id)) selectedIds.value.push(id) })
}
function toggleRow(id: string) {
  const i = selectedIds.value.indexOf(id)
  i >= 0 ? selectedIds.value.splice(i, 1) : selectedIds.value.push(id)
}

const showViewProfile = ref(false)
const viewTarget      = ref<User | null>(null)
function openViewProfile(user: User) { viewTarget.value = user; showViewProfile.value = true }

const showEditUser = ref(false)
const editTarget   = ref<User | null>(null)
function openEditUser(user: User) { editTarget.value = user; showEditUser.value = true }

const showDeleteConfirm = ref(false)
const deleteTarget      = ref<User | null>(null)
function openDeleteConfirm(user: User) { deleteTarget.value = user; showDeleteConfirm.value = true }

const showAddUser = ref(false)

function avatarIndex(id: string) {
  return users.value.findIndex(u => u.id === id)
}

async function handleAdd(data: any) {
  const { state } = useAuth()

  await withLoading('creating', async () => {
    try {
      const contractApiUrl = import.meta.env.VITE_CONTRACT_API_URL || '/api/contract'
      const response = await fetch(`${contractApiUrl}/admin/users`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${state.token}`,
          'X-Session-ID': localStorage.getItem('session_id') || '',
          'Accept': 'application/json'
        },
        body: JSON.stringify(data)
      })

      const result = await response.json()

      if (response.ok) {
        const newUser: User = {
          id: result.user.id || `USR-${Math.random().toString(36).substr(2, 3).toUpperCase()}`,
          name: `${data.first_name} ${data.last_name}`,
          email: data.email,
          role: data.role_name as Role,
          department: data.department_name,
          status: 'Inactive' as Status,
          dateAdded: new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }),
        }
        users.value.push(newUser)
        resultStatus.value = 'success'
        resultTitle.value = 'User Created Successfully'
        resultMessage.value = `${newUser.name}'s account has been created and is currently inactive. A temporary password has been sent to ${newUser.email}. The account will become active once they log in and change their password.`
        showResultDialog.value = true
      } else if (response.status === 422 && result.errors) {
        const firstError = Object.values(result.errors as Record<string, string[]>)[0]?.[0]
        resultStatus.value = 'error'
        resultTitle.value = 'Validation Error'
        resultMessage.value = firstError || result.message || 'Please check the form fields.'
        showResultDialog.value = true
      } else {
        resultStatus.value = 'error'
        resultTitle.value = 'Creation Failed'
        resultMessage.value = result.message || 'Failed to create user.'
        showResultDialog.value = true
      }
    } catch (err) {
      console.error('Network error creating user:', err)
      resultStatus.value = 'error'
      resultTitle.value = 'Network Error'
      resultMessage.value = 'Could not connect to the server. Please check your internet connection.'
      showResultDialog.value = true
    }
  })
}

async function handleEdit(data: { id: string; name: string; email: string; role: Role; status: Status; department: string }) {
  const { state } = useAuth()
  const originalUser = users.value.find(u => u.id === data.id)
  if (!originalUser) return

  await withLoading('updating', async () => {
    try {
      const authApiUrl = import.meta.env.VITE_AUTH_API_URL || '/api'
      const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${state.token}`,
        'X-Session-ID': localStorage.getItem('session_id') || '',
        'Accept': 'application/json'
      }

      // 1. Update role if changed
      if (data.role !== originalUser.role) {
        const selectedRoleObj = rolesList.value.find(r => r.name === data.role)
        if (selectedRoleObj) {
          const res = await fetch(`${authApiUrl}/admin/users/${data.id}/role`, {
            method: 'PATCH',
            headers,
            body: JSON.stringify({ role_id: selectedRoleObj.id })
          })
          if (!res.ok) {
            const errBody = await res.json().catch(() => ({}))
            throw new Error(errBody.message || 'Failed to update role.')
          }
        }
      }

      // 2. Update department if changed
      if (data.department !== originalUser.department) {
        const selectedDeptObj = departmentsList.value.find(d => d.name === data.department)
        if (selectedDeptObj) {
          const res = await fetch(`${authApiUrl}/admin/users/${data.id}/department`, {
            method: 'PATCH',
            headers,
            body: JSON.stringify({ department_id: selectedDeptObj.id })
          })
          if (!res.ok) {
            const errBody = await res.json().catch(() => ({}))
            throw new Error(errBody.message || 'Failed to update department.')
          }
        }
      }

      // Update local state
      const idx = users.value.findIndex(u => u.id === data.id)
      if (idx >= 0) {
        users.value[idx] = {
          ...users.value[idx],
          name: data.name,
          email: data.email,
          role: data.role,
          department: data.department,
          status: data.status
        }
      }
      success('User updated', `${data.name}'s details have been saved.`)
    } catch (err: any) {
      console.error('Error editing user:', err)
      error('Update failed', err.message || 'Could not update user details.')
    }
  })
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  const { id, name } = deleteTarget.value
  showDeleteConfirm.value = false
  deleteTarget.value      = null
  await withLoading('deleting', async () => {
    await new Promise(r => setTimeout(r, 400)) // brief visual
    users.value       = users.value.filter(u => u.id !== id)
    selectedIds.value = selectedIds.value.filter(s => s !== id)
    success('User deleted', `${name} has been removed.`)
  })
}

const showExportConfirm = ref(false)

function exportXLSX() {
  showExportConfirm.value = true
}

async function executeExport() {
  showExportConfirm.value = false
  function splitName(full: string) {
    const parts = full.trim().split(/\s+/)
    return { firstName: parts[0] ?? '', lastName: parts.length > 1 ? parts[parts.length - 1] : '', middleName: parts.length > 2 ? parts.slice(1, -1).join(' ') : '' }
  }
  const rows = users.value.map(u => {
    const { firstName, middleName, lastName } = splitName(u.name)
    return { 'First Name': firstName, 'Last Name': lastName, 'Middle Name': middleName, 'Email': u.email, 'Role': u.role, 'Department': 'Sales Department', 'Status': u.status }
  })
  await exportToExcel('sbsi-users.xlsx', 'Users', rows)
  success('Export complete', `${users.value.length} users exported to sbsi-users.xlsx`)
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-xl font-semibold text-black">User management</h1>
        <p class="text-sm text-black/40 mt-0.5">Manage all users in the system.</p>
      </div>
      <div class="flex items-center gap-2">
        <Button @click="exportXLSX" variant="outline" class="h-9 gap-2 text-sm font-medium border-black/15 text-black/65 hover:text-black">
          <Upload class="w-4 h-4" /> Export XLSX
        </Button>
        <Button @click="showAddUser = true" class="h-9 w-9 p-0 bg-brand-navy hover:bg-brand-dark text-white rounded-lg shadow-sm">
          <Plus class="w-5 h-5" />
        </Button>
      </div>
    </div>

    <!-- Stat cards: skeleton while loading, real values after -->
    <div class="grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-5 gap-4">
      <template v-if="isFetching">
        <div v-for="i in 4" :key="i" class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm animate-pulse">
          <div class="h-2.5 w-20 bg-black/8 rounded mb-4"></div>
          <div class="h-8 w-12 bg-black/8 rounded"></div>
        </div>
      </template>
      <template v-else>
        <div v-for="card in statCards" :key="card.label" class="bg-white rounded-lg border border-black/8 px-6 py-5 shadow-sm">
          <p class="text-xs font-medium text-black/40 mb-3 uppercase tracking-wide">{{ card.label }}</p>
          <div class="flex items-end justify-between gap-2">
            <span class="text-3xl font-semibold text-black tabular-nums">{{ card.value }}</span>
            <span class="text-xs font-medium px-2 py-0.5 rounded-md mb-0.5 shrink-0"
              :class="card.positive ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'">
              {{ card.change }}
            </span>
          </div>
        </div>
      </template>
    </div>

    <UsersTable
      :paginated-users="paginatedUsers"
      :filtered-users="filteredUsers"
      :selected-ids="selectedIds"
      :all-page-selected="allPageSelected"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      :active-tab="activeTab"
      :tabs="roleTabs"
      :search-query="searchQuery"
      @open-view-profile="openViewProfile"
      @open-edit-user="openEditUser"
      @open-delete-confirm="openDeleteConfirm"
      @toggle-row="toggleRow"
      @toggle-select-all="toggleSelectAll"
      @update:current-page="currentPage = $event"
      @update:active-tab="activeTab = $event"
      @update:search-query="searchQuery = $event"
    />

  </div>

  <ViewProfileDialog
    v-model:open="showViewProfile"
    :user="viewTarget"
    :avatar-index="viewTarget ? avatarIndex(viewTarget.id) : 0"
    @edit="u => { showViewProfile = false; openEditUser(u) }"
  />
  <AddUserDialog    v-model:open="showAddUser"   :roles="rolesList" :departments="departmentsList" @submit="handleAdd" />
  <EditUserDialog   v-model:open="showEditUser"  :user="editTarget" :roles="rolesList" :departments="departmentsList" @submit="handleEdit" />
  <DeleteUserDialog v-model:open="showDeleteConfirm" :user="deleteTarget"
    :avatar-index="deleteTarget ? avatarIndex(deleteTarget.id) : 0"
    @confirm="confirmDelete" />

  <!-- Creation Result Dialog -->
  <Dialog v-model:open="showResultDialog">
    <DialogContent class="max-w-md p-6 gap-4" @pointer-down-outside="showResultDialog = false">
      <DialogHeader class="space-y-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
            :class="resultStatus === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
            <component :is="resultStatus === 'success' ? CheckCircle2 : XCircle" class="w-5 h-5" />
          </div>
          <DialogTitle class="text-sm font-bold text-black">{{ resultTitle }}</DialogTitle>
        </div>
        <DialogDescription class="text-xs text-black/55 mt-1.5 leading-relaxed pl-1">
          {{ resultMessage }}
        </DialogDescription>
      </DialogHeader>
      <DialogFooter class="flex items-center justify-end mt-2">
        <Button @click="showResultDialog = false"
          class="h-9 px-4 text-sm bg-brand-navy hover:bg-brand-dark text-white shadow-sm font-medium">
          Close
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <ConfirmationDialog
    v-model:open="showExportConfirm"
    title="Export Users"
    description="Are you sure you want to export the user list to an Excel (.xlsx) file?"
    confirm-label="Export"
    variant="default"
    @confirm="executeExport"
  />
</template>
