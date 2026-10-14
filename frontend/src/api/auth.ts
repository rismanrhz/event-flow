import { client, unwrap } from './client'
import type { ApiEnvelope, User } from '@/types'

export interface AuthPayload {
  user: User
  token: string
}

export interface ForgotPasswordPayload {
  reset_url?: string | null
}

export interface RegisterInput {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface LoginInput {
  email: string
  password: string
  remember?: boolean
}

export interface ResetPasswordInput {
  email: string
  token: string
  password: string
  password_confirmation: string
}

export interface UpdateProfileInput {
  name: string
  email: string
  phone?: string
  institution?: string
  job_title?: string
  address?: string
  avatar?: File | null
}

export interface ChangePasswordInput {
  current_password: string
  new_password: string
  new_password_confirmation: string
}

export const authApi = {
  async register(input: RegisterInput): Promise<AuthPayload> {
    const res = await client.post<ApiEnvelope<AuthPayload>>('/auth/register', input)
    return unwrap(res.data)
  },

  async login(input: LoginInput): Promise<AuthPayload> {
    const res = await client.post<ApiEnvelope<AuthPayload>>('/auth/login', input)
    return unwrap(res.data)
  },

  async logout(): Promise<void> {
    await client.post('/auth/logout')
  },

  async me(): Promise<User> {
    const res = await client.get<ApiEnvelope<User>>('/auth/me')
    return unwrap(res.data)
  },

  async forgotPassword(email: string): Promise<ForgotPasswordPayload | null> {
    const res = await client.post<ApiEnvelope<ForgotPasswordPayload | null>>(
      '/auth/forgot-password',
      { email },
    )
    return unwrap(res.data)
  },

  async resetPassword(input: ResetPasswordInput): Promise<AuthPayload> {
    const res = await client.post<ApiEnvelope<AuthPayload>>('/auth/reset-password', input)
    return unwrap(res.data)
  },

  async updateProfile(input: UpdateProfileInput): Promise<User> {
    const res = await client.put<ApiEnvelope<User>>('/auth/profile', buildProfilePayload(input))
    return unwrap(res.data)
  },

  async changePassword(input: ChangePasswordInput): Promise<void> {
    await client.post('/auth/change-password', input)
  },
}

function buildProfilePayload(input: UpdateProfileInput): Record<string, unknown> | FormData {
  const payload: Record<string, unknown> = { name: input.name, email: input.email }

  for (const key of ['phone', 'institution', 'job_title', 'address'] as const) {
    const value = input[key]
    if (value !== undefined && value !== '') {
      payload[key] = value
    }
  }

  if (!input.avatar) {
    return payload
  }

  const form = new FormData()
  for (const [key, value] of Object.entries(payload)) {
    form.append(key, String(value))
  }
  form.append('avatar', input.avatar)

  return form
}