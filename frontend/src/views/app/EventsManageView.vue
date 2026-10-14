<script setup lang="ts">
import {
  ArrowLeft,
  ListChecks,
  Loader2,
  Pencil,
  Plus,
  QrCode,
  Trash2,
  UserCheck,
  UserPlus,
  Users,
} from '@lucide/vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventMembersApi, type MemberCandidate } from '@/api/eventMembers'
import { eventsApi } from '@/api/events'
import { participantsApi, type ParticipantCandidate } from '@/api/participants'
import { tasksApi } from '@/api/tasks'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type {
  Event,
  EventMember,
  Participant,
  ParticipantStats,
  ParticipantStatusKey,
  Task,
  TaskPriorityKey,
  TaskStatusKey,
} from '@/types'
import {
  EVENT_PERMISSIONS,
  PARTICIPANT_STATUSES,
  TASK_PRIORITIES,
  TASK_STATUSES,
  canManageMembers,
  canManageParticipants,
  canManageTasks,
  canSeeAttendance,
  canSeeParticipants,
  canViewMembers,
  formatDate,
} from '@/utils/events'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const eventId = computed(() => Number(route.params.id))
const event = ref<Event | null>(null)
const loading = ref(true)
const activeTab = ref<'committee' | 'tasks' | 'participants' | 'attendance'>('committee')

const members = ref<EventMember[]>([])
const candidates = ref<MemberCandidate[]>([])
const membersLoading = ref(false)
const memberFormVisible = ref(false)
const editingMemberId = ref<number | null>(null)
const memberSaving = ref(false)
const removingMemberId = ref<number | null>(null)
const memberForm = reactive({ user_id: '', role_title: '', permissions: [] as string[] })

const tasks = ref<Task[]>([])
const tasksLoading = ref(false)
const taskStatusFilter = ref<TaskStatusKey | ''>('')
const createTaskVisible = ref(false)
const editingTaskId = ref<number | null>(null)
const taskSaving = ref(false)
const deletingTaskId = ref<number | null>(null)
const taskForm = reactive({ title: '', description: '', assignee_id: '', due_at: '', priority: 'medium' as TaskPriorityKey })
const editForm = reactive({
  title: '',
  description: '',
  assignee_id: '',
  due_at: '',
  priority: 'medium' as TaskPriorityKey,
  status: 'todo' as TaskStatusKey,
})

const participants = ref<Participant[]>([])
const participantsLoading = ref(false)
const participantStats = ref<ParticipantStats | null>(null)
const participantStatusFilter = ref<ParticipantStatusKey | ''>('')
const participantSearch = ref('')
const addParticipantVisible = ref(false)
const participantCandidates = ref<ParticipantCandidate[]>([])
const participantSaving = ref(false)
const cancellingParticipantId = ref<number | null>(null)
const removingParticipantId = ref<number | null>(null)

const attendanceCode = ref('')
const attendanceChecking = ref(false)
const checkingInId = ref<number | null>(null)
const attendanceList = ref<Participant[]>([])
const attendanceLoading = ref(false)
const scannerStarted = ref(false)
let scanner: { stop: () => Promise<void> } | null = null

const canEditMembers = computed(() => canManageMembers(auth.user, event.value))
const canSeeCommittee = computed(() => canViewMembers(auth.user, event.value))
const canSeeTasks = computed(() => canManageTasks(auth.user, event.value))
const canSeeParticipantsTab = computed(() => canSeeParticipants(auth.user, event.value))
const canManageParticipantsTab = computed(() => canManageParticipants(auth.user, event.value))
const canSeeAttendanceTab = computed(() => canSeeAttendance(auth.user, event.value))

const assigneeOptions = computed(() => {
  if (event.value === null) return []
  const list: { id: number; name: string }[] = []
  const seen = new Set<number>()
  const push = (id: number, name: string): void => {
    if (!seen.has(id)) {
      seen.add(id)
      list.push({ id, name })
    }
  }
  for (const member of members.value) push(member.user.id, member.user.name)
  if (event.value.creator) push(event.value.creator.id, event.value.creator.name)
  if (auth.user) push(auth.user.id, auth.user.name)
  return list
})

