<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { authApi } from '@/api/auth'
import type { ChangePasswordInput } from '@/api/auth'
import { ApiError } from '@/api/client'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const auth = useAuthStore()
const toast = useToastStore()

const form = reactive({
  name: auth.user?.name ?? '',
  email: auth.user?.email ?? '',
  phone: auth.user?.phone ?? '',
  institution: auth.user?.institution ?? '',
  job_title: auth.user?.job_title ?? '',
  address: auth.user?.address ?? '',
})

const passwordForm = reactive<ChangePasswordInput>({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const saving = ref(false)
const savingPassword = ref(false)
const serverErrors = ref<Record<string, string[]>>({})
const passwordErrors = ref<Record<string, string[]>>({})

const initials = computed(() => {
  const name = form.name || auth.user?.name || 'U'
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
})

function fieldError(errors: Record<string, string[]>, field: string): string {
  return errors[field]?.[0] ?? ''
}

async function saveProfile(): Promise<void> {
  saving.value = true
  serverErrors.value = {}
  try {
    const updated = await authApi.updateProfile({ ...form })
    await auth.setUser(updated)
    toast.success('Profile updated successfully.')
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      serverErrors.value = error.errors
    } else {
      toast.error(error instanceof Error ? error.message : 'Failed to update profile.')
    }
  } finally {
    saving.value = false
  }
}

async function savePassword(): Promise<void> {
  savingPassword.value = true
  passwordErrors.value = {}
  try {
    await authApi.changePassword({ ...passwordForm })
    passwordForm.current_password = ''
    passwordForm.new_password = ''
    passwordForm.new_password_confirmation = ''
    toast.success('Password changed successfully.')
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      passwordErrors.value = error.errors
    } else {
      toast.error(error instanceof Error ? error.message : 'Failed to change password.')
    }
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Profile</h1>
      <p class="text-sm text-slate-500">Manage your account information and password.</p>
    </div>

    <BaseCard>
      <form class="space-y-6" novalidate @submit.prevent="saveProfile">
        <div class="flex items-center gap-4">
          <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-lg font-semibold text-primary-700">
            {{ initials }}
          </div>
          <div>
            <p class="text-sm font-medium text-slate-900">{{ auth.user?.role.label }}</p>
            <p class="text-sm text-slate-500">{{ auth.user?.email }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <BaseInput v-model="form.name" label="Full name" autocomplete="name" :error="fieldError(serverErrors, 'name')" required />
          <BaseInput v-model="form.email" label="Email address" type="email" autocomplete="email" :error="fieldError(serverErrors, 'email')" required />
          <BaseInput v-model="form.phone" label="Phone" type="tel" autocomplete="tel" :error="fieldError(serverErrors, 'phone')" />
          <BaseInput v-model="form.institution" label="Institution / Company" :error="fieldError(serverErrors, 'institution')" />
          <BaseInput v-model="form.job_title" label="Job title" :error="fieldError(serverErrors, 'job_title')" />
          <BaseInput v-model="form.address" label="Address" :error="fieldError(serverErrors, 'address')" />
        </div>

        <div class="flex justify-end">
          <BaseButton type="submit" :loading="saving">
            <template v-if="!saving">Save changes</template>
            <template v-else>Saving…</template>
          </BaseButton>
        </div>
      </form>
    </BaseCard>

    <BaseCard>
      <h2 class="text-lg font-semibold">Change password</h2>
      <p class="mt-1 text-sm text-slate-500">
        You'll be signed out everywhere else after changing your password.
      </p>
      <form class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3" novalidate @submit.prevent="savePassword">
        <BaseInput
          v-model="passwordForm.current_password"
          label="Current password"
          type="password"
          autocomplete="current-password"
          :error="fieldError(passwordErrors, 'current_password')"
          required
        />
        <BaseInput
          v-model="passwordForm.new_password"
          label="New password"
          type="password"
          autocomplete="new-password"
          :error="fieldError(passwordErrors, 'new_password')"
          required
        />
        <BaseInput
          v-model="passwordForm.new_password_confirmation"
          label="Confirm new password"
          type="password"
          autocomplete="new-password"
          :error="fieldError(passwordErrors, 'new_password_confirmation')"
          required
        />
        <div class="flex justify-end sm:col-span-3">
          <BaseButton type="submit" variant="secondary" :loading="savingPassword">
            <template v-if="!savingPassword">Change password</template>
            <template v-else>Changing…</template>
          </BaseButton>
        </div>
      </form>
    </BaseCard>
  </div>
</template>