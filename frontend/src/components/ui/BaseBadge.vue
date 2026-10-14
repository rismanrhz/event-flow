<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'primary'
  }>(),
  {
    tone: 'neutral',
  },
)

const classes = computed(() => {
  const tones: Record<NonNullable<typeof props.tone>, string> = {
    neutral: 'bg-slate-100 text-slate-700',
    success: 'bg-emerald-100 text-emerald-700',
    warning: 'bg-amber-100 text-amber-700',
    danger: 'bg-red-100 text-red-700',
    primary: 'bg-primary-100 text-primary-700',
  }
  return `inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${tones[props.tone]}`
})
</script>

<template>
  <span :class="classes"><slot /></span>
</template>