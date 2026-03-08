<?php

declare(strict_types=1);

namespace App\Application\EventProcessing\Handlers;

use App\Domain\Employee\Events\EmployeeEventReceived;
use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class EmployeeUpdatedHandler implements EventHandlerInterface
{
    use InvalidatesCache;

    public function __construct(
        private readonly EmployeeProjectionRepositoryInterface $repository,
    ) {}

    public function supports(array $payload): bool
    {
        return ($payload['event_type'] ?? '') === 'EmployeeUpdated';
    }

    public function handle(array $payload): void
    {
        $country    = $payload['country'];
        $employee   = $payload['data']['employee'] ?? [];
        $employeeId = $payload['data']['employee_id'];

        $this->repository->upsert(ProjectionDataBuilder::build($employeeId, $employee, $country));

        $this->invalidateCache($employeeId, $country);

        Event::dispatch(new EmployeeEventReceived(
            eventType:    'EmployeeUpdated',
            eventId:      $payload['event_id'],
            country:      $country,
            employeeId:   $employeeId,
            employeeData: $employee,
            changedFields: $payload['data']['changed_fields'] ?? [],
        ));

        Log::info('[EmployeeUpdatedHandler][handle] Employee updated event handled', [
            'employee_id' => $employeeId,
            'country' => $country,
        ]);
    }
}
