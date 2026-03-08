<?php

declare(strict_types=1);

namespace App\Domain\Country\Shared;

final class SharedColumns
{
    /** @return array<int, array{key: string, label: string, sortable: bool, format?: string}> */
    public static function base(): array
    {
        return [
            ['key' => 'id',        'label' => 'ID',         'sortable' => true],
            ['key' => 'name',      'label' => 'First Name', 'sortable' => true],
            ['key' => 'last_name', 'label' => 'Last Name',  'sortable' => true],
            ['key' => 'salary',    'label' => 'Salary',     'sortable' => true, 'format' => 'currency'],
        ];
    }
}
