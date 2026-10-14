<script setup lang="ts">
import { ArrowLeft, Mail } from '@lucide/vue'
import { reactive, ref } from 'vue'
import { authApi } from '@/api/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const form = reactive({ email: '' })
const loading = ref(false)
const sentInfo = ref<{ email: string; resetUrl: string | null | undefined } | null>(null)
const errorMessage = ref<string | null>(null)

async function submit(): Promise<void> {
  loading.value = true
  errorMessage.value = null
  try {
    const result = await authApi.forgotPassword(form.email)
    sentInfo.value = { email: form.email, resetUrl: result?.reset_url }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Something went wrong.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-card">
      <div v-if="sentInfo === null" class="text-center">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary-100 text-primary-700">
          <Mail class="h-6 w-6" />
        </span>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Forgot your password?</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
          Enter your email address and we'll send you a link to reset your password.
        </p>
      </div>

      <div v-else class="text-center">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
          <Mail class="h-6 w-6" />
        </span>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Check your inbox</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
          If an account exists for <span class="font-medium text-slate-700">{{ sentInfo.email }}</span>,
          a password reset link has been sent.
        </p>
        <a
          v-if="sentInfo.resetUrl"
          :href="sentInfo.resetUrl"
          class="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700"
        >
          Open the reset link
        </a>
      </div>

      <form v-if="sentInfo === null" class="mt-8 space-y-5" novalidate @submit.prevent="submit">
        <div v-if="errorMessage !== null" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {{ errorMessage }}
        </div>
        <BaseInput v-model="form.email" label="Email address" type="email" placeholder="you@example.com" autocomplete="email" required />
        <BaseButton type="submit" variant="primary" size="lg" block :loading="loading">
          <template v-if="!loading">Send reset link</template>
          <template v-else>Sending…</template>
        </BaseButton>
      </form>

      <div v-if="sentInfo !== null" class="mt-6 text-center">
        <BaseButton variant="secondary" size="md" @click="sentInfo = null">
          Send to a different email
        </BaseButton>
      </div>
    </div>

    <BaseCard class="mt-6 !p-4 sm:hidden">
      <router-link :to="{ name: 'login' }" class="flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700">
        <ArrowLeft class="h-4 w-4" />
        Back to sign in
      </router-link>
    </BaseCard>

    <p class="mt-6 text-center text-sm text-slate-500">
      <router-link :to="{ name: 'login' }" class="inline-flex items-center gap-1.5 font-medium text-primary-600 hover:text-primary-700">
        <ArrowLeft class="h-4 w-4" />
        Back to sign in
      </router-link>
    </p>
  </AuthLayout>
</template>