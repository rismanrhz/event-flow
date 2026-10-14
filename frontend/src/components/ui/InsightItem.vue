<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, Info, Sparkles, TriangleAlert } from '@lucide/vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import type { Insight } from '@/types'

const props = defineProps<{
  insight: Insight
  eventId?: number
}>()

const meta = computed(() => {
  switch (props.insight.severity) {
    case 'critical':
      return { icon: AlertCircle, ring: 'bg-red-50 text-red-600' }
    case 'warning':
      return { icon: TriangleAlert, ring: 'bg-amber-50 text-amber-600' }
    case 'positive':
      return { icon: Sparkles, ring: 'bg-emerald-50 text-emerald-600' }
    default:
      return { icon: Info, ring: 'bg-slate-100 text-slate-500' }
  }
})

const targetTo = computed(() => {
  const action = props.insight.action
  if (!action || !props.eventId) return null
  if (action.target === 'event') return `/app/events/${props.eventId}/edit`
  const tab = action.target === 'participants' ? 'participants' : 'tasks'
  const status = action.params?.status
  return `/app/events/${props.eventId}/manage?tab=${tab}${status ? `&status=${status}` : ''}`
})
</script>

<template>
  <li class="flex gap-3">
    <span :class="['mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg', meta.ring]">
      <component :is="meta.icon" class="h-4 w-4" />
    </span>
    <div class="min-w-0 flex-1">
      <p class="text-sm text-slate-700">{{ insight.message }}</p>
      <div class="mt-1.5 flex flex-wrap items-center gap-2">
        <BaseBadge :tone="insight.tone">{{ insight.label }}</BaseBadge>
        <router-link
          v-if="targetTo"
          :to="targetTo"
          class="text-xs font-semibold text-primary-600 hover:text-primary-700"
        >
          {{ insight.action?.label ?? 'View' }} →
        </router-link>
      </div>
    </div>
  </li>
</template>