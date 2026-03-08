<?php

declare(strict_types=1);

use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns USA employees with columns, meta, and avg_salary', function () {
    EmployeeProjection::factory()->usa()->create(['salary' => 50000]);
    EmployeeProjection::factory()->usa()->create(['salary' => 100000]);

    $response = $this->getJson('/api/v1/employees/USA');
    $response->assertOk()->assertJsonStructure([
        'data',
        'meta' => ['current_page', 'last_page', 'per_page', 'total', 'country', 'avg_salary', 'columns' => [['key', 'label', 'sortable']]],
    ]);

    expect($response->json('meta.country'))->toBe('USA')
        ->and($response->json('meta.avg_salary'))->toEqual(75000.00);

    $keys = array_column($response->json('meta.columns'), 'key');
    expect($keys)->toContain('ssn');
});

it('returns DEU employees with correct columns and doc fields', function () {
    EmployeeProjection::factory()->deu()->create([
        'employee_id' => 50, 'doc_work_permit' => 'https://docs.example.com/permit.pdf',
    ]);

    $columns = $this->getJson('/api/v1/employees/DEU')->json('meta.columns');
    $keys = array_column($columns, 'key');
    expect($keys)->toContain('goal')->and($keys)->not->toContain('ssn');

    $employee = $this->getJson('/api/v1/employees/DEU')->json('data.0');
    expect($employee)->toHaveKey('doc_work_permit');
});

it('paginates employees and returns empty for no data or beyond last page', function () {
    EmployeeProjection::factory()->usa()->count(5)->create();

    $response = $this->getJson('/api/v1/employees/USA?per_page=2&page=1');
    $response->assertOk()->assertJsonPath('meta.per_page', 2)->assertJsonPath('meta.total', 5);

    $empty = $this->getJson('/api/v1/employees/USA?page=999&per_page=15');
    $empty->assertOk();
    expect($empty->json('data'))->toBeEmpty()->and($empty->json('meta.total'))->toBe(5);

    $response0 = $this->getJson('/api/v1/employees/DEU');
    expect($response0->json('meta.avg_salary'))->toEqual(0);
});

it('returns single employee by country and id', function () {
    EmployeeProjection::factory()->usa()->create(['employee_id' => 42]);

    $this->getJson('/api/v1/employees/USA/42')->assertOk()->assertJsonPath('data.id', 42);
});

it('returns 404 for missing employee, wrong country, or unsupported country', function () {
    EmployeeProjection::factory()->create(['employee_id' => 10, 'country' => 'DEU']);

    $this->getJson('/api/v1/employees/USA/9999')->assertNotFound();
    $this->getJson('/api/v1/employees/USA/10')->assertNotFound();
    $this->getJson('/api/v1/employees/France')->assertNotFound()->assertJson(['error' => ['code' => 'NOT_FOUND']]);
});

it('returns DEU employee with document URL fields including nulls', function () {
    EmployeeProjection::factory()->deu()->create([
        'employee_id' => 50,
        'doc_work_permit' => 'https://docs.example.com/permit.pdf',
        'doc_tax_card' => 'https://docs.example.com/tax.pdf',
        'doc_health_insurance' => 'https://docs.example.com/health.pdf',
        'doc_social_security' => 'https://docs.example.com/social.pdf',
        'doc_employment_contract' => 'https://docs.example.com/contract.pdf',
    ]);
    EmployeeProjection::factory()->deu()->create([
        'employee_id' => 51, 'doc_work_permit' => null, 'doc_employment_contract' => null,
    ]);

    $r1 = $this->getJson('/api/v1/employees/DEU/50');
    $r1->assertOk()
        ->assertJsonPath('data.doc_work_permit', 'https://docs.example.com/permit.pdf')
        ->assertJsonPath('data.doc_employment_contract', 'https://docs.example.com/contract.pdf');

    $r2 = $this->getJson('/api/v1/employees/DEU/51');
    $r2->assertOk();
    expect($r2->json('data.doc_work_permit'))->toBeNull();
});
