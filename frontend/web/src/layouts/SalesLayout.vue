<script setup lang="ts">
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubItem,
  SidebarMenuSubButton,
  SidebarProvider,
  SidebarTrigger,
  SidebarHeader,
  SidebarFooter,
} from "@/components/ui/sidebar";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  LayoutDashboard,
  FileText,
  Bell,
  LogOut,
  User,
  ChevronDown,
  Home,
} from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import { ref, watch, computed, onMounted, onUnmounted } from "vue";
import { useAuth } from "@/composables/useAuth";
import { useNotifications } from "@/composables/useNotifications";
import type { Notification } from "@/types/notification";
import AppBreadcrumbs from "@/components/shared/AppBreadcrumbs.vue";

const route  = useRoute();
const router = useRouter();
const { logout, hasPermission, refreshPermissions, user, role } = useAuth();

// Refresh permissions every time the layout mounts so sidebar items
// always reflect the latest permissions saved by an admin.
onMounted(async () => {
  await refreshPermissions()
  // Gate: check if this role has been granted CMS system access.
  if (role.value && !['Admin', 'Super Admin', 'CEO'].includes(role.value)) {
    const stored = localStorage.getItem(`cms_system_access_${role.value}`)
    const allowedSystems: string[] = stored ? JSON.parse(stored) : ['cms']
    if (!allowedSystems.includes('cms')) {
      router.replace('/')
    }
  }
})

const { notifications, unreadCount, fetchNotifications, markAllRead, markRead } = useNotifications();
const recentNotifs = computed(() => notifications.value.filter(n => !n.isArchived).slice(0, 10));
const isNotifOpen = ref(false);

function openNotification(n: Notification) {
  if (!n.isRead) markRead(n.id);
  isNotifOpen.value = false;
  if (n.notifType?.startsWith('execution_confirmation_') && n.contractId) {
    router.push({ name: 'sales-contracts-detail', params: { id: n.contractId }, query: { execution: '1' } });
    return;
  }
  router.push('/sales/notifications');
}

let pollTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => { fetchNotifications(); pollTimer = setInterval(fetchNotifications, 5000); });
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });

const contractPaths = ["/sales/contracts", "/sales/contract-requests", "/sales/contract-requests-review", "/sales/contract-amendments"];
const contractsOpen = ref(contractPaths.includes(route.path));

watch(() => route.path, path => {
  if (contractPaths.includes(path)) contractsOpen.value = true;
});

// Gate contract sub-items behind their respective permissions
const contractSubItems = computed(() => {
  const items = []
  if (hasPermission('cms.contracts.view')) {
    items.push({ title: "All Contracts",     url: "/sales/contracts",         icon: FileText  })
  }
  items.push({ title: "My Requests",       url: "/sales/contract-requests", icon: FileText  })
  if (hasPermission('cms.contracts.approve')) {
    items.push({ title: "Contract Requests", url: "/sales/contract-requests-review", icon: FileText })
  }
  items.push({ title: "Amendments", url: "/sales/contract-amendments", icon: FileText  })
  return items
})

// Dynamic user info derived from auth state
const displayUser = computed(() => {
  const u = user.value as any
  if (!u) return { name: 'Sales', role: 'Sales', initials: 'S', email: '' }
  const first = u.first_name ?? u.profile?.first_name ?? ''
  const last  = u.last_name  ?? u.profile?.last_name  ?? ''
  const name  = [first, last].filter(Boolean).join(' ') || u.email || 'Sales'
  const role  = u.role ?? u.profile?.role?.name ?? 'Sales'
  const initials = [first[0], last[0]].filter(Boolean).join('').toUpperCase() || 'S'
  return { name, role, initials, email: u.email ?? '' }
})



function lnk(active: boolean) {
  return active
    ? "bg-brand-dark text-white"
    : "text-white/45 hover:text-white/80 hover:bg-white/10";
}
function ico(active: boolean) { return active ? "text-white" : "text-white/45"; }
function txt(active: boolean) { return active ? "text-white" : "text-white/45"; }


</script>

