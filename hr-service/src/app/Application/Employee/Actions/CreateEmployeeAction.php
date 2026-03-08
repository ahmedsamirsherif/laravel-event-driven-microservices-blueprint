<?php

declare(strict_types=1);

namespace App\Application\Employee\Actions;

use App\Domain\Employee\DTOs\CreateEmployeeDTO;
use App\Domain\Employee\Events\EmployeeCreated;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Event;

final class CreateEmployeeAction
{
    public function __construct(private readonly EmployeeRepositoryInterface $repository) {}

    public function execute(CreateEmployeeDTO $dto): Employee
    {
        $employee = $this->repository->create($dto);
        Event::dispatch(new EmployeeCreated($employee));
        return $employee;
    }
}
