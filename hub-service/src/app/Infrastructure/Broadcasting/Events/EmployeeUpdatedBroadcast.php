<?php

declare(strict_types=1);

namespace App\Infrastructure\Broadcasting\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmployeeUpdatedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $eventType,
        public readonly string $country,
        public readonly int $employeeId,
        public readonly array $employeeData,
        public readonly string $eventId,
        public readonly ?array $checklistData = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('employees'),
            new Channel("country.{$this->country}"),
            new Channel("checklist.{$this->country}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'employee.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_type'          => $this->eventType,
            'country'             => $this->country,
            'employee_id'         => $this->employeeId,
            'employee_data'       => $this->employeeData,
            'event_id'            => $this->eventId,
            'checklist_completion' => $this->checklistData,
            'timestamp'           => now()->toIso8601String(),
        ];
    }
}
