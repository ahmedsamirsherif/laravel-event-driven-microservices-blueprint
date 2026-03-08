<?php

declare(strict_types=1);

namespace App\Domain\Country\Shared;

final class SharedSteps
{
    public static function dashboard(int $order = 1): array
    {
        return [
            'id'    => $order,
            'key'   => 'dashboard',
            'label' => 'Dashboard',
            'icon'  => 'home',
            'path'  => '/dashboard',
            'order' => $order,
        ];
    }

    public static function employees(int $order = 2): array
    {
        return [
            'id'    => $order,
            'key'   => 'employees',
            'label' => 'Employees',
            'icon'  => 'users',
            'path'  => '/employees',
            'order' => $order,
        ];
    }
}
