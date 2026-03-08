<?php

declare(strict_types=1);

namespace App\Domain\Country\Contracts;

use App\Domain\Shared\Enums\CountryCode;

interface CountryModuleInterface
{
    public function country(): CountryCode;

    public function navigationSteps(): array;

    public function tableColumns(): array;

    public function dashboardWidgets(): array;

    public function schemaFields(): array;

    public function validationRules(): array;

    public function validationMessages(): array;

    public function requiredFields(): array;

    public function checklistSteps(): array;
}
