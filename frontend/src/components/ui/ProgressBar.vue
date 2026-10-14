<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    value: number
    tone?: 'primary' | 'teal' | 'success' | 'warning' | 'danger'
    suffix?: string
    showLabel?: boolean
  }>(),
  { tone: 'primary', suffix: '%', showLabel: true },
)

const barColors: Record<string, string> = {
  primary: 'bg-primary-600',
  teal: 'bg-secondary-500',
  success: 'bg-emerald-500',
  warning: 'bg-amber-500',
  danger: 'bg-red-500',
}

const clamped = computed(() => Math.max(0, Math.min(100, props.value)))
</script>

<template>
  <div class="flex items-center gap-3">
    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
      <div
        :class="barColors[tone]"
        class="h-full rounded-full transition-all duration-700"
        :style="{ width: `${clamped}%` }"
        role="progressbar"
        :aria-valuenow="clamped"
        aria-valuemin="0"
        aria-valuemax="100"
      />
    </div>
    <span
      v-if="showLabel"
      class="w-12 shrink-0 text-right text-xs font-medium tabular-nums text-slate-500"
    >
      {{ Math.round(clamped) }}{{ suffix }}
    </span>
  </div>
</template>