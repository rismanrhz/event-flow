<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue'

defineProps<{
  title?: string
  description?: string
}>()

const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <div class="flex flex-col items-center px-6 py-10 text-center">
    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-500">
      <AlertTriangle class="h-6 w-6" />
    </span>
    <p class="mt-3 text-sm font-semibold text-slate-900">{{ title ?? 'Something went wrong' }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-xs leading-relaxed text-slate-500">{{ description }}</p>
    <button
      type="button"
      class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
      @click="emit('retry')"
    >
      Try again
    </button>
  </div>
</template>