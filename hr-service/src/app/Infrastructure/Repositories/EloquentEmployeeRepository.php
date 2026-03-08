<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Employee\DTOs\CreateEmployeeDTO;
use App\Domain\Employee\DTOs\UpdateEmployeeDTO;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class EloquentEmployeeRepository implements EmployeeRepositoryInterface
{
    public function create(CreateEmployeeDTO $dto): Employee
    {
        return Employee::create([
            'name'                    => $dto->name,
            'last_name'               => $dto->lastName,
            'salary'                  => $dto->salary,
            'country'                 => $dto->country,
            'ssn'                     => $dto->ssn,
            'address'                 => $dto->address,
            'goal'                    => $dto->goal,
            'tax_id'                  => $dto->taxId,
            'doc_work_permit'         => $dto->docWorkPermit,
            'doc_tax_card'            => $dto->docTaxCard,
            'doc_health_insurance'    => $dto->docHealthInsurance,
            'doc_social_security'     => $dto->docSocialSecurity,
            'doc_employment_contract' => $dto->docEmploymentContract,
        ]);
    }

    public function update(Employee $employee, UpdateEmployeeDTO $dto): Employee
    {
        $data = array_filter([
            'name'                    => $dto->name,
            'last_name'               => $dto->lastName,
            'salary'                  => $dto->salary,
            'ssn'                     => $dto->ssn,
            'address'                 => $dto->address,
            'goal'                    => $dto->goal,
            'tax_id'                  => $dto->taxId,
            'doc_work_permit'         => $dto->docWorkPermit,
            'doc_tax_card'            => $dto->docTaxCard,
            'doc_health_insurance'    => $dto->docHealthInsurance,
            'doc_social_security'     => $dto->docSocialSecurity,
            'doc_employment_contract' => $dto->docEmploymentContract,
        ], fn ($v) => $v !== null);

        $employee->update($data);

        return $employee->fresh();
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    public function findOrFail(int $id): Employee
    {
        return Employee::findOrFail($id);
    }

    public function paginate(int $page, int $perPage, ?string $country = null): LengthAwarePaginator
    {
        $query = Employee::query()->orderBy('created_at', 'desc');

        if ($country !== null) {
            $query->where('country', $country);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
