<template>
  <div class="flex flex-col gap-4">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative w-full sm:w-64">
        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="search" type="search" placeholder="Search pages" class="pl-8" />
      </div>

      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="outline">
            Category
            <span v-if="filters.category" class="max-w-32 truncate text-muted-foreground">
              {{ filters.category }}
            </span>
            <ChevronDown />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 min-w-44 overflow-y-auto">
          <DropdownMenuLabel>Category</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <p v-if="!fields.category.length" class="px-1.5 py-1 text-sm text-muted-foreground">
            No categories
          </p>
          <DropdownMenuCheckboxItem
            v-for="category in fields.category"
            :key="category"
            :model-value="filters.category === category"
            @select.prevent
            @update:model-value="setCategory(category)"
          >
            {{ category }}
          </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="outline">
            Tags
            <span v-if="filters.tags.length" class="rounded-sm bg-muted px-1 text-xs tabular-nums">
              {{ filters.tags.length }}
            </span>
            <ChevronDown />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 min-w-44 overflow-y-auto">
          <DropdownMenuLabel>Tags</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <p v-if="!fields.tags.length" class="px-1.5 py-1 text-sm text-muted-foreground">
            No tags
          </p>
          <DropdownMenuCheckboxItem
            v-for="tag in fields.tags"
            :key="tag"
            :model-value="filters.tags.includes(tag)"
            @select.prevent
            @update:model-value="toggleTag(tag)"
          >
            {{ tag }}
          </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Button v-if="hasFilters" variant="ghost" @click="resetFilters">
        <X />
        Reset
      </Button>

      <Button as-child class="ml-auto">
        <RouterLink :to="route.path + '/new'">
          <Plus />
          New page
        </RouterLink>
      </Button>
    </div>

    <p v-if="deleteError" class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {{ deleteError }}
    </p>

    <!-- Table -->
    <div class="overflow-x-auto rounded-lg border">
      <table class="w-full text-left text-sm">
        <thead class="border-b bg-muted/50 text-muted-foreground">
          <tr>
            <th class="px-3 py-2 font-medium">Title</th>
            <th class="hidden px-3 py-2 font-medium sm:table-cell">Category</th>
            <!-- <th class="hidden px-3 py-2 font-medium md:table-cell">Tags</th> -->
            <th class="px-3 py-2 text-right font-medium">Date</th>
            <th class="w-24 px-3 py-2"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading && !data.length">
            <tr v-for="n in 5" :key="n" class="border-b last:border-b-0">
              <td class="px-3 py-3"><Skeleton class="h-4 w-48" /></td>
              <td class="hidden px-3 py-3 sm:table-cell"><Skeleton class="h-4 w-16" /></td>
              <!-- <td class="hidden px-3 py-3 md:table-cell"><Skeleton class="h-4 w-24" /></td> -->
              <td class="px-3 py-3"><Skeleton class="ml-auto h-4 w-20" /></td>
              <td class="px-3 py-3"></td>
            </tr>
          </template>

          <tr v-else-if="error">
            <td colspan="5" class="px-3 py-10 text-center text-destructive">{{ error }}</td>
          </tr>

          <tr v-else-if="!data.length">
            <td colspan="5" class="px-3 py-10 text-center text-muted-foreground">
              {{ hasFilters ? 'No pages match these filters.' : 'No pages yet.' }}
            </td>
          </tr>

          <template v-else>
          <tr
            v-for="item in data"
            :key="item.slug"
            class="border-b transition-opacity last:border-b-0 hover:bg-muted/40"
            :class="{ 'opacity-60': loading }"
          >
            <td class="px-3 py-2.5">
              <RouterLink
                :to="`${route.path}/${encodeURIComponent(item.slug)}`"
                class="font-medium hover:underline"
              >
                {{ item.title }}
              </RouterLink>
              <div class="text-xs text-muted-foreground">{{ item.slug }}</div>
            </td>
            <td class="hidden px-3 py-2.5 sm:table-cell">
              <button
                v-if="item.category"
                type="button"
                class="rounded-md border px-1.5 py-0.5 text-xs hover:bg-muted"
                @click="setCategory(item.category)"
              >
                {{ item.category }}
              </button>
              <span v-else class="text-muted-foreground">-</span>
            </td>
            <!-- <td class="hidden px-3 py-2.5 md:table-cell">
              <div class="flex flex-wrap gap-1">
                <button
                  v-for="tag in item.tags"
                  :key="tag"
                  type="button"
                  class="rounded-md bg-secondary px-1.5 py-0.5 text-xs text-secondary-foreground hover:bg-secondary/70"
                  @click="addTag(tag)"
                >
                  {{ tag }}
                </button>
                <span v-if="!item.tags.length" class="text-muted-foreground">-</span>
              </div>
            </td> -->
            <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-muted-foreground">
              {{ item.date ?? '-' }}
            </td>
            <td class="px-3 py-2.5">
              <div v-if="confirmingSlug === item.slug" class="flex items-center justify-end gap-1">
                <Button
                  variant="destructive"
                  size="icon-sm"
                  :disabled="deleting"
                  aria-label="Confirm delete"
                  @click="removeItem(item.slug)"
                >
                  <Check />
                </Button>
                <Button
                  variant="ghost"
                  size="icon-sm"
                  :disabled="deleting"
                  aria-label="Cancel delete"
                  @click="confirmingSlug = null"
                >
                  <X />
                </Button>
              </div>
              <div v-else class="flex items-center justify-end gap-1">
                <Button as-child variant="ghost" size="icon-sm">
                  <RouterLink :to="`${route.path}/${encodeURIComponent(item.slug)}`" aria-label="Edit">
                    <Pencil />
                  </RouterLink>
                </Button>
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label="Delete"
                  @click="confirmingSlug = item.slug"
                >
                  <Trash2 />
                </Button>
              </div>
            </td>
          </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.total > 0" class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-muted-foreground">
        Showing {{ rangeStart }}–{{ rangeEnd }} of {{ meta.total }}
      </p>

      <nav v-if="meta.last_page > 1" class="flex items-center gap-1" aria-label="Pagination">
        <Button
          variant="outline"
          size="icon"
          :disabled="meta.current_page <= 1"
          aria-label="Previous page"
          @click="goToPage(meta.current_page - 1)"
        >
          <ChevronLeft />
        </Button>

        <template v-for="(p, i) in pagination" :key="i">
          <span v-if="p === '...'" class="px-1.5 text-muted-foreground">…</span>
          <Button
            v-else
            :variant="p === meta.current_page ? 'default' : 'outline'"
            size="icon"
            :aria-current="p === meta.current_page ? 'page' : undefined"
            @click="goToPage(p)"
          >
            {{ p }}
          </Button>
        </template>

        <Button
          variant="outline"
          size="icon"
          :disabled="meta.current_page >= meta.last_page"
          aria-label="Next page"
          @click="goToPage(meta.current_page + 1)"
        >
          <ChevronRight />
        </Button>
      </nav>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import type { LocationQueryValue } from 'vue-router';
