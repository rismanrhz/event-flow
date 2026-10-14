import { client, unwrap } from './client'
import type { ApiEnvelope, EventMember, MemberInput } from '@/types'

export interface MemberCandidate {
  id: number
  name: string
  email: string
  role: { key: string; label: string }
}

export const eventMembersApi = {
  async list(eventId: number): Promise<EventMember[]> {
    const res = await client.get<ApiEnvelope<EventMember[]>>(`/events/${eventId}/members`)
    return unwrap(res.data)
  },

  async candidates(eventId: number): Promise<MemberCandidate[]> {
    const res = await client.get<ApiEnvelope<MemberCandidate[]>>(`/events/${eventId}/member-candidates`)
    return unwrap(res.data)
  },

  async create(eventId: number, input: MemberInput): Promise<EventMember> {
    const res = await client.post<ApiEnvelope<EventMember>>(`/events/${eventId}/members`, input)
    return unwrap(res.data)
  },

  async update(eventId: number, memberId: number, input: MemberInput): Promise<EventMember> {
    const res = await client.put<ApiEnvelope<EventMember>>(`/events/${eventId}/members/${memberId}`, input)
    return unwrap(res.data)
  },

  async remove(eventId: number, memberId: number): Promise<void> {
    await client.delete(`/events/${eventId}/members/${memberId}`)
  },
}