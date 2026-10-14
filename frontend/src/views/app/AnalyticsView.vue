<script setup lang="ts">
import type { EChartsOption } from 'echarts'
import { BarChart3, ListChecks, Ticket, UserCheck, Users } from '@lucide/vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { analyticsApi } from '@/api/analytics'
import { dashboardApi } from '@/api/dashboard'
import AppError from '@/components/ui/AppError.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseChart from '@/components/ui/BaseChart.vue'
import ChartCard from '@/components/ui/ChartCard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import HealthRing from '@/components/ui/HealthRing.vue'
import MetricCard from '@/components/ui/MetricCard.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import type {
  AnalyticsOverview,
  AnalyticsRangeKey,
  DashboardOrganizerData,
  EventSelectorItem,
} from '@/types'
import {
  attendanceHoursOption,
  barListOption,
  funnelOption,
  statusDonutOption,
  taskPriorityOption,
  trendOption,
} from '@/utils/charts'

const route = useRoute()

const RANGES: { key: AnalyticsRangeKey; label: string }[] = [
  { key: '7', label: '7 days' },
  { key: '30', label: '30 days' },
  { key: '90', label: '90 days' },
]

const events = ref<EventSelectorItem[]>([])
const selectedEventId = ref<number | null>(null)
const range = ref<AnalyticsRangeKey>('30')
const data = ref<AnalyticsOverview | null>(null)
const loading = ref(true)
const errorMsg = ref<string | null>(null)
const organizer = ref<DashboardOrganizerData | null>(null)

const selectorEvents = computed(() => events.value.filter((e) => e.can_access_data))

async function loadAnalytics(): Promise<void> {
  if (selectedEventId.value === null) return
  errorMsg.value = null
  loading.value = true
  try {
    data.value = await analyticsApi.get(selectedEventId.value, range.value)
  } catch (error) {
    data.value = null
    errorMsg.value = error instanceof Error ? error.message : 'Failed to load analytics.'
  } finally {
    loading.value = false
  }
}

async function bootstrap(): Promise<void> {
  loading.value = true
  try {
    const overview = await dashboardApi.overview()
    if (overview.mode !== 'organizer') {
      organizer.value = null
      return
    }
    organizer.value = overview
    events.value = overview.events

    const queryId = Number(route.query.event_id)
    const requested = Number.isFinite(queryId) ? queryId : null
    const first = selectorEvents.value[0]
    selectedEventId.value =
      (requested !== null && selectorEvents.value.some((e) => e.value === requested)
        ? requested
        : first?.value) ?? null
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : 'Failed to load analytics.'
    loading.value = false
  }
}

function onSelect(eventId: number | string): void {
  const id = Number(eventId)
  if (id === selectedEventId.value) return
  selectedEventId.value = id
  void loadAnalytics()
}

function onRange(key: AnalyticsRangeKey): void {
  if (key === range.value) return
  range.value = key
  void loadAnalytics()
}

watch(
  () => route.query.event_id,
  () => {
    const queryId = Number(route.query.event_id)
    if (Number.isFinite(queryId) && selectorEvents.value.some((e) => e.value === queryId)) {
      selectedEventId.value = queryId
      void loadAnalytics()
    }
  },
)

onMounted(async () => {
  await bootstrap()
  if (selectorEvents.value.length) {
    await loadAnalytics()
  } else {
    loading.value = false
  }
})

const hasParticipants = computed(() => data.value?.participants ?? null)
const hasTasks = computed(() => data.value?.tasks ?? null)

const trendChartOption = computed<EChartsOption | null>(() =>
  data.value?.trend ? trendOption(data.value.trend) : null,
)
const funnelChartOption = computed<EChartsOption | null>(() =>
  data.value?.funnel ? funnelOption(data.value.funnel) : null,
)
const hoursChartOption = computed<EChartsOption | null>(() =>
  data.value?.attendance ? attendanceHoursOption(data.value.attendance) : null,
)
const participantDonutOption = computed<EChartsOption | null>(() =>
  data.value?.participants
    ? statusDonutOption('Participants', data.value.participants.breakdown)
    : null,
)
const institutionBarOption = computed<EChartsOption | null>(() =>
  data.value?.participants
    ? barListOption(data.value.participants.institutions.map((i) => ({ label: i.label, value: i.count })))
    : null,
)
const taskStatusChartOption = computed<EChartsOption | null>(() =>
  data.value?.tasks ? statusDonutOption('Tasks', data.value.tasks.by_status) : null,
)
const taskPriorityChartOption = computed<EChartsOption | null>(() =>
  data.value?.tasks ? taskPriorityOption(data.value.tasks) : null,
)

const currentEvent = computed(() =>
  selectorEvents.value.find((e) => e.value === selectedEventId.value) ?? null,
)
</script>

