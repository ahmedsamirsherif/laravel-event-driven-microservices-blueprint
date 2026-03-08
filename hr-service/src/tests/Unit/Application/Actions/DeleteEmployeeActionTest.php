<?php

declare(strict_types=1);

use App\Application\Employee\Actions\DeleteEmployeeAction;
use App\Domain\Employee\Events\EmployeeDeleted;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake());

it('deletes employee and dispatches EmployeeDeleted with snapshot', function () {
    $employee = new Employee();
    $employee->fill(['id' => 1, 'name' => 'John', 'last_name' => 'Doe', 'country' => 'USA']);

    $repo = Mockery::mock(EmployeeRepositoryInterface::class);
    $repo->shouldReceive('delete')->once()->with($employee);

    (new DeleteEmployeeAction($repo))->execute($employee);

    Event::assertDispatched(EmployeeDeleted::class, fn ($e) =>
        $e->employee === $employee && $e->employee->name === 'John'
    );
});
