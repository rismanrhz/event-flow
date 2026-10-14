import axios, { AxiosError } from 'axios'
import type { ApiEnvelope } from '@/types'

export const TOKEN_KEY = 'eventflow_token'

export const UNAUTHORIZED_EVENT = 'eventflow:unauthorized'

export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>
  payload?: unknown

  constructor(message: string, status = 0, errors?: Record<string, string[]>, payload?: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.payload = payload
  }
}

export const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api/v1',
  headers: { Accept: 'application/json' },
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token !== null) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiEnvelope>) => {
    const status = error.response?.status ?? 0
    const message =
      error.response?.data?.message ??
      (status === 0 ? 'Network error. Please try again.' : 'Something went wrong.')
    const errors = error.response?.data?.errors
    const payload = error.response?.data?.data

    if (status === 401 && localStorage.getItem(TOKEN_KEY) !== null) {
      localStorage.removeItem(TOKEN_KEY)
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT))
    }

    return Promise.reject(new ApiError(message, status, errors, payload))
  },
)

export function unwrap<T>(envelope: ApiEnvelope<T>): T {
  return envelope.data
}