<?php

declare(strict_types=1);

namespace App\Application\EventProcessing\Handlers;

use App\Domain\Employee\Events\EmployeeEventReceived;
use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class EmployeeDeletedHandler implements EventHandlerInterface
{
    use InvalidatesCache;

    public function __construct(
        private readonly EmployeeProjectionRepositoryInterface $repository,
    ) {}

    public function supports(array $payload): bool
    {
        return ($payload['event_type'] ?? '') === 'EmployeeDeleted';
    }

    public function handle(array $payload): void
    {
        $country    = $payload['country'];
        $employee   = $payload['data']['employee'] ?? [];
        $employeeId = $payload['data']['employee_id'];

        $this->repository->delete($employeeId);

        $this->invalidateCache($employeeId, $country);

        Event::dispatch(new EmployeeEventReceived(
            eventType:    'EmployeeDeleted',
            eventId:      $payload['event_id'],
            country:      $country,
            employeeId:   $employeeId,
            employeeData: $employee,
        ));

        Log::info('EmployeeDeleted handled', ['employee_id' => $employeeId, 'country' => $country]);
    }
}
