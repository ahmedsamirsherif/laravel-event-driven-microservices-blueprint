<?php

declare(strict_types=1);

namespace App\Domain\Country\USA;

use App\Domain\Country\Contracts\CountryModuleInterface;
use App\Domain\Country\Shared\SharedColumns;
use App\Domain\Country\Shared\SharedSchemaFields;
use App\Domain\Country\Shared\SharedSteps;
use App\Domain\Country\Shared\SharedWidgets;
use App\Domain\Shared\Enums\CountryCode;

/**
 * USA country module — consolidates all USA-specific configuration.
 *
 * Replaces: USAStepsProvider, USAColumnsProvider, USAWidgetProvider, USAValidator
 * Auto-discovered by CountryClassResolver via {Country}Module naming convention.
 */
final class USAModule implements CountryModuleInterface
{
    public function country(): CountryCode
    {
        return CountryCode::USA;
    }

    // ─── Navigation UI ───

    public function navigationSteps(): array
    {
        return [
            SharedSteps::dashboard(),
            SharedSteps::employees(),
        ];
    }

    public function tableColumns(): array
    {
        return [
            ...SharedColumns::base(),
            ['key' => 'ssn', 'label' => 'SSN (masked)', 'sortable' => false, 'format' => 'masked'],
        ];
    }

    public function dashboardWidgets(): array
    {
        return [
            SharedWidgets::employeeCount('USA'),
            [
                'id'          => 'average_salary',
                'type'        => 'stat_card',
                'title'       => 'Average Salary',
                'data_source' => '/api/v1/employees/USA',
                'channel'     => 'country.USA',
                'meta'        => ['field' => 'data.avg_salary', 'icon' => 'dollar-sign', 'format' => 'currency'],
            ],
            [
                'id'          => 'completion_rate',
                'type'        => 'progress_bar',
                'title'       => 'Checklist Completion Rate',
                'data_source' => '/api/v1/checklist/USA',
                'channel'     => 'country.USA',
                'meta'        => ['field' => 'data.overall_percentage', 'icon' => 'check-circle', 'format' => 'percentage'],
            ],
        ];
    }

    // ─── Form / Schema ───

    public function schemaFields(): array
    {
        return [
            ...SharedSchemaFields::base(),
            'ssn'     => ['type' => 'string', 'required' => false, 'pattern' => '^\d{3}-\d{2}-\d{4}$'],
            'address' => ['type' => 'string', 'required' => false],
        ];
    }

    // ─── Validation ───

    public function validationRules(): array
    {
        return [
            'salary'  => ['required', 'numeric', 'min:0.01'],
            'ssn'     => ['required', 'string', 'regex:/^\d{3}-\d{2}-\d{4}$/'],
            'address' => ['required', 'string'],
        ];
    }

    public function validationMessages(): array
    {
        return [
            'salary.required'  => 'Salary is required.',
            'salary.numeric'   => 'Salary must be a number.',
            'salary.min'       => 'Salary must be greater than zero.',
            'ssn.required'     => 'SSN is required for USA employees.',
            'ssn.regex'        => 'SSN must be in format XXX-XX-XXXX.',
            'address.required' => 'Address is required for USA employees.',
        ];
    }

    public function requiredFields(): array
    {
        return ['salary', 'ssn', 'address'];
    }

    // ─── Checklist ───

    public function checklistSteps(): array
    {
        return [
            ['id' => 1, 'label' => 'Compensation',      'fields' => ['salary']],
            ['id' => 2, 'label' => 'Identity & Address', 'fields' => ['ssn', 'address']],
        ];
    }
}
