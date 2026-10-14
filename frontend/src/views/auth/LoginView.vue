<script setup lang="ts">
import { CalendarHeart, LogIn } from '@lucide/vue'
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  email: '',
  password: '',
  remember: false,
})

const loading = ref(false)
const serverError = ref<string | null>(null)

const redirectTarget = computed(() => {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/app'
})

async function submit(): Promise<void> {
  loading.value = true
  serverError.value = null
  try {
    await auth.login(form.email, form.password, form.remember)
    toast.success(`Welcome back, ${auth.user?.name?.split(' ')[0]}!`)
    void router.push(redirectTarget.value)
  } catch (error) {
    serverError.value = error instanceof Error ? error.message : 'Login failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <div class="mb-8 flex flex-col items-center text-center lg:hidden">
      <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary-600 text-white">
        <CalendarHeart class="h-6 w-6" />
      </span>
      <span class="mt-3 text-xl font-semibold tracking-tight">EventFlow</span>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-card">
      <h1 class="text-2xl font-semibold tracking-tight">Sign in</h1>
      <p class="mt-1 text-sm text-slate-500">Welcome back! Enter your credentials to continue.</p>

      <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
        <div v-if="serverError !== null" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {{ serverError }}
        </div>

        <div>
          <BaseInput v-model="form.email" label="Email address" type="email" placeholder="you@example.com" autocomplete="email" required />
        </div>
        <div>
          <BaseInput v-model="form.password" label="Password" type="password" placeholder="••••••••" autocomplete="current-password" required />
        </div>

        <div class="flex items-center justify-between">
          <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
            <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
            Remember me
          </label>
          <router-link :to="{ name: 'forgot-password' }" class="text-sm font-medium text-primary-600 hover:text-primary-700">
            Forgot password?
          </router-link>
        </div>

        <BaseButton type="submit" variant="primary" size="lg" block :loading="loading">
          <template v-if="!loading">
            <LogIn class="h-4 w-4" />
            Sign in
          </template>
          <template v-else>Signing in…</template>
        </BaseButton>
      </form>
    </div>

    <p class="mt-6 text-center text-sm text-slate-500">
      Don't have an account?
      <router-link :to="{ name: 'register' }" class="font-medium text-primary-600 hover:text-primary-700">
        Create one
      </router-link>
    </p>
  </AuthLayout>
</template>