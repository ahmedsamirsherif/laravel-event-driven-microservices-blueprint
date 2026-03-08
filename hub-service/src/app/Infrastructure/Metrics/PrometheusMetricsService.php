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
    private Counter $eventsProcessedTotal;
    private Counter $eventProcessingErrorsTotal;
    private Histogram $eventProcessingDuration;
    private Counter $cacheHits;
    private Counter $cacheMisses;
    private Counter $websocketBroadcastsTotal;
    private Counter $websocketBroadcastFailures;
    private Counter $eventRetriesTotal;
    private Counter $eventDlqRoutedTotal;

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

        $this->eventsProcessedTotal = $this->registry->getOrRegisterCounter(
            'app', 'events_processed_total', 'Total events processed', ['event_type'],
        );

        $this->eventProcessingErrorsTotal = $this->registry->getOrRegisterCounter(
            'app', 'event_processing_errors_total', 'Total event processing errors', ['event_type'],
        );

        $this->eventProcessingDuration = $this->registry->getOrRegisterHistogram(
            'app', 'event_processing_duration_seconds', 'Event processing duration',
            ['event_type'], [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0],
        );

        $this->cacheHits = $this->registry->getOrRegisterCounter(
            'app', 'cache_hits_total', 'Total cache hits', ['cache_type'],
        );

        $this->cacheMisses = $this->registry->getOrRegisterCounter(
            'app', 'cache_misses_total', 'Total cache misses', ['cache_type'],
        );

        $this->websocketBroadcastsTotal = $this->registry->getOrRegisterCounter(
            'app', 'websocket_broadcasts_total', 'Total WebSocket broadcasts sent', ['event_type'],
        );

        $this->websocketBroadcastFailures = $this->registry->getOrRegisterCounter(
            'app', 'websocket_broadcast_failures_total', 'Total WebSocket broadcast failures', ['event_type'],
        );

        $this->eventRetriesTotal = $this->registry->getOrRegisterCounter(
            'app', 'event_retries_total', 'Total event processing retries', ['event_type'],
        );

        $this->eventDlqRoutedTotal = $this->registry->getOrRegisterCounter(
            'app', 'event_dlq_routed_total', 'Total events routed to dead letter queue', ['event_type'],
        );
    }

    public function recordRequest(string $method, string $endpoint, string $status, float $duration): void
    {
        $this->httpRequestsTotal->inc(['method' => $method, 'endpoint' => $endpoint, 'status' => $status]);
        $this->httpRequestDuration->observe($duration, ['method' => $method, 'endpoint' => $endpoint]);
    }

    public function incrementEventsProcessed(string $eventType): void
    {
        $this->eventsProcessedTotal->inc(['event_type' => $eventType]);
    }

    public function incrementEventProcessingErrors(string $eventType): void
    {
        $this->eventProcessingErrorsTotal->inc(['event_type' => $eventType]);
    }

    public function recordEventProcessingDuration(string $eventType, float $duration): void
    {
        $this->eventProcessingDuration->observe($duration, ['event_type' => $eventType]);
    }

    public function incrementCacheHit(string $cacheType = 'employee'): void
    {
        $this->cacheHits->inc(['cache_type' => $cacheType]);
    }

    public function incrementCacheMiss(string $cacheType = 'employee'): void
    {
        $this->cacheMisses->inc(['cache_type' => $cacheType]);
    }

    public function incrementWebsocketBroadcast(string $eventType): void
    {
        $this->websocketBroadcastsTotal->inc(['event_type' => $eventType]);
    }

    public function incrementWebsocketBroadcastFailure(string $eventType): void
    {
        $this->websocketBroadcastFailures->inc(['event_type' => $eventType]);
    }

    public function incrementEventRetry(string $eventType): void
    {
        $this->eventRetriesTotal->inc(['event_type' => $eventType]);
    }

    public function incrementEventDlqRouted(string $eventType): void
    {
        $this->eventDlqRoutedTotal->inc(['event_type' => $eventType]);
    }

    public function renderMetrics(): string
    {
        return (new RenderTextFormat())->render($this->registry->getMetricFamilySamples());
    }
}
