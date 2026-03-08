<?php

declare(strict_types=1);

use App\Http\Controllers\CountriesController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\StepsController;
use Illuminate\Support\Facades\Route;

$apiThrottle = app()->runningInConsole()
    ? '10000,1'
    : (app()->environment('local') ? '10000,1' : '120,1');

Route::get('health', HealthController::class);
Route::get('metrics', MetricsController::class);

Route::prefix('v1')->middleware("throttle:{$apiThrottle}")->group(function () {
    Route::apiResource('employees', EmployeeController::class);
    Route::get('countries', [CountriesController::class, 'index']);
    Route::get('steps/{country}', [StepsController::class, 'show']);
});
