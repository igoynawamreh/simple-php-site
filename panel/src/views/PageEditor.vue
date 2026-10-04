<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
      <Button as-child variant="ghost" size="icon" aria-label="Back to pages">
        <RouterLink :to="parentPath"><ArrowLeft /></RouterLink>
      </Button>
      <h1 class="min-w-0 flex-1 truncate text-lg font-semibold">
        <template v-if="isEdit">{{ loaded ? loaded.title || slug : 'Edit Page' }}</template>
        <template v-else>New Page</template>
      </h1>

      <template v-if="isEdit && loaded">
        <Button as-child variant="outline">
          <a :href="previewPath" target="_blank" rel="noopener">
            <ExternalLink />
            View
          </a>
        </Button>

        <template v-if="!confirmingDelete">
          <Button variant="destructive" @click="confirmingDelete = true">
            <Trash2 />
            Delete
          </Button>
        </template>
        <div v-else class="flex items-center gap-2 rounded-lg border border-destructive/40 px-2 py-1">
          <span class="text-sm">Delete permanently?</span>
          <Button variant="destructive" size="sm" :disabled="deleting" @click="remove">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </Button>
          <Button variant="ghost" size="sm" :disabled="deleting" @click="confirmingDelete = false">
            Cancel
          </Button>
        </div>
      </template>
    </div>

    <p v-if="message" class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {{ message }}
    </p>
    <p v-if="saved" class="rounded-lg border px-3 py-2 text-sm text-muted-foreground" role="status">
      Changes saved.
    </p>

    <!-- Loading (edit only) -->
    <div v-if="loading" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_15rem]">
      <div class="flex flex-col gap-4">
        <Skeleton class="h-8 w-full" />
        <Skeleton class="h-96 w-full" />
      </div>
      <div class="flex flex-col gap-4">
        <Skeleton v-for="n in 5" :key="n" class="h-8 w-full" />
      </div>
    </div>

    <!-- Not found / failed to load (edit only) -->
    <div v-else-if="isEdit && !loaded" class="rounded-lg border px-3 py-10 text-center text-muted-foreground">
      {{ loadError || 'Page not found.' }}
    </div>

    <PageForm
      v-else
      :key="`${mode}:${slug}`"
      :mode="mode"
      :initial="initial"
      :saving="saving"
      :errors="errors"
      :configRoutePath="configRoutePath"
      @submit="save"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { joinPath } from '@/lib/utils';
import { ArrowLeft, ExternalLink, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import PageForm from '@/components/PageForm.vue';
import { createPage, deletePage, getPage, updatePage } from '@/services/page';
import type { Data, ReadResponse, FieldErrors } from '@/services/page';

const props = defineProps<{
  mode: 'create' | 'edit';
  slug?: string;
  configRoutePath: string;
}>();

const route = useRoute();
const router = useRouter();

const parentPath = computed(() => route.matched.at(-2)?.path ?? '/');

const isEdit = computed(() => props.mode === 'edit');
const slug = computed(() => props.slug ?? '');
const configRoutePath = computed(() => props.configRoutePath ?? '');
const previewPath = computed(
  () => joinPath(window.APP.BASE_URL, loaded.value?.route),
);

// Today in the browser's local time, as YYYY-MM-DD
function blank(): Data {
  return { title: '', date: new Date().toLocaleDateString('sv-SE') };
}

const loaded = ref<ReadResponse['data'] | null>(null);
const loading = ref(false);
const loadError = ref('');

const initial = ref<Data>(blank());

const saving = ref(false);
const saved = ref(false);
const message = ref('');
const errors = ref<FieldErrors>({});

const confirmingDelete = ref(false);
const deleting = ref(false);

let requestId = 0;
let savedTimer: ReturnType<typeof setTimeout> | undefined;

function reset() {
  loaded.value = null;
  loadError.value = '';
  message.value = '';
  saved.value = false;
  confirmingDelete.value = false;
  errors.value = {};
  initial.value = blank();
  clearTimeout(savedTimer);
}

async function load() {
  const id = ++requestId;
  reset();

  // Create mode: nothing to fetch, the blank form is ready
  if (!isEdit.value) {
    loading.value = false;
    return;
  }

  loading.value = true;

  try {
    const res = await getPage(configRoutePath.value, slug.value);
    if (id !== requestId) return;

    if (res.success && res.data) {
      initial.value = {
        slug: res.data.slug || undefined,
        title: res.data.title,
        date: res.data.date || undefined,
        category: res.data.category || undefined,
        tags: res.data.tags || undefined,
        thumbnail: res.data.thumbnail || undefined,
        body: res.data.body || undefined,
      };
      loaded.value = {
        route: res.data.route,
        slug: res.data.slug,
        title: res.data.title,
        date: res.data.date || null,
        category: res.data.category || null,
        tags: res.data.tags || null,
        thumbnail: res.data.thumbnail || null,
        body: res.data.body || null,
      };
    } else {
      loadError.value = res.message ?? 'Page not found.';
    }
  } catch {
    if (id !== requestId) return;
    loadError.value = 'Could not reach the server.';
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch(() => [props.mode, props.slug], load, { immediate: true });

function fail(res: { errors?: FieldErrors; message?: string }, fallback: string) {
  errors.value = res.errors ?? {};
  // Field errors are shown next to their field; only show a banner otherwise
  if (!res.errors) message.value = res.message ?? fallback;
}

async function save(data: Data) {
  saving.value = true;
  saved.value = false;
  message.value = '';
  errors.value = {};
  clearTimeout(savedTimer);

  try {
    if (isEdit.value) {
      const res = await updatePage(configRoutePath.value, { ...data, slug: slug.value });

      if (res.success) {
        saved.value = true;
        savedTimer = setTimeout(() => (saved.value = false), 3000);
        return;
      }

      fail(res, 'Failed to save the page.');
    } else {
      const res = await createPage(configRoutePath.value, data);

      if (res.success && res.slug) {
        await router.push(`${parentPath.value}/${encodeURIComponent(res.slug)}`);
        return;
      }

      fail(res, 'Failed to create the page.');
    }
  } catch {
    message.value = 'Could not reach the server.';
  } finally {
    saving.value = false;
  }
}

async function remove() {
  deleting.value = true;
  message.value = '';

  try {
    const res = await deletePage(configRoutePath.value, slug.value);

    if (res.success) {
      await router.push(parentPath.value);
      return;
    }

    message.value = res.message ?? 'Failed to delete the page.';
    confirmingDelete.value = false;
  } catch {
    message.value = 'Could not reach the server.';
    confirmingDelete.value = false;
  } finally {
    deleting.value = false;
  }
}

onBeforeUnmount(() => clearTimeout(savedTimer));
</script>
