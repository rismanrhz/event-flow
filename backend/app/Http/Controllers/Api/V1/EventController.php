<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventService $events) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->events->index($request);
        $paginator->setCollection(
            $paginator->getCollection()->transform(fn (Event $event) => new EventResource($event))
        );

        return $this->paginated('Events retrieved.', $paginator);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        $event = $this->events->create($request->validated(), $request->user());

        return $this->success('Event created successfully.', new EventResource($event), 201);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->load('creator:id,name');

        return $this->success('Event retrieved.', new EventResource($event));
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $this->events->update($event, $request->validated());

        return $this->success('Event updated successfully.', new EventResource($event));
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $this->events->delete($event);

        return $this->success('Event deleted successfully.');
    }
}
