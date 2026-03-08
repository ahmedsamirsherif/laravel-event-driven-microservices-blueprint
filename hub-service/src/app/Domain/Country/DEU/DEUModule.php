<?php

declare(strict_types=1);

namespace App\Domain\Country\DEU;

use App\Domain\Country\Contracts\CountryModuleInterface;
use App\Domain\Country\Shared\SharedColumns;
use App\Domain\Country\Shared\SharedSchemaFields;
use App\Domain\Country\Shared\SharedSteps;
use App\Domain\Country\Shared\SharedWidgets;
use App\Domain\Shared\Enums\CountryCode;

/**
 * Germany (DEU) country module — consolidates all Germany-specific configuration.
 *
 * Auto-discovered by CountryClassResolver via {Country}Module naming convention.
 */
final class DEUModule implements CountryModuleInterface
{
    public function country(): CountryCode
    {
        return CountryCode::DEU;
    }

    // ─── Navigation UI ───

    public function navigationSteps(): array
    {
        return [
            SharedSteps::dashboard(),
            SharedSteps::employees(),
            [
                'id'    => 3,
                'key'   => 'documentation',
                'label' => 'Documentation',
                'icon'  => 'file-text',
                'path'  => '/documentation',
                'order' => 3,
            ],
        ];
    }

    public function tableColumns(): array
    {
        return [
            ...SharedColumns::base(),
            ['key' => 'goal', 'label' => 'Goal', 'sortable' => false],
        ];
    }

    public function dashboardWidgets(): array
    {
        return [
            SharedWidgets::employeeCount('DEU'),
            [
                'id'          => 'goal_tracking',
                'type'        => 'progress_list',
                'title'       => 'Goal Tracking',
                'data_source' => '/api/v1/employees/DEU',
                'channel'     => 'country.DEU',
                'meta'        => ['field' => 'data.goals_set_percentage', 'icon' => 'target', 'format' => 'percentage'],
            ],
        ];
    }

    // ─── Form / Schema ───

    public function schemaFields(): array
    {
        return [
            ...SharedSchemaFields::base(),
            'tax_id' => ['type' => 'string',  'required' => true,  'pattern' => '^DE\d{9}$'],
            'goal'   => ['type' => 'string',  'required' => true],
        ];
    }

    // ─── Validation ───

    public function validationRules(): array
    {
        return [
            'salary' => ['required', 'numeric', 'min:0.01'],
            'tax_id' => ['required', 'string', 'regex:/^DE\d{9}$/'],
            'goal'   => ['required', 'string'],
        ];
    }

    public function validationMessages(): array
    {
        return [
            'salary.required'  => 'Salary is required.',
            'salary.numeric'   => 'Salary must be a number.',
            'salary.min'       => 'Salary must be greater than zero.',
            'tax_id.required'  => 'Tax ID is required for Germany employees.',
            'tax_id.regex'     => 'Tax ID must be in format DE + 9 digits.',
            'goal.required'    => 'Goal is required for Germany employees.',
        ];
    }

    public function requiredFields(): array
    {
        return ['salary', 'tax_id', 'goal'];
    }

    // ─── Checklist ───

    public function checklistSteps(): array
    {
        return [
            ['id' => 1, 'label' => 'Compensation',  'fields' => ['salary']],
            ['id' => 2, 'label' => 'Tax & Goals',    'fields' => ['tax_id', 'goal']],
        ];
    }
}
