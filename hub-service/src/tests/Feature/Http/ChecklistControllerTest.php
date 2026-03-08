<?php

declare(strict_types=1);

use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns aggregate checklist with zero employees for both countries', function () {
    $usa = $this->getJson('/api/v1/checklist/USA');
    $usa->assertOk()->assertJsonPath('data.country', 'USA')->assertJsonPath('data.total_employees', 0);

    $deu = $this->getJson('/api/v1/checklist/DEU');
    $deu->assertOk()->assertJsonPath('data.country', 'DEU')->assertJsonPath('data.total_employees', 0);
});

it('complete USA employee shows 100% with correct checklist structure', function () {
    EmployeeProjection::factory()->usa()->create([
        'employee_id' => 1, 'name' => 'Jane', 'last_name' => 'Smith',
        'salary' => 80000, 'ssn' => '987-65-4321', 'address' => '456 Oak Ave',
    ]);

    $response = $this->getJson('/api/v1/checklist/USA');
    $checklist = $response->json('data.employee_checklists.0');

    expect($checklist['completion_percentage'])->toEqual(100);
    expect($response->json('data.complete_employees'))->toBe(1);
    expect($checklist['items'])->not->toBeEmpty();
    expect($checklist['items'][0])->toHaveKeys(['field', 'completed', 'label']);

    foreach ($checklist['items'] as $item) {
        expect($item['message'])->toBeNull();
    }
});

it('incomplete USA employee shows less than 100% with validation messages', function () {
    EmployeeProjection::factory()->create([
        'employee_id' => 3, 'name' => 'Bob', 'last_name' => 'Jones',
        'salary' => 0, 'country' => 'USA', 'ssn' => null, 'address' => '123 St',
    ]);

    $response = $this->getJson('/api/v1/checklist/USA');
    $checklist = $response->json('data.employee_checklists.0');

    expect($checklist['completion_percentage'])->toBeLessThan(100);
    expect($response->json('data.complete_employees'))->toBe(0);

    $ssnItem = collect($checklist['items'])->firstWhere('field', 'ssn');
    expect($ssnItem['completed'])->toBeFalse()->and($ssnItem['message'])->toBeString()->not->toBeEmpty();

    $salaryItem = collect($checklist['items'])->firstWhere('field', 'salary');
    expect($salaryItem['completed'])->toBeFalse()->and($salaryItem['message'])->toBeString()->not->toBeEmpty();
});

it('overall percentage is calculated across multiple employees', function () {
    EmployeeProjection::factory()->create([
        'employee_id' => 4, 'name' => 'Alice', 'last_name' => 'A', 'salary' => 70000,
        'country' => 'USA', 'ssn' => '111-22-3333', 'address' => '111 St',
    ]);
    EmployeeProjection::factory()->create([
        'employee_id' => 5, 'name' => 'Bob', 'last_name' => 'B', 'salary' => 60000,
        'country' => 'USA', 'ssn' => null, 'address' => null,
    ]);

    $response = $this->getJson('/api/v1/checklist/USA');
    expect($response->json('data.total_employees'))->toBe(2)
        ->and($response->json('data.complete_employees'))->toBe(1)
        ->and($response->json('data.overall_percentage'))->toEqual(50);
});

it('DEU checklist includes only challenge-specified fields with correct completion', function () {
    EmployeeProjection::factory()->deu()->create([
        'employee_id' => 20, 'name' => 'Fritz', 'last_name' => 'Weber',
        'salary' => 60000, 'tax_id' => 'DE111222333', 'goal' => null,
    ]);

    $response = $this->getJson('/api/v1/checklist/DEU');
    $checklist = $response->json('data.employee_checklists.0');
    $fields = collect($checklist['items'])->pluck('field')->all();

    expect($fields)->toHaveCount(3)
        ->toContain('salary')->toContain('tax_id')->toContain('goal');
    expect($checklist['completion_percentage'])->toEqual(67);
});

it('complete DEU employee shows 100%', function () {
    EmployeeProjection::factory()->deu()->create([
        'employee_id' => 21, 'name' => 'Klara', 'last_name' => 'Schmidt',
        'salary' => 70000, 'tax_id' => 'DE999888777', 'goal' => 'Excel at work',
    ]);

    $response = $this->getJson('/api/v1/checklist/DEU');
    expect($response->json('data.employee_checklists.0.completion_percentage'))->toEqual(100)
        ->and($response->json('data.overall_percentage'))->toEqual(100);
});

it('paginates employee checklists correctly', function () {
    EmployeeProjection::factory()->usa()->count(20)->create();

    $response = $this->getJson('/api/v1/checklist/USA');
    $response->assertOk()->assertJsonStructure([
        'data' => ['country', 'total_employees', 'complete_employees', 'overall_percentage', 'employee_checklists'],
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
    ]);
    expect($response->json('data.employee_checklists'))->toHaveCount(15)
        ->and($response->json('meta.total'))->toBe(20)
        ->and($response->json('meta.last_page'))->toBe(2);

    $custom = $this->getJson('/api/v1/checklist/USA?per_page=3&page=2');
    expect($custom->json('data.employee_checklists'))->toHaveCount(3)
        ->and($custom->json('meta.per_page'))->toBe(3);
});

it('summary stats reflect all employees not just current page', function () {
    EmployeeProjection::factory()->usa()->count(5)->create(['ssn' => '111-22-3333', 'address' => '123 St']);
    EmployeeProjection::factory()->usa()->count(5)->create(['ssn' => null, 'address' => null]);

    $response = $this->getJson('/api/v1/checklist/USA?per_page=3');
    expect($response->json('data.employee_checklists'))->toHaveCount(3)
        ->and($response->json('data.total_employees'))->toBe(10)
        ->and($response->json('data.complete_employees'))->toBe(5);
});

it('checklist includes grouped steps with required keys for both countries', function () {
    EmployeeProjection::factory()->usa()->create(['employee_id' => 53, 'salary' => 60000, 'ssn' => '123-45-6789', 'address' => '123 St']);
    EmployeeProjection::factory()->deu()->create(['employee_id' => 54, 'salary' => 60000, 'tax_id' => 'DE123456789', 'goal' => 'Work hard']);

    $usaChecklist = $this->getJson('/api/v1/checklist/USA')->json('data.employee_checklists.0');
    $deuChecklist = $this->getJson('/api/v1/checklist/DEU')->json('data.employee_checklists.0');

    foreach ([$usaChecklist, $deuChecklist] as $cl) {
        expect($cl['steps'])->toHaveCount(2);
        expect($cl['steps'][0])->toHaveKeys(['id', 'label', 'fields', 'step_completion_percentage']);
    }
});

it('returns 404 for unsupported country', function () {
    $this->getJson('/api/v1/checklist/France')->assertNotFound()->assertJson(['error' => ['code' => 'NOT_FOUND']]);
});

it('page beyond last_page returns empty employee_checklists', function () {
    EmployeeProjection::factory()->usa()->count(3)->create();
    $response = $this->getJson('/api/v1/checklist/USA?page=999');
    $response->assertOk();
    expect($response->json('data.employee_checklists'))->toBeEmpty()
        ->and($response->json('data.total_employees'))->toBe(3);
});