<template>
  <SidebarProvider class="flex h-screen print:block print:h-auto">
    <!-- ── Sidebar ── -->
    <Sidebar
      collapsible="icon"
      class="border-r border-brand-dark bg-brand-navy text-white shadow-sm print:hidden"
      :style="{
        '--sidebar': 'var(--brand-navy)',
        '--sidebar-foreground': '#FFFFFF',
        '--sidebar-accent': 'var(--brand-dark)',
        '--sidebar-accent-foreground': '#FFFFFF',
        '--sidebar-border': 'var(--brand-dark)',
      }"
    >
      <!-- Logo -->
      <SidebarHeader class="h-16 justify-center border-b border-white/10 px-4">
        <div class="flex items-center justify-center gap-2.5 w-full transition-all duration-200">
          <img
            src="/sbsi-logo1.png"
            alt="SBSI Logo"
            class="h-10 w-10 shrink-0 object-contain rounded-none"
          />
          <div class="min-w-0 flex flex-col font-poppins text-white select-none group-data-[collapsible=icon]:hidden">
            <span class="text-xs font-bold leading-tight text-white tracking-wide uppercase">CONTRACT</span>
            <span class="text-[10px] font-normal leading-tight text-white/80 tracking-tight">MANAGEMENT SYSTEM</span>
          </div>
        </div>
      </SidebarHeader>

      <!-- Navigation -->
      <SidebarContent class="px-3 py-3">

        <!-- Main group -->
        <SidebarGroup class="mb-1 p-0">
          <SidebarGroupLabel
            class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-white/25 group-data-[collapsible=icon]:hidden"
          >Main</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu class="gap-0.5">
              <SidebarMenuItem>
                <SidebarMenuButton as-child :is-active="route.path === '/sales/dashboard'" class="h-auto p-0 rounded-lg">
                  <router-link
                    to="/sales/dashboard"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg transition-all duration-200 group-data-[collapsible=icon]:justify-center"
                    :class="lnk(route.path === '/sales/dashboard')"
                  >
                    <LayoutDashboard
                      class="w-4 h-4 shrink-0 group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5"
                      :class="ico(route.path === '/sales/dashboard')"
                    />
                    <span
                      class="text-sm font-medium flex-1 text-left group-data-[collapsible=icon]:hidden"
                      :class="txt(route.path === '/sales/dashboard')"
                    >Dashboard</span>
                  </router-link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <!-- Contracts group -->
        <SidebarGroup v-if="hasPermission('cms.contracts.view') || hasPermission('cms.contracts.create')" class="mb-1 p-0">
          <SidebarGroupLabel
            class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-white/25 group-data-[collapsible=icon]:hidden font-secondary"
          >Contracts</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu class="gap-0.5">
              <SidebarMenuItem>
                <SidebarMenuButton as-child :is-active="contractPaths.includes(route.path)" class="h-auto p-0 rounded-lg">
                  <button
                    @click="contractsOpen = true; router.push('/sales/contracts')"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg transition-all duration-200 group-data-[collapsible=icon]:justify-center"
                    :class="lnk(contractPaths.includes(route.path))"
                  >
                    <FileText
                      class="w-4 h-4 shrink-0 group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5"
                      :class="ico(contractPaths.includes(route.path))"
                    />
                    <span
                      class="text-sm font-medium flex-1 text-left group-data-[collapsible=icon]:hidden"
                      :class="txt(contractPaths.includes(route.path))"
                    >Contracts</span>
                    <ChevronDown
                      @click.stop="contractsOpen = !contractsOpen"
                      class="w-3.5 h-3.5 shrink-0 transition-transform duration-200 group-data-[collapsible=icon]:hidden"
                      :class="[contractsOpen ? 'rotate-180' : 'rotate-0', ico(contractPaths.includes(route.path))]"
                    />
                  </button>
                </SidebarMenuButton>

                <SidebarMenuSub v-show="contractsOpen">
                  <SidebarMenuSubItem v-for="sub in contractSubItems" :key="sub.title">
                    <SidebarMenuSubButton as-child :is-active="route.path === sub.url" class="h-8">
                      <router-link
                        :to="sub.url"
                        class="flex items-center gap-2 w-full px-2 py-1.5 rounded-md text-sm transition-all duration-150"
                        :class="route.path === sub.url
                          ? 'text-white font-medium'
                          : 'text-white/40 hover:text-white/75'"
                      >
                        <component
                          :is="sub.icon"
                          class="w-3.5 h-3.5 shrink-0"
                          :class="route.path === sub.url ? 'text-white' : 'text-white/40'"
                        />
                        <span>{{ sub.title }}</span>
                      </router-link>
                    </SidebarMenuSubButton>
                  </SidebarMenuSubItem>
                </SidebarMenuSub>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <!-- Vendor Management (Partners) group — shown only when permitted -->
        <SidebarGroup v-if="hasPermission('cms.partners.view')" class="mb-1 p-0">
          <SidebarGroupLabel
            class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-white/25 group-data-[collapsible=icon]:hidden font-secondary"
          >Vendor Management</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu class="gap-0.5">
              <SidebarMenuItem v-if="hasPermission('cms.partners.view')">
                <SidebarMenuButton as-child :is-active="route.path === '/sales/partners'" class="h-auto p-0 rounded-lg">
                  <router-link
                    to="/sales/partners"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg transition-all duration-200 group-data-[collapsible=icon]:justify-center"
                    :class="lnk(route.path === '/sales/partners')"
                  >
                    <svg
                      class="w-4 h-4 shrink-0 group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5"
                      :class="ico(route.path === '/sales/partners')"
                      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                    >
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span
                      class="text-sm font-medium flex-1 text-left group-data-[collapsible=icon]:hidden"
                      :class="txt(route.path === '/sales/partners')"
                    >Business Partners</span>
                  </router-link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>


        <SidebarGroup class="mb-1 p-0">
          <SidebarGroupLabel
            class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-white/25 group-data-[collapsible=icon]:hidden font-secondary"
          >General</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu class="gap-0.5">
              <SidebarMenuItem>
                <SidebarMenuButton as-child :is-active="route.path === '/sales/notifications'" class="h-auto p-0 rounded-lg">
                  <router-link
                    to="/sales/notifications"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg transition-all duration-200 group-data-[collapsible=icon]:justify-center"
                    :class="lnk(route.path === '/sales/notifications')"
                  >
                    <Bell
                      class="w-4 h-4 shrink-0 group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5"
                      :class="ico(route.path === '/sales/notifications')"
                    />
                    <span
                      class="text-sm font-medium flex-1 text-left group-data-[collapsible=icon]:hidden"
                      :class="txt(route.path === '/sales/notifications')"
                    >Notifications</span>
                  </router-link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

      </SidebarContent>

      <!-- Footer: Logout -->
      <SidebarFooter class="border-t border-white/10 p-3 space-y-1">
        <div
          @click="logout"
          class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 cursor-pointer transition-colors group-data-[collapsible=icon]:justify-center"
        >
          <LogOut class="w-4 h-4 text-white group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5" />
          <span class="text-sm font-medium text-white group-data-[collapsible=icon]:hidden">Logout</span>
        </div>
      </SidebarFooter>
    </Sidebar>

    <!-- ── Main Content ── -->
    <main class="flex-1 flex flex-col min-w-0 bg-[#F5F6FA] min-h-screen print:min-h-0 print:bg-white print:p-0 print:overflow-visible">
      <!-- Top Header -->
      <header class="h-16 flex items-center justify-between px-6 bg-white border-b border-black/10 shrink-0 print:hidden">
        <div class="flex items-center gap-3.5 flex-1 min-w-0 mr-4">
          <SidebarTrigger class="text-black/60 shrink-0" />
          <div class="h-4 w-px bg-black/10 shrink-0 hidden sm:block"></div>
          <AppBreadcrumbs />
        </div>

        <div class="flex items-center gap-3">
          <Popover v-model:open="isNotifOpen">
            <PopoverTrigger as-child>
              <button class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-black/5 transition-colors text-black/60 relative">
                <Bell class="w-4.5 h-4.5" />
                <span v-if="unreadCount > 0"
                  class="absolute top-1 right-1 min-w-[16px] h-4 rounded-full bg-brand-blue border-2 border-white flex items-center justify-center text-[9px] font-bold text-white px-0.5">
                  {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
              </button>
            </PopoverTrigger>
            <PopoverContent class="w-80 p-0" align="end">
              <div class="flex items-center justify-between px-4 py-3 border-b border-black/6">
                <p class="text-sm font-semibold text-black">Notifications</p>
                <button v-if="unreadCount > 0" @click="markAllRead"
                  class="text-xs text-brand-blue hover:text-brand-navy transition-colors font-medium">
                  Mark all read
                </button>
              </div>
              <div v-if="recentNotifs.length === 0" class="text-center py-8">
                <Bell class="w-7 h-7 mx-auto text-black/25 mb-2" />
                <p class="text-sm text-black/40">You're all caught up!</p>
              </div>
              <ul v-else class="divide-y divide-black/4 max-h-72 overflow-y-auto">
                <li v-for="n in recentNotifs" :key="n.id"
                  class="flex items-start gap-3 px-4 py-3 hover:bg-black/2 transition-colors cursor-pointer min-w-0"
                  :class="{ 'bg-brand-blue/[0.06]': !n.isRead }"
                  @click="openNotification(n)">
                  <span class="mt-0.5 w-1.5 h-1.5 rounded-full shrink-0"
                    :class="n.isRead ? 'bg-transparent' : 'bg-brand-blue'" />
                  <p class="text-xs leading-relaxed line-clamp-2 font-secondary min-w-0 break-words"
                    :class="n.isRead ? 'text-black/45' : 'text-black font-medium'">{{ n.message }}</p>
                </li>
              </ul>
              <div class="px-4 py-2.5 border-t border-black/6">
                <router-link to="/sales/notifications" @click="isNotifOpen = false"
                  class="block w-full text-xs text-center text-brand-blue hover:text-brand-navy font-medium transition-colors font-secondary">
                  View all notifications
                </router-link>
              </div>
            </PopoverContent>
          </Popover>
          <div class="h-6 w-px bg-black/10"></div>
          <Popover>
            <PopoverTrigger as-child>
              <div class="flex items-center gap-2.5 cursor-pointer hover:bg-black/5 rounded-lg px-2 py-1 transition-colors">
                <Avatar class="w-9 h-9 ring-2 ring-brand-navy/20">
                  <AvatarFallback class="bg-brand-navy text-white text-sm font-bold">
                    {{ displayUser.initials }}
                  </AvatarFallback>
                </Avatar>
                <div class="hidden sm:block text-left">
                  <p class="text-sm font-semibold text-black leading-tight">{{ displayUser.name }}</p>
                  <p class="text-xs text-black/50 font-secondary">{{ displayUser.role }}</p>
                </div>
              </div>
            </PopoverTrigger>
            <PopoverContent class="w-56 p-2" align="end">
              <div class="space-y-1">
                <div class="px-2 py-1.5 border-b border-black/5">
                  <p class="text-sm font-semibold text-black">{{ displayUser.name }}</p>
                  <p class="text-xs text-black/50 font-secondary">{{ displayUser.email }}</p>
                </div>
                <button @click="router.push('/sales/profile')"
                  class="w-full flex items-center gap-3 px-2 py-2 text-sm text-black hover:bg-black/5 rounded-lg transition-colors text-left">
                  <User class="w-4 h-4 text-black/60" />
                  <span>My Profile</span>
                </button>
                <a href="/home"
                  class="w-full flex items-center gap-3 px-2 py-2 text-sm text-black hover:bg-black/5 rounded-lg transition-colors text-left no-underline">
                  <Home class="w-4 h-4 text-black/60" />
                  <span>Back to Operations Hub</span>
                </a>
                <div class="border-t border-black/5 my-1"></div>
                <button @click="logout" class="w-full flex items-center gap-3 px-2 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors text-left">
                  <LogOut class="w-4 h-4" />
                  <span>Logout</span>
                </button>
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </header>

      <!-- Page Content -->
      <div class="flex-1 overflow-auto">
        <router-view />
      </div>
    </main>
  </SidebarProvider>
</template>
