<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Employee\Models\EmployeeProjection;
use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class EloquentEmployeeProjectionRepository implements EmployeeProjectionRepositoryInterface
{
    public function upsert(array $data): EmployeeProjection
    {
        return EmployeeProjection::updateOrCreate(
            ['employee_id' => $data['employee_id']],
            $data,
        );
    }

    public function delete(int $employeeId): void
    {
        EmployeeProjection::where('employee_id', $employeeId)->delete();
    }

    public function findByEmployeeId(int $employeeId): ?EmployeeProjection
    {
        return EmployeeProjection::where('employee_id', $employeeId)->first();
    }

    public function paginateByCountry(string $country, int $page, int $perPage): LengthAwarePaginator
    {
        return EmployeeProjection::where('country', $country)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function allByCountry(string $country): Collection
    {
        return EmployeeProjection::where('country', $country)->get();
    }

    public function averageSalaryByCountry(string $country): float
    {
        return (float) EmployeeProjection::where('country', $country)->avg('salary');
    }

    public function countByCountry(string $country): int
    {
        return EmployeeProjection::where('country', $country)->count();
    }
}
