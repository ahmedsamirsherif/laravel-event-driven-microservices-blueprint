<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;

final class PrometheusMetricsService
{
    private CollectorRegistry $registry;
    private Counter $httpRequestsTotal;
    private Histogram $httpRequestDuration;
    private Counter $eventsPublishedTotal;
    private Counter $eventPublishFailuresTotal;
    private Histogram $eventPublishDuration;

    public function __construct(Adapter $storage)
    {
        $this->registry = new CollectorRegistry($storage);

        $this->httpRequestsTotal = $this->registry->getOrRegisterCounter(
            'app', 'http_requests_total', 'Total HTTP requests', ['method', 'endpoint', 'status'],
        );

        $this->httpRequestDuration = $this->registry->getOrRegisterHistogram(
            'app', 'http_request_duration_seconds', 'HTTP request duration',
            ['method', 'endpoint'], [0.01, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
        );

        $this->eventsPublishedTotal = $this->registry->getOrRegisterCounter(
            'app', 'events_published_total', 'Total events published', ['event_type', 'country'],
        );

        $this->eventPublishFailuresTotal = $this->registry->getOrRegisterCounter(
            'app', 'event_publish_failures_total', 'Total event publish failures', ['event_type', 'country'],
        );

        $this->eventPublishDuration = $this->registry->getOrRegisterHistogram(
            'app', 'event_publish_duration_seconds', 'Event publish duration to RabbitMQ',
            ['event_type'], [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0],
        );
    }

    public function recordRequest(string $method, string $endpoint, string $status, float $duration): void
    {
        $this->httpRequestsTotal->inc(['method' => $method, 'endpoint' => $endpoint, 'status' => $status]);
        $this->httpRequestDuration->observe($duration, ['method' => $method, 'endpoint' => $endpoint]);
    }

    public function incrementEventsPublished(string $eventType, string $country): void
    {
        $this->eventsPublishedTotal->inc(['event_type' => $eventType, 'country' => $country]);
    }

    public function incrementEventPublishFailure(string $eventType, string $country): void
    {
        $this->eventPublishFailuresTotal->inc(['event_type' => $eventType, 'country' => $country]);
    }

    public function recordEventPublishDuration(string $eventType, float $duration): void
    {
        $this->eventPublishDuration->observe($duration, ['event_type' => $eventType]);
    }

    public function renderMetrics(): string
    {
        return (new RenderTextFormat())->render($this->registry->getMetricFamilySamples());
    }
}
