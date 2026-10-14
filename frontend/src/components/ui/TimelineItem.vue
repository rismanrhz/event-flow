<script setup lang="ts">
import type { Component } from 'vue'
import { CalendarClock } from '@lucide/vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { formatDateTime } from '@/utils/events'

withDefaults(
  defineProps<{
    icon?: Component
    iconTone?: string
    title: string
    description?: string
    at?: string | null
    badge?: { label: string; tone: 'neutral' | 'success' | 'warning' | 'danger' | 'primary' } | null
    to?: string
  }>(),
  { iconTone: 'bg-primary-50 text-primary-600', badge: null },
)
</script>

<template>
  <li class="relative flex gap-3">
    <span :class="['mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', iconTone]">
      <component :is="icon ?? CalendarClock" class="h-5 w-5" />
    </span>
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <router-link
          v-if="to"
          :to="to"
          class="truncate text-sm font-semibold text-slate-900 hover:text-primary-600"
        >
          {{ title }}
        </router-link>
        <p v-else class="truncate text-sm font-semibold text-slate-900">{{ title }}</p>
        <BaseBadge v-if="badge" :tone="badge.tone">{{ badge.label }}</BaseBadge>
      </div>
      <p v-if="description" class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ description }}</p>
      <p v-if="at" class="mt-1 inline-flex items-center gap-1 text-xs text-slate-400">
        <CalendarClock class="h-3 w-3" />
        {{ formatDateTime(at) }}
      </p>
    </div>
  </li>
</template>