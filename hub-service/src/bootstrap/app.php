<?php

use App\Http\Middleware\AddApiVersionHeader;
use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RecordRequestMetrics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            RecordRequestMetrics::class,
            AddRequestId::class,
            AddApiVersionHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
