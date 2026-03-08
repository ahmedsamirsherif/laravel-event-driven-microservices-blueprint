<?php

declare(strict_types=1);

namespace App\Domain\Employee\Events;

use App\Domain\Employee\Models\Employee;

class EmployeeUpdated
{
    public function __construct(
        public readonly Employee $employee,
        public readonly array $changedFields = [],
    ) {}
}
