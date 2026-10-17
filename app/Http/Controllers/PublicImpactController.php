<?php

namespace App\Http\Controllers;

use App\Console\Commands\AnalyticsRefresh;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Public "Caraga Reach Atlas". Serves only pre-aggregated, <5-suppressed data
 * from mv_public_reach — never any row-level or identifying data.
 */
class PublicImpactController extends Controller
{
    public function index(): View
    {
        return view('impact.index');
    }

    public function data(AnalyticsService $service): JsonResponse
    {
        $version = (int) Cache::get(AnalyticsRefresh::VERSION_KEY, 0);

        $payload = Cache::remember("analytics:impact:v{$version}", 3600, fn () => $service->publicReach());

        return response()->json($payload);
    }
}
