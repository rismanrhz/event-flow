import { client, unwrap } from './client'
import type { ApiEnvelope, Event, EventInput, EventListParams, EventStatusKey, PaginationMeta } from '@/types'

export interface EventListPayload {
  items: Event[]
  meta: PaginationMeta | undefined
}

export const eventsApi = {
  async list(params: EventListParams = {}): Promise<EventListPayload> {
    const res = await client.get<ApiEnvelope<Event[]>>('/events', { params: buildListParams(params) })
    return { items: unwrap(res.data), meta: res.data.meta }
  },

  async get(id: number): Promise<Event> {
    const res = await client.get<ApiEnvelope<Event>>(`/events/${id}`)
    return unwrap(res.data)
  },

  async create(input: EventInput): Promise<Event> {
    const res = await client.post<ApiEnvelope<Event>>('/events', buildPayload(input))
    return unwrap(res.data)
  },

  async update(id: number, input: EventInput): Promise<Event> {
    const res = await client.put<ApiEnvelope<Event>>(`/events/${id}`, buildPayload(input))
    return unwrap(res.data)
  },

  async remove(id: number): Promise<void> {
    await client.delete(`/events/${id}`)
  },
}

function buildListParams(params: EventListParams): Record<string, string> {
  const query: Record<string, string> = { per_page: String(params.per_page ?? 15) }

  if (params.page !== undefined) {
    query.page = String(params.page)
  }
  if (params.search?.trim()) {
    query['filter[title]'] = params.search.trim()
  }
  if (params.status) {
    query['filter[status]'] = params.status
  }

  return query
}

function buildPayload(input: EventInput): Record<string, unknown> | FormData {
  const payload: Record<string, unknown> = { title: input.title }

  for (const key of ['description', 'location', 'starts_at', 'ends_at', 'status'] as const) {
    const value = input[key]
    if (value !== undefined && value !== '') {
      payload[key] = key === 'starts_at' || key === 'ends_at' ? toIsoUtc(value) : value
    }
  }

  if (input.capacity !== undefined && input.capacity !== null) {
    payload.capacity = input.capacity
  }

  if (!input.cover) {
    return payload
  }

  const form = new FormData()
  for (const [key, value] of Object.entries(payload)) {
    form.append(key, String(value))
  }
  form.append('cover', input.cover)

  return form
}

function toIsoUtc(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toISOString()
}

export const EVENT_STATUSES: { key: EventStatusKey; label: string }[] = [
  { key: 'draft', label: 'Draft' },
  { key: 'published', label: 'Published' },
  { key: 'completed', label: 'Completed' },
  { key: 'cancelled', label: 'Cancelled' },
]