<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Metrics\PrometheusMetricsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class RecordRequestMetrics
{
    public function __construct(
        private readonly PrometheusMetricsService $metrics,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $start;
        $endpoint = $this->normalizeEndpoint($request);

        try {
            $this->metrics->recordRequest(
                method:   $request->method(),
                endpoint: $endpoint,
                status:   (string) $response->getStatusCode(),
                duration: $duration,
            );
        } catch (\Throwable $e) {
            Log::warning('[RecordRequestMetrics][handle] Request metrics recording failed', [
                'method' => $request->method(),
                'endpoint' => $endpoint,
                'status' => $response->getStatusCode(),
                'exception' => $e,
            ]);
        }

        return $response;
    }

    private function normalizeEndpoint(Request $request): string
    {
        $path = $request->path();
        $path = preg_replace('/\/\d+/', '/{id}', $path);
        return '/' . $path;
    }
}
