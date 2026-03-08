<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'ok';

        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok', 'driver' => DB::getDriverName()];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Cannot connect to database'];
            $overallStatus = 'degraded';
        }

        try {
            $testKey = '_health_check_' . time();
            Cache::put($testKey, 1, 5);
            $val = Cache::get($testKey);
            Cache::forget($testKey);
            $checks['cache'] = ['status' => $val == 1 ? 'ok' : 'error', 'store' => config('cache.default')];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'message' => 'Cache unavailable'];
            $overallStatus = 'degraded';
        }

        $checks['rabbitmq'] = [
            'status' => config('rabbitmq.host') ? 'configured' : 'missing',
            'host'   => config('rabbitmq.host', 'unknown'),
        ];

        $statusCode = $overallStatus === 'ok' ? 200 : 503;

        if ($overallStatus !== 'ok') {
            Log::warning('[HealthController][__invoke] Hub service health check degraded', ['checks' => $checks]);
        }

        return response()->json([
            'status'    => $overallStatus,
            'service'   => 'hub-service',
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ], $statusCode);
    }
}
