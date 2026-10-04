<template>
  <div class="flex min-h-[60vh] flex-col items-center justify-center gap-6 px-4 text-center">
    <SearchX class="size-10 text-muted-foreground" />
    <div class="space-y-2">
      <p class="text-sm font-medium text-muted-foreground">404</p>
      <h1 class="text-xl font-semibold text-foreground">Page not found</h1>
      <p class="max-w-sm text-sm text-muted-foreground">
        This page doesn't exist. It might have been moved, renamed, or deleted.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { SearchX } from '@lucide/vue';
</script>
