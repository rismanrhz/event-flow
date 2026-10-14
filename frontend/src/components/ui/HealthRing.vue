<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    score: number | null
    grade?: string | null
    size?: number
    label?: string
    track?: string
  }>(),
  { grade: null, size: 132, label: 'Health score', track: '#f1f5f9' },
)

const RADIUS = 54
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

const normalized = computed(() => {
  if (props.score === null || props.score === undefined) return 0
  return Math.max(0, Math.min(100, props.score))
})

const ringColor = computed(() => {
  if (props.score === null || props.score === undefined) return '#cbd5e1'
  if (normalized.value >= 80) return '#10b981'
  if (normalized.value >= 55) return '#f59e0b'
  return '#ef4444'
})

const gradeText = computed(() => {
  if (props.grade) return props.grade
  if (props.score === null || props.score === undefined) return '—'
  if (normalized.value >= 80) return 'Healthy'
  if (normalized.value >= 55) return 'At risk'
  return 'Critical'
})

const gradeChip = computed(() => {
  const base = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold'
  if (props.score === null || props.score === undefined) return `${base} bg-slate-100 text-slate-500`
  if (normalized.value >= 80) return `${base} bg-emerald-50 text-emerald-600`
  if (normalized.value >= 55) return `${base} bg-amber-50 text-amber-600`
  return `${base} bg-red-50 text-red-600`
})

const scoreClass = computed(() => {
  if (props.size >= 120) return 'text-3xl'
  if (props.size >= 80) return 'text-xl'
  return 'text-sm'
})

const showCaption = computed(() => props.size >= 80)
</script>

<template>
  <div class="flex flex-col items-center gap-2">
    <div class="relative" :style="{ width: `${size}px`, height: `${size}px` }">
      <svg
        :width="size"
        :height="size"
        viewBox="0 0 120 120"
        role="img"
        :aria-label="`${label}: ${normalized} out of 100, ${gradeText}`"
      >
        <circle cx="60" cy="60" r="54" fill="none" :stroke="track" stroke-width="10" />
        <circle
          cx="60"
          cy="60"
          r="54"
          fill="none"
          :stroke="ringColor"
          stroke-width="10"
          stroke-linecap="round"
          :stroke-dasharray="CIRCUMFERENCE"
          :stroke-dashoffset="CIRCUMFERENCE * (1 - normalized / 100)"
          style="transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 0.8s cubic-bezier(0.16, 1, 0.3, 1), stroke 0.4s"
        />
      </svg>
      <div class="absolute inset-0 flex flex-col items-center justify-center">
        <span :class="['font-semibold tracking-tight text-slate-900', scoreClass]">{{ score ?? '—' }}</span>
        <span v-if="showCaption" class="text-[10px] font-medium uppercase tracking-wider text-slate-400">/ 100</span>
      </div>
    </div>
    <span :class="gradeChip">{{ gradeText }}</span>
  </div>
</template>