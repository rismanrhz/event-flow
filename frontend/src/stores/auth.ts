import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/auth'
import type { RegisterInput } from '@/api/auth'
import { TOKEN_KEY } from '@/api/client'
import type { User } from '@/types'

const USER_KEY = 'eventflow_user'

function readStoredUser(): User | null {
  const raw = localStorage.getItem(USER_KEY)
  if (raw === null) return null
  try {
    return JSON.parse(raw) as User
  } catch {
    localStorage.removeItem(USER_KEY)
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const user = ref<User | null>(readStoredUser())
  const hydrated = ref(false)

  const isAuthenticated = computed(() => token.value !== null)

  function persist(payload: { token: string; user: User }): void {
    token.value = payload.token
    user.value = payload.user
    localStorage.setItem(TOKEN_KEY, payload.token)
    localStorage.setItem(USER_KEY, JSON.stringify(payload.user))
  }

  function clear(): void {
    token.value = null
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  }

  async function login(email: string, password: string, remember = false): Promise<void> {
    persist(await authApi.login({ email, password, remember }))
  }

  async function register(input: RegisterInput): Promise<void> {
    persist(await authApi.register(input))
  }

  async function setUser(fresh: User): Promise<void> {
    user.value = fresh
    localStorage.setItem(USER_KEY, JSON.stringify(fresh))
  }

  async function fetchUser(): Promise<User> {
    const fresh = await authApi.me()
    await setUser(fresh)
    return fresh
  }

  async function hydrate(): Promise<void> {
    if (hydrated.value) return
    if (token.value === null) {
      hydrated.value = true
      return
    }
    try {
      await fetchUser()
    } catch {
      clear()
    } finally {
      hydrated.value = true
    }
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } catch {
      // Ignore errors while logging out locally (token may already be invalid).
    } finally {
      clear()
    }
  }

  function hasPermission(permission: string): boolean {
    return user.value?.permissions.includes(permission) ?? false
  }

  return {
    token,
    user,
    hydrated,
    isAuthenticated,
    login,
    register,
    logout,
    fetchUser,
    setUser,
    hydrate,
    clear,
    hasPermission,
  }
})