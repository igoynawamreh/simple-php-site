<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
      <Button as-child variant="ghost" size="icon" aria-label="Back to pages">
        <RouterLink :to="parentPath"><ArrowLeft /></RouterLink>
      </Button>
      <h1 class="min-w-0 flex-1 truncate text-lg font-semibold">
        {{ loaded ? loaded.title || 'Edit Page' : 'Edit Page' }}
      </h1>

      <template v-if="loaded">
        <Button as-child variant="outline">
          <a :href="previewPath" target="_blank" rel="noopener">
            <ExternalLink />
            View
          </a>
        </Button>
      </template>
    </div>

    <p v-if="message" class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {{ message }}
    </p>
    <p v-if="saved" class="rounded-lg border px-3 py-2 text-sm text-muted-foreground" role="status">
      Changes saved.
    </p>

    <!-- Loading -->
    <div v-if="loading" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_15rem]">
      <div class="flex flex-col gap-4">
        <Skeleton class="h-8 w-full" />
        <Skeleton class="h-96 w-full" />
      </div>
      <div class="flex flex-col gap-4">
        <Skeleton v-for="n in 1" :key="n" class="h-8 w-full" />
      </div>
    </div>

    <!-- Not found / failed to load -->
    <div v-else-if="!loaded" class="rounded-lg border px-3 py-10 text-center text-muted-foreground">
      {{ loadError || 'Page not found.' }}
    </div>

    <PageForm
      v-else
      :initial="initial"
      :key="route.path"
      :saving="saving"
      :errors="errors"
      @submit="save"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { joinPath } from '@/lib/utils';
import { ArrowLeft, ExternalLink } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import PageForm from '@/components/StaticPageForm.vue';
import { getPage, updatePage } from '@/services/static-page';
import type { Data, ReadResponse, FieldErrors } from '@/services/static-page';

const props = defineProps<{
  configRoutePath: string;
  configContentPath: string;
}>();

const route = useRoute();

const parentPath = computed(() => route.matched.at(-2)?.path ?? '/');

const configRoutePath = computed(() => props.configRoutePath ?? '');
const configContentPath = computed(() => props.configContentPath ?? '');
const previewPath = computed(
  () => joinPath(window.APP.BASE_URL, loaded.value?.route),
);

const loaded = ref<ReadResponse['data'] | null>(null);
const loading = ref(false);
const loadError = ref('');

const initial = ref<Data>({
  title: '',
  date: '',
  body: '',
});

const saving = ref(false);
const saved = ref(false);
const message = ref('');
const errors = ref<FieldErrors>({});

let requestId = 0;
let savedTimer: ReturnType<typeof setTimeout> | undefined;

function reset() {
  loaded.value = null;
  loadError.value = '';
  message.value = '';
  saved.value = false;
  errors.value = {};
  initial.value = { title: '', date: '', body: '' };
  clearTimeout(savedTimer);
}

async function load() {
  const id = ++requestId;
  reset();

  loading.value = true;

  try {
    const res = await getPage(configRoutePath.value, configContentPath.value);
    if (id !== requestId) return;

    if (res.success && res.data) {
      initial.value = {
        title: res.data.title,
        date: res.data.date || undefined,
        body: res.data.body || undefined,
      }
      loaded.value = {
        route: res.data.route,
        slug: res.data.slug,
        title: res.data.title,
        date: res.data.date || null,
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

watch(() => [route.path], load, { immediate: true });

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
    const res = await updatePage(configRoutePath.value, configContentPath.value, { ...data });

    if (res.success) {
      saved.value = true;
      savedTimer = setTimeout(() => (saved.value = false), 3000);
      return;
    }

    fail(res, 'Failed to save the page.');
  } catch {
    message.value = 'Could not reach the server.';
  } finally {
    saving.value = false;
  }
}

onBeforeUnmount(() => clearTimeout(savedTimer));
</script>
