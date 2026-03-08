<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (NotFoundHttpException $e) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage() ?: 'Resource not found.', 'details' => null],
            ], 404);
        });

        $this->renderable(function (ValidationException $e) {
            return response()->json([
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage(), 'details' => $e->errors()],
            ], 422);
        });

        $this->renderable(function (HttpException $e) {
            return response()->json([
                'error' => ['code' => 'HTTP_ERROR', 'message' => $e->getMessage() ?: 'Request error.', 'details' => null],
            ], $e->getStatusCode());
        });

        $this->renderable(function (Throwable $e) {
            Log::error('[Handler][register] Unhandled exception', [
                'exception' => $e,
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => app()->isProduction() ? 'Internal server error.' : $e->getMessage(),
                    'details' => app()->isProduction() ? null : ['exception' => get_class($e)],
                ],
            ], 500);
        });
    }
}
