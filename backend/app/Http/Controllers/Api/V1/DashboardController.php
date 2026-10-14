<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AnalyticsService $analytics) {}

    public function overview(Request $request): JsonResponse
    {
        $eventId = $request->integer('event_id') ?: null;

        $data = $this->analytics->dashboard($request->user(), $eventId);

        return $this->success('Dashboard retrieved.', $data);
    }
}
