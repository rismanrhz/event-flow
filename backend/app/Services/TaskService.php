<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskService
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function index(Event $event, Request $request): LengthAwarePaginator
    {
        $query = Task::query()
            ->where('event_id', $event->id)
            ->with('assignee:id,name');

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('priority'),
                AllowedFilter::exact('assignee_id')->ignore(null),
                AllowedFilter::partial('title'),
            ])
            ->allowedSorts(['due_at', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data, User $creator): Task
    {
        $this->ensureAssigneeIsAllowed($event, $data['assignee_id'] ?? null);

        $task = $event->tasks()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? Task::DEFAULT_STATUS,
            'priority' => $data['priority'] ?? Task::DEFAULT_PRIORITY,
            'due_at' => $data['due_at'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'created_by' => $creator->id,
        ]);

        $task->load('assignee:id,name');

        $this->activityLog->record(
            'event.task_created',
            'tasks',
            "Task \"{$task->title}\" was added to \"{$event->title}\".",
            $creator->id,
            $event->id,
            ['task_id' => $task->id],
        );

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $this->ensureAssigneeIsAllowed($task->event, $data['assignee_id'] ?? null);

        $task->update($data);
        $task->load('assignee:id,name');

        $this->activityLog->record(
            'event.task_updated',
            'tasks',
            "Task \"{$task->title}\" was updated.",
            auth()->id(),
            $task->event_id,
            ['task_id' => $task->id],
        );

        return $task;
    }

    public function delete(Task $task): void
    {
        $eventId = $task->event_id;
        $title = $task->title;

        $task->delete();

        $this->activityLog->record(
            'event.task_deleted',
            'tasks',
            "Task \"{$title}\" was deleted.",
            auth()->id(),
            $eventId,
            ['task_id' => $task->id],
        );
    }

    private function ensureAssigneeIsAllowed(Event $event, ?int $assigneeId): void
    {
        if ($assigneeId === null) {
            return;
        }

        $assignee = User::find($assigneeId);

        $isEligible = $assignee !== null && (
            $assignee->isSuperAdmin()
            || $event->created_by === $assignee->id
            || $event->eventMembers()->where('user_id', $assigneeId)->exists()
        );

        if (! $isEligible) {
            throw ValidationException::withMessages([
                'assignee_id' => 'The assignee must be a committee member of this event.',
            ]);
        }
    }
}
