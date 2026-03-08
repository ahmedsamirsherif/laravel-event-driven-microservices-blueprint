<?php

declare(strict_types=1);

use App\Domain\EventProcessing\DTOs\ReceivedEventDTO;

function validPayload(): array
{
    return [
        'event_type' => 'EmployeeCreated', 'event_id' => '550e8400-e29b-41d4-a716-446655440000',
        'timestamp' => '2024-02-09T10:30:00Z', 'country' => 'USA', 'schema_version' => '1.0',
        'data' => [
            'employee_id' => 42, 'changed_fields' => [],
            'employee' => ['id' => 42, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'country' => 'USA', 'ssn' => '123-45-6789', 'address' => '123 Main St'],
        ],
    ];
}

it('parses valid payload from array and JSON with correct properties', function () {
    $dto = ReceivedEventDTO::fromArray(validPayload());
    expect($dto->eventType)->toBe('EmployeeCreated')
        ->and($dto->eventId)->toBe('550e8400-e29b-41d4-a716-446655440000')
        ->and($dto->country)->toBe('USA')
        ->and($dto->employeeId)->toBe(42)
        ->and($dto->employeeData['name'])->toBe('John')
        ->and($dto->employeeData['salary'])->toBe(75000);

    $fromJson = ReceivedEventDTO::fromJson(json_encode(validPayload()));
    expect($fromJson->eventType)->toBe('EmployeeCreated')->and($fromJson->employeeId)->toBe(42);
});

it('parses changed fields for EmployeeUpdated', function () {
    $payload = array_merge(validPayload(), [
        'event_type' => 'EmployeeUpdated',
        'data' => ['employee_id' => 42, 'changed_fields' => ['salary', 'address'], 'employee' => ['id' => 42, 'name' => 'John']],
    ]);
    expect(ReceivedEventDTO::fromArray($payload)->changedFields)->toBe(['salary', 'address']);
});

it('throws on invalid JSON and missing required fields', function () {
    expect(fn () => ReceivedEventDTO::fromJson('{invalid json}'))->toThrow(\InvalidArgumentException::class, 'Invalid JSON');

    foreach (['event_type', 'event_id', 'country'] as $field) {
        $payload = validPayload();
        unset($payload[$field]);
        expect(fn () => ReceivedEventDTO::fromArray($payload))->toThrow(\InvalidArgumentException::class, $field);
    }

    $noEmpId = validPayload();
    unset($noEmpId['data']['employee_id']);
    expect(fn () => ReceivedEventDTO::fromArray($noEmpId))->toThrow(\InvalidArgumentException::class, 'employee_id');
});

it('defaults optional fields and is readonly', function () {
    $payload = validPayload();
    unset($payload['data']['changed_fields'], $payload['data']['employee']);
    $dto = ReceivedEventDTO::fromArray($payload);
    expect($dto->changedFields)->toBe([])->and($dto->employeeData)->toBe([]);

    $dto2 = ReceivedEventDTO::fromArray(validPayload());
    expect(fn () => $dto2->eventType = 'EmployeeUpdated')->toThrow(\Error::class);
});
