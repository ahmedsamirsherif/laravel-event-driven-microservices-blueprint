<?php

declare(strict_types=1);

namespace App\Domain\Employee\Repositories;

use App\Domain\Employee\DTOs\CreateEmployeeDTO;
use App\Domain\Employee\DTOs\UpdateEmployeeDTO;
use App\Domain\Employee\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmployeeRepositoryInterface
{
    public function create(CreateEmployeeDTO $dto): Employee;
    public function update(Employee $employee, UpdateEmployeeDTO $dto): Employee;
    public function delete(Employee $employee): void;
    public function findOrFail(int $id): Employee;
    public function paginate(int $page, int $perPage, ?string $country = null): LengthAwarePaginator;
}
