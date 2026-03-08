<?php

declare(strict_types=1);

use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;
use App\Domain\Employee\Events\EmployeeEventReceived;
use App\Domain\Employee\Models\EmployeeProjection;
use App\Domain\EventProcessing\Models\EventLog;
use App\Domain\EventProcessing\Models\ProcessedEvent;
use App\Infrastructure\Broadcasting\Events\EmployeeUpdatedBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake());

function usaCreatedPayload(int $employeeId = 1, string $eventId = 'uuid-test-1'): array
{
    return [
        'event_type' => 'EmployeeCreated', 'event_id' => $eventId,
        'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
        'data' => [
            'employee_id' => $employeeId, 'changed_fields' => [],
            'employee' => [
                'id' => $employeeId, 'name' => 'John', 'last_name' => 'Doe',
                'salary' => 75000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 Main St',
            ],
        ],
    ];
}

function deuCreatedPayload(int $employeeId = 2, string $eventId = 'uuid-test-2'): array
{
    return [
        'event_type' => 'EmployeeCreated', 'event_id' => $eventId,
        'timestamp' => now()->toIso8601String(), 'country' => 'DEU', 'schema_version' => '1.0',
        'data' => [
            'employee_id' => $employeeId, 'changed_fields' => [],
            'employee' => [
                'id' => $employeeId, 'name' => 'Hans', 'last_name' => 'Mueller',
                'salary' => 65000, 'country' => 'DEU', 'tax_id' => 'DE123456789',
                'goal' => 'Improve team productivity',
                'doc_work_permit' => 'https://docs.example.com/permit.pdf',
                'doc_tax_card' => 'https://docs.example.com/tax.pdf',
                'doc_health_insurance' => 'https://docs.example.com/health.pdf',
                'doc_social_security' => 'https://docs.example.com/social.pdf',
                'doc_employment_contract' => 'https://docs.example.com/contract.pdf',
            ],
        ],
    ];
}

it('creates USA projection with event log and idempotency record', function () {
    $pipeline = app(EventProcessingPipeline::class);
    $pipeline->process(usaCreatedPayload(employeeId: 10, eventId: 'uuid-create-usa'));

    $proj = EmployeeProjection::where('employee_id', 10)->first();
    expect($proj)->not->toBeNull()
        ->and($proj->name)->toBe('John')
        ->and($proj->country)->toBe('USA')
        ->and($proj->ssn)->toBe('123-45-6789');

    $log = EventLog::where('event_id', 'uuid-create-usa')->first();
    expect($log->status)->toBe('processed')->and($log->event_type)->toBe('EmployeeCreated');
    expect(ProcessedEvent::where('event_id', 'uuid-create-usa')->exists())->toBeTrue();

    Event::assertDispatched(EmployeeEventReceived::class, fn ($e) => $e->eventType === 'EmployeeCreated' && $e->employeeId === 10);
});

it('creates DEU projection with country-specific and document fields', function () {
    $pipeline = app(EventProcessingPipeline::class);
    $pipeline->process(deuCreatedPayload(employeeId: 20, eventId: 'uuid-create-deu'));

    $proj = EmployeeProjection::where('employee_id', 20)->first();
    expect($proj)->not->toBeNull()
        ->and($proj->country)->toBe('DEU')
        ->and($proj->tax_id)->toBe('DE123456789')
        ->and($proj->goal)->toBe('Improve team productivity')
        ->and($proj->doc_work_permit)->toBe('https://docs.example.com/permit.pdf')
        ->and($proj->doc_employment_contract)->toBe('https://docs.example.com/contract.pdf');
});

it('updates existing projection and logs event', function () {
    EmployeeProjection::create([
        'employee_id' => 30, 'name' => 'John', 'last_name' => 'Doe',
        'salary' => 50000, 'country' => 'USA', 'ssn' => '111-22-3333', 'address' => 'Old Address',
    ]);

    app(EventProcessingPipeline::class)->process([
        'event_type' => 'EmployeeUpdated', 'event_id' => 'uuid-update-usa',
        'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
        'data' => [
            'employee_id' => 30, 'changed_fields' => ['salary', 'address'],
            'employee' => ['id' => 30, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 80000, 'country' => 'USA', 'ssn' => '111-22-3333', 'address' => 'New Address'],
        ],
    ]);

    $proj = EmployeeProjection::where('employee_id', 30)->first();
    expect($proj->salary)->toBe(80000.0)->and($proj->address)->toBe('New Address');
    expect(EventLog::where('event_id', 'uuid-update-usa')->where('status', 'processed')->exists())->toBeTrue();
});

