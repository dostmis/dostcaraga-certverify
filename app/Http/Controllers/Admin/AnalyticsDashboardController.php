<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\AnalyticsRefresh;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsDataRequest;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AnalyticsDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.analytics.dashboard');
    }

    public function data(AnalyticsDataRequest $request, AnalyticsService $service): JsonResponse
    {
        $user = $request->user();
        $filters = AnalyticsFilters::fromArray($request->validated());

        // Versioned cache key: bumped by analytics:refresh, so no tags are
        // needed (the cache driver is `database`). Operations data is scoped
        // per-user for non-RD, so the scope token is part of the key.
        $version = (int) Cache::get(AnalyticsRefresh::VERSION_KEY, 0);
        $scope = $user->isRegionalDirector() ? 'rd' : 'u'.$user->id;
        $key = "analytics:data:{$scope}:{$filters->cacheKey()}:v{$version}";

        $payload = Cache::remember($key, 3600, fn () => $service->data($user, $filters));

        return response()->json($payload);
    }
}
