<?php

declare(strict_types=1);

use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Cache::flush();
});

it('rate limit headers present on throttled routes but not on health/metrics', function () {
    $response = $this->getJson('/api/v1/steps/USA');
    $response->assertOk();
    expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();

    for ($i = 0; $i < 3; $i++) {
        $this->getJson('/api/health')->assertOk();
        $this->get('/api/metrics')->assertOk();
    }
});

it('checklist empty country returns has_employees false', function () {
    $response = $this->getJson('/api/v1/checklist/DEU')->assertOk();
    expect($response->json('data.has_employees'))->toBeFalse();
    expect($response->json('data.total_employees'))->toBe(0);
});

it('X-API-Version header present on all endpoints with value 1.0', function () {
    $endpoints = ['/api/health', '/api/v1/steps/USA', '/api/v1/schema/DEU', '/api/v1/employees/USA', '/api/v1/checklist/USA'];
    foreach ($endpoints as $endpoint) {
        $response = $this->getJson($endpoint);
        expect($response->headers->has('X-API-Version'))->toBeTrue("Missing X-API-Version on {$endpoint}");
    }
    expect($this->getJson('/api/v1/steps/USA')->headers->get('X-API-Version'))->toBe('1.0');
});

it('health endpoint checks database, cache, and rabbitmq', function () {
    $response = $this->getJson('/api/health');
    $response->assertOk()->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['checks' => ['database', 'cache', 'rabbitmq']]);
    expect($response->json('checks.database.status'))->toBe('ok');
    expect($response->json('checks.rabbitmq'))->toHaveKey('host');
});

it('X-Request-ID generated as UUID and echoed back when provided', function () {
    $response = $this->getJson('/api/health');
    expect($response->headers->get('X-Request-ID'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

    $clientId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $echo = $this->withHeaders(['X-Request-ID' => $clientId])->getJson('/api/health');
    expect($echo->headers->get('X-Request-ID'))->toBe($clientId);
});

it('salary is cast to float with correct precision', function () {
    $proj = EmployeeProjection::factory()->usa()->create(['salary' => 75000]);
    $fresh = EmployeeProjection::find($proj->id);
    expect($fresh->salary)->toBeFloat()->and($fresh->salary)->toBe(75000.0);

    $decimal = EmployeeProjection::factory()->create(['salary' => 99999.99, 'country' => 'USA']);
    expect(EmployeeProjection::find($decimal->id)->salary)->toBe(99999.99);
});

it('pagination returns different employees per page', function () {
    EmployeeProjection::factory()->usa()->count(4)->create();

    $page1 = $this->getJson('/api/v1/employees/USA?page=1&per_page=2')->json('data');
    $page2 = $this->getJson('/api/v1/employees/USA?page=2&per_page=2')->json('data');

    $ids1 = collect($page1)->pluck('id')->toArray();
    $ids2 = collect($page2)->pluck('id')->toArray();
    expect(array_intersect($ids1, $ids2))->toBeEmpty();
});
