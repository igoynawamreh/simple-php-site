<template>
  <SidebarProvider v-if="auth.loggedIn">
    <AppSidebar />
    <SidebarInset>
      <header class="flex h-14 shrink-0 items-center gap-2 border-b px-4">
        <SidebarTrigger class="-ml-1" />
        <Separator
          orientation="vertical"
          class="mr-2 data-[orientation=vertical]:h-4 data-[orientation=vertical]:self-auto"
        />

        <nav aria-label="Breadcrumb" class="flex items-center text-sm">
          <ol class="flex items-center gap-1.5">
            <li
              v-for="(item, index) in breadcrumbs"
              :key="`${item.title}-${index}`"
              class="flex items-center gap-1.5"
            >
              <ChevronRight
                v-if="index > 0"
                class="h-3.5 w-3.5 text-muted-foreground"
                aria-hidden="true"
              />

              <RouterLink
                v-if="index < breadcrumbs.length - 1"
                :to="item.to"
                class="text-muted-foreground transition-colors hover:text-foreground"
              >
                {{ item.title }}
              </RouterLink>

              <span
                v-else
                class="font-medium text-foreground"
                aria-current="page"
              >
                {{ item.title }}
              </span>
            </li>
          </ol>
        </nav>

        <ModeToggle class="ml-auto" />
      </header>
      <main class="flex-1 p-4">
        <router-view />
      </main>
    </SidebarInset>
  </SidebarProvider>

  <Login v-else />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AppSidebar from '@/components/AppSidebar.vue';
import ModeToggle from '@/components/ModeToggle.vue';
import { ChevronRight } from '@lucide/vue';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { Separator } from '@/components/ui/separator';
import Login from '@/views/Login.vue';
import { auth } from '@/services/auth';

const route = useRoute();

const breadcrumbs = computed(() => {
  const items = route.matched
    .filter((record) => record.meta.title)
    .map((record) => ({
      title: record.meta.title as string,
      name: record.name,
      to: record.path,
      params: route.params,
    }));

  // Collapse consecutive entries with the same title — happens when a
  // layout route (e.g. '/blog') and its index child share the same
  // label, which would otherwise render as "Blog / Blog".
  return items.filter((item, index) => items[index - 1]?.title !== item.title);
});
</script>
