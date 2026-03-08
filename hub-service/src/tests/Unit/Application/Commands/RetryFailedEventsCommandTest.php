<?php

declare(strict_types=1);

use App\Domain\EventProcessing\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake());

it('retry-failed command with no failed events exits successfully', function () {
    $this->artisan('events:retry-failed')
        ->expectsOutputToContain('No failed events')
        ->assertExitCode(0);
});

it('retry-failed command retries failed event from event_log', function () {
    EventLog::create([
        'event_id'    => 'uuid-failed-retry-cmd',
        'event_type'  => 'EmployeeCreated',
        'country'     => 'USA',
        'employee_id' => 200,
        'status'      => 'failed',
        'payload'     => [
            'event_type' => 'EmployeeCreated',
            'event_id'   => 'uuid-failed-retry-cmd',
            'timestamp'  => now()->toIso8601String(),
            'country'    => 'USA',
            'schema_version' => '1.0',
            'data' => [
                'employee_id' => 200,
                'changed_fields' => [],
                'employee' => [
                    'id' => 200, 'name' => 'Retry', 'last_name' => 'Me',
                    'salary' => 65000, 'country' => 'USA',
                    'ssn' => '999-88-7777', 'address' => '999 St',
                ],
            ],
        ],
        'error_message' => 'Simulated failure',
        'received_at'  => now(),
    ]);

    $this->artisan('events:retry-failed', ['--limit' => 10])
        ->assertExitCode(0);
});
