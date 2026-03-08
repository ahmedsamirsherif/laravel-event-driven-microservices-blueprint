<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Support\ServiceProvider;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis as RedisAdapter;

final class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PrometheusMetricsService::class, function ($app) {
            return new PrometheusMetricsService($this->createStorage($app));
        });
    }

    private function createStorage($app): Adapter
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

            RedisAdapter::setPrefix('PROM_HR_');

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
