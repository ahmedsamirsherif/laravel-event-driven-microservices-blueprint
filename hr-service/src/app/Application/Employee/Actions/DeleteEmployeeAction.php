<?php

declare(strict_types=1);

namespace App\Application\Employee\Actions;

use App\Domain\Employee\Events\EmployeeDeleted;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Event;

final class DeleteEmployeeAction
{
    public function __construct(private readonly EmployeeRepositoryInterface $repository) {}

    public function execute(Employee $employee): void
    {
        $this->repository->delete($employee);
        Event::dispatch(new EmployeeDeleted($employee));
    }
}