import { useDebounceFn } from '@vueuse/core';
import {
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Pencil,
  Plus,
  Search,
  Trash2,
  X,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { deletePage, getPages } from '@/services/page';
import type { ListData, ListResponse } from '@/services/page';

const props = defineProps<{
  configRoutePath: string;
}>();

const PAGE_SIZE = 20;

const route = useRoute();
const router = useRouter();

const configRoutePath = computed(() => props.configRoutePath ?? '');

// The URL (?q=&category=&tags=&page=) is the single source of truth, so
// filters survive a refresh and work with the browser's back button.
const first = (v: LocationQueryValue | LocationQueryValue[] | undefined) =>
  (Array.isArray(v) ? v[0] : v) ?? '';

const filters = computed(() => ({
  q: first(route.query.q),
  category: first(route.query.category),
  tags: ([] as (LocationQueryValue)[])
    .concat(route.query.tags ?? [])
    .filter((t): t is string => !!t),
  page: Math.max(1, parseInt(first(route.query.page)) || 1),
}));

const hasFilters = computed(
  () => !!(filters.value.q || filters.value.category || filters.value.tags.length),
);

function updateQuery(patch: { q?: string; category?: string; tags?: string[]; page?: number }) {
  const next = { ...filters.value, ...patch };
  // A filter change usually invalidates the current page number
  if (patch.page === undefined) next.page = 1;

  router.push({
    query: {
      page: next.page > 1 ? String(next.page) : undefined,
      q: next.q || undefined,
      category: next.category || undefined,
      tags: next.tags.length ? next.tags : undefined,
    },
  });
}

// Search box: local value for typing, debounced into the URL
const search = ref(filters.value.q);
const applySearch = useDebounceFn((value: string) => {
  if (value.trim() !== filters.value.q) updateQuery({ q: value.trim() });
}, 300);
watch(search, (value) => applySearch(String(value)));
// Keep the box in sync when the URL changes from elsewhere (reset, back button)
watch(
  () => filters.value.q,
  (q) => {
    if (q !== search.value.trim()) search.value = q;
  },
);

const setCategory = (category: string) =>
  updateQuery({ category: filters.value.category === category ? '' : category });
const toggleTag = (tag: string) => {
  const tags = filters.value.tags;
  updateQuery({ tags: tags.includes(tag) ? tags.filter((t) => t !== tag) : [...tags, tag] });
};
// const addTag = (tag: string) => {
//   if (!filters.value.tags.includes(tag)) toggleTag(tag);
// };
const resetFilters = () => {
  search.value = '';
  updateQuery({ q: '', category: '', tags: [] });
};
const goToPage = (page: number) => updateQuery({ page });

// Data
const data = ref<ListData[]>([]);
const meta = reactive({ total: 0, count: PAGE_SIZE, current_page: 1, last_page: 1 });
const pagination = ref<ListResponse['pagination']>([]);
const fields = reactive<ListResponse['fields']>({ category: [], tags: [] });
const loading = ref(false);
const error = ref('');

let requestId = 0;

async function load() {
  const id = ++requestId;
  loading.value = true;
  error.value = '';

  try {
    const res = await getPages(
      configRoutePath.value,
      {
        page: filters.value.page,
        count: PAGE_SIZE,
        q: filters.value.q,
        category: filters.value.category,
        tags: filters.value.tags,
      }
    );

    // A newer request has been fired in the meantime, so drop this one
    if (id !== requestId) return;

    if (!res.success) throw new Error(res.message ?? 'Failed to load pages.');

    data.value = res.data;
    Object.assign(meta, res.meta);
    pagination.value = res.pagination;
    Object.assign(fields, res.fields);

    // Requested page is past the end (e.g. after a filter change)
    if (res.meta.last_page >= 1 && filters.value.page > res.meta.last_page) {
      goToPage(res.meta.last_page);
    }
  } catch (e) {
    if (id !== requestId) return;
    data.value = [];
    error.value = e instanceof Error ? e.message : 'Failed to load pages.';
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch(() => route.fullPath, load, { immediate: true });

// Delete (two-step: trash icon, then confirm)
const confirmingSlug = ref<string | null>(null);
const deleting = ref(false);
const deleteError = ref('');

async function removeItem(slug: string) {
  deleting.value = true;
  deleteError.value = '';

  try {
    const res = await deletePage(configRoutePath.value, slug);
    if (!res.success) throw new Error(res.message ?? 'Failed to delete the page.');
    confirmingSlug.value = null;
    // Reload; if this emptied the last page, load() steps back one page
    await load();
  } catch (e) {
    deleteError.value = e instanceof Error ? e.message : 'Failed to delete the page.';
    confirmingSlug.value = null;
  } finally {
    deleting.value = false;
  }
}

const rangeStart = computed(() => (meta.current_page - 1) * meta.count + 1);
const rangeEnd = computed(() => Math.min(meta.current_page * meta.count, meta.total));
</script>
