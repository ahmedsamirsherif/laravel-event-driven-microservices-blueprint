<?php

declare(strict_types=1);

use App\Application\Employee\Actions\UpdateEmployeeAction;
use App\Domain\Employee\DTOs\UpdateEmployeeDTO;
use App\Domain\Employee\Events\EmployeeUpdated;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake());

it('updates employee and dispatches EmployeeUpdated with changed fields', function () {
    $before = new Employee();
    $before->fill(['name' => 'John', 'last_name' => 'Doe', 'salary' => 50000, 'ssn' => '123-45-6789', 'address' => '123 Main St', 'goal' => null, 'tax_id' => null]);

    $after = new Employee();
    $after->fill(['name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'ssn' => '123-45-6789', 'address' => '123 Main St', 'goal' => null, 'tax_id' => null]);

    $dto = new UpdateEmployeeDTO(salary: 75000.0);

    $repo = Mockery::mock(EmployeeRepositoryInterface::class);
    $repo->shouldReceive('update')->once()->with($before, $dto)->andReturn($after);

    $result = (new UpdateEmployeeAction($repo))->execute($before, $dto);

    expect($result->salary)->toBe(75000.0);
    Event::assertDispatched(EmployeeUpdated::class, fn ($e) => in_array('salary', $e->changedFields));
});
