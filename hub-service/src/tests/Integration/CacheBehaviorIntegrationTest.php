<?php

declare(strict_types=1);

use App\Application\Checklist\ChecklistService;
use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;
use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Cache::flush();
});

it('populates employee and checklist caches after first access', function () {
    EmployeeProjection::factory()->usa()->create([
        'employee_id' => 100, 'ssn' => '123-45-6789', 'address' => '123 Main St', 'salary' => 75000,
    ]);
    EmployeeProjection::factory()->usa()->count(2)->create();

    $version = (int) cache()->get('employees:USA:v', 0);
    expect(Cache::has("employees:USA:v{$version}:p1:pp15"))->toBeFalse();

    $this->getJson('/api/v1/employees/USA?page=1&per_page=15')->assertOk();
    expect(Cache::has("employees:USA:v{$version}:p1:pp15"))->toBeTrue();

    $service = app(ChecklistService::class);
    $checklist = $service->getChecklist(100, 'USA');
    expect($checklist)->toHaveKey('completion_percentage')->and($checklist['employee_id'])->toBe(100);
    expect(Cache::has('checklist:USA:100'))->toBeTrue();
});

it('invalidates employee cache when event is processed', function () {
    EmployeeProjection::factory()->usa()->count(2)->create();
    $this->getJson('/api/v1/employees/USA?page=1&per_page=15')->assertOk();
    $vBefore = (int) cache()->get('employees:USA:v', 0);

    app(EventProcessingPipeline::class)->process([
        'event_type' => 'EmployeeCreated', 'event_id' => 'uuid-cache-inval',
        'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
        'data' => ['employee_id' => 200, 'changed_fields' => [],
            'employee' => ['id' => 200, 'name' => 'New', 'last_name' => 'Employee', 'salary' => 65000, 'country' => 'USA', 'ssn' => '999-88-7777', 'address' => '999 St']],
    ]);

    expect((int) cache()->get('employees:USA:v', 0))->toBeGreaterThan($vBefore);
    expect($this->getJson('/api/v1/employees/USA?page=1&per_page=15')->json('meta.total'))->toBe(3);
});

it('rebuilds checklist cache after employee update and scopes by country', function () {
    EmployeeProjection::factory()->create([
        'employee_id' => 300, 'name' => 'Test', 'last_name' => 'User',
        'salary' => 50000, 'country' => 'USA', 'ssn' => null, 'address' => null,
    ]);

    $service = app(ChecklistService::class);
    expect($service->getChecklist(300, 'USA')['completion_percentage'])->toBeLessThan(100);

    app(EventProcessingPipeline::class)->process([
        'event_type' => 'EmployeeUpdated', 'event_id' => 'uuid-cache-rebuild',
        'timestamp' => now()->toIso8601String(), 'country' => 'USA', 'schema_version' => '1.0',
        'data' => ['employee_id' => 300, 'changed_fields' => ['ssn', 'address'],
            'employee' => ['id' => 300, 'name' => 'Test', 'last_name' => 'User', 'salary' => 50000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 Main St']],
    ]);

    expect($service->getChecklist(300, 'USA')['completion_percentage'])->toEqual(100);
});

it('DEU checklist validates challenge-specified fields and updates via events', function () {
    EmployeeProjection::factory()->deu()->create([
        'employee_id' => 400, 'salary' => 65000, 'tax_id' => 'DE123456789', 'goal' => null,
    ]);

    $service = app(ChecklistService::class);
    $initial = $service->getChecklist(400, 'DEU');
    expect($initial['completion_percentage'])->toBeLessThan(100);
    expect(collect($initial['items'])->pluck('field')->all())->toEqualCanonicalizing(['salary', 'tax_id', 'goal']);

    app(EventProcessingPipeline::class)->process([
        'event_type' => 'EmployeeUpdated', 'event_id' => 'uuid-deu-goal',
        'timestamp' => now()->toIso8601String(), 'country' => 'DEU', 'schema_version' => '1.0',
        'data' => ['employee_id' => 400, 'changed_fields' => ['goal'],
            'employee' => ['id' => 400, 'name' => 'Hans', 'last_name' => 'Mueller', 'salary' => 65000, 'country' => 'DEU', 'tax_id' => 'DE123456789', 'goal' => 'Complete onboarding']],
    ]);

    expect($service->getChecklist(400, 'DEU')['completion_percentage'])->toEqual(100);
});

it('getPaginatedChecklists returns correct structure and pagination', function () {
    EmployeeProjection::factory()->usa()->count(7)->create();

    $service = app(ChecklistService::class);
    $result = $service->getPaginatedChecklists('USA', 1, 5);

    expect($result['checklists'])->toHaveCount(5);
    expect($result['pagination'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
    expect($result['pagination']['total'])->toBe(7)->and($result['pagination']['last_page'])->toBe(2);

    $checklist = $result['checklists'][0];
    expect($checklist)->toHaveKeys(['employee_id', 'country', 'completion_percentage', 'items', 'steps']);

    $page2 = $service->getPaginatedChecklists('USA', 2, 5);
    expect($page2['checklists'])->toHaveCount(2)->and($page2['pagination']['current_page'])->toBe(2);
});