async function loadEvent(): Promise<void> {
  loading.value = true
  try {
    const loaded = await eventsApi.get(eventId.value)

    if (
      !canViewMembers(auth.user, loaded) &&
      !canManageTasks(auth.user, loaded) &&
      !canSeeParticipants(auth.user, loaded) &&
      !canSeeAttendance(auth.user, loaded)
    ) {
      toast.error("You don't have access to manage this event.")
      void router.replace({ name: 'app.events' })
      return
    }

    event.value = loaded

    const requestTab = String(route.query.tab ?? '')
    const validTabs = ['committee', 'tasks', 'participants', 'attendance'] as const
    type ManageTab = (typeof validTabs)[number]
    const requested = (validTabs as readonly string[]).includes(requestTab) ? (requestTab as ManageTab) : null
    const firstAllowed: ManageTab = canSeeCommittee.value
      ? 'committee'
      : canSeeParticipantsTab.value
        ? 'participants'
        : canSeeAttendanceTab.value
          ? 'attendance'
          : 'tasks'

    activeTab.value = requested ?? firstAllowed
    if (activeTab.value === 'committee') {
      await loadMembers()
    } else if (activeTab.value === 'tasks') {
      await loadTasks()
    } else if (activeTab.value === 'participants') {
      await loadParticipantsTab()
    } else {
      await loadAttendanceTab()
    }
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load event.')
    void router.replace({ name: 'app.events' })
  } finally {
    loading.value = false
  }
}

async function loadMembers(): Promise<void> {
  membersLoading.value = true
  try {
    members.value = await eventMembersApi.list(eventId.value)
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load committee.')
  } finally {
    membersLoading.value = false
  }
}

async function loadCandidates(): Promise<void> {
  try {
    candidates.value = await eventMembersApi.candidates(eventId.value)
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load candidates.')
  }
}

function resetMemberForm(): void {
  memberForm.user_id = ''
  memberForm.role_title = ''
  memberForm.permissions = []
}

function togglePermission(target: { permissions: string[] }, key: string): void {
  const index = target.permissions.indexOf(key)
  if (index >= 0) {
    target.permissions.splice(index, 1)
  } else {
    target.permissions.push(key)
  }
}

async function openAddMember(): Promise<void> {
  resetMemberForm()
  editingMemberId.value = null
  memberFormVisible.value = true
  await loadCandidates()
}

async function startEditMember(member: EventMember): Promise<void> {
  editingMemberId.value = member.id
  memberForm.user_id = String(member.user.id)
  memberForm.role_title = member.role_title ?? ''
  memberForm.permissions = [...member.permissions]
  memberFormVisible.value = true
  if (candidates.value.length === 0) {
    await loadCandidates()
  }
}

async function saveMember(): Promise<void> {
  if (editingMemberId.value === null && memberForm.user_id === '') {
    toast.error('Select a user to add.')
    return
  }

  memberSaving.value = true
  try {
    const input = {
      role_title: memberForm.role_title || undefined,
      permissions: memberForm.permissions,
    }
    if (editingMemberId.value === null) {
      await eventMembersApi.create(eventId.value, { ...input, user_id: Number(memberForm.user_id) })
      toast.success('Committee member added.')
    } else {
      await eventMembersApi.update(eventId.value, editingMemberId.value, input)
      toast.success('Committee member updated.')
    }
    memberFormVisible.value = false
    editingMemberId.value = null
    await loadMembers()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to save member.')
  } finally {
    memberSaving.value = false
  }
}

async function removeMember(member: EventMember): Promise<void> {
  if (!window.confirm(`Remove ${member.user.name} from this committee?`)) return

  removingMemberId.value = member.id
  try {
    await eventMembersApi.remove(eventId.value, member.id)
    toast.success('Committee member removed.')
    await loadMembers()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to remove member.')
  } finally {
    removingMemberId.value = null
  }
}

async function loadTasks(): Promise<void> {
  tasksLoading.value = true
  try {
    const { items } = await tasksApi.list(eventId.value, {
      per_page: 100,
      status: taskStatusFilter.value === '' ? undefined : taskStatusFilter.value,
    })
    tasks.value = items
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load tasks.')
  } finally {
    tasksLoading.value = false
  }
}

watch(taskStatusFilter, () => {
  void loadTasks()
})

watch([participantStatusFilter, participantSearch], () => {
  void loadParticipants()
})

watch(
  () => activeTab.value,
  () => {
    if (activeTab.value === 'committee') {
      void loadMembers()
    } else if (activeTab.value === 'tasks') {
      void loadTasks()
    } else if (activeTab.value === 'participants') {
      void loadParticipantsTab()
    } else {
      void loadAttendanceTab()
    }
  },
)

