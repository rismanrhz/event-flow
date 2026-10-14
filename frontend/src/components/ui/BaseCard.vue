<script setup lang="ts">
withDefaults(
  defineProps<{
    padded?: boolean
  }>(),
  {
    padded: true,
  },
)
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-surface shadow-card" :class="padded ? 'p-6' : ''">
    <slot />
  </div>
</template>