<script setup lang="ts">
import type { Component } from 'vue'
import {
  ArrowRight,
  BadgeCheck,
  CalendarHeart,
  ChartNoAxesCombined,
  CheckCircle2,
  ClipboardList,
  Quote,
  Star,
  TrendingUp,
  Users,
  Wallet,
} from '@lucide/vue'
import { onMounted, onUnmounted, reactive, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import HealthRing from '@/components/ui/HealthRing.vue'
import { useReveal } from '@/composables/useReveal'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const sparkPoints = '0,52 30,40 60,46 90,30 120,34 150,20 180,26 210,12 240,16 270,6 300,10'

const revealRoot = ref<HTMLElement | null>(null)
useReveal(revealRoot)

const navLinks = [
  { href: '#features', label: 'Features' },
  { href: '#command-center', label: 'Command center' },
  { href: '#testimonials', label: 'Testimonials' },
]

interface Feature {
  icon: Component
  title: string
  description: string
  gradient: string
}

const features: Feature[] = [
  {
    icon: ClipboardList,
    title: 'Committees & tasks',
    description: 'Organize committees, assign responsibilities, and track every task to completion.',
    gradient: 'from-indigo-500 to-indigo-600',
  },
  {
    icon: Users,
    title: 'Participants',
    description: 'Manage registration, check attendance with QR codes, and issue certificates.',
    gradient: 'from-teal-500 to-emerald-600',
  },
  {
    icon: Wallet,
    title: 'Budgets',
    description: 'Keep a clear picture of income and expenses against your event budget.',
    gradient: 'from-amber-500 to-orange-600',
  },
  {
    icon: ChartNoAxesCombined,
    title: 'Insights & analytics',
    description: 'Live dashboards, event health scores, and trends that update as your event grows.',
    gradient: 'from-violet-500 to-fuchsia-600',
  },
]

const showcaseBullets = [
  { icon: CheckCircle2, text: 'Health score surfaces risks before they become problems' },
  { icon: TrendingUp, text: 'Registrations and attendance trends update daily' },
  { icon: Users, text: 'Task completion tracked across every committee' },
]

interface Stat {
  label: string
  target: number
  format?: 'k'
  suffix?: string
}

const stats: Stat[] = [
  { label: 'Registrations captured', target: 12600, format: 'k' },
  { label: 'Events managed', target: 40, suffix: '+' },
  { label: 'Committees onboard', target: 18, suffix: '+' },
  { label: 'Average attendance', target: 94, suffix: '%' },
]

const statValues = reactive<number[]>(stats.map(() => 0))
let raf = 0

function formatStat(value: number, stat: Stat): string {
  if (stat.format === 'k') return `${(value / 1000).toFixed(1)}k`
  return `${value}${stat.suffix ?? ''}`
}

function animateStats(): void {
  const start = performance.now()
  const duration = 900
  const tick = (now: number) => {
    const progress = Math.min(1, (now - start) / duration)
    const eased = 1 - Math.pow(1 - progress, 3)
    stats.forEach((stat, i) => {
      statValues[i] = Math.round(stat.target * eased)
    })
    if (progress < 1) raf = requestAnimationFrame(tick)
  }
  raf = requestAnimationFrame(tick)
}

onMounted(animateStats)
onUnmounted(() => cancelAnimationFrame(raf))

interface Testimonial {
  quote: string
  name: string
  role: string
  initials: string
  gradient: string
}

const testimonials: Testimonial[] = [
  {
    quote:
      'We went from spreadsheets chaos to a single command center. Health alerts flagged an overdue task two weeks before our event — we fixed it in an hour.',
    name: 'Risma Putri',
    role: 'Event Owner · Tech Conference 2026',
    initials: 'RP',
    gradient: 'from-indigo-500 to-violet-600',
  },
  {
    quote:
      'Attendance tracking with QR codes paid for itself on day one. The dashboard makes it easy to show our committee exactly where we stand.',
    name: 'Budi Santoso',
    role: 'Committee Lead · Design Workshop',
    initials: 'BS',
    gradient: 'from-teal-500 to-emerald-600',
  },
  {
    quote:
      'As a participant I love that I can see my tickets and the venue details in one place. No more digging through email.',
    name: 'Siti Aminah',
    role: 'Participant · Community Charity Run',
    initials: 'SA',
    gradient: 'from-amber-500 to-orange-600',
  },
]

const footerCols = [
  {
    title: 'Product',
    links: [
      { label: 'Features', href: '#features' },
      { label: 'Command center', href: '#command-center' },
      { label: 'Testimonials', href: '#testimonials' },
    ],
  },
  {
    title: 'Company',
    links: [
      { label: 'About', href: '#' },
      { label: 'Careers', href: '#' },
      { label: 'Contact', href: '#' },
    ],
  },
  {
    title: 'Resources',
    links: [
      { label: 'Help center', href: '#' },
      { label: 'API docs', href: '#' },
      { label: 'Status', href: '#' },
    ],
  },
]
</script>

<template>
  <div class="flex min-h-screen flex-col bg-slate-50">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6">
        <a href="#" class="flex items-center gap-2.5">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-violet-600 text-white shadow-glow">
            <CalendarHeart class="h-5 w-5" />
          </span>
          <span class="text-lg font-semibold tracking-tight text-slate-900">EventFlow</span>
        </a>

        <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
          <a v-for="link in navLinks" :key="link.href" :href="link.href" class="transition-colors hover:text-slate-900">
            {{ link.label }}
          </a>
        </nav>

        <div v-if="auth.isAuthenticated" class="flex items-center gap-3">
          <span class="hidden text-sm text-slate-500 sm:block">{{ auth.user?.email }}</span>
          <router-link :to="{ name: 'app.dashboard' }">
            <BaseButton size="sm">Open dashboard</BaseButton>
          </router-link>
        </div>
        <div v-else class="flex items-center gap-2">
          <router-link :to="{ name: 'login' }">
            <BaseButton variant="ghost" size="sm">Sign in</BaseButton>
          </router-link>
          <router-link :to="{ name: 'register' }">
            <BaseButton size="sm">Get started</BaseButton>
          </router-link>
        </div>
      </div>
    </header>

    <main ref="revealRoot" class="flex-1">
      <!-- ===================== HERO ===================== -->
      <section class="relative overflow-hidden bg-hero-gradient">
        <div class="pointer-events-none absolute -left-28 -top-28 h-80 w-80 rounded-full bg-primary-400/25 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute -right-24 top-32 h-72 w-72 rounded-full bg-secondary-400/25 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute bottom-0 left-1/2 h-64 w-[52rem] -translate-x-1/2 rounded-full bg-violet-300/20 blur-3xl" aria-hidden="true" />

        <div class="relative mx-auto w-full max-w-6xl px-4 pb-20 pt-20 sm:px-6 lg:pb-24 lg:pt-28">
          <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-white/70 px-3 py-1 text-xs font-medium text-primary-700 shadow-sm backdrop-blur">
              <BadgeCheck class="h-3.5 w-3.5" />
              All-in-one event management
            </span>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl">
              Plan, organize, and run events
              <span class="bg-gradient-to-r from-primary-600 via-primary-500 to-secondary-500 bg-clip-text text-transparent">
                without the chaos
              </span>
            </h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-slate-500">
              EventFlow brings committees, schedules, budgets, participants and reporting together in
              one clean workspace — so you can focus on making your event a success.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
              <router-link :to="{ name: 'register' }">
                <BaseButton size="lg">
                  Create your account
                  <ArrowRight class="h-4 w-4" />
                </BaseButton>
              </router-link>
              <router-link :to="{ name: 'login' }">
                <BaseButton variant="secondary" size="lg">Sign in</BaseButton>
              </router-link>
            </div>
          </div>
        </div>

        <div class="pointer-events-none absolute left-[4%] top-40 hidden xl:block" aria-hidden="true">
          <div class="flex items-center gap-2 rounded-2xl border border-white/60 bg-white/80 px-4 py-3 shadow-cardHover backdrop-blur">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
              <TrendingUp class="h-4 w-4" />
            </span>
            <div class="text-left">
              <p class="text-sm font-semibold text-slate-900">+87%</p>
              <p class="text-xs text-slate-500">registrations this month</p>
            </div>
          </div>
        </div>
        <div class="pointer-events-none absolute right-[4%] top-56 hidden xl:block" aria-hidden="true">
          <div class="flex items-center gap-2 rounded-2xl border border-white/60 bg-white/80 px-4 py-3 shadow-cardHover backdrop-blur">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-secondary-50 text-secondary-600">
              <Users class="h-4 w-4" />
            </span>
            <div class="text-left">
              <p class="text-sm font-semibold text-slate-900">1,2k</p>
              <p class="text-xs text-slate-500">attendees this quarter</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ===================== STATS BAND ===================== -->
      <section class="relative z-10 mx-auto -mt-8 w-full max-w-6xl px-4 sm:px-6">
        <div class="reveal grid grid-cols-2 overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-r from-primary-700 via-primary-600 to-secondary-600 shadow-glow sm:grid-cols-4">
          <div v-for="(stat, i) in stats" :key="stat.label" class="px-6 py-7 text-center">
            <p class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
              {{ formatStat(statValues[i], stat) }}
            </p>
            <p class="mt-1 text-xs font-medium uppercase tracking-wider text-primary-100 sm:text-sm">
              {{ stat.label }}
            </p>
          </div>
        </div>
      </section>

      <!-- ===================== FEATURES ===================== -->
      <section id="features" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-xs font-semibold uppercase tracking-widest text-primary-600">Everything you need</p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
            One workspace for the whole event team
          </h2>
          <p class="mt-4 text-base leading-relaxed text-slate-500">
            From first brainstorm to final report — keep committees, participants, budgets and
            insights firmly in view.
          </p>
        </div>

        <div class="mt-14 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="(feature, i) in features"
            :key="feature.title"
            class="reveal group rounded-2xl border border-slate-200 bg-white p-6 shadow-card transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-cardHover"
            :style="{ transitionDelay: `${i * 70}ms` }"
          >
            <span
              :class="['inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-sm transition-transform duration-300 group-hover:scale-110', feature.gradient]"
            >
              <component :is="feature.icon" class="h-5 w-5" />
            </span>
            <h3 class="mt-4 text-base font-semibold text-slate-900">{{ feature.title }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ feature.description }}</p>
          </div>
        </div>
      </section>

      <!-- ===================== COMMAND CENTER ===================== -->
      <section id="command-center" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 pb-20 sm:px-6">
        <div class="reveal overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-card">
          <div class="grid gap-0 lg:grid-cols-2">
            <div class="flex flex-col justify-center p-8 sm:p-10">
              <p class="text-xs font-semibold uppercase tracking-widest text-primary-600">Command center</p>
              <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                See the whole picture at a glance
              </h2>
              <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-500 sm:text-base">
                Track registrations, attendance, and task progress on a live dashboard. A health
                score surfaces risks early, so nothing catches you off guard.
              </p>
              <ul class="mt-8 space-y-3">
                <li v-for="bullet in showcaseBullets" :key="bullet.text" class="flex items-start gap-2.5 text-sm text-slate-600">
                  <component :is="bullet.icon" class="mt-0.5 h-4 w-4 shrink-0 text-secondary-500" />
                  {{ bullet.text }}
                </li>
              </ul>
            </div>

            <div class="relative flex items-center justify-center overflow-hidden bg-slate-950 p-8 sm:p-10">
              <div class="absolute inset-0 bg-grid-light opacity-40" aria-hidden="true" />
              <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-primary-500/20 blur-3xl" aria-hidden="true" />

              <div class="relative w-full max-w-sm rounded-2xl border border-white/10 bg-white/95 p-6 shadow-2xl backdrop-blur">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Registrations</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">1,206</p>
                  </div>
                  <HealthRing :score="87" grade="Healthy" :size="72" label="Sample health score" />
                </div>

                <div class="mt-6 flex items-end gap-1.5" role="img" aria-label="Weekly activity bar mini chart">
                  <div
                    v-for="(h, i) in [38, 52, 44, 62, 56, 78, 92]"
                    :key="i"
                    class="flex-1 rounded-t-md bg-gradient-to-t from-primary-600 to-violet-500"
                    :style="{ height: `${h}px`, opacity: 0.45 + i * 0.08 }"
                  />
                </div>

                <svg viewBox="0 0 300 64" class="mt-4 h-20 w-full" role="img" aria-label="Upward trend line">
                  <defs>
                    <linearGradient id="sparkFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="rgb(99 102 241 / 0.25)" />
                      <stop offset="100%" stop-color="rgb(99 102 241 / 0)" />
                    </linearGradient>
                  </defs>
                  <path d="M 0,52 L 30,40 L 60,46 L 90,30 L 120,34 L 150,20 L 180,26 L 210,12 L 240,16 L 270,6 L 300,10 L 300 64 L 0 64 Z" fill="url(#sparkFill)" />
                  <polyline
                    :points="sparkPoints"
                    fill="none"
                    stroke="#6366f1"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <circle cx="300" cy="10" r="3.5" fill="#6366f1" />
                </svg>

                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                  <span>Last 30 days</span>
                  <span class="inline-flex items-center gap-1 font-medium text-emerald-600">
                    +18% week over week
                  </span>
                </div>
              </div>

              <div class="absolute -left-3 -top-3 flex items-center gap-2 rounded-xl border border-white/20 bg-white/90 px-3 py-2 shadow-cardHover backdrop-blur">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                  <CheckCircle2 class="h-4 w-4" />
                </span>
                <div class="text-left">
                  <p class="text-xs font-semibold text-slate-900">3 tasks done</p>
                  <p class="text-[10px] text-slate-500">today</p>
                </div>
              </div>
              <div class="absolute -bottom-3 -right-3 flex items-center gap-2 rounded-xl border border-white/20 bg-white/90 px-3 py-2 shadow-cardHover backdrop-blur">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-secondary-50 text-secondary-600">
                  <Users class="h-4 w-4" />
                </span>
                <div class="text-left">
                  <p class="text-xs font-semibold text-slate-900">+24 registrations</p>
                  <p class="text-[10px] text-slate-500">this week</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===================== TESTIMONIALS ===================== -->
      <section id="testimonials" class="mx-auto w-full max-w-6xl scroll-mt-20 px-4 pb-20 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-xs font-semibold uppercase tracking-widest text-primary-600">Loved by organizers</p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
            Teams that traded the chaos for a plan
          </h2>
        </div>

        <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
          <div
            v-for="(t, i) in testimonials"
            :key="t.name"
            class="reveal flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-card transition-all duration-300 hover:shadow-cardHover"
            :style="{ transitionDelay: `${i * 80}ms` }"
          >
            <Quote class="h-6 w-6 text-primary-200" />
            <p class="mt-4 flex-1 text-sm leading-relaxed text-slate-600">“{{ t.quote }}”</p>
            <div class="mt-6 flex items-center gap-1 text-amber-400">
              <Star v-for="n in 5" :key="n" class="h-4 w-4 fill-current" />
            </div>
            <div class="mt-4 flex items-center gap-3 border-t border-slate-100 pt-4">
              <span :class="['inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-semibold text-white', t.gradient]">
                {{ t.initials }}
              </span>
              <div>
                <p class="text-sm font-semibold text-slate-900">{{ t.name }}</p>
                <p class="text-xs text-slate-500">{{ t.role }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===================== CTA ===================== -->
      <section class="mx-auto w-full max-w-6xl px-4 pb-24 sm:px-6">
        <div class="reveal relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-700 via-primary-600 to-secondary-700 px-8 py-14 text-center shadow-glow sm:px-12">
          <div class="absolute inset-0 bg-grid-light opacity-40" aria-hidden="true" />
          <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" aria-hidden="true" />
          <div class="relative">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
              Ready to run your next event?
            </h2>
            <p class="mx-auto mt-3 max-w-xl text-primary-100">
              Join EventFlow today and turn your event plan into a live command center — for your
              team and your participants.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
              <router-link :to="{ name: 'register' }">
                <BaseButton variant="secondary" size="lg">
                  Create your account
                  <ArrowRight class="h-4 w-4" />
                </BaseButton>
              </router-link>
              <router-link
                :to="{ name: 'login' }"
                class="inline-flex items-center gap-2 rounded-lg border border-white/30 px-5 py-3 text-base font-medium text-white transition-colors hover:bg-white/10"
              >
                Sign in
              </router-link>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
      <div class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
          <div class="col-span-2 md:col-span-1">
            <div class="flex items-center gap-2.5">
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-violet-600 text-white">
                <CalendarHeart class="h-5 w-5" />
              </span>
              <span class="text-lg font-semibold tracking-tight text-slate-900">EventFlow</span>
            </div>
            <p class="mt-4 max-w-xs text-sm leading-relaxed text-slate-500">
              The website management platform by Risma. Plan, organize, and run events without the chaos.
            </p>
          </div>
          <div v-for="col in footerCols" :key="col.title">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ col.title }}</p>
            <ul class="mt-4 space-y-2.5">
              <li v-for="link in col.links" :key="link.label">
                <a :href="link.href" class="text-sm text-slate-500 transition-colors hover:text-slate-900">
                  {{ link.label }}
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-slate-100 pt-6 text-sm text-slate-400 sm:flex-row">
          <p>© {{ new Date().getFullYear() }} EventFlow. All rights reserved.</p>
          <p>Built with care for event teams.</p>
        </div>
      </div>
    </footer>
  </div>
</template>