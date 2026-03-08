<?php

declare(strict_types=1);

namespace App\Domain\Country\Shared;

final class SharedWidgets
{
    /** @return array{id: string, type: string, title: string, data_source: string, channel: string, meta: array} */
    public static function employeeCount(string $country): array
    {
        return [
            'id'          => 'employee_count',
            'type'        => 'stat_card',
            'title'       => 'Total Employees',
            'data_source' => "/api/v1/employees/{$country}",
            'channel'     => "country.{$country}",
            'meta'        => ['field' => 'meta.total', 'icon' => 'users'],
        ];
    }
}
