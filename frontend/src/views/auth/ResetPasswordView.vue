<script setup lang="ts">
import { KeyRound } from '@lucide/vue'
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/client'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const token = typeof route.query.token === 'string' ? route.query.token : ''
const email = typeof route.query.email === 'string' ? route.query.email : ''

const form = reactive({
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
    await authApi.resetPassword({
      email,
      token,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    await auth.login(email, form.password)
    toast.success('Password reset successfully. You are now signed in.')
    void router.push({ name: 'app.dashboard' })
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      serverErrors.value = error.errors
    }
    generalError.value =
      error instanceof Error ? error.message : 'Unable to reset your password. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <div v-if="token === '' || email === ''" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-card">
      <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-100 text-red-700">
        <KeyRound class="h-6 w-6" />
      </span>
      <h1 class="mt-4 text-2xl font-semibold tracking-tight">Invalid reset link</h1>
      <p class="mt-2 text-sm text-slate-500">
        This password reset link is incomplete or has expired. Please request a new one.
      </p>
      <router-link :to="{ name: 'forgot-password' }" class="mt-6 inline-block text-sm font-medium text-primary-600 hover:text-primary-700">
        Request a new link
      </router-link>
    </div>

    <div v-else class="rounded-2xl border border-slate-200 bg-white p-8 shadow-card">
      <div class="text-center">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary-100 text-primary-700">
          <KeyRound class="h-6 w-6" />
        </span>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Set a new password</h1>
        <p class="mt-2 text-sm text-slate-500">
          Choose a strong password for <span class="font-medium text-slate-700">{{ email }}</span>.
        </p>
      </div>

      <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
        <div v-if="generalError !== null" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {{ generalError }}
        </div>
        <div>
          <BaseInput v-model="form.password" label="New password" type="password" placeholder="••••••••" autocomplete="new-password" :error="fieldError('password')" required />
        </div>
        <div>
          <BaseInput v-model="form.password_confirmation" label="Confirm new password" type="password" placeholder="••••••••" autocomplete="new-password" :error="fieldError('password_confirmation')" required />
        </div>

        <BaseButton type="submit" variant="primary" size="lg" block :loading="loading">
          <template v-if="!loading">Reset password</template>
          <template v-else>Resetting…</template>
        </BaseButton>
      </form>
    </div>
  </AuthLayout>
</template>