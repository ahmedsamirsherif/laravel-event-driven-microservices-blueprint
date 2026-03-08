<?php

declare(strict_types=1);

namespace App\Domain\EventProcessing\DTOs;

use InvalidArgumentException;

final readonly class ReceivedEventDTO
{
    private const REQUIRED_FIELDS = ['event_type', 'event_id', 'timestamp', 'country', 'schema_version', 'data'];

    public function __construct(
        public string $eventType,
        public string $eventId,
        public string $timestamp,
        public string $country,
        public string $schemaVersion,
        public array $data,
        public int $employeeId,
        public array $employeeData,
        public array $changedFields,
    ) {}

    public static function fromArray(array $payload): self
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $data = $payload['data'];

        if (! isset($data['employee_id'])) {
            throw new InvalidArgumentException("Missing required field: data.employee_id");
        }

        return new self(
            eventType: (string) $payload['event_type'],
            eventId: (string) $payload['event_id'],
            timestamp: (string) $payload['timestamp'],
            country: (string) $payload['country'],
            schemaVersion: (string) $payload['schema_version'],
            data: $data,
            employeeId: (int) $data['employee_id'],
            employeeData: $data['employee'] ?? [],
            changedFields: $data['changed_fields'] ?? [],
        );
    }

    public static function fromJson(string $json): self
    {
        if (! json_validate($json)) {
            throw new InvalidArgumentException('Invalid JSON payload');
        }

        $payload = json_decode($json, true);

        return self::fromArray($payload);
    }
}
