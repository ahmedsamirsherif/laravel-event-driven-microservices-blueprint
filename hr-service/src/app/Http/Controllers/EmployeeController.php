<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Employee\Actions\CreateEmployeeAction;
use App\Application\Employee\Actions\DeleteEmployeeAction;
use App\Application\Employee\Actions\ListEmployeesAction;
use App\Application\Employee\Actions\UpdateEmployeeAction;
use App\Domain\Employee\DTOs\CreateEmployeeDTO;
use App\Domain\Employee\DTOs\UpdateEmployeeDTO;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeCollection;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class EmployeeController extends Controller
{
    public function index(Request $request, ListEmployeesAction $action): EmployeeCollection
    {
        $country = $request->query('country');

        return new EmployeeCollection(
            $action->execute(
                (int) $request->query('page', 1),
                (int) $request->query('per_page', 15),
                $country ? (string) $country : null,
            )
        );
    }

    public function store(StoreEmployeeRequest $request, CreateEmployeeAction $action): JsonResponse
    {
        $employee = $action->execute(CreateEmployeeDTO::fromArray($request->validated()));

        Log::info('Employee created', [
            'employee_id' => $employee->id,
            'country' => $employee->country,
        ]);

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    public function show(int $id, EmployeeRepositoryInterface $repo): EmployeeResource
    {
        return new EmployeeResource($repo->findOrFail($id));
    }

    public function update(UpdateEmployeeRequest $request, int $id, UpdateEmployeeAction $action, EmployeeRepositoryInterface $repo): EmployeeResource
    {
        $employee = $repo->findOrFail($id);
        $validated = $request->validated();

        $employee = $action->execute($employee, UpdateEmployeeDTO::fromArray($validated));

        Log::info('Employee updated', [
            'employee_id' => $employee->id,
            'country' => $employee->country,
            'changed_fields' => array_keys($validated),
        ]);

        return new EmployeeResource($employee);
    }

    public function destroy(int $id, DeleteEmployeeAction $action, EmployeeRepositoryInterface $repo): JsonResponse
    {
        $employee = $repo->findOrFail($id);

        $action->execute($employee);

        Log::info('Employee deleted', [
            'employee_id' => $employee->id,
            'country' => $employee->country,
        ]);

        return response()->json(null, 204);
    }
}
