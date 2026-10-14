<script setup lang="ts">
import {
  Bell,
  CalendarDays,
  ChartNoAxesCombined,
  ChevronDown,
  CircleUser,
  LayoutDashboard,
  LogOut,
  PanelLeft,
  X,
} from '@lucide/vue'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const sidebarOpen = ref(false)
const menuOpen = ref(false)

const isOrganizer = computed(() => {
  const key = auth.user?.role.key
  return key === 'super_admin' || key === 'event_owner' || key === 'committee_member'
})

const navItems = computed(() => {
  const items = [{ name: 'app.dashboard', label: 'Dashboard', icon: LayoutDashboard }]
  if (isOrganizer.value) {
    items.push({ name: 'app.analytics', label: 'Analytics', icon: ChartNoAxesCombined })
  }
  items.push({ name: 'app.events', label: 'Events', icon: CalendarDays })
  return items
})

function isActive(name: string): boolean {
  return router.currentRoute.value.name === name
}

const initials = computed(() => {
  const name = auth.user?.name ?? ''
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
    || 'U'
})

async function handleLogout(): Promise<void> {
  menuOpen.value = false
  await auth.logout()
  void router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex h-screen flex-col overflow-hidden bg-slate-100 lg:flex-row">
    <transition name="fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
        @click="sidebarOpen = false"
      />
    </transition>

    <aside
      :class="[
        'z-40 flex h-full w-64 shrink-0 flex-col border-r border-slate-200 bg-white',
        'fixed inset-y-0 left-0 transition-transform duration-200 lg:static lg:translate-x-0',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
      ]"
    >
      <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
        <div class="flex items-center gap-2.5">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white">
            <CalendarDays class="h-4 w-4" />
          </span>
          <span class="text-base font-semibold tracking-tight">EventFlow</span>
        </div>
        <button
          type="button"
          class="text-slate-400 transition-colors hover:text-slate-600 lg:hidden"
          aria-label="Close sidebar"
          @click="sidebarOpen = false"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto p-3">
        <router-link
          v-for="item in navItems"
          :key="item.name"
          :to="{ name: item.name }"
          :class="[
            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
            isActive(item.name)
              ? 'bg-primary-50 text-primary-700'
              : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
          ]"
          @click="sidebarOpen = false"
        >
          <component :is="item.icon" class="h-4 w-4" />
          {{ item.label }}
        </router-link>
      </nav>

      <div class="border-t border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
            {{ initials }}
          </span>
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-900">{{ auth.user?.name }}</p>
            <BaseBadge tone="primary">{{ auth.user?.role.label }}</BaseBadge>
          </div>
        </div>
      </div>
    </aside>

    <div class="flex flex-1 flex-col overflow-hidden">
      <header class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 lg:px-8">
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="text-slate-500 transition-colors hover:text-slate-800 lg:hidden"
            aria-label="Open sidebar"
            @click="sidebarOpen = true"
          >
            <PanelLeft class="h-5 w-5" />
          </button>
          <h2 class="text-base font-semibold tracking-tight capitalize">
            {{ String(router.currentRoute.value.name ?? '').replace('app.', '').replace('.', ' ') }}
          </h2>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="relative rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
            aria-label="Notifications"
          >
            <Bell class="h-5 w-5" />
            <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-primary-600" />
          </button>

          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-2 rounded-lg p-1.5 transition-colors hover:bg-slate-100"
              aria-haspopup="menu"
              :aria-expanded="menuOpen"
              @click="menuOpen = !menuOpen"
            >
              <span v-if="auth.user?.avatar_url" class="h-8 w-8 overflow-hidden rounded-full">
                <img :src="auth.user.avatar_url" :alt="auth.user.name" class="h-full w-full object-cover" />
              </span>
              <span v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
                {{ initials }}
              </span>
              <ChevronDown class="h-4 w-4 text-slate-400" />
            </button>

            <transition name="fade">
              <div
                v-if="menuOpen"
                class="absolute right-0 top-full z-50 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-card"
              >
                <div class="border-b border-slate-100 px-4 py-2.5">
                  <p class="truncate text-sm font-medium text-slate-900">{{ auth.user?.name }}</p>
                  <p class="truncate text-xs text-slate-500">{{ auth.user?.email }}</p>
                </div>
                <router-link
                  :to="{ name: 'app.profile' }"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50"
                  @click="menuOpen = false"
                >
                  <CircleUser class="h-4 w-4" />
                  Profile
                </router-link>
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50"
                  @click="handleLogout"
                >
                  <LogOut class="h-4 w-4" />
                  Sign out
                </button>
              </div>
            </transition>
          </div>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto p-6 lg:p-8">
        <router-view v-slot="{ Component }">
          <transition name="page" mode="out-in">
            <component :is="Component" :key="String(route.name)" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.page-enter-active,
.page-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.page-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.page-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>