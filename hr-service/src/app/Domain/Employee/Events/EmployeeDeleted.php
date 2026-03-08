<?php

declare(strict_types=1);

namespace App\Domain\Employee\Events;

use App\Domain\Employee\Models\Employee;

class EmployeeDeleted
{
    public function __construct(public readonly Employee $employee) {}
}
