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
        <label for="page-date" class="text-sm font-medium">Date</label>
        <Input id="page-date" v-model="form.date" type="date" :aria-invalid="!!err('date')" />
        <p v-if="err('date')" class="text-xs text-destructive">{{ err('date') }}</p>
      </div>

      <div class="flex items-center gap-2 pt-2">
        <Button type="submit" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </Button>
        <Button as-child variant="ghost">
          <RouterLink :to="parentPath || '/'">Cancel</RouterLink>
        </Button>
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Data, FieldErrors } from '@/services/static-page';

const props = defineProps<{
  initial: Data
  saving?: boolean
  errors?: FieldErrors
}>();

const route = useRoute();

const parentPath = computed(() => route.matched.at(-2)?.path ?? '/');

const emit = defineEmits<{
  (e: 'submit', data: Data): void
}>();

const form = reactive({
  slug: '',
  title: '',
  date: '',
  body: '',
});

watch(
  () => props.initial,
  (v) => {
    form.title = v?.title ?? '';
    form.date = v?.date ?? '';
    form.body = v?.body ?? '';
  },
  { immediate: true },
);

const err = (field: string) => {
  const e = props.errors?.[field];
  return Array.isArray(e) ? e[0] : e;
};

function submit() {
  emit('submit', {
    title: form.title.trim(),
    date: form.date || undefined,
    body: form.body || undefined,
  });
}
</script>
