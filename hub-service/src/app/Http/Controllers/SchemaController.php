<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Country\CountryRegistry;
use Illuminate\Http\JsonResponse;

final class SchemaController extends Controller
{
    public function __construct(
        private readonly CountryRegistry $registry,
    ) {}

    /**
     * GET /api/v1/schema/{country}
     * Returns the full server-driven UI schema for the country:
     * - form field definitions
     * - dashboard widget configuration
     * - column definitions
     */
    public function show(string $country): JsonResponse
    {
        abort_if(! $this->registry->supports($country), 404, "No schema configuration for country: {$country}");

        $module = $this->registry->for($country);

        return response()->json([
            'data' => [
                'country'     => $country,
                'form_fields' => $module->schemaFields(),
                'widgets'     => $module->dashboardWidgets(),
                'columns'     => $module->tableColumns(),
            ],
        ]);
    }
}
