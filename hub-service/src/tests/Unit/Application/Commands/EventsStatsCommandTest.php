<?php

declare(strict_types=1);

use App\Domain\EventProcessing\Models\EventLog;
use App\Domain\EventProcessing\Models\ProcessedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake());

it('runs with empty tables and accepts country option', function () {
    $this->artisan('events:stats')->assertExitCode(0);
    $this->artisan('events:stats', ['--country' => 'USA'])->assertExitCode(0);
});

it('shows correct counts when events exist', function () {
    EventLog::create(['event_id' => 'uuid-1', 'event_type' => 'EmployeeCreated', 'country' => 'USA', 'employee_id' => 1, 'status' => 'processed', 'payload' => [], 'received_at' => now()]);
    EventLog::create(['event_id' => 'uuid-2', 'event_type' => 'EmployeeUpdated', 'country' => 'DEU', 'employee_id' => 2, 'status' => 'failed', 'payload' => [], 'received_at' => now(), 'error_message' => 'test']);
    ProcessedEvent::create(['event_id' => 'uuid-1', 'event_type' => 'EmployeeCreated', 'processed_at' => now()]);

    $this->artisan('events:stats')->expectsOutputToContain('processed')->assertExitCode(0);
});
