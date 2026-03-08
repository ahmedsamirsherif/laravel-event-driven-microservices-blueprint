<?php

declare(strict_types=1);

use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\SchemaController;
use App\Http\Controllers\StepsController;
use Illuminate\Support\Facades\Route;

$apiThrottle = app()->runningInConsole()
    ? '10000,1'
    : (app()->environment('local') ? '10000,1' : '120,1');

Route::get('health', HealthController::class);
Route::get('metrics', MetricsController::class);

Route::prefix('v1')->middleware("throttle:{$apiThrottle}")->group(function () {
    // Checklist: aggregate per-country validation status
    Route::get('checklist/{country}', [ChecklistController::class, 'show']);

    // Employees: per-country list with column definitions
    Route::get('employees/{country}', [EmployeeController::class, 'index']);
    Route::get('employees/{country}/{id}', [EmployeeController::class, 'show']);

    // Server-Driven UI: steps and schema configuration
    Route::get('steps/{country}', [StepsController::class, 'show']);
    Route::get('schema/{country}', [SchemaController::class, 'show']);
});
