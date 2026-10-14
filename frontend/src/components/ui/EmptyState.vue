<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  icon?: Component
  title: string
  description?: string
  tone?: 'neutral' | 'primary'
}>()
</script>

<template>
  <div class="flex flex-col items-center px-6 py-10 text-center">
    <span
      v-if="icon"
      :class="[
        'inline-flex h-12 w-12 items-center justify-center rounded-2xl',
        tone === 'primary' ? 'bg-primary-50 text-primary-600' : 'bg-slate-100 text-slate-400',
      ]"
    >
      <component :is="icon" class="h-6 w-6" />
    </span>
    <p class="mt-3 text-sm font-semibold text-slate-900">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-xs leading-relaxed text-slate-500">
      {{ description }}
    </p>
    <div v-if="$slots.actions" class="mt-4">
      <slot name="actions" />
    </div>
  </div>
</template>