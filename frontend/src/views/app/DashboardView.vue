<script setup lang="ts">
import type { EChartsOption } from 'echarts'
import {
  Activity,
  AlarmClock,
  ArrowRight,
  CalendarDays,
  CheckCircle2,
  Clock,
  ListChecks,
  MapPin,
  Sparkles,
  Ticket,
  TriangleAlert,
  UserCheck,
  Users,
  Wallet,
} from '@lucide/vue'
import { computed, nextTick, onMounted, ref } from 'vue'
import { dashboardApi } from '@/api/dashboard'
import AppError from '@/components/ui/AppError.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseChart from '@/components/ui/BaseChart.vue'
import ChartCard from '@/components/ui/ChartCard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import HealthRing from '@/components/ui/HealthRing.vue'
import InsightItem from '@/components/ui/InsightItem.vue'
import MetricCard from '@/components/ui/MetricCard.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import TimelineItem from '@/components/ui/TimelineItem.vue'
import { useReveal } from '@/composables/useReveal'
import { useAuthStore } from '@/stores/auth'
import type {
  DashboardOrganizerData,
  DashboardOverview,
  DashboardParticipantData,
  EventSelectorItem,
  HealthFactor,
} from '@/types'
import {
  attendanceHoursOption,
  funnelOption,
  statusDonutOption,
  taskPriorityOption,
  trendOption,
} from '@/utils/charts'
import { formatDateTime } from '@/utils/events'

type BadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'primary'

const auth = useAuthStore()

const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? 'there')

const overview = ref<DashboardOverview | null>(null)
const loading = ref(true)
const switching = ref(false)
const errorMsg = ref<string | null>(null)
const selectedEventId = ref<number | null>(null)

const revealRoot = ref<HTMLElement | null>(null)
const { refresh: refreshReveal } = useReveal(revealRoot)

const org = computed<DashboardOrganizerData | null>(() =>
  overview.value?.mode === 'organizer' ? overview.value : null,
)
const part = computed<DashboardParticipantData | null>(() =>
  overview.value?.mode === 'participant' ? overview.value : null,
)

const selectorEvents = computed<EventSelectorItem[]>(() =>
  (org.value ?? part.value)?.events.filter((e) => e.can_access_data) ?? [],
)

async function load(keepData = false): Promise<void> {
  if (!keepData) {
    loading.value = true
  }
  errorMsg.value = null
  try {
    overview.value = await dashboardApi.overview(selectedEventId.value ?? undefined)
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : 'Failed to load dashboard.'
    if (!keepData) {
      overview.value = null
    }
  } finally {
    loading.value = false
    switching.value = false
    void nextTick(() => refreshReveal())
  }
}

async function onSelect(eventId: number | string): Promise<void> {
  const id = Number(eventId)
  if (id === selectedEventId.value) return
  selectedEventId.value = id
  switching.value = true
  await load(true)
}

onMounted(() => {
  void load()
})

const activity = computed(() => org.value?.kpi.activity ?? null)
const attendance = computed(() => org.value?.kpi.attendance ?? null)
const completion = computed(() => org.value?.kpi.task_completion ?? null)
const overdue = computed(() => org.value?.kpi.overdue ?? null)

const attentionItems = computed(() => org.value?.attention ?? [])
const hasCriticalAttention = computed(() => attentionItems.value.some((i) => i.severity === 'critical'))
const goodNews = computed(() =>
  (org.value?.insights ?? []).filter((i) => i.severity !== 'warning' && i.severity !== 'critical'),
)

const trendChartOption = computed<EChartsOption | null>(() =>
  org.value?.trend ? trendOption(org.value.trend) : null,
)
const funnelChartOption = computed<EChartsOption | null>(() =>
  org.value?.funnel ? funnelOption(org.value.funnel) : null,
)
const hoursChartOption = computed<EChartsOption | null>(() =>
  org.value?.attendance ? attendanceHoursOption(org.value.attendance) : null,
)
const taskStatusChartOption = computed<EChartsOption | null>(() =>
  org.value?.tasks ? statusDonutOption('Tasks', org.value.tasks.by_status) : null,
)
const taskPriorityChartOption = computed<EChartsOption | null>(() =>
  org.value?.tasks ? taskPriorityOption(org.value.tasks) : null,
)

