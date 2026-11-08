<script setup lang="ts">
import { reactive, onMounted } from 'vue'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useEmailPreferences } from '@/composables/useEmailPreferences'
import ProfileHero       from './ProfileHero.vue'
import PersonalInfoCard  from './PersonalInfoCard.vue'
import SecurityCard      from './SecurityCard.vue'
import PreferencesCard   from './PreferencesCard.vue'

const { error: showError, success: showSuccess } = useToast()
const { state } = useAuth()
const { preferences: apiPrefs, fetchPreferences, savePreferences } = useEmailPreferences()

const profile = reactive({
  firstName:  '',
  lastName:   '',
  middleName: '',
  email:      '',
  phone:      '',
  role:       'Admin',
  department: 'IT Department',
  dateJoined: 'January 5, 2025',
})

const preferences = reactive({
  emailNotifications: true,
  systemAlerts:       true,
  contractExpiry:     true,
  loginAlerts:        true,
})

function loadProfile() {
  const u = state.user as any
  if (u) {
    profile.firstName = u.profile?.first_name || u.first_name || ''
    profile.lastName = u.profile?.last_name || u.last_name || ''
    profile.middleName = u.profile?.middle_name || ''
    profile.email = u.email || ''
    profile.phone = u.profile?.phone || ''
    profile.role = u.profile?.role?.name || u.role || 'Admin'
    profile.department = u.profile?.department?.name || u.department || 'IT Department'
    profile.dateJoined = u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }) : 'January 5, 2025'
  }
}

onMounted(async () => {
  loadProfile()
  await fetchPreferences()
  preferences.emailNotifications = apiPrefs.value.emailNotificationsEnabled
  preferences.contractExpiry = apiPrefs.value.contractExpiryAlerts
  preferences.systemAlerts = apiPrefs.value.systemAlertsEnabled ?? true
  preferences.loginAlerts = apiPrefs.value.loginAlertsEnabled ?? true
})

async function handleProfileSave(data: Partial<typeof profile>) {
  if (!state.user) return

  try {
    const res = await fetch(`${import.meta.env.VITE_AUTH_API_URL}/me/profile`, {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${state.token || ''}`,
        'X-Session-ID': localStorage.getItem('session_id') || '',
      },
      body: JSON.stringify({
        first_name: data.firstName,
        last_name: data.lastName,
        middle_name: data.middleName,
        phone: data.phone,
        email: data.email,
      })
    })

    const result = await res.json()
    if (res.ok && result.user) {
      // Synchronize in-memory auth state
      const currentUser = state.user as any
      state.user = {
        ...currentUser,
        email: result.user.email,
        first_name: result.user.first_name,
        last_name: result.user.last_name,
        middle_name: result.user.middle_name,
        phone: result.user.phone,
        role: result.user.role,
        department: result.user.department,
        profile: currentUser?.profile ? {
          ...currentUser.profile,
          first_name: result.user.first_name,
          last_name: result.user.last_name,
          middle_name: result.user.middle_name,
          phone: result.user.phone,
        } : undefined
      } as any

      localStorage.setItem('user', JSON.stringify(state.user))
      loadProfile()

      showSuccess('Profile updated', 'Your personal details have been saved.')
    } else {
      showError('Update failed', result.message || 'Could not save profile changes.')
    }
  } catch (err) {
    console.error(err)
    showError('Network Error', 'Could not connect to the authentication service.')
  }
}

async function handlePasswordChange(data: { current: string, next: string }) {
  if (!state.user) return

  try {
    const res = await fetch(`${import.meta.env.VITE_AUTH_API_URL}/me/password`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${state.token || ''}`,
        'X-Session-ID': localStorage.getItem('session_id') || '',
      },
      body: JSON.stringify({
        current_password: data.current,
        new_password: data.next,
      })
    })

    const result = await res.json()
    if (res.ok) {
      showSuccess('Password updated', 'Your security credentials have been updated successfully.')
    } else {
      const errMsg = result.errors 
        ? Object.values(result.errors).flat().join(' ') 
        : (result.message || 'Could not update your password.')
      showError('Password update failed', errMsg)
    }
  } catch (err) {
    console.error(err)
    showError('Network Error', 'Could not connect to the authentication service.')
  }
}

async function handlePreferencesSave(data: typeof preferences) {
  const success = await savePreferences({
    emailNotificationsEnabled: data.emailNotifications,
    contractExpiryAlerts: data.contractExpiry,
    systemAlertsEnabled: data.systemAlerts,
    loginAlertsEnabled: data.loginAlerts,
  })
  if (success) {
    Object.assign(preferences, data)
    showSuccess('Preferences saved', 'Your notification settings have been updated.')
  } else {
    showError('Save failed', 'Could not save notification preferences.')
  }
}
</script>

<template>
  <div class="p-8 space-y-6">

    <div>
      <h1 class="text-xl font-semibold text-black">My Profile</h1>
      <p class="text-sm text-black/40 mt-0.5">Manage your account details and preferences.</p>
    </div>

    <ProfileHero :profile="profile" />

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <PersonalInfoCard :profile="profile" @save="handleProfileSave" />
      <PreferencesCard  :preferences="preferences" :role="profile.role" @save="handlePreferencesSave" />
    </div>

    <SecurityCard @save="handlePasswordChange" />

  </div>
</template>
