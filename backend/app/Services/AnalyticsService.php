<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\InsightSeverity;
use App\Enums\ParticipantStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    private const HEALTH_WEIGHTS = [
        'registration_fill' => 0.3,
        'attendance_rate' => 0.3,
        'task_progress' => 0.25,
        'overdue_penalty' => 0.15,
    ];

    /**
     * Events the user may inspect, newest first, each with a lightweight health summary.
     *
     * @param  int|null  $take  Cap the number of selector entries.
     * @return array<int, array<string, mixed>>
     */
    public function selectorEvents(User $user, ?int $take = 12): array
    {
        return $this->userEvents($user)
            ->take($take)
            ->map(fn (Event $event) => $this->eventSummary($event, $user))
            ->values()
            ->all();
    }

    /**
     * Resolve the event that should be shown first on the command center.
     * Preference: explicitly requested event > ongoing > closest upcoming (published
     * over draft) > newest.
     */
    public function primaryEvent(User $user, ?int $preferredEventId = null): ?Event
    {
        $events = $this->userEvents($user);

        if ($preferredEventId !== null) {
            $candidate = $events->firstWhere('id', $preferredEventId);
            if ($candidate instanceof Event) {
                return $candidate;
            }
        }

        $now = now();

        $ongoing = $events
            ->filter(fn (Event $event) => $event->starts_at?->lte($now) && $event->ends_at?->gte($now))
            ->sortByDesc('starts_at')
            ->first();

        if ($ongoing instanceof Event) {
            return $ongoing;
        }

        $upcoming = $events
            ->filter(fn (Event $event) => $event->starts_at?->gt($now))
            ->sortBy('starts_at');

        $closestPublished = $upcoming
            ->where('status', EventStatus::Published)
            ->sortBy('starts_at')
            ->first();

        if ($closestPublished instanceof Event) {
            return $closestPublished;
        }

        $closestUpcoming = $upcoming->first();

        if ($closestUpcoming instanceof Event) {
            return $closestUpcoming;
        }

        return $events->sortByDesc('starts_at')->first();
    }

    /**
     * Decide whether the user is allowed to see the analytical payload of an event.
     */
    public function canAccessData(User $user, Event $event): bool
    {
        return $user->can('viewParticipants', $event) || $user->can('viewTasks', $event);
    }

    /**
     * Dashboard payload. Organizers get the full command center; other users get
     * their public event list and personal tickets.
     *
     * @return array<string, mixed>
     */
    public function dashboard(User $user, ?int $preferredEventId = null): array
    {
        $events = $this->selectorEvents($user);
        $primary = $this->primaryEvent($user, $preferredEventId);

        if (! $primary instanceof Event || ! $this->canAccessData($user, $primary)) {
            return [
                'mode' => 'participant',
                'events' => $events,
                'my_tickets' => $this->myTickets($user),
            ];
        }

        return [
            'mode' => 'organizer',
            'events' => $events,
            'event' => $this->eventMeta($primary, $user),
            'kpi' => $this->kpis($primary, $user),
            'trend' => $user->can('viewParticipants', $primary) ? $this->trend($primary, 30) : null,
            'attendance' => $user->can('viewParticipants', $primary) ? $this->attendance($primary) : null,
            'funnel' => $user->can('viewParticipants', $primary) ? $this->funnel($primary) : null,
            'tasks' => $user->can('viewTasks', $primary) ? $this->taskBreakdown($primary) : null,
            'schedule' => $this->upcomingSchedule($primary, 8),
            'activity' => $this->activityFeed($primary, 8),
            'health' => $this->health($primary),
            'insights' => $this->insights($primary, $user),
            'attention' => $this->attention($primary, $user),
        ];
    }

    /**
     * Deep-dive analytics for a single event, used by the analytics page.
     *
     * @return array<string, mixed>
     */
    public function analytics(Event $event, User $user, int $days = 30): array
    {
        $canParticipants = $user->can('viewParticipants', $event);
        $canTasks = $user->can('viewTasks', $event);

        return [
            'event' => $this->eventMeta($event, $user),
            'range' => $days,
            'kpi' => $this->kpis($event, $user),
            'trend' => $this->trend($event, $days),
            'participants' => $canParticipants ? $this->participantProfile($event) : null,
            'attendance' => $canParticipants ? $this->attendance($event) : null,
            'funnel' => $canParticipants ? $this->funnel($event) : null,
            'tasks' => $canTasks ? $this->taskBreakdown($event) : null,
            'health' => $this->health($event),
        ];
    }

    /**
     * @return Collection<int, Event>
     */
    private function userEvents(User $user): Collection
    {
        $query = Event::query();

        if ($user->isSuperAdmin()) {
            // Everything is visible to a super admin.
        } elseif ($user->isEventOwner()) {
            $query->where('created_by', $user->id);
        } elseif ($user->isCommitteeMember()) {
            $query->whereHas('eventMembers', fn ($q) => $q->where('user_id', $user->id));
        } else {
            $query->whereIn('status', [
                EventStatus::Published->value,
                EventStatus::Completed->value,
            ]);
        }

        return $query->orderByDesc('starts_at')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSummary(Event $event, User $user): array
    {
        return [
            'value' => $event->id,
            'label' => $event->title,
            'slug' => $event->slug,
            'status' => $this->eventStatus($event),
            'location' => $event->location,
            'starts_at' => $event->starts_at?->toISOString(),
            'ends_at' => $event->ends_at?->toISOString(),
            'capacity' => $event->capacity,
            'health' => $this->score($event),
            'can_access_data' => $this->canAccessData($user, $event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventMeta(Event $event, User $user): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'location' => $event->location,
            'starts_at' => $event->starts_at?->toISOString(),
            'ends_at' => $event->ends_at?->toISOString(),
            'capacity' => $event->capacity,
            'status' => $this->eventStatus($event),
            'creator' => $event->creator ? ['id' => $event->creator->id, 'name' => $event->creator->name] : null,
            'can' => [
                'participants' => $user->can('viewParticipants', $event),
                'tasks' => $user->can('viewTasks', $event),
            ],
        ];
    }

    /**
     * The four headline metrics with period-over-period deltas.
     *
     * @return array<string, mixed>
     */
    private function kpis(Event $event, User $user): array
    {
        $now = now();
        $prevFrom = $now->copy()->subDays(60);
        $prevTo = $now->copy()->subDays(30);
        $currFrom = $now->copy()->subDays(30);

        $canParticipants = $user->can('viewParticipants', $event);
        $canTasks = $user->can('viewTasks', $event);

        $taskTotal = Task::query()->where('event_id', $event->id)->count();
        $taskDone = Task::query()
            ->where('event_id', $event->id)
            ->where('status', TaskStatus::Done->value)
            ->count();
        $taskCompletion = $taskTotal > 0 ? round(($taskDone / $taskTotal) * 100) : 0;
        $overdue = Task::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [TaskStatus::Todo->value, TaskStatus::InProgress->value])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        return [
            'activity' => $canParticipants ? $this->periodGrowth(
                EventParticipant::query()->where('event_id', $event->id),
                $prevFrom, $prevTo, $currFrom, $now
            ) : null,
            'attendance' => $canParticipants ? $this->attendanceRateDelta($event, $prevFrom, $prevTo, $currFrom) : null,
            'task_completion' => $canTasks ? $this->periodGrowth(
                Task::query()->where('event_id', $event->id)->where('status', TaskStatus::Done->value),
                $prevFrom, $prevTo, $currFrom, $now, 'updated_at'
            ) : null,
            'overdue' => $canTasks ? [
                'value' => $overdue,
                'total' => $taskTotal,
                'done' => $taskDone,
                'completion' => $taskCompletion,
            ] : null,
            'capacity' => $canParticipants ? $this->capacitySummary($event) : null,
            'budget' => null,
        ];
    }

    /**
     * Active registrations relative to capacity; all null when capacity is untracked.
     *
     * @return array<string, mixed>
     */
    private function capacitySummary(Event $event): array
    {
        $active = $this->activeStatuses();
        $totalActive = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('status', $active)
            ->count();

        return [
            'value' => $totalActive,
            'capacity' => $event->capacity,
            'remaining' => $event->capacity !== null ? max(0, $event->capacity - $totalActive) : null,
            'filled_percent' => $event->capacity ? round(($totalActive / $event->capacity) * 100) : null,
        ];
    }

    /**
     * Percentage growth between the two trailing windows; null when there is no baseline.
     *
     * @return array<string, mixed>
     */
    private function periodGrowth($query, Carbon $prevFrom, Carbon $prevTo, Carbon $currFrom, Carbon $now, string $column = 'created_at'): array
    {
        $prev = (clone $query)->whereBetween($column, [$prevFrom, $prevTo])->count();
        $curr = (clone $query)->where($column, '>=', $currFrom)->count();

        if ($prev > 0) {
            $delta = round((($curr - $prev) / $prev) * 100);
        } else {
            $delta = $curr > 0 ? 100 : null;
        }

        return [
            'current' => $curr,
            'previous' => $prev,
            'delta' => $delta,
        ];
    }

    /**
     * Attendance rate of the current 30-day cohort compared to the previous one (points).
     *
     * @return array<string, mixed>
     */
    private function attendanceRateDelta(
        Event $event,
        Carbon $prevFrom,
        Carbon $prevTo,
        Carbon $currFrom
    ): array {
        $base = EventParticipant::query()->where('event_id', $event->id);
        $active = $this->activeStatuses();
        $checkedFlag = ParticipantStatus::CheckedIn;

        $rate = function (Carbon $from, Carbon $to) use ($base, $active, $checkedFlag): ?array {
            $cohort = (clone $base)->whereBetween('created_at', [$from, $to])->get(['id', 'status']);
            $total = $cohort->whereIn('status', $active)->count();
            $checked = $cohort->where('status', $checkedFlag)->count();

            return ['value' => $total > 0 ? round(($checked / $total) * 100) : 0, 'total' => $total];
        };

        $previous = $rate($prevFrom, $prevTo);
        $current = $rate($currFrom, now());

        $delta = null;
        if ($previous !== null && $previous['total'] > 0 && $current !== null) {
            $delta = $current['value'] - $previous['value'];
        }

        return [
            'current' => $current['value'] ?? 0,
            'previous' => $previous['value'] ?? null,
            'delta' => $delta,
            'applicable' => $event->starts_at?->lte(now()) ?? false,
        ];
    }

    /**
     * Daily registrations vs check-ins for the trailing window (days default 30).
     *
     * @return array<string, mixed>
     */
    private function trend(Event $event, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('created_at', '>=', $from)
            ->get(['id', 'created_at', 'status', 'checked_in_at']);

        $registered = [];
        $checkedIn = [];
        $labels = [];
        $running = 0;

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $keys = [$day->format('Y-m-d'), $day->format('D, M j')];
            $labels[] = $keys[1];
            $registered[$keys[0]] = 0;
            $checkedIn[$keys[0]] = 0;
        }

        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            if (array_key_exists($key, $registered)) {
                $registered[$key]++;
            }

            if ($row->status?->value === ParticipantStatus::CheckedIn->value && $row->checked_in_at !== null) {
                $checkKey = $row->checked_in_at->format('Y-m-d');
                if (array_key_exists($checkKey, $checkedIn)) {
                    $checkedIn[$checkKey]++;
                }
            }
        }

        $cumulative = [];
        foreach ($registered as $day => $count) {
            $running += $count;
            $cumulative[] = $running;
        }

        return [
            'unit' => 'day',
            'labels' => $labels,
            'registered' => array_values($registered),
            'checked_in' => array_values($checkedIn),
            'cumulative' => $cumulative,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendance(Event $event): array
    {
        $rows = EventParticipant::query()
            ->where('event_id', $event->id)
            ->get(['status', 'checked_in_at', 'cancelled_at']);

        $registered = 0;
        $checkedIn = 0;
        $waitlisted = 0;
        $cancelled = 0;
        $hours = array_fill(0, 12, 0);

        foreach ($rows as $row) {
            match ($row->status?->value) {
                ParticipantStatus::Registered->value => $registered++,
                ParticipantStatus::CheckedIn->value => $checkedIn++,
                ParticipantStatus::Waitlisted->value => $waitlisted++,
                ParticipantStatus::Cancelled->value => $cancelled++,
                default => null,
            };

            if ($row->status?->value === ParticipantStatus::CheckedIn->value && $row->checked_in_at !== null) {
                $hours[(int) floor($row->checked_in_at->format('G') / 2)]++;
            }
        }

        $total = $registered + $checkedIn;

        return [
            'registered' => $registered,
            'checked_in' => $checkedIn,
            'no_show' => $registered,
            'waitlisted' => $waitlisted,
            'cancelled' => $cancelled,
            'total' => $total,
            'rate' => $total > 0 ? round(($checkedIn / $total) * 100) : 0,
            'hours' => [
                'labels' => ['00–02', '02–04', '04–06', '06–08', '08–10', '10–12', '12–14', '14–16', '16–18', '18–20', '20–22', '22–00'],
                'values' => $hours,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function funnel(Event $event): array
    {
        $rows = EventParticipant::query()
            ->where('event_id', $event->id)
            ->get(['status']);

        $active = $rows->whereIn('status', $this->activeStatuses())->count();
        $checkedIn = $rows->where('status', ParticipantStatus::CheckedIn)->count();
        $noShow = $rows->where('status', ParticipantStatus::Registered)->count();

        return [
            'registered' => $active,
            'checked_in' => $checkedIn,
            'no_show' => $noShow,
            'conversion' => $active > 0 ? round(($checkedIn / $active) * 100) : 0,
        ];
    }

    /**
     * Participant profile: status split and institution distribution.
     *
     * @return array<string, mixed>
     */
    private function participantProfile(Event $event): array
    {
        $base = EventParticipant::query()->where('event_id', $event->id);

        $statusRows = (clone $base)->get(['status']);
        $breakdown = [
            ['key' => ParticipantStatus::Registered->value, 'label' => ParticipantStatus::Registered->label(), 'tone' => ParticipantStatus::Registered->tone(), 'count' => $statusRows->where('status', ParticipantStatus::Registered)->count()],
            ['key' => ParticipantStatus::CheckedIn->value, 'label' => ParticipantStatus::CheckedIn->label(), 'tone' => ParticipantStatus::CheckedIn->tone(), 'count' => $statusRows->where('status', ParticipantStatus::CheckedIn)->count()],
            ['key' => ParticipantStatus::Waitlisted->value, 'label' => ParticipantStatus::Waitlisted->label(), 'tone' => ParticipantStatus::Waitlisted->tone(), 'count' => $statusRows->where('status', ParticipantStatus::Waitlisted)->count()],
            ['key' => ParticipantStatus::Cancelled->value, 'label' => ParticipantStatus::Cancelled->label(), 'tone' => ParticipantStatus::Cancelled->tone(), 'count' => $statusRows->where('status', ParticipantStatus::Cancelled)->count()],
        ];

        $byInstitution = EventParticipant::query()
            ->join('users', 'users.id', '=', 'event_participants.user_id')
            ->where('event_participants.event_id', $event->id)
            ->whereNotNull('users.institution')
            ->where('users.institution', '!=', '')
            ->selectRaw('users.institution as institution, COUNT(*) as total')
            ->groupBy('users.institution')
            ->orderByDesc('total')
            ->get();

        $top = $byInstitution->take(8);
        $other = $byInstitution->slice(8)->sum('total');

        $institutions = $top->map(fn ($row) => [
            'label' => $row->institution,
            'count' => (int) $row->total,
        ])->values()->all();

        if ($other > 0) {
            $institutions[] = ['label' => 'Other', 'count' => $other];
        }

        return [
            'total' => $base->count(),
            'breakdown' => $breakdown,
            'institutions' => $institutions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskBreakdown(Event $event): array
    {
        $base = Task::query()->where('event_id', $event->id);
        $rows = (clone $base)->get(['status', 'priority', 'due_at']);

        $now = now();
        $total = $rows->count();
        $done = $rows->where('status', TaskStatus::Done)->count();
        $overdue = $rows
            ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])
            ->filter(fn ($row) => $row->due_at !== null && $row->due_at->lt($now))
            ->count();

        $byStatus = collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
            'key' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
            'count' => $rows->where('status', $status)->count(),
        ])->values()->all();

        $byPriority = collect(TaskPriority::cases())->map(function (TaskPriority $priority) use ($rows) {
            $group = $rows->where('priority', $priority);
            $count = $group->count();

            return [
                'key' => $priority->value,
                'label' => $priority->label(),
                'count' => $count,
                'done' => $group->where('status', TaskStatus::Done)->count(),
                'completion' => $count > 0 ? round(($group->where('status', TaskStatus::Done)->count() / $count) * 100) : 0,
            ];
        })->values()->all();

        return [
            'total' => $total,
            'done' => $done,
            'todo' => $rows->where('status', TaskStatus::Todo)->count(),
            'in_progress' => $rows->where('status', TaskStatus::InProgress)->count(),
            'overdue' => $overdue,
            'completion' => $total > 0 ? round(($done / $total) * 100) : 0,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
        ];
    }

    /**
     * Upcoming milestones: the event window itself plus non-completed tasks by due date.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingSchedule(Event $event, int $limit): array
    {
        $items = [];

        if ($event->starts_at !== null) {
            $items[] = [
                'type' => 'event',
                'title' => 'Event day · '.$event->title,
                'at' => $event->starts_at->toISOString(),
                'location' => $event->location,
                'status' => $this->eventStatus($event),
            ];
        }

        Task::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [TaskStatus::Todo->value, TaskStatus::InProgress->value])
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->limit($limit * 2)
            ->get(['id', 'title', 'due_at', 'priority', 'status'])
            ->each(function (Task $task) use (&$items) {
                $items[] = [
                    'type' => 'task',
                    'id' => $task->id,
                    'title' => $task->title,
                    'at' => $task->due_at?->toISOString(),
                    'priority' => ['key' => $task->priority?->value, 'label' => $task->priority?->label()],
                    'status' => ['key' => $task->status?->value, 'label' => $task->status?->label(), 'tone' => $task->status?->tone()],
                ];
            });

        usort($items, fn ($a, $b) => strcmp((string) Carbon::parse($a['at']), (string) Carbon::parse($b['at'])));

        return array_slice($items, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activityFeed(Event $event, int $limit): array
    {
        return ActivityLog::query()
            ->where('event_id', $event->id)
            ->with('user:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'description' => $log->description,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'iso' => $log->created_at?->toISOString(),
                'human' => $log->created_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Weighted health score (0–100) with the underlying factors.
     *
     * @return array<string, mixed>
     */
    private function health(Event $event): array
    {
        $factors = $this->healthFactors($event);
        $totalWeight = array_sum(self::HEALTH_WEIGHTS);
        $score = 0;

        foreach ($factors as $factor) {
            $score += $factor['score'] * ($factor['weight'] / $totalWeight);
        }

        $score = (int) round(max(0, min(100, $score)));

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'factors' => $factors,
        ];
    }

    /**
     * Compact health summary (score only) used in lists and the event selector.
     */
    public function score(Event $event): array
    {
        $score = $this->health($event)['score'];

        return [
            'score' => $score,
            'grade' => $this->grade($score),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function healthFactors(Event $event): array
    {
        $active = $this->activeStatuses();

        $totalActive = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('status', $active)
            ->count();
        $checkedIn = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', ParticipantStatus::CheckedIn->value)
            ->count();

        $taskRows = Task::query()->where('event_id', $event->id)->get(['status', 'due_at']);
        $taskTotal = $taskRows->count();
        $taskDone = $taskRows->where('status', TaskStatus::Done)->count();
        $overdue = $taskRows
            ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])
            ->filter(fn ($row) => $row->due_at !== null && $row->due_at->lt(now()))
            ->count();

        $fillScore = $event->capacity > 0
            ? round(min(100, ($totalActive / $event->capacity) * 100))
            : 50;
        $eventStarted = $event->starts_at?->lte(now()) ?? false;
        $attendanceScore = $eventStarted ? ($totalActive > 0 ? round(($checkedIn / $totalActive) * 100) : 0) : 50;
        $taskScore = $taskTotal > 0 ? round(($taskDone / $taskTotal) * 100) : 50;
        $overdueScore = max(40, 100 - ($overdue * 10));

        return [
            [
                'key' => 'registration_fill',
                'label' => 'Registration fill',
                'score' => $fillScore,
                'weight' => self::HEALTH_WEIGHTS['registration_fill'],
            ],
            [
                'key' => 'attendance_rate',
                'label' => 'Attendance rate',
                'score' => $attendanceScore,
                'weight' => self::HEALTH_WEIGHTS['attendance_rate'],
            ],
            [
                'key' => 'task_progress',
                'label' => 'Task progress',
                'score' => $taskScore,
                'weight' => self::HEALTH_WEIGHTS['task_progress'],
            ],
            [
                'key' => 'overdue_penalty',
                'label' => 'Overdue tasks',
                'score' => $overdueScore,
                'weight' => self::HEALTH_WEIGHTS['overdue_penalty'],
            ],
        ];
    }

    /**
     * Rule-based insights, most severe first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function insights(Event $event, User $user): array
    {
        $now = now();
        $eventStarted = $event->starts_at?->lte($now) ?? true;
        $canParticipants = $user->can('viewParticipants', $event);
        $canTasks = $user->can('viewTasks', $event);

        $insights = [];

        if ($canTasks) {
            $taskRows = Task::query()->where('event_id', $event->id)->get(['status', 'priority', 'due_at']);
            $overdueTasks = $taskRows
                ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress])
                ->filter(fn ($row) => $row->due_at !== null && $row->due_at->lt($now));
            $highOverdue = $overdueTasks->where('priority', TaskPriority::High)->count();
            $taskTotal = $taskRows->count();
            $taskDone = $taskRows->where('status', TaskStatus::Done)->count();
            $taskCompletion = $taskTotal > 0 ? round(($taskDone / $taskTotal) * 100) : 0;

            if ($highOverdue > 0) {
                $insights[] = $this->makeInsight(
                    key: 'critical-overdue-tasks',
                    severity: InsightSeverity::Critical,
                    message: sprintf('%d high-priority task%s overdue — address before the event window closes.', $highOverdue, $highOverdue > 1 ? 's are' : ' is'),
                    target: 'tasks',
                    label: 'Review tasks',
                );
            } elseif ($overdueTasks->count() > 0) {
                $insights[] = $this->makeInsight(
                    'overdue-tasks',
                    InsightSeverity::Warning,
                    sprintf('%d task%s overdue. Review the schedule to keep preparation on track.', $overdueTasks->count(), $overdueTasks->count() > 1 ? 's are' : ' is'),
                    'tasks',
                    'Review tasks',
                );
            }

            if ($taskTotal > 0 && $taskCompletion >= 75) {
                $insights[] = $this->makeInsight(
                    'task-progress',
                    InsightSeverity::Positive,
                    sprintf('Task completion looks healthy at %d%%.', $taskCompletion),
                    'tasks',
                    'View tasks',
                );
            }
        }

        if ($canParticipants) {
            $registered = EventParticipant::query()
                ->where('event_id', $event->id)
                ->whereIn('status', $this->activeStatuses())
                ->count();
            $checkedIn = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', ParticipantStatus::CheckedIn->value)
                ->count();
            $noShow = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', ParticipantStatus::Registered->value)
                ->count();
            $waitlisted = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', ParticipantStatus::Waitlisted->value)
                ->count();
            $capacity = $event->capacity;

            if ($eventStarted && $noShow > 0) {
                $insights[] = $this->makeInsight(
                    'no-show-risk',
                    InsightSeverity::Warning,
                    sprintf('%d participant%s registered but not yet checked in — follow up to secure attendance.', $noShow, $noShow > 1 ? 's are' : ' is'),
                    'participants',
                    'View registrations',
                    ['status' => 'registered'],
                );
            }

            if ($eventStarted && $registered > 0 && $checkedIn > 0) {
                $rate = round(($checkedIn / $registered) * 100);
                if ($rate >= 85) {
                    $insights[] = $this->makeInsight(
                        'strong-attendance',
                        InsightSeverity::Positive,
                        sprintf('Attendance is strong at %d%%.', $rate),
                        'participants',
                        'View attendance',
                    );
                } elseif ($rate < 60) {
                    $insights[] = $this->makeInsight(
                        'low-attendance',
                        InsightSeverity::Warning,
                        sprintf('Attendance sits at %d%% — below the healthy 60%% mark.', $rate),
                        'participants',
                        'View attendance',
                    );
                }
            }

            $weekAgo = $now->copy()->subDays(7);
            $newThisWeek = EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('created_at', '>=', $weekAgo)
                ->count();
            $prevWeek = EventParticipant::query()
                ->where('event_id', $event->id)
                ->whereBetween('created_at', [$weekAgo->copy()->subDays(7), $weekAgo])
                ->count();

            if ($newThisWeek > 0 && $prevWeek > 0 && $newThisWeek > $prevWeek) {
                $growth = round((($newThisWeek - $prevWeek) / $prevWeek) * 100);
                $insights[] = $this->makeInsight(
                    'registration-growth',
                    InsightSeverity::Positive,
                    sprintf('Registrations grew %d%% week-over-week. Keep promoting.', $growth),
                    'participants',
                    'View registrations',
                );
            }

            if ($capacity && $registered > 0) {
                $fill = round(($registered / $capacity) * 100);
                if ($fill >= 90) {
                    $insights[] = $this->makeInsight(
                        'near-capacity',
                        InsightSeverity::Positive,
                        sprintf('Event is nearly full — %d%% of capacity used.', min(100, $fill)),
                        'participants',
                        'View registrations',
                    );
                }
            }

            if ($waitlisted > 0) {
                $insights[] = $this->makeInsight(
                    'waitlist-growth',
                    InsightSeverity::Normal,
                    sprintf('%d participant%s on the waitlist — consider raising capacity.', $waitlisted, $waitlisted > 1 ? 's are' : ' is'),
                    'participants',
                    'View waitlist',
                    ['status' => 'waitlisted'],
                );
            }
        }

        if ($insights === []) {
            $insights[] = $this->makeInsight(
                'on-track',
                InsightSeverity::Normal,
                'Everything looks on track for this event.',
                'tasks',
                'View tasks',
            );
        }

        $order = [InsightSeverity::Critical->value => 0, InsightSeverity::Warning->value => 1, InsightSeverity::Positive->value => 2, InsightSeverity::Normal->value => 3];

        usort($insights, fn ($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);

        return array_slice($insights, 0, 6);
    }

    /**
     * Only the actionable (warning/critical) insights.
     *
     * @return array<int, array<string, mixed>>
     */
    private function attention(Event $event, User $user): array
    {
        return array_values(array_filter(
            $this->insights($event, $user),
            fn ($insight) => in_array($insight['severity'], [InsightSeverity::Warning->value, InsightSeverity::Critical->value], true)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function makeInsight(
        string $key,
        InsightSeverity $severity,
        string $message,
        ?string $target = null,
        ?string $label = null,
        array $params = []
    ): array {
        return [
            'key' => $key,
            'severity' => $severity->value,
            'tone' => $severity->tone(),
            'label' => $severity->label(),
            'message' => $message,
            'action' => $target !== null ? [
                'label' => $label,
                'target' => $target,
                'params' => $params,
            ] : null,
        ];
    }

    /**
     * @return array<int, ParticipantStatus>
     */
    private function activeStatuses(): array
    {
        return [
            ParticipantStatus::Registered,
            ParticipantStatus::CheckedIn,
            ParticipantStatus::Waitlisted,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function myTickets(User $user): array
    {
        return EventParticipant::query()
            ->where('user_id', $user->id)
            ->whereHas('event')
            ->with('event:id,title,slug,location,starts_at,capacity,status')
            ->latest()
            ->get()
            ->map(fn (EventParticipant $participant) => [
                'id' => $participant->id,
                'registration_code' => $participant->registration_code,
                'status' => [
                    'key' => $participant->status?->value,
                    'label' => $participant->status?->label(),
                    'tone' => $participant->status?->tone(),
                ],
                'checked_in_at' => $participant->checked_in_at?->toISOString(),
                'created_at' => $participant->created_at?->toISOString(),
                'event' => $participant->event ? [
                    'id' => $participant->event->id,
                    'title' => $participant->event->title,
                    'slug' => $participant->event->slug,
                    'location' => $participant->event->location,
                    'starts_at' => $participant->event->starts_at?->toISOString(),
                    'capacity' => $participant->event->capacity,
                    'status' => $this->eventStatus($participant->event),
                ] : null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventStatus(Event $event): array
    {
        return [
            'key' => $event->status?->value,
            'label' => $event->status?->label(),
            'tone' => $event->status?->tone(),
        ];
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Healthy',
            $score >= 55 => 'At risk',
            default => 'Critical',
        };
    }
}