<template>
  <div class="mx-auto max-w-7xl pb-16">
    <!-- Loading (first fetch) -->
    <div v-if="loading && !data && !errorMsg" class="space-y-8">
      <div class="space-y-3">
        <Skeleton class="h-6 w-40" />
        <Skeleton class="h-10 w-full max-w-lg" />
      </div>
      <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-32" />
      </div>
      <div class="grid gap-6 lg:grid-cols-3">
        <Skeleton class="h-72 lg:col-span-2" />
        <Skeleton class="h-72" />
      </div>
    </div>

    <AppError
      v-else-if="errorMsg && !data"
      title="Could not load analytics"
      :description="errorMsg"
      @retry="loadAnalytics()"
    />

    <!-- No organizer access -->
    <BaseCard v-else-if="organizer === null">
      <EmptyState
        :icon="BarChart3"
        tone="primary"
        title="Analytics is for organizers"
        description="Sign in as an event owner or committee member to explore registration, attendance, and task analytics."
      />
    </BaseCard>

    <!-- Body -->
    <template v-else>
      <header>
        <BaseCard :padded="false" class="relative overflow-hidden p-6 sm:p-8">
          <div class="absolute inset-0 bg-grid opacity-60" aria-hidden="true" />
          <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <p class="text-xs font-semibold uppercase tracking-widest text-primary-600">Analytics</p>
                <BaseBadge v-if="currentEvent" :tone="currentEvent.status.tone">
                  {{ currentEvent.status.label }}
                </BaseBadge>
              </div>
              <h1 class="mt-1.5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                {{ currentEvent?.label ?? 'Select an event' }}
              </h1>
              <p class="mt-2 max-w-xl text-sm text-slate-500">
                Registrations, attendance, and task performance over the selected window.
              </p>
            </div>

            <div class="flex flex-col gap-4 sm:w-64 sm:min-w-64">
              <div>
                <label for="analytics-event" class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  Event
                </label>
                <select
                  id="analytics-event"
                  :value="selectedEventId ?? ''"
                  class="input-base mt-1.5"
                  @change="onSelect(($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="item in selectorEvents" :key="item.value" :value="item.value">
                    {{ item.label }}
                  </option>
                </select>
              </div>

              <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  Time range
                </span>
                <div class="mt-1.5 flex rounded-lg border border-slate-200 bg-slate-50 p-0.5" role="tablist" aria-label="Time range">
                  <button
                    v-for="option in RANGES"
                    :key="option.key"
                    type="button"
                    role="tab"
                    :aria-selected="range === option.key"
                    :class="[
                      'flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors',
                      range === option.key
                        ? 'bg-white text-primary-700 shadow-sm'
                        : 'text-slate-500 hover:text-slate-800',
                    ]"
                    @click="onRange(option.key)"
                  >
                    {{ option.label }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </BaseCard>
      </header>

      <!-- Summary metrics -->
      <section aria-label="Summary" class="mt-8">
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
          <MetricCard label="Total participants" :value="hasParticipants?.total ?? null" tone="primary" :icon="Users" />
          <MetricCard
            label="Checked in"
            :value="data?.attendance?.checked_in ?? null"
            tone="teal"
            :icon="UserCheck"
          />
          <MetricCard
            label="Attendance rate"
            :value="data?.attendance?.rate ?? null"
            suffix="%"
            tone="violet"
            :icon="Ticket"
          />
          <MetricCard
            label="Task completion"
            :value="hasTasks?.completion ?? null"
            suffix="%"
            tone="amber"
            :icon="ListChecks"
          />
          <BaseCard :padded="false" class="flex items-center justify-center gap-3 p-5">
            <HealthRing
              v-if="data"
              :score="data.health.score"
              :grade="data.health.grade"
              :size="84"
              label="Event health score"
            />
            <div class="text-sm">
              <p class="font-semibold text-slate-900">{{ data?.health.score ?? '—' }} / 100</p>
              <p class="text-slate-500">Health</p>
            </div>
          </BaseCard>
        </div>
      </section>

      <!-- Trend + funnel -->
      <section aria-label="Registrations" class="mt-8">
        <div class="grid gap-6 lg:grid-cols-3">
          <ChartCard
            title="Registration trend"
            :subtitle="`Daily activity over the last ${range} days`"
            :loading="loading"
            :empty="!trendChartOption"
            class="lg:col-span-2"
          >
            <BaseChart v-if="trendChartOption" :option="trendChartOption" />
          </ChartCard>
          <ChartCard title="Registration funnel" subtitle="Registered → checked in" :loading="loading" :empty="!funnelChartOption">
            <BaseChart v-if="funnelChartOption" :option="funnelChartOption" />
          </ChartCard>
        </div>
      </section>

      <!-- Participants deep dive -->
      <section aria-label="Participant profile" class="mt-8">
        <div v-if="hasParticipants" class="grid gap-6 lg:grid-cols-3">
          <ChartCard title="Check-in hours" subtitle="When attendees arrived" :loading="loading" :empty="!hoursChartOption">
            <BaseChart v-if="hoursChartOption" :option="hoursChartOption" />
          </ChartCard>
          <ChartCard title="Institutions" subtitle="Top institutions of attendees" :loading="loading" :empty="!institutionBarOption">
            <BaseChart v-if="institutionBarOption" :option="institutionBarOption" />
          </ChartCard>
          <ChartCard title="Participant status" subtitle="Registered, checked in, waitlisted" :loading="loading" :empty="!participantDonutOption">
            <BaseChart v-if="participantDonutOption" :option="participantDonutOption" />
          </ChartCard>
        </div>
        <BaseCard v-else>
          <EmptyState
            :icon="Users"
            title="No participant data access"
            description="Ask the event owner for participant permissions to see the full analytics profile."
          />
        </BaseCard>
      </section>

      <!-- Task deep dive -->
      <section aria-label="Task performance" class="mt-8">
        <div v-if="hasTasks" class="grid gap-6 lg:grid-cols-2">
          <ChartCard title="Tasks by status" subtitle="Workload breakdown" :loading="loading" :empty="!taskStatusChartOption">
            <BaseChart v-if="taskStatusChartOption" :option="taskStatusChartOption" />
          </ChartCard>
          <ChartCard title="Completion by priority" subtitle="% of tasks done" :loading="loading" :empty="!taskPriorityChartOption">
            <BaseChart v-if="taskPriorityChartOption" :option="taskPriorityChartOption" />
          </ChartCard>
        </div>
        <BaseCard v-else>
          <EmptyState
            :icon="ListChecks"
            title="No task data access"
            description="Ask the event owner for task permissions to see completion analytics."
          />
        </BaseCard>
      </section>
    </template>
  </div>
</template>