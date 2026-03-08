<?php

declare(strict_types=1);

it('returns prometheus metrics in text format with php_info', function () {
    $response = $this->get('/api/metrics');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');
    expect($response->getContent())->toContain('php_info')->and($response->getContent())->not->toBeEmpty();
});

it('shows recorded request and event metrics', function () {
    $metrics = app(\App\Infrastructure\Metrics\PrometheusMetricsService::class);
    $metrics->recordRequest('GET', '/api/v1/checklist/USA', '200', 0.01);
    $metrics->incrementEventsProcessed('EmployeeCreated');

    $response = $this->get('/api/metrics');
    $response->assertOk();
    expect($response->getContent())->toContain('app_http_requests_total')
        ->and($response->getContent())->toContain('app_events_processed_total');
});
