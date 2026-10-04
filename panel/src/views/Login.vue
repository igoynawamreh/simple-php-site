<template>
  <div class="relative flex min-h-svh items-center justify-center p-4">
    <ModeToggle class="absolute right-4 top-4" />

    <form class="flex w-full max-w-sm flex-col gap-4 rounded-xl border p-6" @submit.prevent="submit">
      <div class="flex flex-col gap-1">
        <h1 class="text-lg font-semibold">Login</h1>
        <p class="text-sm text-muted-foreground">Enter the password to continue.</p>
      </div>

      <div class="flex flex-col gap-1.5">
        <label for="login-password" class="text-sm font-medium">Password</label>
        <Input
          id="login-password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          autofocus
          required
          :aria-invalid="!!error"
        />
        <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
      </div>

      <Button type="submit" :disabled="loading || !password">
        {{ loading ? 'Signing in…' : 'Sign in' }}
      </Button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ModeToggle from '@/components/ModeToggle.vue';
import { login } from '@/services/auth';

const password = ref('');
const loading = ref(false);
const error = ref('');

async function submit() {
  if (loading.value || !password.value) return;

  loading.value = true;
  error.value = '';

  try {
    // On success the shared auth state flips and App.vue swaps this page out
    const res = await login(String(password.value));
    if (!res.success) {
      error.value = res.message ?? 'Login failed.';
      password.value = '';
    }
  } catch {
    error.value = 'Could not reach the server.';
  } finally {
    loading.value = false;
  }
}
</script>
