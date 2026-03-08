<?php

declare(strict_types=1);

namespace App\Application\EventProcessing\Handlers;

final class ProjectionDataBuilder
{
    public static function build(int $employeeId, array $employee, string $country): array
    {
        return [
            'employee_id' => $employeeId,
            'name' => $employee['name'] ?? '',
            'last_name' => $employee['last_name'] ?? '',
            'salary' => $employee['salary'] ?? 0,
            'country' => $country,
            'ssn' => $employee['ssn'] ?? null,
            'address' => $employee['address'] ?? null,
            'goal' => $employee['goal'] ?? null,
            'tax_id' => $employee['tax_id'] ?? null,
            'doc_work_permit' => $employee['doc_work_permit'] ?? null,
            'doc_tax_card' => $employee['doc_tax_card'] ?? null,
            'doc_health_insurance' => $employee['doc_health_insurance'] ?? null,
            'doc_social_security' => $employee['doc_social_security'] ?? null,
            'doc_employment_contract' => $employee['doc_employment_contract'] ?? null,
            'raw_data' => $employee,
        ];
    }
}