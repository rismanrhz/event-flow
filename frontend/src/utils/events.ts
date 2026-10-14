import type { Event, ParticipantStatusKey, TaskPriorityKey, TaskStatusKey, User } from '@/types'

export function canCreateEvent(user: User | null): boolean {
  if (user === null) return false
  return user.role.key === 'super_admin' || user.role.key === 'event_owner'
}

export function canManageEvent(user: User | null, event: Event | null): boolean {
  if (user === null || event === null) return false
  return event.member_permissions.includes('edit_event')
}

export function canManageTasks(user: User | null, event: Event | null): boolean {
  if (user === null || event === null) return false
  return event.member_permissions.includes('manage_tasks')
}

export function canManageMembers(user: User | null, event: Event | null): boolean {
  if (user === null || event === null) return false
  if (user.role.key === 'super_admin') return true
  return user.role.key === 'event_owner' && event.creator?.id === user.id
}

export function canViewMembers(user: User | null, event: Event | null): boolean {
  if (user === null || event === null) return false
  if (canManageMembers(user, event)) return true
  return event.is_member
}

export function canSeeParticipants(user: User | null, event: Event | null): boolean {
  if (user === null || event === null) return false
  if (canManageMembers(user, event)) return true
  return event.member_permissions.includes('manage_participants') || event.member_permissions.includes('manage_attendance')
}

export function canManageParticipants(user: User | null, event: Event | null): boolean {
  if (user === null || event === null) return false
  if (canManageMembers(user, event)) return true
  return event.member_permissions.includes('manage_participants')
}

export function canSeeAttendance(user: User | null, event: Event | null): boolean {
  if (user === null || event === null) return false
  if (canManageMembers(user, event)) return true
  return event.member_permissions.includes('manage_attendance') || event.member_permissions.includes('manage_participants')
}

export const EVENT_PERMISSIONS: { key: string; label: string }[] = [
  { key: 'view_event', label: 'View Event' },
  { key: 'edit_event', label: 'Edit Event' },
  { key: 'manage_participants', label: 'Manage Participants' },
  { key: 'manage_tasks', label: 'Manage Tasks' },
  { key: 'manage_schedule', label: 'Manage Schedule' },
  { key: 'manage_budget', label: 'Manage Budget' },
  { key: 'manage_attendance', label: 'Manage Attendance' },
  { key: 'manage_certificates', label: 'Manage Certificates' },
  { key: 'view_reports', label: 'View Reports' },
]

export const TASK_STATUSES: { key: TaskStatusKey; label: string }[] = [
  { key: 'todo', label: 'Todo' },
  { key: 'in_progress', label: 'In progress' },
  { key: 'done', label: 'Done' },
]

export const TASK_PRIORITIES: { key: TaskPriorityKey; label: string }[] = [
  { key: 'low', label: 'Low' },
  { key: 'medium', label: 'Medium' },
  { key: 'high', label: 'High' },
]

export const PARTICIPANT_STATUSES: { key: ParticipantStatusKey; label: string; tone: 'neutral' | 'success' | 'warning' | 'danger' | 'primary' }[] = [
  { key: 'registered', label: 'Registered', tone: 'primary' },
  { key: 'checked_in', label: 'Checked in', tone: 'success' },
  { key: 'waitlisted', label: 'Waitlisted', tone: 'warning' },
  { key: 'cancelled', label: 'Cancelled', tone: 'danger' },
]

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
})

const dateTimeWithTimeFormatter = new Intl.DateTimeFormat('en-US', {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
})

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return 'TBA'
  return dateTimeFormatter.format(new Date(iso))
}

export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return 'TBA'
  return dateTimeWithTimeFormatter.format(new Date(iso))
}