<?php

declare(strict_types=1);

use App\Application\EventProcessing\Handlers\EmployeeCreatedHandler;
use App\Domain\Employee\Events\EmployeeEventReceived;
use App\Infrastructure\Repositories\EloquentEmployeeProjectionRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Cache::flush();
});

it('upserts USA+DEU projections, invalidates caches, supports only EmployeeCreated', function () {
    $repo    = app(EloquentEmployeeProjectionRepository::class);
    $handler = new EmployeeCreatedHandler($repo);

    // Supports check
    expect($handler->supports(['event_type' => 'EmployeeCreated']))->toBeTrue();
    expect($handler->supports(['event_type' => 'EmployeeUpdated']))->toBeFalse();

    // USA creation with cache invalidation
    cache()->put('employees:USA:v', 5, 86400);
    cache()->put('checklist:USA:42', ['old'], 3600);
    cache()->put('checklist_summary:USA', ['old'], 3600);

    $handler->handle([
        'event_type' => 'EmployeeCreated', 'event_id' => 'uuid-123', 'country' => 'USA',
        'data' => [
            'employee_id' => 42, 'changed_fields' => [],
            'employee' => ['id' => 42, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 Main St'],
        ],
    ]);

    $this->assertDatabaseHas('employee_projections', ['employee_id' => 42, 'name' => 'John', 'country' => 'USA']);
    expect((int) cache()->get('employees:USA:v'))->toBe(6);
    expect(cache()->has('checklist:USA:42'))->toBeFalse();
    expect(cache()->has('checklist_summary:USA'))->toBeFalse();

    // DEU creation with doc fields
    $handler->handle([
        'event_type' => 'EmployeeCreated', 'event_id' => 'uuid-deu-456', 'country' => 'DEU',
        'data' => [
            'employee_id' => 99, 'changed_fields' => [],
            'employee' => ['id' => 99, 'name' => 'Hans', 'last_name' => 'Mueller', 'salary' => 65000, 'country' => 'DEU', 'tax_id' => 'DE123456789', 'goal' => 'Increase productivity', 'doc_work_permit' => 'https://docs.example.com/permit.pdf', 'doc_employment_contract' => 'https://docs.example.com/contract.pdf'],
        ],
    ]);

    $this->assertDatabaseHas('employee_projections', ['employee_id' => 99, 'country' => 'DEU', 'doc_work_permit' => 'https://docs.example.com/permit.pdf']);

    Event::assertDispatched(EmployeeEventReceived::class, fn ($e) => $e->employeeId === 42 && $e->country === 'USA');
    Event::assertDispatched(EmployeeEventReceived::class, fn ($e) => $e->employeeId === 99 && $e->country === 'DEU');
});