const health = computed(() => org.value?.health ?? null)
const healthFactors = computed<HealthFactor[]>(() => org.value?.health?.factors ?? [])
const scheduleItems = computed(() => org.value?.schedule ?? [])
const activityFeed = computed(() => org.value?.activity ?? [])

const event = computed(() => org.value?.event ?? null)
const manageHref = computed(() => (event.value ? `/app/events/${event.value.id}/manage` : null))
const analyticsHref = computed(() => (event.value ? `/app/analytics?event_id=${event.value.id}` : null))

const eventCountdown = computed(() => {
  if (!event.value?.starts_at) return null
  const diff = new Date(event.value.starts_at).getTime() - Date.now()
  if (diff <= 0) {
    return { label: 'Event passed', tone: 'bg-white/10 text-slate-300' }
  }
  const days = Math.ceil(diff / 86_400_000)
  if (days === 0) {
    return { label: 'Event day', tone: 'bg-amber-400/20 text-amber-300' }
  }
  return {
    label: days === 1 ? '1 day to go' : `${days} days to go`,
    tone: 'bg-secondary-400/15 text-secondary-200',
  }
})

const taskBadgeTone = (key: string): BadgeTone => {
  const map: Record<string, BadgeTone> = { todo: 'neutral', in_progress: 'warning', done: 'success' }
  return map[key] ?? 'neutral'
}

const priorityTone = (key: string): BadgeTone => {
  const map: Record<string, BadgeTone> = { low: 'neutral', medium: 'warning', high: 'danger' }
  return map[key] ?? 'neutral'
}

const primarySmLink = [
  'inline-flex items-center gap-2 rounded-lg bg-primary-500 px-3 py-1.5 text-sm font-medium text-white transition-colors',
  'hover:bg-primary-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950',
].join(' ')

const secondarySmLink = [
  'inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium text-white backdrop-blur transition-colors',
  'hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40',
].join(' ')
</script>