function resetTaskForm(): void {
  taskForm.title = ''
  taskForm.description = ''
  taskForm.assignee_id = ''
  taskForm.due_at = ''
  taskForm.priority = 'medium'
}

function toIsoUtc(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toISOString()
}

function toDatetimeLocal(iso: string): string {
  const date = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
    date.getHours(),
  )}:${pad(date.getMinutes())}`
}

function payloadFor(form: {
  title: string
  description: string
  assignee_id: string
  due_at: string
  priority: TaskPriorityKey
  status?: TaskStatusKey
}): Record<string, unknown> {
  const payload: Record<string, unknown> = { title: form.title, priority: form.priority }
  if (form.description.trim() !== '') payload.description = form.description.trim()
  if (form.assignee_id !== '') payload.assignee_id = Number(form.assignee_id)
  if (form.due_at !== '') payload.due_at = toIsoUtc(form.due_at)
  if (form.status !== undefined) payload.status = form.status
  return payload
}

async function createTask(): Promise<void> {
  if (taskForm.title.trim() === '') {
    toast.error('Task title is required.')
    return
  }

  taskSaving.value = true
  try {
    await tasksApi.create(eventId.value, payloadFor(taskForm))
    toast.success('Task created.')
    createTaskVisible.value = false
    resetTaskForm()
    await loadTasks()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to create task.')
  } finally {
    taskSaving.value = false
  }
}

function startEditTask(task: Task): void {
  editingTaskId.value = task.id
  editForm.title = task.title
  editForm.description = task.description ?? ''
  editForm.assignee_id = task.assignee ? String(task.assignee.id) : ''
  editForm.due_at = task.due_at ? toDatetimeLocal(task.due_at) : ''
  editForm.priority = task.priority.key
  editForm.status = task.status.key
}

function cancelEditTask(): void {
  editingTaskId.value = null
}

async function saveEditTask(task: Task): Promise<void> {
  if (editForm.title.trim() === '') {
    toast.error('Task title is required.')
    return
  }

  taskSaving.value = true
  try {
    await tasksApi.update(eventId.value, task.id, payloadFor(editForm))
    toast.success('Task updated.')
    editingTaskId.value = null
    await loadTasks()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to update task.')
  } finally {
    taskSaving.value = false
  }
}

async function changeTaskStatus(task: Task, status: TaskStatusKey): Promise<void> {
  try {
    await tasksApi.update(eventId.value, task.id, { status })
    toast.success(`Task marked as ${TASK_STATUSES.find((s) => s.key === status)?.label.toLowerCase() ?? status}.`)
    await loadTasks()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to update task status.')
  }
}

async function deleteTask(task: Task): Promise<void> {
  if (!window.confirm(`Delete task "${task.title}"?`)) return

  deletingTaskId.value = task.id
  try {
    await tasksApi.remove(eventId.value, task.id)
    toast.success('Task deleted.')
    await loadTasks()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to delete task.')
  } finally {
    deletingTaskId.value = null
  }
}

function priorityTone(priority: TaskPriorityKey): 'neutral' | 'warning' | 'danger' {
  if (priority === 'high') return 'danger'
  if (priority === 'medium') return 'warning'
  return 'neutral'
}

function statusTone(status: TaskStatusKey): 'neutral' | 'primary' | 'success' {
  if (status === 'done') return 'success'
  if (status === 'in_progress') return 'primary'
  return 'neutral'
}

function statusInfo(key: string): { label: string; tone: 'neutral' | 'success' | 'warning' | 'danger' | 'primary' } {
  return PARTICIPANT_STATUSES.find((s) => s.key === key) ?? { label: key, tone: 'neutral' }
}

async function loadStats(): Promise<void> {
  try {
    participantStats.value = await participantsApi.stats(eventId.value)
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load participant stats.')
  }
}

async function loadParticipants(): Promise<void> {
  participantsLoading.value = true
  try {
    const { items } = await participantsApi.list(eventId.value, {
      per_page: 100,
      status: participantStatusFilter.value === '' ? undefined : participantStatusFilter.value,
      search: participantSearch.value === '' ? undefined : participantSearch.value,
    })
    participants.value = items
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load participants.')
  } finally {
    participantsLoading.value = false
  }
}

async function loadAttendance(): Promise<void> {
  attendanceLoading.value = true
  try {
    const { items } = await participantsApi.list(eventId.value, { per_page: 100, status: 'registered' })
    attendanceList.value = items
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load attendance queue.')
  } finally {
    attendanceLoading.value = false
  }
}

async function loadParticipantsTab(): Promise<void> {
  await Promise.all([loadStats(), loadParticipants()])
}

async function loadAttendanceTab(): Promise<void> {
  await Promise.all([loadStats(), loadAttendance()])
}

async function openAddParticipant(): Promise<void> {
  addParticipantVisible.value = true
  try {
    participantCandidates.value = await participantsApi.candidates(eventId.value)
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to load candidates.')
  }
}

async function addParticipant(userId: number): Promise<void> {
  participantSaving.value = true
  try {
    await participantsApi.add(eventId.value, userId)
    toast.success('Participant added.')
    addParticipantVisible.value = false
    await loadParticipantsTab()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to add participant.')
  } finally {
    participantSaving.value = false
  }
}

async function cancelParticipant(participant: Participant): Promise<void> {
  const reason = window.prompt(`Cancel registration for ${participant.user.name}? Reason (optional)`)
  if (reason === null) return

  cancellingParticipantId.value = participant.id
  try {
    await participantsApi.cancel(eventId.value, participant.id, reason || undefined)
    toast.success('Registration cancelled.')
    await loadParticipantsTab()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to cancel registration.')
  } finally {
    cancellingParticipantId.value = null
  }
}

async function removeParticipant(participant: Participant): Promise<void> {
  if (!window.confirm(`Remove ${participant.user.name} from participants?`)) return

  removingParticipantId.value = participant.id
  try {
    await participantsApi.remove(eventId.value, participant.id)
    toast.success('Participant removed.')
    await loadParticipantsTab()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to remove participant.')
  } finally {
    removingParticipantId.value = null
  }
}

async function checkInByCode(code: string): Promise<void> {
  const trimmed = code.trim()
  if (trimmed === '') {
    toast.error('Enter a registration code first.')
    return
  }

  attendanceChecking.value = true
  try {
    const result = await participantsApi.checkIn(eventId.value, { code: trimmed })
    toast.success(`${result.user.name} checked in.`)
    attendanceCode.value = ''
    await loadAttendanceTab()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to check in.')
  } finally {
    attendanceChecking.value = false
  }
}

async function checkInParticipant(participant: Participant): Promise<void> {
  checkingInId.value = participant.id
  try {
    const result = await participantsApi.checkIn(eventId.value, { participant_id: participant.id })
    toast.success(`${result.user.name} checked in.`)
    await loadAttendanceTab()
  } catch (error) {
    toast.error(error instanceof Error ? error.message : 'Failed to check in.')
  } finally {
    checkingInId.value = null
  }
}

async function toggleScanner(): Promise<void> {
  if (scannerStarted.value) {
    stopScanner()
    return
  }

  const { Html5Qrcode } = await import('html5-qrcode')
  const region = document.getElementById('scanner-region')
  if (region === null) return

  try {
    const instance = new Html5Qrcode('scanner-region')
    scanner = instance
    await instance.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 240 } },
      (decodedText) => {
        void (async () => {
          stopScanner()
          await checkInByCode(decodedText)
        })()
      },
      () => {
        /* keep scanning */
      },
    )
    scannerStarted.value = true
  } catch (error) {
    toast.error('Camera unavailable or permission denied.')
    scanner = null
    scannerStarted.value = false
  }
}

async function stopScanner(): Promise<void> {
  if (scanner !== null) {
    try {
      await scanner.stop()
    } catch {
      /* already stopped */
    }
    scanner = null
  }
  scannerStarted.value = false
}

onMounted(() => {
  void loadEvent()
})
</script>

<template>
  <div class="mx-auto max-w-6xl">
    <div v-if="loading" class="flex justify-center py-16">
      <Loader2 class="h-8 w-8 animate-spin text-primary-600" />
    </div>

    <template v-else-if="event">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <BaseButton variant="ghost" size="sm" @click="router.push({ name: 'app.events' })">
            <ArrowLeft class="h-4 w-4" />
            Events
          </BaseButton>
          <div>
            <h1 class="text-2xl font-semibold tracking-tight">Manage event</h1>
            <p class="text-sm text-slate-500">{{ event.title }}</p>
          </div>
        </div>
      </div>

      <nav class="mt-6 flex items-center gap-2 overflow-x-auto border-b border-slate-200">
        <button
          v-if="canSeeCommittee"
          type="button"
          :class="[
            '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium',
            activeTab === 'committee'
              ? 'border-primary-600 text-primary-700'
              : 'border-transparent text-slate-500 hover:text-slate-700',
          ]"
          @click="activeTab = 'committee'"
        >
          <Users class="h-4 w-4" />
          Committee
        </button>
        <button
          v-if="canSeeTasks"
          type="button"
          :class="[
            '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium',
            activeTab === 'tasks'
              ? 'border-primary-600 text-primary-700'
              : 'border-transparent text-slate-500 hover:text-slate-700',
          ]"
          @click="activeTab = 'tasks'"
        >
          <ListChecks class="h-4 w-4" />
          Tasks
        </button>
        <button
          v-if="canSeeParticipantsTab"
          type="button"
          :class="[
            '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium',
            activeTab === 'participants'
              ? 'border-primary-600 text-primary-700'
              : 'border-transparent text-slate-500 hover:text-slate-700',
          ]"
          @click="activeTab = 'participants'"
        >
          <UserCheck class="h-4 w-4" />
          Participants
        </button>
        <button
          v-if="canSeeAttendanceTab"
          type="button"
          :class="[
            '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium',
            activeTab === 'attendance'
              ? 'border-primary-600 text-primary-700'
              : 'border-transparent text-slate-500 hover:text-slate-700',
          ]"
          @click="activeTab = 'attendance'"
        >
          <QrCode class="h-4 w-4" />
          Attendance
        </button>
      </nav>

      <div class="mt-6">
        <section v-if="activeTab === 'committee' && canSeeCommittee" class="space-y-4">
          <div class="flex justify-end">
            <BaseButton v-if="canEditMembers" size="sm" @click="openAddMember">
              <UserPlus class="h-4 w-4" />
              Add member
            </BaseButton>
          </div>

          <BaseCard v-if="memberFormVisible" :padded="false" class="space-y-4 p-5">
            <h3 class="text-sm font-semibold text-slate-900">
              {{ editingMemberId === null ? 'Add committee member' : 'Edit committee member' }}
            </h3>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">User</label>
                <select
                  v-model="memberForm.user_id"
                  :disabled="editingMemberId !== null"
                  class="input-base"
                  aria-label="Member user"
                >
                  <option value="">Select a user…</option>
                  <option v-for="candidate in candidates" :key="candidate.id" :value="String(candidate.id)">
                    {{ candidate.name }} — {{ candidate.role.label }}
                  </option>
                </select>
              </div>
              <BaseInput v-model="memberForm.role_title" label="Role title" placeholder="e.g. Secretary" />
            </div>
            <div>
              <span class="mb-1.5 block text-sm font-medium text-slate-700">Permissions</span>
              <div class="grid gap-2 sm:grid-cols-3">
                <label
                  v-for="permission in EVENT_PERMISSIONS"
                  :key="permission.key"
                  class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"
                >
                  <input
                    type="checkbox"
                    :checked="memberForm.permissions.includes(permission.key)"
                    class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                    @change="togglePermission(memberForm, permission.key)"
                  />
                  {{ permission.label }}
                </label>
              </div>
            </div>
            <div class="flex justify-end gap-2">
              <BaseButton size="sm" variant="secondary" @click="memberFormVisible = false">Cancel</BaseButton>
              <BaseButton size="sm" :loading="memberSaving" @click="saveMember">Save</BaseButton>
            </div>
          </BaseCard>

          <div v-if="membersLoading" class="flex justify-center py-10">
            <Loader2 class="h-7 w-7 animate-spin text-primary-600" />
          </div>

          <div
            v-else-if="members.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-300 py-14 text-center"
          >
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
              <Users class="h-6 w-6" />
            </span>
            <p class="text-sm text-slate-500">No committee members yet.</p>
          </div>

          <ul v-else class="space-y-3">
            <BaseCard v-for="member in members" :key="member.id" :padded="false" class="p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <span
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700"
                  >
                    {{ member.user.name.slice(0, 1).toUpperCase() }}
                  </span>
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="text-sm font-semibold text-slate-900">{{ member.user.name }}</span>
                      <BaseBadge v-if="member.role_title" tone="primary">{{ member.role_title }}</BaseBadge>
                      <BaseBadge>{{ member.user.role.label }}</BaseBadge>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">{{ member.user.email }}</p>
                  </div>
                </div>
                <div v-if="canEditMembers" class="flex items-center gap-2">
                  <BaseButton size="sm" variant="secondary" @click="startEditMember(member)">
                    <Pencil class="h-4 w-4" />
                    Edit
                  </BaseButton>
                  <BaseButton size="sm" variant="danger" :loading="removingMemberId === member.id" @click="removeMember(member)">
                    <Trash2 class="h-4 w-4" />
                    Remove
                  </BaseButton>
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-1.5">
                <BaseBadge v-if="member.permissions.length === 0" tone="neutral">No permissions</BaseBadge>
                <BaseBadge v-for="permission in member.permissions" :key="permission">{{ permission }}</BaseBadge>
              </div>
            </BaseCard>
          </ul>
        </section>

        <section v-if="activeTab === 'tasks' && canSeeTasks" class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <select v-model="taskStatusFilter" class="input-base sm:w-48" aria-label="Filter tasks by status">
              <option value="">All statuses</option>
              <option v-for="status in TASK_STATUSES" :key="status.key" :value="status.key">{{ status.label }}</option>
            </select>
            <BaseButton size="sm" @click="createTaskVisible = !createTaskVisible">
              <Plus class="h-4 w-4" />
              New task
            </BaseButton>
          </div>

          <BaseCard v-if="createTaskVisible" :padded="false" class="space-y-4 p-5">
            <h3 class="text-sm font-semibold text-slate-900">Create task</h3>
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Title</label>
                <input v-model="taskForm.title" type="text" class="input-base" placeholder="Task title" />
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                <textarea
                  v-model="taskForm.description"
                  rows="3"
                  class="input-base min-h-[96px] w-full"
                  placeholder="Optional details…"
                />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Assignee</label>
                <select v-model="taskForm.assignee_id" class="input-base" aria-label="Task assignee">
                  <option value="">Unassigned</option>
                  <option v-for="user in assigneeOptions" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Due date</label>
                <input v-model="taskForm.due_at" type="datetime-local" class="input-base" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Priority</label>
                <select v-model="taskForm.priority" class="input-base" aria-label="Task priority">
                  <option v-for="priority in TASK_PRIORITIES" :key="priority.key" :value="priority.key">
                    {{ priority.label }}
                  </option>
                </select>
              </div>
            </div>
            <div class="flex justify-end gap-2">
              <BaseButton size="sm" variant="secondary" @click="createTaskVisible = false">Cancel</BaseButton>
              <BaseButton size="sm" :loading="taskSaving" @click="createTask">Create task</BaseButton>
            </div>
          </BaseCard>

          <div v-if="tasksLoading" class="flex justify-center py-10">
            <Loader2 class="h-7 w-7 animate-spin text-primary-600" />
          </div>

          <div
            v-else-if="tasks.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-300 py-14 text-center"
          >
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
              <ListChecks class="h-6 w-6" />
            </span>
            <p class="text-sm text-slate-500">No tasks yet.</p>
          </div>

          <ul v-else class="space-y-3">
            <BaseCard v-for="task in tasks" :key="task.id" :padded="false" class="p-4">
              <template v-if="editingTaskId !== task.id">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="text-sm font-semibold text-slate-900">{{ task.title }}</span>
                      <BaseBadge :tone="statusTone(task.status.key)">{{ task.status.label }}</BaseBadge>
                      <BaseBadge :tone="priorityTone(task.priority.key)">{{ task.priority.label }}</BaseBadge>
                    </div>
                    <p v-if="task.description" class="mt-1 text-sm text-slate-600">{{ task.description }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <select
                      :value="task.status.key"
                      class="input-base w-40"
                      aria-label="Task status"
                      @change="changeTaskStatus(task, ($event.target as HTMLSelectElement).value as TaskStatusKey)"
                    >
                      <option v-for="status in TASK_STATUSES" :key="status.key" :value="status.key">{{ status.label }}</option>
                    </select>
                    <BaseButton size="sm" variant="secondary" @click="startEditTask(task)">
                      <Pencil class="h-4 w-4" />
                      Edit
                    </BaseButton>
                    <BaseButton size="sm" variant="danger" :loading="deletingTaskId === task.id" @click="deleteTask(task)">
                      <Trash2 class="h-4 w-4" />
                    </BaseButton>
                  </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                  <span>Assignee: {{ task.assignee?.name ?? 'Unassigned' }}</span>
                  <span v-if="task.due_at">Due: {{ formatDate(task.due_at) }}</span>
                </div>
              </template>

              <template v-else>
                <h3 class="text-sm font-semibold text-slate-900">Edit task</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                  <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Title</label>
                    <input v-model="editForm.title" type="text" class="input-base" />
                  </div>
                  <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                    <textarea
                      v-model="editForm.description"
                      rows="2"
                      class="input-base min-h-[80px] w-full"
                      placeholder="Optional details…"
                    />
                  </div>
                  <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                    <select v-model="editForm.status" class="input-base" aria-label="Edit task status">
                      <option v-for="status in TASK_STATUSES" :key="status.key" :value="status.key">{{ status.label }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Priority</label>
                    <select v-model="editForm.priority" class="input-base" aria-label="Edit task priority">
                      <option v-for="priority in TASK_PRIORITIES" :key="priority.key" :value="priority.key">
                        {{ priority.label }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Assignee</label>
                    <select v-model="editForm.assignee_id" class="input-base" aria-label="Edit task assignee">
                      <option value="">Unassigned</option>
                      <option v-for="user in assigneeOptions" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Due date</label>
                    <input v-model="editForm.due_at" type="datetime-local" class="input-base" />
                  </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                  <BaseButton size="sm" variant="secondary" @click="cancelEditTask">Cancel</BaseButton>
                  <BaseButton size="sm" :loading="taskSaving" @click="saveEditTask(task)">Save changes</BaseButton>
                </div>
              </template>
            </BaseCard>
          </ul>
        </section>

        <section v-if="activeTab === 'participants' && canSeeParticipantsTab" class="space-y-4">
          <div v-if="participantStats" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-slate-900">{{ participantStats.registered }}</p>
              <p class="text-xs text-slate-500">Registered</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-emerald-600">{{ participantStats.checked_in }}</p>
              <p class="text-xs text-slate-500">Checked in</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-amber-600">{{ participantStats.waitlisted }}</p>
              <p class="text-xs text-slate-500">Waitlisted</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-red-600">{{ participantStats.cancelled }}</p>
              <p class="text-xs text-slate-500">Cancelled</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-slate-900">{{ participantStats.remaining ?? '∞' }}</p>
              <p class="text-xs text-slate-500">Seats left</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-slate-900">{{ participantStats.capacity ?? '∞' }}</p>
              <p class="text-xs text-slate-500">Capacity</p>
            </BaseCard>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-1 flex-wrap items-center gap-3">
              <input
                v-model="participantSearch"
                type="search"
                placeholder="Search participants…"
                aria-label="Search participants"
                class="input-base min-w-0 flex-1 sm:max-w-xs"
              />
              <select v-model="participantStatusFilter" class="input-base sm:w-44" aria-label="Filter participants by status">
                <option value="">All statuses</option>
                <option v-for="status in PARTICIPANT_STATUSES" :key="status.key" :value="status.key">{{ status.label }}</option>
              </select>
            </div>
            <BaseButton v-if="canManageParticipantsTab" size="sm" @click="openAddParticipant">
              <UserPlus class="h-4 w-4" />
              Add participant
            </BaseButton>
          </div>

          <BaseCard v-if="addParticipantVisible" :padded="false" class="space-y-4 p-5">
            <h3 class="text-sm font-semibold text-slate-900">Add participant</h3>
            <div v-if="participantCandidates.length === 0">
              <p class="text-sm text-slate-500">No eligible users available.</p>
            </div>
            <ul v-else class="space-y-2">
              <li
                v-for="candidate in participantCandidates"
                :key="candidate.id"
                class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2"
              >
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ candidate.name }}</p>
                  <p class="text-xs text-slate-500">{{ candidate.email }}</p>
                </div>
                <BaseButton size="sm" :loading="participantSaving" @click="addParticipant(candidate.id)">
                  Add
                </BaseButton>
              </li>
            </ul>
            <div class="flex justify-end">
              <BaseButton size="sm" variant="secondary" @click="addParticipantVisible = false">Close</BaseButton>
            </div>
          </BaseCard>

          <div v-if="participantsLoading" class="flex justify-center py-10">
            <Loader2 class="h-7 w-7 animate-spin text-primary-600" />
          </div>

          <div
            v-else-if="participants.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-300 py-14 text-center"
          >
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
              <UserCheck class="h-6 w-6" />
            </span>
            <p class="text-sm text-slate-500">No participants found.</p>
          </div>

          <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-4 py-3">Participant</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3">Code</th>
                  <th class="px-4 py-3">Registered</th>
                  <th v-if="canManageParticipantsTab" class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="participant in participants" :key="participant.id">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-900">{{ participant.user.name }}</p>
                    <p class="text-xs text-slate-500">{{ participant.user.email }}</p>
                  </td>
                  <td class="px-4 py-3">
                    <BaseBadge :tone="statusInfo(participant.status.key).tone">{{ participant.status.label }}</BaseBadge>
                  </td>
                  <td class="px-4 py-3">
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ participant.registration_code }}</code>
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(participant.created_at) }}</td>
                  <td v-if="canManageParticipantsTab" class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <BaseButton
                        v-if="!['cancelled', 'checked_in'].includes(participant.status.key)"
                        size="sm"
                        variant="secondary"
                        :loading="cancellingParticipantId === participant.id"
                        @click="cancelParticipant(participant)"
                      >
                        Cancel
                      </BaseButton>
                      <BaseButton
                        size="sm"
                        variant="danger"
                        :loading="removingParticipantId === participant.id"
                        @click="removeParticipant(participant)"
                      >
                        Remove
                      </BaseButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section v-if="activeTab === 'attendance' && canSeeAttendanceTab" class="space-y-4">
          <div v-if="participantStats" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-slate-900">{{ participantStats.registered }}</p>
              <p class="text-xs text-slate-500">Waiting to check in</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-emerald-600">{{ participantStats.checked_in }}</p>
              <p class="text-xs text-slate-500">Checked in</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-slate-900">{{ participantStats.capacity ?? '∞' }}</p>
              <p class="text-xs text-slate-500">Capacity</p>
            </BaseCard>
            <BaseCard :padded="false" class="p-3 text-center">
              <p class="text-lg font-semibold text-slate-900">{{ participantStats.remaining ?? '∞' }}</p>
              <p class="text-xs text-slate-500">Seats left</p>
            </BaseCard>
          </div>

          <BaseCard :padded="false" class="space-y-4 p-5">
            <h3 class="text-sm font-semibold text-slate-900">Check in</h3>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
              <div class="flex-1">
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Registration code</label>
                <input
                  v-model="attendanceCode"
                  type="text"
                  placeholder="Scan or type the participant code…"
                  aria-label="Registration code"
                  class="input-base font-mono uppercase"
                  @keyup.enter="checkInByCode(attendanceCode)"
                />
              </div>
              <BaseButton :loading="attendanceChecking" @click="checkInByCode(attendanceCode)">
                <UserCheck class="h-4 w-4" />
                Check in
              </BaseButton>
              <BaseButton variant="secondary" @click="toggleScanner">
                <QrCode class="h-4 w-4" />
                {{ scannerStarted ? 'Stop camera' : 'Scan with camera' }}
              </BaseButton>
            </div>

            <div v-if="scannerStarted" id="scanner-region" class="mx-auto max-w-sm overflow-hidden rounded-lg border border-slate-200" />
          </BaseCard>

          <div v-if="attendanceLoading" class="flex justify-center py-10">
            <Loader2 class="h-7 w-7 animate-spin text-primary-600" />
          </div>

          <div
            v-else-if="attendanceList.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-300 py-14 text-center"
          >
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
              <UserCheck class="h-6 w-6" />
            </span>
            <p class="text-sm text-slate-500">No registrations waiting for check-in.</p>
          </div>

          <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-4 py-3">Participant</th>
                  <th class="px-4 py-3">Code</th>
                  <th class="px-4 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="participant in attendanceList" :key="participant.id">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-900">{{ participant.user.name }}</p>
                    <p class="text-xs text-slate-500">{{ participant.user.email }}</p>
                  </td>
                  <td class="px-4 py-3">
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ participant.registration_code }}</code>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <BaseButton size="sm" :loading="checkingInId === participant.id" @click="checkInParticipant(participant)">
                      Check in
                    </BaseButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
