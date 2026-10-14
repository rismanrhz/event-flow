<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Event;
use App\Models\Task;
use App\Services\TaskService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewTasks', $event);

        $paginator = $this->tasks->index($event, $request);
        $paginator->setCollection(
            $paginator->getCollection()->transform(fn (Task $task) => new TaskResource($task))
        );

        return $this->paginated('Tasks retrieved.', $paginator);
    }

    public function store(StoreTaskRequest $request, Event $event): JsonResponse
    {
        $this->authorize('manageTasks', $event);

        $task = $this->tasks->create($event, $request->validated(), $request->user());

        return $this->success('Task created successfully.', new TaskResource($task), 201);
    }

    public function show(Request $request, Event $event, Task $task): JsonResponse
    {
        $this->authorize('viewTasks', $event);

        if ($task->event_id !== $event->id) {
            return $this->error('Task not found for this event.', 404);
        }

        $task->load('assignee:id,name');

        return $this->success('Task retrieved.', new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, Event $event, Task $task): JsonResponse
    {
        $this->authorize('manageTasks', $event);

        if ($task->event_id !== $event->id) {
            return $this->error('Task not found for this event.', 404);
        }

        $task = $this->tasks->update($task, $request->validated());

        return $this->success('Task updated successfully.', new TaskResource($task));
    }

    public function destroy(Request $request, Event $event, Task $task): JsonResponse
    {
        $this->authorize('manageTasks', $event);

        if ($task->event_id !== $event->id) {
            return $this->error('Task not found for this event.', 404);
        }

        $this->tasks->delete($task);

        return $this->success('Task deleted successfully.');
    }
}
