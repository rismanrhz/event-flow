import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  type: ToastType
  message: string
}

let nextId = 1

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  function remove(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  function push(type: ToastType, message: string): void {
    const id = nextId++
    toasts.value.push({ id, type, message })
    window.setTimeout(() => remove(id), 4500)
  }

  const success = (message: string) => push('success', message)
  const error = (message: string) => push('error', message)
  const info = (message: string) => push('info', message)

  return { toasts, push, remove, success, error, info }
})