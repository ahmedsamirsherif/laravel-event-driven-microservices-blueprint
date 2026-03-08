<?php

declare(strict_types=1);

use App\Application\Employee\Actions\CreateEmployeeAction;
use App\Domain\Employee\DTOs\CreateEmployeeDTO;
use App\Domain\Employee\Events\EmployeeCreated;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake());

it('creates employee via repository and dispatches EmployeeCreated', function () {
    $dto = new CreateEmployeeDTO(name: 'John', lastName: 'Doe', salary: 75000.0, country: 'USA', ssn: '123-45-6789', address: '123 Main St');
    $employee = new Employee(['id' => 1, 'name' => 'John', 'last_name' => 'Doe', 'country' => 'USA']);
    $employee->id = 1;

    $repo = Mockery::mock(EmployeeRepositoryInterface::class);
    $repo->shouldReceive('create')->once()->with($dto)->andReturn($employee);

    $result = (new CreateEmployeeAction($repo))->execute($dto);

    expect($result)->toBe($employee);
    Event::assertDispatched(EmployeeCreated::class, fn ($e) => $e->employee === $employee);
});
