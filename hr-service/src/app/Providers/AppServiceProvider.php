<?php

namespace App\Providers;

use App\Application\Employee\Listeners\PublishEmployeeEventToRabbitMQ;
use App\Domain\Employee\Events\EmployeeCreated;
use App\Domain\Employee\Events\EmployeeDeleted;
use App\Domain\Employee\Events\EmployeeUpdated;
use App\Exceptions\Handler;
use App\Infrastructure\Country\CountryFieldsRegistry;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, Handler::class);

        $this->app->singleton(CountryFieldsRegistry::class, fn () => CountryFieldsRegistry::discover());
    }

    public function boot(): void
    {
        Event::listen(EmployeeCreated::class, [PublishEmployeeEventToRabbitMQ::class, 'handleCreated']);
        Event::listen(EmployeeUpdated::class, [PublishEmployeeEventToRabbitMQ::class, 'handleUpdated']);
        Event::listen(EmployeeDeleted::class, [PublishEmployeeEventToRabbitMQ::class, 'handleDeleted']);
    }
}
