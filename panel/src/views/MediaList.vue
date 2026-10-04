<template>
  <div
    class="relative flex flex-col gap-4"
    @dragenter.prevent="onDragEnter"
    @dragover.prevent
    @dragleave.prevent="onDragLeave"
    @drop.prevent="onDrop"
  >
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative w-full sm:w-64">
        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="search" type="search" placeholder="Search files" class="pl-8" />
      </div>

      <div class="flex items-center gap-1" role="group" aria-label="Filter by type">
        <Button
          v-for="option in typeOptions"
          :key="option.value"
          :variant="filters.type === option.value ? 'default' : 'outline'"
          @click="updateQuery({ type: option.value })"
        >
          {{ option.label }}
        </Button>
      </div>

      <div class="ml-auto flex items-center gap-3">
        <Button :disabled="uploading" @click="fileInput?.click()">
          <Upload />
          {{ uploading ? 'Uploading…' : 'Upload' }}
        </Button>
        <input
          ref="fileInput"
          type="file"
          multiple
          class="hidden"
          :accept="accept"
          @change="onPick"
        />
      </div>
    </div>

    <!-- Upload result -->
    <div
      v-if="notice"
      class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
      :class="notice.error || notice.failures.length ? 'border-destructive/40 bg-destructive/10' : ''"
      role="status"
    >
      <div class="flex min-w-0 flex-1 flex-col gap-1">
        <p v-if="notice.error" class="text-destructive">{{ notice.error }}</p>
        <p v-if="notice.ok > 0">
          {{ notice.ok }} {{ notice.ok === 1 ? 'file' : 'files' }} uploaded.
        </p>
        <ul v-if="notice.failures.length" class="flex flex-col gap-0.5 text-destructive">
          <li v-for="(f, i) in notice.failures" :key="i" class="break-words">
            <span class="font-medium">{{ f.file }}</span>: {{ f.message }}
          </li>
        </ul>
      </div>
      <Button variant="ghost" size="icon-sm" aria-label="Dismiss" @click="notice = null">
        <X />
      </Button>
    </div>

    <p v-if="deleteError" class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {{ deleteError }}
    </p>

    <!-- Loading -->
    <ul v-if="loading && !items.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
      <li v-for="n in 12" :key="n">
        <Skeleton class="aspect-square w-full" />
      </li>
    </ul>

    <!-- Error -->
    <div v-else-if="error" class="rounded-lg border px-3 py-10 text-center text-destructive">
      {{ error }}
    </div>

    <!-- Empty -->
    <div v-else-if="!items.length" class="rounded-lg border border-dashed px-3 py-16 text-center text-muted-foreground">
      <p>{{ hasFilters ? 'No files match these filters.' : 'No files yet.' }}</p>
      <p v-if="!hasFilters" class="mt-1 text-sm">Drop files anywhere on this page, or use the Upload button.</p>
    </div>

    <!-- Grid -->
    <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
      <li
        v-for="item in items"
        :key="item.name"
        class="flex flex-col overflow-hidden rounded-lg border transition-opacity"
        :class="{ 'opacity-60': loading }"
      >
        <a
          :href="item.url"
          target="_blank"
          rel="noopener"
          class="block aspect-square bg-muted"
          :aria-label="`Open ${item.name}`"
        >
          <img
            v-if="item.type === 'image'"
            :src="item.url"
            :alt="item.name"
            loading="lazy"
            class="size-full object-cover"
          />
          <div v-else class="flex size-full flex-col items-center justify-center gap-1 text-muted-foreground">
            <component :is="iconFor(item.ext)" class="size-10" />
            <span class="text-xs font-medium uppercase">{{ item.ext }}</span>
          </div>
        </a>

        <div class="flex flex-col gap-0.5 px-2.5 pt-2">
          <span class="truncate text-sm font-medium" :title="item.name">{{ item.name }}</span>
          <span class="text-xs text-muted-foreground">
            {{ formatBytes(item.size) }} · {{ formatDate(item.modified) }}
          </span>
        </div>

        <div class="mt-auto flex items-center justify-end gap-1 p-1.5">
          <template v-if="confirmingName === item.name">
            <span class="mr-auto pl-1 text-xs text-destructive">Delete?</span>
            <Button
              variant="destructive"
              size="icon-sm"
              :disabled="deleting"
              aria-label="Confirm delete"
              @click="removeItem(item.name)"
            >
              <Check />
            </Button>
            <Button
              variant="ghost"
              size="icon-sm"
              :disabled="deleting"
              aria-label="Cancel delete"
              @click="confirmingName = null"
            >
              <X />
            </Button>
          </template>
          <template v-else>
            <span v-if="copiedName === item.name" class="mr-auto pl-1 text-xs text-muted-foreground">
              Copied
            </span>
            <Button variant="ghost" size="icon-sm" aria-label="Copy path" title="Copy path" @click="copyPath(item)">
              <Link />
            </Button>
            <Button variant="ghost" size="icon-sm" aria-label="Delete" title="Delete" @click="confirmingName = item.name">
              <Trash2 />
            </Button>
          </template>
        </div>
      </li>
    </ul>

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

        <template v-for="(p, i) in pageNumbers" :key="i">
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

    <!-- Drop overlay -->
    <div
      v-if="dragging"
      class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-background/80 text-lg font-medium"
    >
      Drop files to upload
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LocationQueryValue } from 'vue-router';
import { useDebounceFn } from '@vueuse/core';
import {
  Check,
  ChevronLeft,
  ChevronRight,
  File as FileIcon,
  FileArchive,
  FileSpreadsheet,
  FileText,
  Link,
  Presentation,
  Search,
  Trash2,
  Upload,
  X,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { deleteMedia, listMedia, uploadMedia } from '@/services/media';
import type { ListResponse, MediaItem, MediaType, UploadFileResult } from '@/services/media';

const PAGE_SIZE = 24;

const route = useRoute();
const router = useRouter();

// The URL (?q=&type=&page=) is the single source of truth, so filters survive
// a refresh and work with the browser's back button.
const first = (v: LocationQueryValue | LocationQueryValue[] | undefined) =>
  (Array.isArray(v) ? v[0] : v) ?? '';

const typeOptions: { label: string; value: MediaType | '' }[] = [
  { label: 'All', value: '' },
  { label: 'Images', value: 'image' },
  { label: 'Documents', value: 'document' },
  { label: 'Other', value: 'other' },
];

const filters = computed(() => {
  const type = first(route.query.type);

  return {
    q: first(route.query.q),
    type: (['image', 'document', 'other'].includes(type) ? type : '') as MediaType | '',
    page: Math.max(1, parseInt(first(route.query.page)) || 1),
  };
});

const hasFilters = computed(() => !!(filters.value.q || filters.value.type));

function updateQuery(patch: { q?: string; type?: MediaType | ''; page?: number }) {
  const next = { ...filters.value, ...patch };
  // A filter change usually invalidates the current page number
  if (patch.page === undefined) next.page = 1;

  router.push({
    query: {
      q: next.q || undefined,
      type: next.type || undefined,
      page: next.page > 1 ? String(next.page) : undefined,
    },
  });
}

const goToPage = (page: number) => updateQuery({ page });

// Search box: local value for typing, debounced into the URL
const search = ref(filters.value.q);
const applySearch = useDebounceFn((value: string) => {
  if (value.trim() !== filters.value.q) updateQuery({ q: value.trim() });
}, 300);
watch(search, (value) => applySearch(String(value)));
// Keep the box in sync when the URL changes from elsewhere (back button)
watch(
  () => filters.value.q,
  (q) => {
    if (q !== search.value.trim()) search.value = q;
  },
);

// Data
const items = ref<MediaItem[]>([]);
const meta = reactive({ total: 0, count: PAGE_SIZE, current_page: 1, last_page: 1 });
const limits = ref<ListResponse['limits'] | null>(null);
const loading = ref(false);
const error = ref('');

let requestId = 0;

async function load() {
  const id = ++requestId;
  loading.value = true;
  error.value = '';

  try {
    const res = await listMedia({
      page: filters.value.page,
      count: PAGE_SIZE,
      q: filters.value.q,
      type: filters.value.type,
    });

    // A newer request has been fired in the meantime, so drop this one
    if (id !== requestId) return;

    if (!res.success) throw new Error(res.message ?? 'Failed to load media.');

    items.value = res.items;
    Object.assign(meta, res.meta);
    limits.value = res.limits;

    // Requested page is past the end (e.g. after deleting the last item on it)
    if (filters.value.page > res.meta.last_page) goToPage(res.meta.last_page);
  } catch (e) {
    if (id !== requestId) return;
    items.value = [];
    error.value = e instanceof Error ? e.message : 'Failed to load media.';
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch(() => route.fullPath, load, { immediate: true });

// Pagination
const rangeStart = computed(() => (meta.current_page - 1) * meta.count + 1);
const rangeEnd = computed(() => Math.min(meta.current_page * meta.count, meta.total));

// 1 … 4 5 6 … 20
const pageNumbers = computed<(number | '...')[]>(() => {
  const last = meta.last_page;
  const wanted = new Set(
    [1, meta.current_page - 1, meta.current_page, meta.current_page + 1, last].filter(
      (n) => n >= 1 && n <= last,
    ),
  );

  const pages: (number | '...')[] = [];
  let previous = 0;
  for (const n of [...wanted].sort((a, b) => a - b)) {
    if (n - previous > 1) pages.push('...');
    pages.push(n);
    previous = n;
  }
  return pages;
});

// Upload
interface Notice {
  ok: number
  failures: { file: string; message: string }[]
  error: string
}

const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const notice = ref<Notice | null>(null);

// Lets the file picker pre-filter by extension
const accept = computed(() => limits.value?.extensions.map((e) => `.${e}`).join(','));

async function upload(files: File[]) {
  if (!files.length || uploading.value) return;

  uploading.value = true;
  notice.value = null;

  try {
    const res = await uploadMedia(files);
    const results: UploadFileResult[] = res.results ?? [];

    notice.value = {
      ok: results.filter((r) => r.success).length,
      failures: results
        .filter((r) => !r.success)
        .map((r) => ({ file: r.file, message: r.message ?? 'Upload failed.' })),
      error: res.results ? '' : (res.message ?? 'Upload failed.'),
    };

    // New files are listed newest first, so go back to the first page
    if (notice.value.ok > 0) {
      if (filters.value.page === 1) await load();
      else goToPage(1);
    }
  } catch {
    notice.value = { ok: 0, failures: [], error: 'Could not reach the server.' };
  } finally {
    uploading.value = false;
  }
}

function onPick(e: Event) {
  const input = e.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  input.value = ''; // allow picking the same file again
  upload(files);
}

// Drag and drop anywhere on the page. dragenter/dragleave also fire for child
// elements, so count them instead of toggling a flag.
const dragging = ref(false);
let dragDepth = 0;

const hasFiles = (e: DragEvent) => !!e.dataTransfer?.types.includes('Files');

function onDragEnter(e: DragEvent) {
  if (!hasFiles(e)) return;
  dragDepth++;
  dragging.value = true;
}

function onDragLeave(e: DragEvent) {
  if (!hasFiles(e)) return;
  dragDepth = Math.max(0, dragDepth - 1);
  if (dragDepth === 0) dragging.value = false;
}

function onDrop(e: DragEvent) {
  dragDepth = 0;
  dragging.value = false;
  upload(Array.from(e.dataTransfer?.files ?? []));
}

// Delete (two-step: trash icon, then confirm)
const confirmingName = ref<string | null>(null);
const deleting = ref(false);
const deleteError = ref('');

async function removeItem(name: string) {
  deleting.value = true;
  deleteError.value = '';

  try {
    const res = await deleteMedia(name);
    if (!res.success) throw new Error(res.message ?? 'Failed to delete the file.');
    confirmingName.value = null;
    // Reload; if this emptied the last page, load() steps back one page
    await load();
  } catch (e) {
    deleteError.value = e instanceof Error ? e.message : 'Failed to delete the file.';
    confirmingName.value = null;
  } finally {
    deleting.value = false;
  }
}

// Copy the site-relative path (e.g. /media/photo.jpg) for use in content
const copiedName = ref<string | null>(null);
let copiedTimer: ReturnType<typeof setTimeout> | undefined;

async function copyPath(item: MediaItem) {
  try {
    await navigator.clipboard.writeText(item.url);
    copiedName.value = item.name;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copiedName.value = null), 1500);
  } catch {
    // The clipboard needs https or localhost
    deleteError.value = 'Could not copy. The clipboard is only available over https or on localhost.';
  }
}

onBeforeUnmount(() => clearTimeout(copiedTimer));

// Formatting
function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;

  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let i = 0;
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024;
    i++;
  }
  return `${value >= 10 ? Math.round(value) : value.toFixed(1)} ${units[i]}`;
}

const formatDate = (timestamp: number) => new Date(timestamp * 1000).toLocaleDateString();

function iconFor(ext: string) {
  if (['xls', 'xlsx', 'ods', 'csv'].includes(ext)) return FileSpreadsheet;
  if (['ppt', 'pptx', 'odp'].includes(ext)) return Presentation;
  if (['pdf', 'doc', 'docx', 'odt', 'rtf'].includes(ext)) return FileText;
  if (ext === 'zip') return FileArchive;
  return FileIcon;
}
</script>
