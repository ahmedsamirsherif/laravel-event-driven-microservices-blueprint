<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use App\Http\Resources\EmployeeProjectionCollection;
use App\Http\Resources\EmployeeProjectionResource;
use App\Infrastructure\Country\CountryRegistry;
use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class EmployeeController extends Controller
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly EmployeeProjectionRepositoryInterface $repository,
        private readonly CountryRegistry $registry,
        private readonly PrometheusMetricsService $metrics,
    ) {}

    public function index(Request $request, string $country): JsonResponse
    {
        abort_if(! $this->registry->supports($country), 404, "No configuration for country: {$country}");

        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 15);

        $version    = (int) cache()->get("employees:{$country}:v", 0);
        $cacheKey   = "employees:{$country}:v{$version}:p{$page}:pp{$perPage}";
        $avgKey     = "employees:{$country}:avg:v{$version}";
        $employeesCacheHit = cache()->has($cacheKey);
        $avgCacheHit = cache()->has($avgKey);

        $employeesCacheHit ? $this->metrics->incrementCacheHit('employee_list') : $this->metrics->incrementCacheMiss('employee_list');
        $avgCacheHit ? $this->metrics->incrementCacheHit('avg_salary') : $this->metrics->incrementCacheMiss('avg_salary');

        $employees  = cache()->remember($cacheKey, self::CACHE_TTL, fn () => $this->repository->paginateByCountry($country, $page, $perPage));
        $columns    = $this->registry->for($country)->tableColumns();
        $avgSalary  = (float) cache()->remember($avgKey, self::CACHE_TTL, fn () => $this->repository->averageSalaryByCountry($country));

        Log::debug('[EmployeeController][index] Employee list served', [
            'country' => $country,
            'page' => $page,
            'per_page' => $perPage,
            'employees_cache_hit' => $employeesCacheHit,
            'avg_salary_cache_hit' => $avgCacheHit,
        ]);

        $collection = new EmployeeProjectionCollection($employees);
        $collection->extraMeta = [
            'avg_salary' => round($avgSalary, 2),
            'country'    => $country,
            'columns'    => $columns,
        ];

        return response()->json($collection->toArray($request));
    }

    public function show(string $country, int $id): JsonResponse
    {
        $employee = $this->repository->findByEmployeeId($id);

        abort_if(! $employee || $employee->country !== $country, 404, 'Employee not found.');

        return response()->json(['data' => new EmployeeProjectionResource($employee)]);
    }
}
