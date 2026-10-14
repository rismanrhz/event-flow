<script setup lang="ts">
import { ArrowLeft, CalendarPlus, ImagePlus, Loader2 } from '@lucide/vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi } from '@/api/events'
import { ApiError } from '@/api/client'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { EventInput, EventStatusKey } from '@/types'
import { canManageEvent } from '@/utils/events'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const isEdit = computed(() => route.name === 'app.events.edit')
const eventId = computed(() => (route.params.id ? Number(route.params.id) : null))

const form = reactive({
  title: '',
  description: '',
  location: '',
  starts_at: '',
  ends_at: '',
  capacity: '',
  status: 'draft' as EventStatusKey,
})

const coverFile = ref<File | null>(null)
const coverPreview = ref<string | null>(null)
const loading = ref(isEdit.value)
const saving = ref(false)
const serverErrors = ref<Record<string, string[]>>({})

function fieldError(field: string): string {
  return serverErrors.value[field]?.[0] ?? ''
}

function toDatetimeLocal(iso: string): string {
  const date = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
    date.getHours(),
  )}:${pad(date.getMinutes())}`
}

async function loadEvent(): Promise<void> {
  if (!isEdit.value || eventId.value === null) return

  loading.value = true
  try {
    const event = await eventsApi.get(eventId.value)

    if (!canManageEvent(auth.user, event)) {
      toast.error("You don't have permission to edit this event.")
      void router.replace({ name: 'app.events' })
      return
    }

    form.title = event.title
    form.description = event.description ?? ''
    form.location = event.location ?? ''
    form.starts_at = toDatetimeLocal(event.starts_at)
    form.ends_at = event.ends_at ? toDatetimeLocal(event.ends_at) : ''
    form.capacity = event.capacity !== null ? String(event.capacity) : ''
    form.status = event.status.key
    coverPreview.value = event.cover_url
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load event.')
    void router.replace({ name: 'app.events' })
  } finally {
    loading.value = false
  }
}

function handleCoverChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  if (!file) return
  if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)) {
    toast.error('Cover image must be a JPEG, PNG or WebP file.')
    input.value = ''
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Cover image must not be larger than 2 MB.')
    input.value = ''
    return
  }

  coverFile.value = file
  if (coverPreview.value) {
    URL.revokeObjectURL(coverPreview.value)
  }
  coverPreview.value = URL.createObjectURL(file)
}

function removeCover(): void {
  coverFile.value = null
  coverPreview.value = null
}

async function handleSubmit(): Promise<void> {
  saving.value = true
  serverErrors.value = {}

  const input: EventInput = {
    title: form.title,
    description: form.description || undefined,
    location: form.location || undefined,
    starts_at: form.starts_at || undefined,
    ends_at: form.ends_at || undefined,
    capacity: form.capacity === '' ? undefined : Number(form.capacity),
    status: form.status,
    cover: coverFile.value,
  }

  try {
    if (isEdit.value && eventId.value !== null) {
      await eventsApi.update(eventId.value, input)
      toast.success('Event updated successfully.')
    } else {
      await eventsApi.create(input)
      toast.success('Event created successfully.')
    }
    void router.push({ name: 'app.events' })
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      serverErrors.value = error.errors
    } else {
      toast.error(error instanceof Error ? error.message : 'Failed to save event.')
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void loadEvent()
})
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
        aria-label="Back to events"
        @click="router.push({ name: 'app.events' })"
      >
        <ArrowLeft class="h-5 w-5" />
      </button>
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Edit event' : 'New event' }}</h1>
        <p class="text-sm text-slate-500">
          {{ isEdit ? 'Update the details of your event.' : 'Create a new event and share it with participants.' }}
        </p>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <Loader2 class="h-8 w-8 animate-spin text-primary-600" />
    </div>

    <BaseCard v-else class="mt-6">
      <form class="space-y-5" @submit.prevent="handleSubmit">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Cover image</label>
          <div v-if="coverPreview" class="relative flex flex-col items-start gap-3">
            <img :src="coverPreview" alt="Event cover preview" class="h-44 w-full rounded-xl object-cover" />
            <BaseButton type="button" variant="secondary" size="sm" @click="removeCover">
              Remove cover
            </BaseButton>
          </div>
          <label
            v-else
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center transition-colors hover:border-primary-400 hover:bg-primary-50/50"
          >
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary-600">
              <ImagePlus class="h-5 w-5" />
            </span>
            <span class="text-sm font-medium text-slate-700">Upload a cover image</span>
            <span class="text-xs text-slate-500">JPEG, PNG or WebP up to 2 MB</span>
            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="handleCoverChange" />
          </label>
        </div>

        <BaseInput
          v-model="form.title"
          label="Title"
          required
          placeholder="e.g. Tech Conference 2026"
          :error="fieldError('title')"
        />

        <div>
          <label for="event-description" class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
          <textarea
            id="event-description"
            v-model="form.description"
            rows="4"
            placeholder="What is this event about?"
            class="input-base resize-y"
          />
          <p v-if="fieldError('description')" class="mt-1.5 text-sm text-red-600">{{ fieldError('description') }}</p>
        </div>

        <BaseInput
          v-model="form.location"
          label="Location"
          placeholder="e.g. Jakarta Convention Center"
          :error="fieldError('location')"
        />

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <BaseInput
            v-model="form.starts_at"
            label="Starts at"
            required
            type="datetime-local"
            :error="fieldError('starts_at')"
          />
          <BaseInput
            v-model="form.ends_at"
            label="Ends at"
            type="datetime-local"
            :error="fieldError('ends_at')"
          />
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <BaseInput
            v-model="form.capacity"
            label="Capacity"
            type="number"
            min="1"
            placeholder="e.g. 500"
            :error="fieldError('capacity')"
          />
          <div>
            <label for="event-status" class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
            <select id="event-status" v-model="form.status" class="input-base">
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <p v-if="fieldError('status')" class="mt-1.5 text-sm text-red-600">{{ fieldError('status') }}</p>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
          <BaseButton type="button" variant="ghost" @click="router.push({ name: 'app.events' })">
            Cancel
          </BaseButton>
          <BaseButton type="submit" :loading="saving">
            <CalendarPlus class="h-4 w-4" />
            {{ isEdit ? 'Save changes' : 'Create event' }}
          </BaseButton>
        </div>
      </form>
    </BaseCard>
  </div>
</template>