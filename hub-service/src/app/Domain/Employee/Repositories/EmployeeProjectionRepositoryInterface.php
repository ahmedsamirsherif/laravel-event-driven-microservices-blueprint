<?php

declare(strict_types=1);

namespace App\Domain\Employee\Repositories;

use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmployeeProjectionRepositoryInterface
{
    public function upsert(array $data): EmployeeProjection;
    public function delete(int $employeeId): void;
    public function findByEmployeeId(int $employeeId): ?EmployeeProjection;
    public function paginateByCountry(string $country, int $page, int $perPage): LengthAwarePaginator;
    public function allByCountry(string $country): \Illuminate\Database\Eloquent\Collection;
    public function averageSalaryByCountry(string $country): float;
    public function countByCountry(string $country): int;
}
