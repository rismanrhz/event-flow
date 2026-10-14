import { client, unwrap } from './client'
import type { ApiEnvelope, PaginationMeta, Participant, ParticipantListParams, ParticipantStats } from '@/types'

export interface ParticipantListPayload {
  items: Participant[]
  meta: PaginationMeta | undefined
}

export interface ParticipantCandidate {
  id: number
  name: string
  email: string
  role: { key: string; label: string }
}

export const participantsApi = {
  async list(eventId: number, params: ParticipantListParams = {}): Promise<ParticipantListPayload> {
    const query: Record<string, string> = { per_page: String(params.per_page ?? 15) }
    if (params.page !== undefined) query.page = String(params.page)
    if (params.status !== undefined) query['filter[status]'] = params.status
    if (params.search?.trim()) {
      query['filter[search]'] = params.search.trim()
    }
    const res = await client.get<ApiEnvelope<Participant[]>>(`/events/${eventId}/participants`, { params: query })
    return { items: unwrap(res.data), meta: res.data.meta }
  },

  async stats(eventId: number): Promise<ParticipantStats> {
    const res = await client.get<ApiEnvelope<ParticipantStats>>(`/events/${eventId}/participants/stats`)
    return unwrap(res.data)
  },

  async candidates(eventId: number): Promise<ParticipantCandidate[]> {
    const res = await client.get<ApiEnvelope<ParticipantCandidate[]>>(`/events/${eventId}/participant-candidates`)
    return unwrap(res.data)
  },

  async register(eventId: number): Promise<Participant> {
    const res = await client.post<ApiEnvelope<Participant>>(`/events/${eventId}/participants`)
    return unwrap(res.data)
  },

  async add(eventId: number, userId: number): Promise<Participant> {
    const res = await client.post<ApiEnvelope<Participant>>(`/events/${eventId}/participants`, { user_id: userId })
    return unwrap(res.data)
  },

  async mine(eventId: number): Promise<Participant> {
    const res = await client.get<ApiEnvelope<Participant>>(`/events/${eventId}/participants/mine`)
    return unwrap(res.data)
  },

  async checkIn(eventId: number, payload: { code?: string; participant_id?: number }): Promise<Participant> {
    const res = await client.post<ApiEnvelope<Participant>>(`/events/${eventId}/participants/check-in`, payload)
    return unwrap(res.data)
  },

  async cancel(eventId: number, participantId: number, reason?: string): Promise<Participant> {
    const res = await client.post<ApiEnvelope<Participant>>(`/events/${eventId}/participants/${participantId}/cancel`, {
      reason,
    })
    return unwrap(res.data)
  },

  async remove(eventId: number, participantId: number): Promise<void> {
    await client.delete(`/events/${eventId}/participants/${participantId}`)
  },
}

export { unwrap }