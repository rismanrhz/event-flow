<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import { ArrowDownRight, ArrowUpRight, Lock } from '@lucide/vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import { useCountUp } from '@/composables/useCountUp'

const props = withDefaults(
  defineProps<{
    label: string
    icon?: Component
    tone?: 'primary' | 'teal' | 'amber' | 'emerald' | 'red' | 'violet' | 'slate'
    accent?: string
    value?: number | null
    suffix?: string
    delta?: number | null
    deltaLabel?: string
    hint?: string
    loading?: boolean
    unavailable?: boolean
    unavailableMessage?: string
    link?: { to: string; label: string }
  }>(),
  {
    tone: 'primary',
    value: null,
    suffix: '',
    delta: null,
    deltaLabel: 'pts',
    loading: false,
    unavailable: false,
  },
)

const tones: Record<string, string> = {
  primary: 'bg-primary-50 text-primary-600',
  teal: 'bg-secondary-50 text-secondary-600',
  amber: 'bg-amber-50 text-amber-600',
  emerald: 'bg-emerald-50 text-emerald-600',
  red: 'bg-red-50 text-red-600',
  violet: 'bg-violet-50 text-violet-600',
  slate: 'bg-slate-100 text-slate-500',
}

const isNumeric = computed(
  () => props.value !== null && props.value !== undefined && !Number.isNaN(props.value),
)

const displayed = useCountUp(() => (isNumeric.value ? (props.value as number) : 0))

const displayValue = computed(() => {
  if (!isNumeric.value) return '—'
  if (props.loading) return '…'
  return String(Math.round(displayed.value))
})

const deltaTone = computed(() => {
  if (props.delta === null || props.delta === undefined || props.delta === 0) return 'neutral'
  return props.delta > 0 ? 'positive' : 'negative'
})

const deltaRecord = computed(() => {
  if (props.delta === null || props.delta === undefined) return null
  return {
    label: `${props.delta > 0 ? '+' : '−'}${Math.abs(props.delta)}${props.deltaLabel}`,
    positive: props.delta > 0,
  }
})
</script>

<template>
  <BaseCard
    :padded="false"
    :class="[unavailable ? 'opacity-70' : '']"
    class="relative flex flex-col gap-3 overflow-hidden p-5"
  >
    <span v-if="accent" :class="['absolute inset-x-0 top-0 h-1 bg-gradient-to-r', accent]" aria-hidden="true" />
    <span
      :class="[
        'inline-flex h-10 w-10 items-center justify-center rounded-xl',
        tones[tone],
      ]"
    >
      <component :is="icon" v-if="icon" class="h-5 w-5" />
    </span>

    <div class="flex items-end justify-between gap-2">
      <p class="text-3xl font-semibold leading-none tracking-tight text-slate-900">
        {{ displayValue }}<template v-if="suffix && displayValue !== '—'">{{ suffix }}</template>
      </p>

      <span
        v-if="deltaRecord"
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="{
          'bg-emerald-50 text-emerald-600': deltaRecord.positive && deltaTone === 'positive',
          'bg-red-50 text-red-600': !deltaRecord.positive && deltaTone === 'negative',
          'bg-slate-100 text-slate-500': deltaTone === 'neutral',
        }"
      >
        <ArrowUpRight v-if="deltaRecord.positive" class="h-3 w-3" />
        <ArrowDownRight v-else class="h-3 w-3" />
        {{ deltaRecord.label }}
      </span>
    </div>

    <div class="flex items-center justify-between gap-2">
      <p class="text-sm font-medium text-slate-500">{{ label }}</p>
      <router-link
        v-if="!unavailable && link"
        :to="link.to"
        class="text-xs font-medium text-primary-600 hover:text-primary-700"
      >
        {{ link.label }}
      </router-link>
    </div>

    <p v-if="hint && !unavailable" class="text-xs leading-relaxed text-slate-400">{{ hint }}</p>

    <div
      v-if="unavailable"
      class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-500"
    >
      <Lock class="h-3.5 w-3.5" />
      {{ unavailableMessage ?? 'Unavailable' }}
    </div>
  </BaseCard>
</template>