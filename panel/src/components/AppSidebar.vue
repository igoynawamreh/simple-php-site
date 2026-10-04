<template>
  <Sidebar collapsible="icon">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <RouterLink to="/">
              <div
                class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
              >
                <span class="text-xs font-bold">{{ APP.SITE_TITLE.charAt(0) }}</span>
              </div>
              <div class="grid flex-1 text-left text-sm leading-tight">
                <span class="truncate font-semibold">{{ APP.SITE_TITLE }}</span>
              </div>
            </RouterLink>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <SidebarGroup>
        <SidebarGroupLabel>Menu</SidebarGroupLabel>
        <SidebarGroupContent>
          <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in navItems" :key="item.to">
              <SidebarMenuButton :isActive="isActive(item.to).value" :tooltip="item.title" as-child>
                <RouterLink :to="item.to">
                  <component :is="item.icon" />
                  <span>{{ item.title }}</span>
                </RouterLink>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroupContent>
      </SidebarGroup>
    </SidebarContent>

    <SidebarFooter>
      <SidebarMenu>
        <SidebarMenuItem>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <SidebarMenuButton
                size="lg"
                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
              >
                <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-muted">
                  <span class="text-xs font-medium">A</span>
                </div>
                <div class="grid flex-1 text-left text-sm leading-tight">
                  <span class="truncate font-semibold">Admin</span>
                </div>
                <ChevronsUpDown class="ml-auto size-4" />
              </SidebarMenuButton>
            </DropdownMenuTrigger>

            <DropdownMenuContent
              class="min-w-56"
              :side="isMobile ? 'bottom' : 'right'"
              align="end"
            >
              <DropdownMenuLabel class="font-normal">
                <div class="flex items-center gap-2 text-left text-sm">
                  <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-muted">
                    <span class="text-xs font-medium">A</span>
                  </div>
                  <span class="truncate font-semibold">Admin</span>
                </div>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />

              <!-- Add more items here, e.g. Settings / Account -->

              <DropdownMenuItem @select="logout">
                <LogOut />
                Log out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarFooter>

    <SidebarRail />
  </Sidebar>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
  ChevronsUpDown,
  LogOut,
} from '@lucide/vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  useSidebar,
} from '@/components/ui/sidebar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/services/auth';

const APP = window.APP;

const route = useRoute();
const router = useRouter();

// Derived from each route's `meta.nav` (see router.ts) — a route only
// shows up here if it was explicitly given a `nav` entry.
const navItems = computed(() =>
  router.getRoutes()
    .filter((r) => r.meta.nav)
    .sort((a, b) => a.meta.nav!.order - b.meta.nav!.order)
    .map((r) => ({
      title: r.meta.title ?? r.path,
      to: r.path,
      icon: r.meta.nav!.icon,
    })),
);

const isActive = (to: string) => computed(() => route.path === to)

// Automatically close the sidebar overlay after navigating on mobile
const { isMobile, setOpenMobile } = useSidebar()
watch(
  () => route.fullPath,
  () => {
    if (isMobile.value) setOpenMobile(false)
  },
)
</script>
