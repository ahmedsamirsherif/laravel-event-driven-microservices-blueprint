<?php

declare(strict_types=1);

namespace App\Application\EventProcessing\Handlers;

use App\Domain\Employee\Events\EmployeeEventReceived;
use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class EmployeeCreatedHandler implements EventHandlerInterface
{
    use InvalidatesCache;

    public function __construct(
        private readonly EmployeeProjectionRepositoryInterface $repository,
    ) {}

    public function supports(array $payload): bool
    {
        return ($payload['event_type'] ?? '') === 'EmployeeCreated';
    }

    public function handle(array $payload): void
    {
        $country    = $payload['country'];
        $employee   = $payload['data']['employee'] ?? [];
        $employeeId = $payload['data']['employee_id'];

        $this->repository->upsert(ProjectionDataBuilder::build($employeeId, $employee, $country));

        $this->invalidateCache($employeeId, $country);

        Event::dispatch(new EmployeeEventReceived(
            eventType:   'EmployeeCreated',
            eventId:     $payload['event_id'],
            country:     $country,
            employeeId:  $employeeId,
            employeeData: $employee,
        ));

        Log::info('[EmployeeCreatedHandler][handle] Employee created event handled', [
            'employee_id' => $employeeId,
            'country' => $country,
        ]);
    }
}
