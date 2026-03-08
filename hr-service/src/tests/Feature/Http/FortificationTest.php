<?php

declare(strict_types=1);

use App\Application\Employee\Listeners\PublishEmployeeEventToRabbitMQ;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::listen(PublishEmployeeEventToRabbitMQ::class, fn () => null));

it('rate limit headers present on v1 routes', function () {
    $response = $this->getJson('/api/v1/employees');
    $response->assertOk();
    expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
});

it('forces JSON responses regardless of Accept header', function () {
    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/api/v1/employees')
        ->assertHeader('Content-Type', 'application/json');
});

it('health endpoint includes database and rabbitmq checks', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonStructure(['status', 'service', 'timestamp', 'checks'])
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonStructure(['checks' => ['rabbitmq']]);
});

it('generates X-Request-ID and echoes provided one', function () {
    $auto = $this->getJson('/api/health');
    expect($auto->headers->has('X-Request-ID'))->toBeTrue()
        ->and($auto->headers->get('X-Request-ID'))
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

    $clientId = 'test1234-test-test-test-test12345678';
    $echo = $this->withHeaders(['X-Request-ID' => $clientId])->getJson('/api/health');
    expect($echo->headers->get('X-Request-ID'))->toBe($clientId);
});
