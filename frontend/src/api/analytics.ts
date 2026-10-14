import { client, unwrap } from './client'
import type { AnalyticsOverview, AnalyticsRangeKey } from '@/types'

export const analyticsApi = {
  async get(eventId: number, range: AnalyticsRangeKey = '30'): Promise<AnalyticsOverview> {
    const res = await client.get(`/events/${eventId}/analytics`, { params: { range } })
    return unwrap(res.data)
  },
}