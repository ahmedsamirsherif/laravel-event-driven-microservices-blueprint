<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Enums\CountryCode;
use App\Infrastructure\Country\CountryRegistry;
use Illuminate\Http\JsonResponse;

final class StepsController extends Controller
{
    public function __construct(
        private readonly CountryRegistry $registry,
    ) {}

    /**
     * GET /api/v1/steps/{country}
     * Returns navigation steps configuration for the given country.
     * USA: Dashboard, Employees
     * DEU: Dashboard, Employees, Documentation
     */
    public function show(string $country): JsonResponse
    {
        abort_if(! $this->registry->supports($country), 404, "No steps configuration for country: {$country}");

        return response()->json([
            'data' => $this->registry->for($country)->navigationSteps(),
            'meta' => [
                'country' => $country,
                'label'   => CountryCode::from($country)->label(),
            ],
        ]);
    }
}
