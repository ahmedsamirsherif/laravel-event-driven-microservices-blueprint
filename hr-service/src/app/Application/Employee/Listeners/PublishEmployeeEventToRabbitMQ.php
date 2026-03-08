<?php

declare(strict_types=1);

namespace App\Application\Employee\Listeners;

use App\Domain\Employee\Events\EmployeeCreated;
use App\Domain\Employee\Events\EmployeeDeleted;
use App\Domain\Employee\Events\EmployeeUpdated;
use App\Domain\Employee\Models\Employee;
use App\Infrastructure\Messaging\RabbitMQPublisher;
use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PublishEmployeeEventToRabbitMQ
{
    public function __construct(
        private readonly RabbitMQPublisher $publisher,
        private readonly PrometheusMetricsService $metrics,
    ) {}

    public function handleCreated(EmployeeCreated $event): void
    {
        $this->publish('EmployeeCreated', $event->employee, []);
    }

    public function handleUpdated(EmployeeUpdated $event): void
    {
        $this->publish('EmployeeUpdated', $event->employee, $event->changedFields);
    }

    public function handleDeleted(EmployeeDeleted $event): void
    {
        $this->publish('EmployeeDeleted', $event->employee, []);
    }

    private function publish(string $eventType, Employee $employee, array $changedFields): void
    {
        $country = $employee->country;
        $action = strtolower(str_replace('Employee', '', $eventType));
        $routingKey = "employee.{$action}.".strtolower($country);

        $payload = [
            'event_type' => $eventType,
            'event_id' => (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'country' => $country,
            'schema_version' => '1.0',
            'data' => [
                'employee_id' => $employee->id,
                'changed_fields' => $changedFields,
                'employee' => $this->employeePayload($employee),
            ],
        ];

        $publishStart = microtime(true);

        try {
            $this->publisher->publish($routingKey, $payload);
            $this->metrics->incrementEventsPublished($eventType, $country);
            $this->metrics->recordEventPublishDuration($eventType, microtime(true) - $publishStart);
        } catch (\Throwable $e) {
            $this->metrics->incrementEventPublishFailure($eventType, $country);
            Log::error('Failed to publish employee event', [
                'event_type' => $eventType,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function employeePayload(Employee $employee): array
    {
        $payload = Arr::only($employee->toArray(), [
            'id',
            'name',
            'last_name',
            'salary',
            'country',
            'ssn',
            'address',
            'goal',
            'tax_id',
            'doc_work_permit',
            'doc_tax_card',
            'doc_health_insurance',
            'doc_social_security',
            'doc_employment_contract',
        ]);

        $payload['id'] = (int) $employee->id;
        $payload['salary'] = (float) $employee->salary;
        $payload['country'] = $employee->country;

        return $payload;
    }
}
