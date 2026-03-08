<?php

declare(strict_types=1);

use App\Domain\Employee\Models\EmployeeProjection;
use App\Domain\EventProcessing\Models\EventLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake());

it('dry-run shows events without processing', function () {
    EventLog::create([
        'event_id' => 'uuid-dry-run', 'event_type' => 'EmployeeCreated', 'country' => 'USA', 'employee_id' => 1, 'status' => 'processed',
        'payload' => ['event_type' => 'EmployeeCreated', 'event_id' => 'uuid-dry-run', 'country' => 'USA'],
        'received_at' => now(),
    ]);

    $this->artisan('events:replay', ['--dry-run' => true, '--force' => true])
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('no changes made')
        ->assertExitCode(0);

    expect(EmployeeProjection::count())->toBe(0);
});
