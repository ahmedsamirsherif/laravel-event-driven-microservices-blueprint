<?php

declare(strict_types=1);

use App\Domain\Employee\Models\EmployeeProjection;
use App\Domain\EventProcessing\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake());

it('rebuilds from projections and accepts country filter', function () {
    EmployeeProjection::factory()->usa()->count(2)->create();
    EmployeeProjection::factory()->deu()->count(2)->create();

    $this->artisan('events:replay', ['--force' => true])->assertExitCode(0);
    $this->artisan('events:replay', ['--country' => 'USA', '--force' => true])->assertExitCode(0);
});

it('processes events from event_log', function () {
    EventLog::create([
        'event_id' => 'uuid-replay-log', 'event_type' => 'EmployeeCreated', 'country' => 'USA', 'employee_id' => 101, 'status' => 'processed',
        'payload' => [
            'event_type' => 'EmployeeCreated', 'event_id' => 'uuid-replay-fresh', 'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
            'data' => ['employee_id' => 101, 'changed_fields' => [], 'employee' => ['id' => 101, 'name' => 'Replay', 'last_name' => 'Test', 'salary' => 75000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 Main St']],
        ],
        'received_at' => now(),
    ]);

    $this->artisan('events:replay', ['--force' => true])->assertExitCode(0);
});
