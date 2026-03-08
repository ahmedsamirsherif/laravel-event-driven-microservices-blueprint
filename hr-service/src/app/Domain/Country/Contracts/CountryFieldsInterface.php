<?php

declare(strict_types=1);

namespace App\Domain\Country\Contracts;

use App\Domain\Shared\Enums\CountryCode;

interface CountryFieldsInterface
{
    public function country(): CountryCode;

    public function storeRules(): array;

    public function updateRules(): array;

    public function storeMessages(): array;

    public function resourceFields(object $employee): array;

    public function steps(): array;
}
