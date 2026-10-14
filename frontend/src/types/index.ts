export type RoleKey = 'super_admin' | 'event_owner' | 'committee_member' | 'participant'

export interface RoleInfo {
  key: RoleKey
  label: string
}

export interface User {
  id: number
  name: string
  email: string
  role: RoleInfo
  phone: string | null
  avatar_url: string | null
  institution: string | null
  job_title: string | null
  address: string | null
  is_active: boolean
  permissions: string[]
  last_login_at: string | null
  created_at: string
}

export interface ApiEnvelope<T = unknown> {
  success: boolean
  message: string
  data: T
  meta?: PaginationMeta
  errors?: Record<string, string[]>
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface SessionInfo {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  created_at: string
  expires_at: string | null
}

export type EventStatusKey = 'draft' | 'published' | 'completed' | 'cancelled'

export interface EventStatusInfo {
  key: EventStatusKey
  label: string
  tone: 'neutral' | 'success' | 'primary' | 'danger'
}

export interface Event {
  id: number
  title: string
  slug: string
  description: string | null
  location: string | null
  starts_at: string
  ends_at: string | null
  capacity: number | null
  status: EventStatusInfo
  cover_url: string | null
  creator: { id: number; name: string } | null
  is_member: boolean
  member_permissions: string[]
  created_at: string
  updated_at: string
}

export interface EventInput {
  title: string
  description?: string
  location?: string
  starts_at?: string
  ends_at?: string
  capacity?: number
  status?: EventStatusKey
  cover?: File | null
}

export interface EventListParams {
  page?: number
  per_page?: number
  search?: string
  status?: EventStatusKey
}

export interface EventMember {
  id: number
  role_title: string | null
  user: {
    id: number
    name: string
    email: string
    role: RoleInfo
  }
  permissions: string[]
  created_at: string
}

export interface MemberInput {
  user_id?: number
  role_title?: string
  permissions?: string[]
}

export type TaskStatusKey = 'todo' | 'in_progress' | 'done'

export type TaskPriorityKey = 'low' | 'medium' | 'high'

export interface TaskStatusInfo {
  key: TaskStatusKey
  label: string
  tone: 'neutral' | 'success' | 'warning' | 'danger' | 'primary'
}

export interface TaskPriorityInfo {
  key: TaskPriorityKey
  label: string
}

export interface Task {
  id: number
  event_id: number
  title: string
  description: string | null
  status: TaskStatusInfo
  priority: TaskPriorityInfo
  due_at: string | null
  assignee: { id: number; name: string } | null
  created_at: string
  updated_at: string
}

export interface TaskInput {
  title?: string
  description?: string
  status?: TaskStatusKey
  priority?: TaskPriorityKey
  due_at?: string
  assignee_id?: number | null
}

export type ParticipantStatusKey = 'registered' | 'checked_in' | 'waitlisted' | 'cancelled'

export interface ParticipantStatusInfo {
  key: ParticipantStatusKey
  label: string
  tone: 'neutral' | 'success' | 'warning' | 'danger' | 'primary'
}

export interface Participant {
  id: number
  event_id: number
  registration_code: string
  status: ParticipantStatusInfo
  user: {
    id: number
    name: string
    email: string
  }
  event?: { id: number; title: string; starts_at: string | null } | null
  checked_in_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  created_at: string
  updated_at: string
}

export interface ParticipantStats {
  total: number
  registered: number
  checked_in: number
  waitlisted: number
  cancelled: number
  capacity: number | null
  remaining: number | null
}

export interface ParticipantListParams {
  page?: number
  per_page?: number
  status?: ParticipantStatusKey
  search?: string
}

export type InsightSeverityKey = 'positive' | 'normal' | 'warning' | 'critical'

export interface HealthInfo {
  score: number
  grade: string
}

export interface HealthFactor extends HealthInfo {
  key: string
  label: string
  weight: number
}

export type InsightActionTarget = 'participants' | 'tasks' | 'event'

export interface InsightAction {
  label: string
  target: InsightActionTarget
  params: Record<string, string>
}

export interface Insight {
  key: string
  severity: InsightSeverityKey
  label: string
  tone: 'neutral' | 'success' | 'warning' | 'danger' | 'primary'
  message: string
  action: InsightAction | null
}

export interface EventSelectorItem {
  value: number
  label: string
  slug: string
  status: EventStatusInfo
  location: string | null
  starts_at: string | null
  ends_at: string | null
  capacity: number | null
  health: HealthInfo
  can_access_data: boolean
}

export interface GrowthMetric {
  current: number
  previous: number | null
  delta: number | null
}

export interface KpiOverdue {
  value: number
  total: number
  done: number
  completion: number
}

export interface KpiCapacity {
  value: number
  capacity: number
  remaining: number | null
  filled_percent: number | null
}

export interface DashboardKpi {
  activity: GrowthMetric | null
  attendance: (GrowthMetric & { applicable: boolean }) | null
  task_completion: GrowthMetric | null
  overdue: KpiOverdue | null
  capacity: KpiCapacity | null
  budget: null
}

export interface TrendSeries {
  unit: string
  labels: string[]
  registered: number[]
  checked_in: number[]
  cumulative: number[]
}

export interface AttendanceSummary {
  registered: number
  checked_in: number
  no_show: number
  waitlisted: number
  cancelled: number
  total: number
  rate: number
  hours: { labels: string[]; values: number[] }
}

export interface FunnelSummary {
  registered: number
  checked_in: number
  no_show: number
  conversion: number
}

export interface TaskSummary {
  total: number
  done: number
  todo: number
  in_progress: number
  overdue: number
  completion: number
  by_status: (TaskStatusInfo & { count: number })[]
  by_priority: (TaskPriorityInfo & { count: number; done: number; completion: number })[]
}

export interface ScheduleItem {
  type: 'event' | 'task'
  id?: number
  title: string
  at: string
  location?: string | null
  priority: TaskPriorityInfo | null
  status: TaskStatusInfo | null
}

export interface ActivityItem {
  id: number
  action: string
  module: string
  description: string
  user: { id: number; name: string } | null
  iso: string
  human: string
}

export interface DashboardEventDetail {
  id: number
  title: string
  slug: string
  location: string | null
  starts_at: string | null
  ends_at: string | null
  capacity: number | null
  status: EventStatusInfo
  creator: { id: number; name: string } | null
  can: { participants: boolean; tasks: boolean }
}

export interface DashboardOrganizerData {
  mode: 'organizer'
  events: EventSelectorItem[]
  event: DashboardEventDetail | null
  kpi: DashboardKpi
  trend: TrendSeries | null
  attendance: AttendanceSummary | null
  funnel: FunnelSummary | null
  tasks: TaskSummary | null
  schedule: ScheduleItem[]
  activity: ActivityItem[]
  health: HealthInfo & { factors: HealthFactor[] }
  insights: Insight[]
  attention: Insight[]
}

export interface TicketSummary {
  id: number
  registration_code: string
  status: ParticipantStatusInfo
  event: { id: number; title: string; starts_at: string | null; location: string | null } | null
  created_at: string
}

export interface DashboardParticipantData {
  mode: 'participant'
  events: EventSelectorItem[]
  my_tickets: TicketSummary[]
}

export type DashboardOverview = DashboardOrganizerData | DashboardParticipantData

export type AnalyticsRangeKey = '7' | '30' | '90'

export interface ParticipantProfile {
  total: number
  breakdown: (ParticipantStatusInfo & { key: ParticipantStatusKey; count: number })[]
  institutions: { label: string; count: number }[]
}

export interface AnalyticsOverview {
  event: DashboardEventDetail
  range: number
  kpi: DashboardKpi
  trend: TrendSeries | null
  participants: ParticipantProfile | null
  attendance: AttendanceSummary | null
  funnel: FunnelSummary | null
  tasks: TaskSummary | null
  health: HealthInfo
}