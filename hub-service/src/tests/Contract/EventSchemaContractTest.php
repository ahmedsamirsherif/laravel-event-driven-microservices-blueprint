<?php

declare(strict_types=1);

use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;
use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake());

function loadSchema(): array
{
    $candidates = [
        base_path('../../../../contracts/employee-event.schema.json'),
        dirname(__DIR__, 5) . '/contracts/employee-event.schema.json',
        realpath(base_path() . '/../../..') . '/contracts/employee-event.schema.json',
    ];
    foreach ($candidates as $path) {
        if ($path && file_exists($path)) {
            return json_decode(file_get_contents($path), true) ?? [];
        }
    }
    return [
        'required' => ['event_type', 'event_id', 'timestamp', 'country', 'schema_version', 'data'],
        'properties' => [
            'event_type' => ['enum' => ['EmployeeCreated', 'EmployeeUpdated', 'EmployeeDeleted']],
            'country' => ['enum' => ['USA', 'DEU']],
            'schema_version' => ['const' => '1.0'],
        ],
    ];
}

function validateAgainstSchema(array $payload): bool
{
    $schema = loadSchema();
    if (empty($schema)) return true;
    foreach ($schema['required'] ?? [] as $field) {
        if (!array_key_exists($field, $payload)) return false;
    }
    $allowedTypes = $schema['properties']['event_type']['enum'] ?? [];
    if (!empty($allowedTypes) && !in_array($payload['event_type'] ?? '', $allowedTypes)) return false;
    $allowedCountries = $schema['properties']['country']['enum'] ?? [];
    if (!empty($allowedCountries) && !in_array($payload['country'] ?? '', $allowedCountries)) return false;
    $requiredVersion = $schema['properties']['schema_version']['const'] ?? null;
    if ($requiredVersion && ($payload['schema_version'] ?? '') !== $requiredVersion) return false;
    return true;
}

function makePayload(string $type = 'EmployeeCreated', string $country = 'USA', array $overrides = []): array
{
    return array_merge([
        'event_type' => $type, 'event_id' => (string) \Illuminate\Support\Str::uuid(),
        'timestamp' => now()->toIso8601String(), 'country' => $country, 'schema_version' => '1.0',
        'data' => ['employee_id' => 1, 'changed_fields' => [],
            'employee' => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'country' => $country]],
    ], $overrides);
}

it('all valid event type and country combinations are schema-compliant', function () {
    foreach (['EmployeeCreated', 'EmployeeUpdated', 'EmployeeDeleted'] as $type) {
        foreach (['USA', 'DEU'] as $country) {
            expect(validateAgainstSchema(makePayload($type, $country)))->toBeTrue("{$type}/{$country} violates schema");
        }
    }
});

it('rejects payloads with missing fields, invalid country, or wrong version', function () {
    $missingType = makePayload();
    unset($missingType['event_type']);
    expect(validateAgainstSchema($missingType))->toBeFalse();

    expect(validateAgainstSchema(makePayload('EmployeeCreated', 'France')))->toBeFalse();
    expect(validateAgainstSchema(makePayload('EmployeeCreated', 'USA', ['schema_version' => '2.0'])))->toBeFalse();
});

it('pipeline processes all valid schema event types without throwing', function () {
    $pipeline = app(EventProcessingPipeline::class);
    EmployeeProjection::create([
        'employee_id' => 100, 'name' => 'Test', 'last_name' => 'User',
        'salary' => 60000, 'country' => 'USA', 'ssn' => '111-22-3333', 'address' => 'Test St',
    ]);

    foreach (['EmployeeCreated', 'EmployeeUpdated', 'EmployeeDeleted'] as $i => $type) {
        $threw = false;
        try {
            $pipeline->process(makePayload($type, 'USA', [
                'event_id' => (string) \Illuminate\Support\Str::uuid(),
                'data' => ['employee_id' => 100 + $i, 'changed_fields' => [],
                    'employee' => ['id' => 100 + $i, 'name' => 'Test', 'last_name' => 'User', 'salary' => 60000, 'country' => 'USA', 'ssn' => '111-22-3333', 'address' => 'Test St']],
            ]));
        } catch (\Throwable) { $threw = true; }
        expect($threw)->toBeFalse("Pipeline should handle {$type}");
    }
});
