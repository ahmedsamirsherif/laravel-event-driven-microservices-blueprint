<?php

declare(strict_types=1);

use App\Application\EventProcessing\Handlers\EmployeeDeletedHandler;
use App\Domain\Employee\Events\EmployeeEventReceived;
use App\Domain\Employee\Models\EmployeeProjection;
use App\Infrastructure\Repositories\EloquentEmployeeProjectionRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Cache::flush();
});

it('deletes projection, invalidates caches, and supports only EmployeeDeleted', function () {
    $repo    = app(EloquentEmployeeProjectionRepository::class);
    $handler = new EmployeeDeletedHandler($repo);

    expect($handler->supports(['event_type' => 'EmployeeDeleted']))->toBeTrue();
    expect($handler->supports(['event_type' => 'EmployeeCreated']))->toBeFalse();

    EmployeeProjection::create(['employee_id' => 55, 'name' => 'Jane', 'last_name' => 'Doe', 'salary' => 60000, 'country' => 'DEU']);
    cache()->put('employees:DEU:v', 5, 86400);
    cache()->put('checklist:DEU:55', ['old'], 3600);
    cache()->put('checklist_summary:DEU', ['old'], 3600);

    $handler->handle([
        'event_type' => 'EmployeeDeleted', 'event_id' => 'uuid-del', 'country' => 'DEU',
        'data' => ['employee_id' => 55, 'changed_fields' => [], 'employee' => []],
    ]);

    $this->assertDatabaseMissing('employee_projections', ['employee_id' => 55]);
    expect((int) cache()->get('employees:DEU:v'))->toBe(6);
    expect(cache()->has('checklist:DEU:55'))->toBeFalse();
    expect(cache()->has('checklist_summary:DEU'))->toBeFalse();
    Event::assertDispatched(EmployeeEventReceived::class, fn ($e) => $e->eventType === 'EmployeeDeleted' && $e->employeeId === 55);
});
