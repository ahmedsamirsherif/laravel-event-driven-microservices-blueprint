<?php

declare(strict_types=1);

namespace App\Application\Employee\Actions;

use App\Domain\Employee\DTOs\UpdateEmployeeDTO;
use App\Domain\Employee\Events\EmployeeUpdated;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Event;

final class UpdateEmployeeAction
{
    public function __construct(private readonly EmployeeRepositoryInterface $repository) {}

    public function execute(Employee $employee, UpdateEmployeeDTO $dto): Employee
    {
        $tracked = ['name', 'last_name', 'salary', 'ssn', 'address', 'goal', 'tax_id',
            'doc_work_permit', 'doc_tax_card', 'doc_health_insurance',
            'doc_social_security', 'doc_employment_contract'];
        $before = $employee->only($tracked);
        $updated = $this->repository->update($employee, $dto);
        $after = $updated->only($tracked);
        $changedFields = array_keys(array_diff_assoc($after, $before));
        Event::dispatch(new EmployeeUpdated($updated, $changedFields));
        return $updated;
    }
}
