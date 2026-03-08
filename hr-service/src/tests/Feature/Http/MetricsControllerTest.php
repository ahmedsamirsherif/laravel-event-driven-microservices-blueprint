<?php

declare(strict_types=1);

it('returns prometheus metrics in text format', function () {
    $response = $this->get('/api/metrics');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->not->toBeEmpty()
        ->and($response->getContent())->toContain('php_info');
});

it('shows recorded application metrics', function () {
    $metrics = app(\App\Infrastructure\Metrics\PrometheusMetricsService::class);
    $metrics->recordRequest('GET', '/api/v1/employees', '200', 0.01);
    $metrics->incrementEventsPublished('EmployeeCreated', 'USA');

    $response = $this->get('/api/metrics');
    $response->assertOk();
    expect($response->getContent())->toContain('app_http_requests_total')
        ->and($response->getContent())->toContain('app_events_published_total');
});
