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
  SidebarProvider,
  SidebarTrigger,
  SidebarHeader,
  SidebarFooter,
} from "@/components/ui/sidebar";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  LayoutDashboard,
  FileText,
  Users,
  Shield,
  Handshake,
  ClipboardList,
  Settings2,
  LogOut,
  Bell,
  User,
  Home,
} from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { useAuth } from "@/composables/useAuth";
import { useNotifications } from "@/composables/useNotifications";
import type { Notification } from "@/types/notification";
import AppBreadcrumbs from "@/components/shared/AppBreadcrumbs.vue";

const route  = useRoute();
const router = useRouter();
const { user: authUser, role, hasPermission, refreshPermissions, logout } = useAuth();

const { notifications, unreadCount, fetchNotifications, markAllRead, markRead } = useNotifications();

const recentNotifs = computed(() =>
  notifications.value.filter(n => !n.isArchived).slice(0, 10)
);

const isNotifOpen = ref(false);

function openNotification(n: Notification) {
  if (!n.isRead) markRead(n.id);
  isNotifOpen.value = false;
  if (n.notifType?.startsWith('execution_confirmation_') && n.contractId) {
    router.push({ name: 'admin-contracts-detail', params: { id: n.contractId }, query: { execution: '1' } });
    return;
  }
  router.push('/admin/notifications');
}

let pollTimer: ReturnType<typeof setInterval> | null = null;
onMounted(async () => {
  await refreshPermissions();
  fetchNotifications();
  pollTimer = setInterval(fetchNotifications, 5000);
});
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });

const contractPaths = ["/admin/contracts"];
const contractsOpen = ref(contractPaths.some(p => route.path.startsWith(p)));

watch(() => route.path, path => {
  if (contractPaths.some(p => path.startsWith(p))) contractsOpen.value = true;
});

// ── Strict Admin isolation flag ─────────────────────────────────────
const isAdmin = computed(() =>
  ['Admin', 'Super Admin'].includes(role.value || (authUser.value as any)?.role || '')
);

// ── Nav groups (Permission & Role Gated) ─────────────────────────────
const navGroups = computed(() => {
  const groups = [
    {
      label: "Main",
      items: [
        { title: "Dashboard", url: "/admin/dashboard", icon: LayoutDashboard, show: true },
        { title: "Contracts", url: "/admin/contracts", icon: FileText, show: hasPermission('cms.contracts.view') },
        { title: "Contract Requests", url: "/admin/contract-requests", icon: FileText, show: hasPermission('cms.contracts.approve') },
      ],
    },
    {
      label: "Management",
      items: [
        { title: "User Management",    url: "/admin/users",    icon: Users,     show: isAdmin.value },
        { title: "Roles & Permission", url: "/admin/roles",    icon: Shield,    show: isAdmin.value },
        { title: "Business & Suppliers", url: "/admin/partners", icon: Handshake, show: hasPermission('cms.partners.view') },
      ],
    },
    {
      label: "System",
      items: [
        { title: "Notifications",      url: "/admin/notifications", icon: Bell,         show: true },
        { title: "Audit Log",          url: "/admin/audit-log",     icon: ClipboardList, show: isAdmin.value || hasPermission('cms.audit.view') || hasPermission('view-audit-logs') },
        { title: "System Configuration", url: "/admin/system-config", icon: Settings2,  show: isAdmin.value },
      ],
    },
  ];

  return groups
    .map(group => ({
      ...group,
      items: group.items.filter(item => item.show),
    }))
    .filter(group => group.items.length > 0);
});

const user = computed(() => {
  const u = authUser.value as any;
  const firstName = u?.first_name ?? u?.profile?.first_name ?? "Admin";
  const lastName = u?.last_name ?? u?.profile?.last_name ?? "User";
  const name = `${firstName} ${lastName}`.trim();
  const currentRole = role.value ?? u?.role ?? "Admin";
  const email = u?.email ?? "";
  const initials = `${firstName[0] ?? "A"}${lastName[0] ?? "U"}`.toUpperCase();
  return { name, role: currentRole, email, initials };
});



// ── Calendar in Header ──

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
        <SidebarGroup
          v-for="group in navGroups"
          :key="group.label"
          class="mb-1 p-0"
        >
          <SidebarGroupLabel
            class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-white/25 group-data-[collapsible=icon]:hidden font-secondary"
          >
            {{ group.label }}
          </SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu class="gap-0.5">
              <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                  as-child
                  :is-active="item.url === '/admin/dashboard' ? route.path === item.url : route.path.startsWith(item.url)"
                  class="h-auto p-0 rounded-lg"
                >
                  <component
                    :is="'router-link'"
                    :to="item.url"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg transition-all duration-200 group-data-[collapsible=icon]:justify-center"
                    :class="(item.url === '/admin/dashboard' ? route.path === item.url : route.path.startsWith(item.url))
                      ? 'bg-brand-dark text-white'
                      : 'text-white/45 hover:text-white/80 hover:bg-white/10'"
                  >
                    <component
                      :is="item.icon"
                      class="w-4 h-4 shrink-0 group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5"
                      :class="(item.url === '/admin/dashboard' ? route.path === item.url : route.path.startsWith(item.url)) ? 'text-white' : 'text-white/45'"
                    />
                    <span
                      class="text-sm font-medium flex-1 text-left group-data-[collapsible=icon]:hidden"
                      :class="(item.url === '/admin/dashboard' ? route.path === item.url : route.path.startsWith(item.url)) ? 'text-white' : 'text-white/45'"
                    >
                      {{ item.title }}
                    </span>
                  </component>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <!-- Footer: Settings + Logout -->
      <SidebarFooter class="border-t border-white/10 p-3 space-y-1">
        <div
          @click="logout"
          class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 cursor-pointer transition-colors group-data-[collapsible=icon]:justify-center"
        >
          <LogOut class="w-4 h-4 text-white group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:h-5" />
          <span
            class="text-sm font-medium text-white group-data-[collapsible=icon]:hidden"
            >Logout</span
          >
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
                <router-link to="/admin/notifications" @click="isNotifOpen = false"
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
                    {{ user.initials }}
                  </AvatarFallback>
                </Avatar>
                <div class="hidden sm:block text-left">
                  <p class="text-sm font-semibold text-black leading-tight">
                    {{ user.name }}
                  </p>
                  <p class="text-xs text-black/50 font-secondary">{{ user.role }}</p>
                </div>
              </div>
            </PopoverTrigger>
            <PopoverContent class="w-56 p-2" align="end">
              <div class="space-y-1">
                <div class="px-2 py-1.5 border-b border-black/5">
                  <p class="text-sm font-semibold text-black">{{ user.name }}</p>
                  <p class="text-xs text-black/50 font-secondary">{{ user.email }}</p>
                </div>
                <button @click="router.push('/admin/profile')"
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
