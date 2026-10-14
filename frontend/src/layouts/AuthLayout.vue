<script setup lang="ts">
import { CalendarHeart, Sparkles } from '@lucide/vue'
</script>

<template>
  <div class="flex min-h-screen bg-slate-50">
    <div class="hidden w-1/2 flex-col justify-between bg-primary-700 p-12 text-white lg:flex">
      <div class="flex items-center gap-2.5">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white/10">
          <CalendarHeart class="h-5 w-5" />
        </span>
        <span class="text-lg font-semibold tracking-tight">EventFlow</span>
      </div>

      <blockquote class="max-w-md">
        <p class="text-2xl font-medium leading-relaxed">
          "Plan and manage your event effortlessly — all in one place."
        </p>
        <footer class="mt-4 text-sm text-primary-200">Maintenance team</footer>
      </blockquote>

      <div class="flex items-center gap-1.5 text-sm text-primary-200">
        <Sparkles class="h-4 w-4" />
        <span>The website management platform by Risma</span>
      </div>
    </div>

    <div class="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-1/2">
      <div class="w-full max-w-md">
        <slot />
      </div>
    </div>
  </div>
</template>