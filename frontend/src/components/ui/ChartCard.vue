<script setup lang="ts">
import BaseCard from '@/components/ui/BaseCard.vue'
import SectionHeading from '@/components/ui/SectionHeading.vue'
import Skeleton from '@/components/ui/Skeleton.vue'

defineProps<{
  title: string
  subtitle?: string
  loading?: boolean
  empty?: boolean
  compact?: boolean
}>()
</script>

<template>
  <BaseCard :padded="false" class="flex h-full flex-col overflow-hidden">
    <div :class="['border-b border-slate-100', compact ? 'px-5 py-4' : 'px-6 py-5']">
      <SectionHeading :title="title" :subtitle="subtitle">
        <template v-if="$slots.actions" #actions><slot name="actions" /></template>
      </SectionHeading>
    </div>

    <div class="min-h-0 flex-1 p-5">
      <Skeleton v-if="loading" :lines="3" class="h-full min-h-40" />
      <div
        v-else-if="empty"
        class="flex h-full min-h-40 items-center justify-center rounded-xl bg-slate-50"
      >
        <p class="px-6 text-center text-sm text-slate-500">No data available yet.</p>
      </div>
      <slot v-else />
    </div>
  </BaseCard>
</template>