it('deletes projection and handles non-existent gracefully', function () {
    EmployeeProjection::create([
        'employee_id' => 40, 'name' => 'ToDelete', 'last_name' => 'Employee',
        'salary' => 60000, 'country' => 'DEU',
    ]);

    $pipeline = app(EventProcessingPipeline::class);
    $deletePayload = fn (int $id, string $eventId) => [
        'event_type' => 'EmployeeDeleted', 'event_id' => $eventId,
        'timestamp' => now()->toIso8601String(), 'country' => 'DEU', 'schema_version' => '1.0',
        'data' => ['employee_id' => $id, 'changed_fields' => [], 'employee' => []],
    ];

    $pipeline->process($deletePayload(40, 'uuid-delete-1'));
    expect(EmployeeProjection::where('employee_id', 40)->exists())->toBeFalse();

    expect(fn () => $pipeline->process($deletePayload(999, 'uuid-delete-nonexist')))->not->toThrow(\Throwable::class);
});

it('enforces idempotency: duplicate event_id skipped, different event_id processed', function () {
    $pipeline = app(EventProcessingPipeline::class);
    $pipeline->process(usaCreatedPayload(50, 'uuid-idempotent'));
    $pipeline->process(usaCreatedPayload(50, 'uuid-idempotent'));
    expect(EmployeeProjection::where('employee_id', 50)->count())->toBe(1);
    expect(ProcessedEvent::where('event_id', 'uuid-idempotent')->count())->toBe(1);

    $pipeline->process([
        'event_type' => 'EmployeeUpdated', 'event_id' => 'uuid-idempotent-2',
        'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
        'data' => ['employee_id' => 50, 'changed_fields' => ['salary'],
            'employee' => ['id' => 50, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 90000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 St']],
    ]);
    expect(ProcessedEvent::count())->toBe(2);
    expect(EmployeeProjection::where('employee_id', 50)->first()->salary)->toBe(90000.0);
});

it('handles unknown event types and failed payloads gracefully', function () {
    $pipeline = app(EventProcessingPipeline::class);

    expect(fn () => $pipeline->process([
        'event_type' => 'EmployeePromoted', 'event_id' => 'uuid-unknown',
        'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
        'data' => ['employee_id' => 99],
    ]))->not->toThrow(\Throwable::class);

    $threw = false;
    try {
        $pipeline->process([
            'event_type' => 'EmployeeCreated', 'event_id' => 'uuid-fail',
            'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
            'data' => [],
        ]);
    } catch (\Throwable) { $threw = true; }
    expect($threw)->toBeTrue();
});

it('broadcasts on correct channels with checklist data', function () {
    $broadcast = new EmployeeUpdatedBroadcast(
        eventType: 'EmployeeCreated', country: 'USA', employeeId: 10,
        employeeData: ['id' => 10, 'name' => 'Test'], eventId: 'uuid-ws-test',
        checklistData: ['completion_percentage' => 100, 'items' => []],
    );

    $channels = collect($broadcast->broadcastOn())->map(fn ($c) => $c->name)->all();
    expect($channels)->toContain('employees')->and($channels)->toContain('country.USA')->and($channels)->toContain('checklist.USA');

    $payload = $broadcast->broadcastWith();
    expect($payload)->toHaveKey('checklist_completion')->and($payload['checklist_completion']['completion_percentage'])->toBe(100);

    $nullBroadcast = new EmployeeUpdatedBroadcast(
        eventType: 'EmployeeDeleted', country: 'USA', employeeId: 30, employeeData: [], eventId: 'uuid-null',
    );
    expect($nullBroadcast->broadcastWith()['checklist_completion'])->toBeNull();
});
