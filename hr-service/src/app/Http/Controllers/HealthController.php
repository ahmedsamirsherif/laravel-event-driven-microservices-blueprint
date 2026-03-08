<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'ok';

        // PostgreSQL check
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok', 'driver' => DB::getDriverName()];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Cannot connect to database'];
            $overallStatus = 'degraded';
        }

        // RabbitMQ config check
        $checks['rabbitmq'] = [
            'status' => config('rabbitmq.host') ? 'configured' : 'missing',
            'host'   => config('rabbitmq.host', 'unknown'),
        ];

        $statusCode = $overallStatus === 'ok' ? 200 : 503;

        return response()->json([
            'status'    => $overallStatus,
            'service'   => 'hr-service',
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ], $statusCode);
    }
}