<template>
  <div class="mx-auto max-w-7xl pb-16">
    <div v-if="loading && !overview" class="space-y-8">
      <div class="space-y-3">
        <Skeleton class="h-6 w-56" />
        <Skeleton class="h-10 w-full max-w-lg" />
      </div>
      <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
        <Skeleton v-for="i in 5" :key="i" class="h-36" />
      </div>
      <div class="grid gap-6 lg:grid-cols-3">
        <Skeleton class="h-72 lg:col-span-2" />
        <Skeleton class="h-72" />
      </div>
    </div>

    <AppError
      v-else-if="errorMsg && !overview"
      title="Could not load your dashboard"
      :description="errorMsg"
      @retry="load()"
    />

    <div v-else-if="overview" ref="revealRoot" :class="['space-y-8', switching ? 'opacity-50' : '']">
      <!-- ===================== ORGANIZER ===================== -->
      <template v-if="org">
        <header>
          <div
            class="relative overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 p-6 shadow-card sm:p-8"
          >
            <div class="absolute inset-0 bg-grid-light opacity-20" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-primary-600/25 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-secondary-500/20 blur-3xl" aria-hidden="true" />

            <div class="relative flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                  <p class="text-xs font-semibold uppercase tracking-widest text-secondary-300">
                    Command center
                  </p>
                  <BaseBadge v-if="event" :tone="event.status.tone">{{ event.status.label }}</BaseBadge>
                  <span
                    v-if="eventCountdown"
                    :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', eventCountdown.tone]"
                  >
                    <Clock class="h-3.5 w-3.5" />
                    {{ eventCountdown.label }}
                  </span>
                </div>
                <h1 class="mt-1.5 text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                  {{ event?.title ?? 'Your event' }}
                </h1>
                <div v-if="event" class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm text-slate-300">
                  <span class="inline-flex items-center gap-1.5">
                    <Clock class="h-4 w-4 text-slate-400/70" />
                    {{ formatDateTime(event.starts_at) }}
                  </span>
                  <span v-if="event.location" class="inline-flex items-center gap-1.5">
                    <MapPin class="h-4 w-4 text-slate-400/70" />
                    {{ event.location }}
                  </span>
                  <span v-if="event.capacity" class="inline-flex items-center gap-1.5">
                    <Users class="h-4 w-4 text-slate-400/70" />
                    Capacity {{ event.capacity }}
                  </span>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                  <router-link v-if="manageHref" :to="manageHref" :class="primarySmLink">
                    Manage event
                  </router-link>
                  <router-link v-if="analyticsHref" :to="analyticsHref" :class="secondarySmLink">
                    Open analytics
                  </router-link>
                </div>
              </div>

              <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                <div class="w-full rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur sm:w-72">
                  <label for="event-selector" class="text-[11px] font-semibold uppercase tracking-wider text-slate-300">
                    Snapshot for
                  </label>
                  <select
                    id="event-selector"
                    :value="selectedEventId ?? org.event?.id ?? ''"
                    class="input-base-dark mt-1.5 [&>option]:bg-slate-900 [&>option]:text-white"
                    @change="onSelect(($event.target as HTMLSelectElement).value)"
                  >
                    <option v-for="item in selectorEvents" :key="item.value" :value="item.value">
                      {{ item.label }}
                    </option>
                  </select>
                </div>
                <div v-if="health" class="rounded-2xl bg-white/95 p-3 shadow-card">
                  <HealthRing :score="health.score" :grade="health.grade" :size="112" label="Event health score" />
                </div>
              </div>
            </div>
          </div>
        </header>

        <!-- KPI -->
        <section aria-label="Key metrics">
          <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
            <MetricCard
              label="New registrations"
              :value="activity?.current ?? null"
              :delta="activity?.delta ?? null"
              delta-label="%"
              :hint="activity ? `vs ${activity.previous} in the previous 30 days` : 'Tracking registrations'"
              tone="primary"
              :icon="Users"
              accent="from-primary-500 via-primary-600 to-secondary-500"
              class="reveal"
            />
            <MetricCard
              label="Attendance rate"
              :value="attendance?.current ?? null"
              suffix="%"
              :delta="attendance?.delta ?? null"
              :hint="attendance?.applicable ? 'Share checked in vs registered' : 'Happens on event day'"
              :unavailable="attendance !== null && !attendance.applicable"
              unavailable-message="Waiting for event day"
              tone="teal"
              :icon="UserCheck"
              accent="from-teal-500 to-emerald-500"
              class="reveal"
              :style="{ transitionDelay: '70ms' }"
            />
            <MetricCard
              label="Tasks completed"
              :value="completion?.current ?? null"
              :delta="completion?.delta ?? null"
              delta-label="%"
              :hint="completion ? `vs ${completion.previous} in the previous 30 days` : 'Tracking task progress'"
              tone="violet"
              :icon="ListChecks"
              accent="from-violet-500 to-fuchsia-500"
              class="reveal"
              :style="{ transitionDelay: '140ms' }"
            />
            <MetricCard
              label="Overdue tasks"
              :value="overdue?.value ?? null"
              :hint="overdue ? `of ${overdue.total} total · ${overdue.completion}% done` : 'No tasks yet'"
              tone="red"
              :icon="AlarmClock"
              :link="manageHref ? { to: `${manageHref}?tab=tasks`, label: 'Follow up' } : undefined"
              accent="from-red-500 to-rose-500"
              class="reveal"
              :style="{ transitionDelay: '210ms' }"
            />
            <MetricCard
              label="Budget"
              tone="slate"
              :icon="Wallet"
              unavailable
              unavailable-message="Budget module on the roadmap"
              accent="from-slate-300 to-slate-400"
              class="reveal"
              :style="{ transitionDelay: '280ms' }"
            />
          </div>
        </section>

        <!-- Attention -->
        <section aria-label="Needs attention">
          <BaseCard
            :padded="false"
            :class="[hasCriticalAttention ? 'border-red-200' : 'border-amber-200', 'reveal']"
          >
            <div
              :class="[
                'flex items-center gap-2.5 border-b px-6 py-4',
                hasCriticalAttention ? 'border-red-100 bg-red-50/60' : 'border-amber-100 bg-amber-50/50',
              ]"
            >
              <span
                :class="[
                  'inline-flex h-9 w-9 items-center justify-center rounded-xl',
                  hasCriticalAttention ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600',
                ]"
              >
                <TriangleAlert class="h-4 w-4" />
              </span>
              <div>
                <p class="text-sm font-semibold text-slate-900">
                  {{ hasCriticalAttention ? 'Needs urgent attention' : 'Needs attention' }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ attentionItems.length }} item{{ attentionItems.length !== 1 ? 's' : '' }} to review
                  before the event
                </p>
              </div>
            </div>
            <ul v-if="attentionItems.length" class="divide-y divide-slate-100 px-6 py-2">
              <li v-for="item in attentionItems" :key="item.key" class="py-3">
                <InsightItem :insight="item" :event-id="event?.id" />
              </li>
            </ul>
            <EmptyState
              v-else
              :icon="CheckCircle2"
              title="All clear"
              description="No warnings or critical issues right now — everything is on track for this event."
            />
          </BaseCard>
        </section>

        <!-- Performance charts -->
        <section aria-label="Performance">
          <div class="grid gap-6 lg:grid-cols-3">
            <ChartCard
              title="Registration trend"
              subtitle="Last 30 days, updated daily"
              :loading="loading"
              :empty="!trendChartOption"
              class="reveal lg:col-span-2"
            >
              <BaseChart v-if="trendChartOption" :option="trendChartOption" />
            </ChartCard>
            <ChartCard
              title="Registration funnel"
              subtitle="Registered → checked in"
              :loading="loading"
              :empty="!funnelChartOption"
              class="reveal"
              :style="{ transitionDelay: '80ms' }"
            >
              <BaseChart v-if="funnelChartOption" :option="funnelChartOption" />
            </ChartCard>
          </div>
        </section>

        <!-- Deep charts -->
        <section aria-label="Activity and tasks">
          <div class="grid gap-6 lg:grid-cols-3">
            <ChartCard
              title="Check-in hours"
              subtitle="When attendees arrived"
              :loading="loading"
              :empty="!hoursChartOption"
              class="reveal"
            >
              <BaseChart v-if="hoursChartOption" :option="hoursChartOption" />
            </ChartCard>
            <ChartCard title="Tasks by status" subtitle="Workload breakdown" :loading="loading" :empty="!taskStatusChartOption" class="reveal" :style="{ transitionDelay: '80ms' }">
              <BaseChart v-if="taskStatusChartOption" :option="taskStatusChartOption" />
            </ChartCard>
            <ChartCard title="Completion by priority" subtitle="% of tasks done" :loading="loading" :empty="!taskPriorityChartOption" class="reveal" :style="{ transitionDelay: '160ms' }">
              <BaseChart v-if="taskPriorityChartOption" :option="taskPriorityChartOption" />
            </ChartCard>
          </div>
        </section>

        <!-- Schedule / health / activity -->
        <section aria-label="Planning">
          <div class="grid gap-6 lg:grid-cols-3">
            <BaseCard :padded="false" class="reveal flex h-full flex-col">
              <div class="border-b border-slate-100 px-6 py-5">
                <p class="text-base font-semibold tracking-tight text-slate-900">Upcoming schedule</p>
                <p class="mt-0.5 text-sm text-slate-500">Event window and open tasks</p>
              </div>
              <ul v-if="scheduleItems.length" class="flex-1 divide-y divide-slate-50 px-5 py-4">
                <li v-for="(item, index) in scheduleItems" :key="`${item.type}-${item.id ?? index}`" class="py-4 first:pt-0">
                  <TimelineItem
                    :title="item.title"
                    :at="item.at"
                    :description="item.location ?? undefined"
                    :icon="item.type === 'event' ? CalendarDays : ListChecks"
                    :icon-tone="item.type === 'event' ? 'bg-primary-50 text-primary-600' : 'bg-violet-50 text-violet-600'"
                    :badge="
                      item.type === 'task'
                        ? item.priority
                          ? { label: item.priority.label, tone: priorityTone(item.priority.key) }
                          : null
                        : item.status
                          ? { label: item.status.label, tone: taskBadgeTone(item.status.key) }
                          : null
                    "
                  />
                </li>
              </ul>
              <div v-else class="flex flex-1 items-center justify-center">
                <EmptyState :icon="CalendarDays" title="Nothing scheduled yet" />
              </div>
            </BaseCard>

            <BaseCard :padded="false" class="reveal flex h-full flex-col" :style="{ transitionDelay: '80ms' }">
              <div class="border-b border-slate-100 px-6 py-5">
                <p class="text-base font-semibold tracking-tight text-slate-900">Event health</p>
                <p class="mt-0.5 text-sm text-slate-500">Weighted score across 4 factors</p>
              </div>
              <div v-if="health" class="flex flex-1 flex-col items-center justify-center gap-6 px-6 py-6">
                <HealthRing :score="health.score" :grade="health.grade" :size="150" label="Event health score" />
                <ul class="w-full space-y-4">
                  <li v-for="factor in healthFactors" :key="factor.key">
                    <div class="mb-1 flex items-center justify-between text-sm">
                      <span class="font-medium text-slate-700">{{ factor.label }}</span>
                      <span class="tabular-nums text-slate-500">{{ factor.score }}</span>
                    </div>
                    <ProgressBar
                      :value="factor.score"
                      :tone="factor.score >= 80 ? 'success' : factor.score >= 55 ? 'warning' : 'danger'"
                    />
                  </li>
                </ul>
              </div>
              <div v-else class="flex flex-1 items-center justify-center px-6 py-6">
                <Skeleton :lines="4" class="h-5 w-full" />
              </div>
            </BaseCard>

            <BaseCard :padded="false" class="reveal flex h-full flex-col" :style="{ transitionDelay: '160ms' }">
              <div class="border-b border-slate-100 px-6 py-5">
                <p class="text-base font-semibold tracking-tight text-slate-900">Recent activity</p>
                <p class="mt-0.5 text-sm text-slate-500">Latest changes on this event</p>
              </div>
              <ul v-if="activityFeed.length" class="flex-1 space-y-4 px-5 py-4">
                <li v-for="entry in activityFeed" :key="entry.id">
                  <TimelineItem
                    :title="entry.description"
                    :at="entry.iso"
                    :description="entry.user ? `by ${entry.user.name}` : undefined"
                    icon-tone="bg-slate-100 text-slate-500"
                  />
                </li>
              </ul>
              <div v-else class="flex flex-1 items-center justify-center">
                <EmptyState
                  :icon="Activity"
                  title="No activity yet"
                  description="Changes you and your team make will appear here."
                />
              </div>
            </BaseCard>
          </div>
        </section>

        <!-- Good news -->
        <BaseCard :padded="false" class="reveal">
          <div class="flex items-center gap-2.5 border-b border-slate-100 px-6 py-4">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-secondary-50 text-secondary-600">
              <Sparkles class="h-4 w-4" />
            </span>
            <div>
              <p class="text-sm font-semibold text-slate-900">Insights &amp; suggestions</p>
              <p class="text-xs text-slate-500">What's going well and where to nudge next</p>
            </div>
          </div>
          <ul v-if="goodNews.length" class="divide-y divide-slate-100 px-6 py-2">
            <li v-for="item in goodNews" :key="item.key" class="py-3">
              <InsightItem :insight="item" :event-id="event?.id" />
            </li>
          </ul>
          <EmptyState
            v-else
            :icon="Sparkles"
            title="No insights yet"
            description="Insights will appear as your team and participants interact with the event."
          />
        </BaseCard>
      </template>

      <!-- ===================== PARTICIPANT ===================== -->
      <template v-else-if="part">
        <header>
          <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 p-6 shadow-card sm:p-8">
            <div class="absolute inset-0 bg-grid-light opacity-20" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-secondary-500/20 blur-3xl" aria-hidden="true" />
            <div class="relative">
              <p class="text-xs font-semibold uppercase tracking-widest text-secondary-300">Welcome</p>
              <h1 class="mt-1.5 text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                Hello, {{ firstName }}
              </h1>
              <p class="mt-2 max-w-xl text-sm text-slate-300">
                Here's what's happening with your events and tickets.
              </p>
              <div class="mt-5 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white">
                  <CalendarDays class="h-3.5 w-3.5 text-secondary-300" />
                  {{ part.events.length }} public event{{ part.events.length !== 1 ? 's' : '' }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white">
                  <Ticket class="h-3.5 w-3.5 text-secondary-300" />
                  {{ part.my_tickets.length }} ticket{{ part.my_tickets.length !== 1 ? 's' : '' }}
                </span>
              </div>
            </div>
          </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-2">
          <BaseCard :padded="false" class="reveal">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
              <div>
                <p class="text-base font-semibold tracking-tight text-slate-900">Events for you</p>
                <p class="mt-0.5 text-sm text-slate-500">Published and completed events</p>
              </div>
              <router-link
                :to="{ name: 'app.events' }"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700"
              >
                Browse <ArrowRight class="h-4 w-4" />
              </router-link>
            </div>
            <ul v-if="part.events.length" class="divide-y divide-slate-100 px-6 py-2">
              <li v-for="item in part.events" :key="item.value" class="py-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ item.label }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                      <span v-if="item.starts_at" class="inline-flex items-center gap-1">
                        <Clock class="h-3.5 w-3.5" /> {{ formatDateTime(item.starts_at) }}
                      </span>
                      <span v-if="item.location" class="inline-flex items-center gap-1">
                        <MapPin class="h-3.5 w-3.5" /> {{ item.location }}
                      </span>
                    </div>
                  </div>
                  <BaseBadge :tone="item.status.tone">{{ item.status.label }}</BaseBadge>
                </div>
              </li>
            </ul>
            <EmptyState v-else :icon="CalendarDays" title="No public events yet" description="Events will show here once organizers publish them." />
          </BaseCard>

          <BaseCard :padded="false" class="reveal" :style="{ transitionDelay: '80ms' }">
            <div class="border-b border-slate-100 px-6 py-5">
              <p class="text-base font-semibold tracking-tight text-slate-900">My tickets</p>
              <p class="mt-0.5 text-sm text-slate-500">Your registrations and their status</p>
            </div>
            <ul v-if="part.my_tickets.length" class="divide-y divide-slate-100 px-6 py-2">
              <li v-for="ticket in part.my_tickets" :key="ticket.id" class="py-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ ticket.event?.title ?? 'Event unavailable' }}</p>
                    <p class="mt-1 font-mono text-xs tracking-wide text-slate-500">
                      {{ ticket.registration_code }}
                    </p>
                    <p v-if="ticket.event?.starts_at" class="mt-1 inline-flex items-center gap-1 text-xs text-slate-400">
                      <Ticket class="h-3.5 w-3.5" /> {{ formatDateTime(ticket.event.starts_at) }}
                    </p>
                  </div>
                  <BaseBadge :tone="ticket.status.tone">{{ ticket.status.label }}</BaseBadge>
                </div>
              </li>
            </ul>
            <EmptyState v-else :icon="Ticket" title="No tickets yet" description="Register for an event and your ticket will appear here." />
          </BaseCard>
        </div>
      </template>
    </div>
  </div>
</template>