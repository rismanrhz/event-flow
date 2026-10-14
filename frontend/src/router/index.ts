import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guest?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: () => import('@/views/LandingView.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { guest: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/auth/ResetPasswordView.vue'),
      meta: { guest: true },
    },
    {
      path: '/app',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'app.dashboard',
          component: () => import('@/views/app/DashboardView.vue'),
        },
        {
          path: 'analytics',
          name: 'app.analytics',
          component: () => import('@/views/app/AnalyticsView.vue'),
        },
        {
          path: 'events',
          name: 'app.events',
          component: () => import('@/views/app/EventsIndexView.vue'),
        },
        {
          path: 'events/new',
          name: 'app.events.new',
          component: () => import('@/views/app/EventsFormView.vue'),
        },
        {
          path: 'events/:id/edit',
          name: 'app.events.edit',
          component: () => import('@/views/app/EventsFormView.vue'),
        },
        {
          path: 'events/:id/manage',
          name: 'app.events.manage',
          component: () => import('@/views/app/EventsManageView.vue'),
        },
        {
          path: 'profile',
          name: 'app.profile',
          component: () => import('@/views/app/ProfileView.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.hydrated) {
    await auth.hydrate()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : undefined }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'app.dashboard' }
  }

  return true
})

export default router