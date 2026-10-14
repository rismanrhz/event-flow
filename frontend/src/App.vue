<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { UNAUTHORIZED_EVENT } from '@/api/client'
import ToastStack from '@/components/ui/ToastStack.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

onMounted(() => {
  window.addEventListener(UNAUTHORIZED_EVENT, () => {
    auth.clear()
    if (router.currentRoute.value.meta.requiresAuth) {
      void router.push({ name: 'login' })
    }
  })
})
</script>

<template>
  <router-view />
  <ToastStack />
</template>