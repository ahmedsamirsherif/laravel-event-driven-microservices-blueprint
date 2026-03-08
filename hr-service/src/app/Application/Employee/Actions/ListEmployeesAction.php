<?php

declare(strict_types=1);

namespace App\Application\Employee\Actions;

use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployeesAction
{
    public function __construct(private readonly EmployeeRepositoryInterface $repository) {}

    public function execute(int $page = 1, int $perPage = 15, ?string $country = null): LengthAwarePaginator
    {
        return $this->repository->paginate($page, $perPage, $country);
    }
}
