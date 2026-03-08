<?php

declare(strict_types=1);

use App\Domain\Employee\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake());

it('creates a USA employee with all required fields', function () {
    $this->postJson('/api/v1/employees', [
        'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000,
        'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 Main St',
    ])->assertCreated()
      ->assertJsonPath('data.name', 'John')
      ->assertJsonPath('data.country', 'USA');

    $this->assertDatabaseHas('employees', ['name' => 'John', 'country' => 'USA']);
});

it('creates a DEU employee with doc URLs', function () {
    $this->postJson('/api/v1/employees', [
        'name' => 'Hans', 'last_name' => 'Mueller', 'salary' => 65000,
        'country' => 'DEU', 'tax_id' => 'DE123456789', 'goal' => 'Increase productivity',
        'doc_work_permit' => 'https://docs.example.com/permit.pdf',
        'doc_employment_contract' => 'https://docs.example.com/contract.pdf',
    ])->assertCreated()
      ->assertJsonPath('data.country', 'DEU')
      ->assertJsonPath('data.doc_work_permit', 'https://docs.example.com/permit.pdf');

    $this->assertDatabaseHas('employees', ['name' => 'Hans', 'country' => 'DEU']);
});

it('rejects invalid inputs with 422', function (array $payload) {
    $this->postJson('/api/v1/employees', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
})->with([
    'invalid USA SSN' => [['name' => 'J', 'last_name' => 'D', 'salary' => 75000, 'country' => 'USA', 'ssn' => 'bad', 'address' => 'St']],
    'missing DEU tax_id' => [['name' => 'H', 'last_name' => 'M', 'salary' => 65000, 'country' => 'DEU', 'goal' => 'G']],
    'unsupported country' => [['name' => 'P', 'last_name' => 'D', 'salary' => 55000, 'country' => 'France']],
    'negative salary' => [['name' => 'J', 'last_name' => 'D', 'salary' => -1000, 'country' => 'USA']],
]);

it('lists employees with pagination and country filter', function () {
    Employee::factory()->usa()->count(3)->create();
    Employee::factory()->deu()->count(2)->create();

    $this->getJson('/api/v1/employees?per_page=2&page=1')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);

    expect($this->getJson('/api/v1/employees?country=USA')->json('meta.total'))->toBe(3);
});

it('shows employee with masked SSN and returns 404 for missing', function () {
    $emp = Employee::factory()->usa()->create(['ssn' => '123-45-6789']);

    $response = $this->getJson("/api/v1/employees/{$emp->id}");
    $response->assertOk()->assertJsonPath('data.id', $emp->id);
    expect($response->json('data.ssn'))->toContain('6789')
        ->and($response->json('data.ssn'))->not->toBe('123-45-6789');

    $this->getJson('/api/v1/employees/9999')
        ->assertNotFound()
        ->assertJson(['error' => ['code' => 'NOT_FOUND']]);
});

it('shows DEU employee with doc fields in response', function () {
    $emp = Employee::factory()->deu()->create();
    $this->getJson("/api/v1/employees/{$emp->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['doc_work_permit', 'doc_tax_card', 'doc_health_insurance']]);
});

it('updates employee fields partially and rejects invalid formats', function () {
    $emp = Employee::factory()->usa()->create(['name' => 'John', 'salary' => 50000]);

    $this->putJson("/api/v1/employees/{$emp->id}", ['salary' => 70000])
        ->assertOk()
        ->assertJsonPath('data.name', 'John')
        ->assertJsonPath('data.salary', 70000);

    $this->putJson("/api/v1/employees/{$emp->id}", ['ssn' => 'bad'])->assertUnprocessable();
});

it('deletes an employee and returns 404 for missing on update/delete', function () {
    $emp = Employee::factory()->usa()->create();
    $this->deleteJson("/api/v1/employees/{$emp->id}")->assertNoContent();
    $this->assertDatabaseMissing('employees', ['id' => $emp->id]);

    $this->putJson('/api/v1/employees/9999', ['salary' => 60000])->assertNotFound();
    $this->deleteJson('/api/v1/employees/9999')->assertNotFound();
});
