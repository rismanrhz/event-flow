<script setup lang="ts">
import { CircleCheck, Info, TriangleAlert, X } from '@lucide/vue'
import type { Component } from 'vue'
import { useToastStore } from '@/stores/toast'
import type { ToastType } from '@/stores/toast'

const toast = useToastStore()

const iconByType: Record<ToastType, Component> = {
  success: CircleCheck,
  error: TriangleAlert,
  info: Info,
}

const iconTone: Record<ToastType, string> = {
  success: 'text-emerald-600',
  error: 'text-red-600',
  info: 'text-primary-600',
}
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6"
  >
    <TransitionGroup name="toast" tag="div" class="flex w-full max-w-sm flex-col gap-2">
      <div
        v-for="item in toast.toasts"
        :key="item.id"
        class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-card"
      >
        <component
          :is="iconByType[item.type]"
          :class="['mt-0.5 h-5 w-5 shrink-0', iconTone[item.type]]"
        />
        <p class="flex-1 text-sm leading-snug text-slate-700">{{ item.message }}</p>
        <button
          type="button"
          class="text-slate-400 transition-colors hover:text-slate-600"
          aria-label="Dismiss notification"
          @click="toast.remove(item.id)"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>