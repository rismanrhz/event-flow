<script setup lang="ts">
import { CircleX } from '@lucide/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-6 text-center">
    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-red-100 text-red-600">
      <CircleX class="h-7 w-7" />
    </span>
    <h1 class="mt-6 text-3xl font-semibold tracking-tight text-slate-900">Page not found</h1>
    <p class="mt-2 max-w-md text-sm text-slate-500">
      The page you're looking for doesn't exist or may have been moved.
    </p>
    <router-link :to="{ name: 'landing' }" class="mt-8">
      <BaseButton>Back to home</BaseButton>
    </router-link>
  </div>
</template>