<?php

declare(strict_types=1);

use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('reports consistent when both have zero employees', function () {
    Http::fake(['*/api/v1/employees*' => Http::response(['data' => [], 'meta' => ['total' => 0]])]);
    $this->artisan('consistency:check', ['--hr-url' => 'http://fake-hr'])->assertSuccessful();
});

it('reports consistent when counts match with spot-check', function () {
    EmployeeProjection::factory()->usa()->count(3)->create();
    Http::fake([
        '*/api/v1/employees?*' => Http::response(['data' => [['id' => 1], ['id' => 2], ['id' => 3]], 'meta' => ['total' => 3]]),
        '*/api/v1/employees/1' => Http::response(['data' => ['id' => 1]], 200),
        '*/api/v1/employees/2' => Http::response(['data' => ['id' => 2]], 200),
        '*/api/v1/employees/3' => Http::response(['data' => ['id' => 3]], 200),
    ]);
    $this->artisan('consistency:check', ['--hr-url' => 'http://fake-hr'])->assertSuccessful();
});

it('detects inconsistency: count mismatch and orphaned projections', function () {
    EmployeeProjection::factory()->usa()->count(3)->create();
    Http::fake(['*/api/v1/employees*' => Http::response(['data' => [], 'meta' => ['total' => 0]])]);
    $this->artisan('consistency:check', ['--hr-url' => 'http://fake-hr'])->assertFailed();
});

it('fix flag removes projections when HR has zero', function () {
    EmployeeProjection::factory()->usa()->count(3)->create();
    Http::fake(['*/api/v1/employees*' => Http::response(['data' => [], 'meta' => ['total' => 0]])]);
    $this->artisan('consistency:check', ['--hr-url' => 'http://fake-hr', '--fix' => true])->assertFailed();
    expect(EmployeeProjection::count())->toBe(0);
});
