import { client, unwrap } from './client'
import type { DashboardOverview } from '@/types'

export interface DashboardSelection {
  events: DashboardOverview['events']
  currentEventId: number | null
}

export const dashboardApi = {
  async overview(eventId?: number): Promise<DashboardOverview> {
    const res = await client.get('/dashboard/overview', { params: eventId ? { event_id: eventId } : {} })
    return unwrap(res.data)
  },
}