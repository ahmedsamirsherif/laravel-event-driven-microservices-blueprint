<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Checklist\ChecklistService;
use App\Application\EventProcessing\Handlers\EmployeeCreatedHandler;
use App\Application\EventProcessing\Handlers\EmployeeDeletedHandler;
use App\Application\EventProcessing\Handlers\EmployeeUpdatedHandler;
use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;
use App\Exceptions\Handler;
use App\Infrastructure\Country\CountryRegistry;
use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis as RedisAdapter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, Handler::class);
        $this->app->singleton(PrometheusMetricsService::class, function ($app) {
            return new PrometheusMetricsService($this->createMetricsStorage($app));
        });

        // Checklist business logic — uses cache() internally
        $this->app->singleton(ChecklistService::class);

        // Auto-discovers all country modules via CountryClassResolver convention:
        // App\Domain\Country\{Country}\{Country}Module implements CountryModuleInterface
        $this->app->singleton(CountryRegistry::class, fn () => CountryRegistry::discover());

        $this->app->singleton(EventProcessingPipeline::class, function ($app) {
            $pipeline = new EventProcessingPipeline();
            $pipeline->pipe($app->make(EmployeeCreatedHandler::class));
            $pipeline->pipe($app->make(EmployeeUpdatedHandler::class));
            $pipeline->pipe($app->make(EmployeeDeletedHandler::class));
            return $pipeline;
        });
    }

    public function boot(): void {}

    private function createMetricsStorage($app): Adapter
    {
        if ($app->environment('testing')) {
            return new InMemory();
        }

        $host = env('REDIS_HOST');

        if (! $host) {
            return new InMemory();
        }

        try {
            $password = env('REDIS_PASSWORD');

            RedisAdapter::setPrefix('PROM_HUB_');

            return new RedisAdapter([
                'host' => $host,
                'port' => (int) env('REDIS_PORT', 6379),
                'password' => ($password && $password !== 'null') ? $password : null,
                'database' => 2,
                'timeout' => 0.1,
                'read_timeout' => '10',
                'persistent_connections' => false,
            ]);
        } catch (\Throwable) {
            return new InMemory();
        }
    }
}
