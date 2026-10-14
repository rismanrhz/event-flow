import { client, unwrap } from './client'
import type { ApiEnvelope, PaginationMeta, Task, TaskInput, TaskStatusKey } from '@/types'

export interface TaskListParams {
  page?: number
  per_page?: number
  status?: TaskStatusKey
}

export interface TaskListPayload {
  items: Task[]
  meta: PaginationMeta | undefined
}

export const tasksApi = {
  async list(eventId: number, params: TaskListParams = {}): Promise<TaskListPayload> {
    const query: Record<string, string> = { per_page: String(params.per_page ?? 50) }
    if (params.page !== undefined) {
      query.page = String(params.page)
    }
    if (params.status) {
      query['filter[status]'] = params.status
    }

    const res = await client.get<ApiEnvelope<Task[]>>(`/events/${eventId}/tasks`, { params: query })
    return { items: unwrap(res.data), meta: res.data.meta }
  },

  async get(eventId: number, taskId: number): Promise<Task> {
    const res = await client.get<ApiEnvelope<Task>>(`/events/${eventId}/tasks/${taskId}`)
    return unwrap(res.data)
  },

  async create(eventId: number, input: TaskInput): Promise<Task> {
    const res = await client.post<ApiEnvelope<Task>>(`/events/${eventId}/tasks`, input)
    return unwrap(res.data)
  },

  async update(eventId: number, taskId: number, input: TaskInput): Promise<Task> {
    const res = await client.put<ApiEnvelope<Task>>(`/events/${eventId}/tasks/${taskId}`, input)
    return unwrap(res.data)
  },

  async remove(eventId: number, taskId: number): Promise<void> {
    await client.delete(`/events/${eventId}/tasks/${taskId}`)
  },
}