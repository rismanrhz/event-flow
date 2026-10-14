<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight, Clock, Loader2, MapPin, Pencil, Plus, QrCode, Search, Settings2, Trash2, Users, X } from '@lucide/vue'
import QRCode from 'qrcode'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import HealthRing from '@/components/ui/HealthRing.vue'
import { eventsApi } from '@/api/events'
import { dashboardApi } from '@/api/dashboard'
import { participantsApi } from '@/api/participants'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Event, EventStatusKey, HealthInfo, PaginationMeta, Participant } from '@/types'
import { canCreateEvent, canManageEvent, canManageMembers, canManageTasks, canViewMembers, formatDate, formatDateTime } from '@/utils/events'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const isOrganizer = computed(() => {
  const key = auth.user?.role.key
  return key === 'super_admin' || key === 'event_owner' || key === 'committee_member'
})

const healthById = ref<Map<number, HealthInfo>>(new Map())

const events = ref<Event[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const deletingId = ref<number | null>(null)
const search = ref('')
const statusFilter = ref<EventStatusKey | ''>('')
const page = ref(1)

const noResults = ref(false)

const registeredIds = ref<Set<number>>(new Set())
const registeringId = ref<number | null>(null)
const ticket = ref<Participant | null>(null)
const ticketQr = ref('')
const ticketLoading = ref(false)

watch([search, statusFilter], () => {
  page.value = 1
  void loadEvents()
})

watch(page, () => {
  void loadEvents()
})

async function loadEvents(): Promise<void> {
  loading.value = true
  noResults.value = false
  try {
    const { items, meta: resultMeta } = await eventsApi.list({
      page: page.value,
      per_page: 9,
      search: search.value,
      status: statusFilter.value === '' ? undefined : statusFilter.value,
    })
    events.value = items
    meta.value = resultMeta ?? null
    noResults.value = items.length === 0
    void refreshRegistrations(items)
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load events.')
  } finally {
    loading.value = false
  }
}

async function refreshRegistrations(items: Event[]): Promise<void> {
  if (isOrganizer.value) return
  const results = await Promise.allSettled(
    items.map(async (event) => {
      try {
        const mine = await participantsApi.mine(event.id)
        return mine
      } catch {
        return null
      }
    }),
  )
  registeredIds.value = new Set(
    results.filter((r) => r.status === 'fulfilled' && r.value !== null).map((r) => (r as PromiseFulfilledResult<Participant>).value.event_id),
  )
}

function goToNew(): void {
  void router.push({ name: 'app.events.new' })
}

function goToEdit(event: Event): void {
  void router.push({ name: 'app.events.edit', params: { id: String(event.id) } })
}

function goToManage(event: Event): void {
  void router.push({ name: 'app.events.manage', params: { id: String(event.id) } })
}

async function handleDelete(event: Event): Promise<void> {
  if (!window.confirm(`Delete "${event.title}"? This cannot be undone.`)) return

  deletingId.value = event.id
  try {
    await eventsApi.remove(event.id)
    toast.success('Event deleted successfully.')
    if (events.value.length === 1 && (meta.value?.current_page ?? 1) > 1) {
      page.value -= 1
    } else {
      await loadEvents()
    }
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to delete event.')
  } finally {
    deletingId.value = null
  }
}

function isRegistered(eventId: number): boolean {
  return registeredIds.value.has(eventId)
}

async function handleTicket(event: Event): Promise<void> {
  ticketLoading.value = true
  try {
    let mine: Participant
    try {
      mine = await participantsApi.mine(event.id)
    } catch {
      if (!window.confirm(`Register as a participant of "${event.title}"?`)) return
      registeringId.value = event.id
      mine = await participantsApi.register(event.id)
      registeredIds.value.add(event.id)
      toast.success('You are registered!')
    }
    ticket.value = mine
    ticketQr.value = ''
    QRCode.toDataURL(mine.registration_code, {
      margin: 1,
      width: 220,
      color: { dark: '#1e293b', light: '#ffffff' },
    })
      .then((url) => {
        ticketQr.value = url
      })
      .catch(() => {
        toast.error('Failed to render QR code.')
      })
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to manage registration.')
  } finally {
    ticketLoading.value = false
    registeringId.value = null
  }
}

function closeTicket(): void {
  ticket.value = null
  ticketQr.value = ''
}

async function loadHealth(): Promise<void> {
  if (!isOrganizer.value) return
  try {
    const overview = await dashboardApi.overview()
    if (overview.mode === 'organizer') {
      const next = new Map(healthById.value)
      for (const item of overview.events) {
        if (item.can_access_data) {
          next.set(item.value, item.health)
        }
      }
      healthById.value = next
    }
  } catch {
    // Health ring is best-effort; the list still renders without it.
  }
}

onMounted(() => {
  void loadEvents()
  void loadHealth()
})
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Events</h1>
        <p class="text-sm text-slate-500">Browse and manage the event catalog.</p>
      </div>
      <BaseButton v-if="canCreateEvent(auth.user)" @click="goToNew">
        <Plus class="h-4 w-4" />
        New event
      </BaseButton>
    </div>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
      <div class="relative flex-1">
        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="search"
          placeholder="Search events…"
          aria-label="Search events"
          class="input-base pl-10"
        />
      </div>
      <select
        v-model="statusFilter"
        class="input-base sm:w-48"
        aria-label="Filter by status"
      >
        <option value="">All statuses</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <div class="mt-8">
      <div v-if="loading" class="flex justify-center py-16">
        <Loader2 class="h-8 w-8 animate-spin text-primary-600" />
      </div>

      <div v-else-if="noResults" class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-300 py-16 text-center">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
          <CalendarDays class="h-6 w-6" />
        </span>
        <p class="text-sm text-slate-500">No events match your filters.</p>
      </div>

      <div v-else>
        <BaseCard v-if="ticket" :padded="false" class="mb-6 p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="rounded-xl border border-slate-200 bg-white p-3">
                <img v-if="ticketQr" :src="ticketQr" alt="Registration QR code" class="h-36 w-36" />
                <div v-else class="flex h-36 w-36 items-center justify-center">
                  <Loader2 class="h-6 w-6 animate-spin text-primary-600" />
                </div>
              </div>
              <div>
                <BaseBadge :tone="ticket.status.tone">{{ ticket.status.label }}</BaseBadge>
                <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ ticket.event?.title ?? 'Your ticket' }}</h3>
                <p class="mt-1 text-sm text-slate-500">Present this QR code at the venue to check in.</p>
                <p class="mt-3">
                  <code class="rounded bg-slate-100 px-2 py-1 font-mono text-sm tracking-widest text-slate-700">
                    {{ ticket.registration_code }}
                  </code>
                </p>
              </div>
            </div>
            <BaseButton variant="ghost" size="sm" @click="closeTicket">
              <X class="h-4 w-4" />
              Close
            </BaseButton>
          </div>
        </BaseCard>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
          <BaseCard v-for="event in events" :key="event.id" :padded="false" class="group flex flex-col overflow-hidden">
            <div class="relative h-40 w-full bg-slate-100">
              <img
                v-if="event.cover_url"
                :src="event.cover_url"
                :alt="event.title"
                class="h-full w-full object-cover"
              />
              <div v-else class="flex h-full w-full items-center justify-center">
                <CalendarDays class="h-10 w-10 text-slate-300" />
              </div>
              <div class="absolute left-3 top-3">
                <BaseBadge :tone="event.status.tone">{{ event.status.label }}</BaseBadge>
              </div>
              <div v-if="isOrganizer && healthById.has(event.id)" class="absolute right-3 top-3">
                <HealthRing
                  :score="healthById.get(event.id)?.score ?? null"
                  :grade="healthById.get(event.id)?.grade ?? null"
                  :size="54"
                  label="Event health score"
                />
              </div>
            </div>

            <div class="flex flex-1 flex-col gap-3 p-5">
              <div>
                <h3 class="text-base font-semibold leading-snug text-slate-900">{{ event.title }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">by {{ event.creator?.name ?? 'Unknown' }}</p>
              </div>

              <p class="line-clamp-2 text-sm leading-relaxed text-slate-600">
                {{ event.description || 'No description provided.' }}
              </p>

              <div class="mt-auto space-y-1.5 text-sm text-slate-500">
                <div class="flex items-center gap-2">
                  <Clock class="h-4 w-4 shrink-0 text-slate-400" />
                  <span>{{ formatDateTime(event.starts_at) }}</span>
                  <span v-if="event.ends_at">– {{ formatDate(event.ends_at) }}</span>
                </div>
                <div v-if="event.location" class="flex items-center gap-2">
                  <MapPin class="h-4 w-4 shrink-0 text-slate-400" />
                  <span class="truncate">{{ event.location }}</span>
                </div>
                <div v-if="event.capacity" class="flex items-center gap-2">
                  <Users class="h-4 w-4 shrink-0 text-slate-400" />
                  <span>{{ event.capacity.toLocaleString() }} capacity</span>
                </div>
              </div>

              <div
                v-if="
                  canManageEvent(auth.user, event) ||
                  canViewMembers(auth.user, event) ||
                  canManageTasks(auth.user, event) ||
                  event.status.key === 'published'
                "
                class="mt-2 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3"
              >
                <BaseButton
                  v-if="event.status.key === 'published'"
                  size="sm"
                  :loading="ticketLoading && registeringId === event.id"
                  @click="handleTicket(event)"
                >
                  <QrCode class="h-4 w-4" />
                  {{ isRegistered(event.id) ? 'View ticket' : 'Register' }}
                </BaseButton>
                <BaseButton v-if="canViewMembers(auth.user, event) || canManageTasks(auth.user, event)" size="sm" variant="secondary" @click="goToManage(event)">
                  <Settings2 class="h-4 w-4" />
                  Manage
                </BaseButton>
                <BaseButton v-if="canManageEvent(auth.user, event)" size="sm" variant="secondary" @click="goToEdit(event)">
                  <Pencil class="h-4 w-4" />
                  Edit
                </BaseButton>
                <BaseButton v-if="canManageMembers(auth.user, event)" size="sm" variant="danger" :loading="deletingId === event.id" @click="handleDelete(event)">
                  <Trash2 class="h-4 w-4" />
                  Delete
                </BaseButton>
              </div>
            </div>
          </BaseCard>
        </div>
      </div>

      <div v-if="!loading && (meta?.last_page ?? 1) > 1" class="mt-8 flex items-center justify-between">
        <BaseButton variant="secondary" size="sm" :disabled="page <= 1" @click="page -= 1">
          <ChevronLeft class="h-4 w-4" />
          Previous
        </BaseButton>
        <p class="text-sm text-slate-500">
          Page {{ meta?.current_page ?? 1 }} of {{ meta?.last_page ?? 1 }}
        </p>
        <BaseButton variant="secondary" size="sm" :disabled="page >= (meta?.last_page ?? 1)" @click="page += 1">
          Next
          <ChevronRight class="h-4 w-4" />
        </BaseButton>
      </div>
    </div>
  </div>
</template>