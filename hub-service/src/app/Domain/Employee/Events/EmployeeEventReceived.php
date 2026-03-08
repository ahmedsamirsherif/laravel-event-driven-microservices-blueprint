<?php

declare(strict_types=1);

namespace App\Domain\Employee\Events;

class EmployeeEventReceived
{
    public function __construct(
        public readonly string $eventType,
        public readonly string $eventId,
        public readonly string $country,
        public readonly int $employeeId,
        public readonly array $employeeData,
        public readonly array $changedFields = [],
    ) {}
}
