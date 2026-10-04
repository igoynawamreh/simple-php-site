<template>
  <form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_15rem]" @submit.prevent="submit">
    <!-- Main column -->
    <div class="flex min-w-0 flex-col gap-4">
      <div class="flex flex-col gap-1.5">
        <label for="page-title" class="text-sm font-medium">Title</label>
        <Input
          id="page-title"
          v-model="form.title"
          maxlength="200"
          required
          :aria-invalid="!!err('title')"
        />
        <p v-if="err('title')" class="text-xs text-destructive">{{ err('title') }}</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="page-body" class="text-sm font-medium">Content (Markdown)</label>
        <textarea
          id="page-body"
          v-model="form.body"
          spellcheck="false"
          class="dark:bg-input/30 border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-96 w-full resize-y rounded-lg border bg-transparent px-2.5 py-2 font-mono text-sm outline-none transition-colors focus-visible:ring-3"
        />
        <p v-if="err('body')" class="text-xs text-destructive">{{ err('body') }}</p>
      </div>
    </div>

    <!-- Side column -->
    <div class="flex flex-col gap-4">
      <div class="flex flex-col gap-1.5">
        <label for="page-slug" class="text-sm font-medium">Slug</label>
        <Input
          id="page-slug"
          v-model="form.slug"
          :disabled="mode === 'edit'"
          :placeholder="mode === 'create' ? 'Generated from the title if left blank' : ''"
          :aria-invalid="!!err('slug')"
        />
        <p v-if="err('slug')" class="text-xs text-destructive">{{ err('slug') }}</p>
        <p v-else-if="mode === 'edit'" class="text-xs text-muted-foreground">
          The slug is the file name and can't be changed here.
        </p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="page-category" class="text-sm font-medium">Category</label>
        <Input id="page-category" v-model="form.category" list="page-category-options" />
        <datalist id="page-category-options">
          <option v-for="c in options.category" :key="c" :value="c" />
        </datalist>
        <p v-if="err('category')" class="text-xs text-destructive">{{ err('category') }}</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="page-tags" class="text-sm font-medium">Tags</label>
        <div v-if="form.tags.length" class="flex flex-wrap gap-1">
          <span
            v-for="tag in form.tags"
            :key="tag"
            class="inline-flex items-center gap-1 rounded-md bg-secondary py-0.5 pl-1.5 pr-0.5 text-xs text-secondary-foreground"
          >
            {{ tag }}
            <button
              type="button"
              class="rounded-sm p-0.5 hover:bg-background/60"
              :aria-label="`Remove tag ${tag}`"
              @click="removeTag(tag)"
            >
              <X class="size-3" />
            </button>
          </span>
        </div>
        <Input
          id="page-tags"
          v-model="tagInput"
          list="page-tag-options"
          placeholder="Type a tag, then press Enter"
          @keydown="onTagKeydown"
          @blur="addTags(tagInput)"
        />
        <datalist id="page-tag-options">
          <option v-for="t in options.tags" :key="t" :value="t" />
        </datalist>
        <p v-if="err('tags')" class="text-xs text-destructive">{{ err('tags') }}</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="page-date" class="text-sm font-medium">Date</label>
        <Input id="page-date" v-model="form.date" type="date" :aria-invalid="!!err('date')" />
        <p v-if="err('date')" class="text-xs text-destructive">{{ err('date') }}</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="page-thumbnail" class="text-sm font-medium">Thumbnail</label>
        <Input
          id="page-thumbnail"
          v-model="form.thumbnail"
          placeholder="/media/image.jpeg"
        />
        <p v-if="err('thumbnail')" class="text-xs text-destructive">{{ err('thumbnail') }}</p>
      </div>

      <div class="flex items-center gap-2 pt-2">
        <Button type="submit" :disabled="saving">
          {{ saving ? 'Saving…' : mode === 'create' ? 'Create page' : 'Save changes' }}
        </Button>
        <Button as-child variant="ghost">
          <RouterLink :to="parentPath || '/'">Cancel</RouterLink>
        </Button>
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { getPages } from '@/services/page';
import type { Data, FieldErrors } from '@/services/page';

const props = defineProps<{
  mode: 'create' | 'edit'
  initial?: Data
  saving?: boolean
  errors?: FieldErrors
  configRoutePath: string
}>();

const route = useRoute();

const parentPath = computed(() => route.matched.at(-2)?.path ?? '/');

const configRoutePath = computed(() => props.configRoutePath ?? '');

const emit = defineEmits<{
  (e: 'submit', data: Data): void
}>();

const form = reactive({
  slug: '',
  title: '',
  date: '',
  category: '',
  tags: [] as string[],
  thumbnail: '',
  body: '',
});

watch(
  () => props.initial,
  (v) => {
    form.slug = v?.slug ?? '';
    form.title = v?.title ?? '';
    form.date = v?.date ?? '';
    form.category = v?.category ?? '';
    form.tags = [...(v?.tags ?? [])];
    form.thumbnail = v?.thumbnail ?? '';
    form.body = v?.body ?? '';
  },
  { immediate: true },
);

const err = (field: string) => {
  const e = props.errors?.[field];
  return Array.isArray(e) ? e[0] : e;
};

// Existing categories/tags, used only as autocomplete suggestions
const options = reactive({ category: [] as string[], tags: [] as string[] });
onMounted(async () => {
  try {
    const res = await getPages(configRoutePath.value, { count: 1 });
    if (res.success) Object.assign(options, res.fields);
  } catch {
    // Suggestions are optional
  }
});

// Tags
const tagInput = ref('');
function addTags(raw: string) {
  for (const tag of String(raw).split(',').map((t) => t.trim()).filter(Boolean)) {
    if (!form.tags.includes(tag)) form.tags.push(tag);
  }
  tagInput.value = '';
}
const removeTag = (tag: string) => {
  form.tags = form.tags.filter((t) => t !== tag);
};
function onTagKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addTags(tagInput.value);
  } else if (e.key === 'Backspace' && !tagInput.value) {
    form.tags.pop();
  }
}

function submit() {
  addTags(tagInput.value);
  emit('submit', {
    slug: form.slug.trim() || undefined,
    title: form.title.trim(),
    date: form.date || undefined,
    category: form.category.trim() || undefined,
    tags: [...form.tags],
    thumbnail: form.thumbnail.trim() || undefined,
    body: form.body || undefined,
  });
}
</script>
