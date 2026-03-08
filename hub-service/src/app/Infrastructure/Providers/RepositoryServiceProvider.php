<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use App\Infrastructure\Repositories\EloquentEmployeeProjectionRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            EmployeeProjectionRepositoryInterface::class,
            EloquentEmployeeProjectionRepository::class,
        );
    }
}
