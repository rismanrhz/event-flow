<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    private const RANGES = [7, 30, 90];

    public function __construct(private readonly AnalyticsService $analytics) {}

    public function overview(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        if (! $this->analytics->canAccessData($request->user(), $event)) {
            return $this->error('You do not have permission to view analytics for this event.', 403);
        }

        $range = $request->integer('range', 30);

        if (! in_array($range, self::RANGES, true)) {
            $range = 30;
        }

        $data = $this->analytics->analytics($event, $request->user(), $range);

        return $this->success('Event analytics retrieved.', $data);
    }
}
