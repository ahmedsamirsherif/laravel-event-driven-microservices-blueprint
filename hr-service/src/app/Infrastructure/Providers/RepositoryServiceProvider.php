<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use App\Infrastructure\Repositories\EloquentEmployeeRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmployeeRepositoryInterface::class, EloquentEmployeeRepository::class);
    }
}
