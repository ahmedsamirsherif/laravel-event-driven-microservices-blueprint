<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

final class MetricsController extends Controller
{
    public function __invoke(PrometheusMetricsService $metrics): Response
    {
        return response($metrics->renderMetrics(), 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
