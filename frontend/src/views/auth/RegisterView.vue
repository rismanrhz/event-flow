<script setup lang="ts">
import { CalendarHeart, UserPlus } from '@lucide/vue'
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '@/api/client'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const loading = ref(false)
const serverErrors = ref<Record<string, string[]>>({})
const generalError = ref<string | null>(null)

function fieldError(field: string): string {
  return serverErrors.value[field]?.[0] ?? ''
}

async function submit(): Promise<void> {
  loading.value = true
  serverErrors.value = {}
  generalError.value = null
  try {
    await auth.register({ ...form })
    toast.success('Account created. Welcome to EventFlow!')
    void router.push({ name: 'app.dashboard' })
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      serverErrors.value = error.errors
      generalError.value = error.message
    } else {
      generalError.value = error instanceof Error ? error.message : 'Registration failed. Please try again.'
    }
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
      <h1 class="text-2xl font-semibold tracking-tight">Create your account</h1>
      <p class="mt-1 text-sm text-slate-500">Join EventFlow and start organizing events.</p>

      <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
        <div v-if="generalError !== null" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {{ generalError }}
        </div>

        <div>
          <BaseInput v-model="form.name" label="Full name" placeholder="Jane Doe" autocomplete="name" :error="fieldError('name')" required />
        </div>
        <div>
          <BaseInput v-model="form.email" label="Email address" type="email" placeholder="you@example.com" autocomplete="email" :error="fieldError('email')" required />
        </div>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <BaseInput v-model="form.password" label="Password" type="password" placeholder="••••••••" autocomplete="new-password" :error="fieldError('password')" required />
          <BaseInput v-model="form.password_confirmation" label="Confirm password" type="password" placeholder="••••••••" autocomplete="new-password" :error="fieldError('password_confirmation')" required />
        </div>

        <p class="text-xs text-slate-500">
          Password must be at least 8 characters and include letters and numbers.
        </p>

        <BaseButton type="submit" variant="primary" size="lg" block :loading="loading">
          <template v-if="!loading">
            <UserPlus class="h-4 w-4" />
            Create account
          </template>
          <template v-else>Creating account…</template>
        </BaseButton>
      </form>
    </div>

    <p class="mt-6 text-center text-sm text-slate-500">
      Already have an account?
      <router-link :to="{ name: 'login' }" class="font-medium text-primary-600 hover:text-primary-700">
        Sign in
      </router-link>
    </p>
  </AuthLayout>
</template>