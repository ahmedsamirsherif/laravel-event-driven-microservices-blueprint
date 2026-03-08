<?php

declare(strict_types=1);

use App\Application\EventProcessing\Handlers\EmployeeUpdatedHandler;
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

it('upserts USA+DEU projections on update and invalidates caches', function () {
    $repo    = app(EloquentEmployeeProjectionRepository::class);
    $handler = new EmployeeUpdatedHandler($repo);

    // USA: create then update
    EmployeeProjection::create(['employee_id' => 42, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 Main St']);
    cache()->put('employees:USA:v', 3, 86400);
    cache()->put('checklist:USA:42', ['old'], 3600);
    cache()->put('checklist_summary:USA', ['old'], 3600);

    $handler->handle([
        'event_type' => 'EmployeeUpdated', 'event_id' => 'uuid-upd-123', 'country' => 'USA',
        'data' => [
            'employee_id' => 42, 'changed_fields' => ['salary', 'address'],
            'employee' => ['id' => 42, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 85000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '456 New Ave'],
        ],
    ]);

    $this->assertDatabaseHas('employee_projections', ['employee_id' => 42, 'salary' => 85000, 'address' => '456 New Ave']);
    expect((int) cache()->get('employees:USA:v'))->toBe(4);
    expect(cache()->has('checklist:USA:42'))->toBeFalse();

    Event::assertDispatched(EmployeeEventReceived::class, fn ($e) => $e->eventType === 'EmployeeUpdated' && $e->changedFields === ['salary', 'address']);

    // DEU: update with doc fields
    EmployeeProjection::create(['employee_id' => 99, 'name' => 'Hans', 'last_name' => 'Mueller', 'salary' => 65000, 'country' => 'DEU', 'tax_id' => 'DE123456789', 'goal' => 'Increase productivity']);
    $handler->handle([
        'event_type' => 'EmployeeUpdated', 'event_id' => 'uuid-upd-deu', 'country' => 'DEU',
        'data' => [
            'employee_id' => 99, 'changed_fields' => ['doc_work_permit'],
            'employee' => ['id' => 99, 'name' => 'Hans', 'last_name' => 'Mueller', 'salary' => 65000, 'country' => 'DEU', 'tax_id' => 'DE123456789', 'goal' => 'Increase productivity', 'doc_work_permit' => 'https://docs.example.com/permit.pdf'],
        ],
    ]);

    $this->assertDatabaseHas('employee_projections', ['employee_id' => 99, 'doc_work_permit' => 'https://docs.example.com/permit.pdf']);
});

it('supports only EmployeeUpdated and creates projection if not exists', function () {
    $repo    = app(EloquentEmployeeProjectionRepository::class);
    $handler = new EmployeeUpdatedHandler($repo);

    expect($handler->supports(['event_type' => 'EmployeeUpdated']))->toBeTrue();
    expect($handler->supports(['event_type' => 'EmployeeCreated']))->toBeFalse();

    // Upsert creates new projection when none exists
    $handler->handle([
        'event_type' => 'EmployeeUpdated', 'event_id' => 'uuid-upd-new', 'country' => 'USA',
        'data' => [
            'employee_id' => 77, 'changed_fields' => ['name'],
            'employee' => ['id' => 77, 'name' => 'New', 'last_name' => 'Employee', 'salary' => 50000, 'country' => 'USA', 'ssn' => '999-88-7777', 'address' => '789 Oak Rd'],
        ],
    ]);

    $this->assertDatabaseHas('employee_projections', ['employee_id' => 77, 'name' => 'New']);
});
