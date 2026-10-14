<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    lines?: number
    class?: string
  }>(),
  { lines: 1 },
)

const itemClass = computed(() => props.class ?? 'h-5')
</script>

<template>
  <div
    class="flex flex-col gap-2"
    role="status"
    :aria-label="lines > 1 ? `Loading ${lines} rows` : 'Loading'"
  >
    <div
      v-for="i in lines"
      :key="i"
      :class="`animate-pulse rounded-lg bg-slate-100 ${itemClass}`"
      aria-hidden="true"
    />
  </div>
</template>