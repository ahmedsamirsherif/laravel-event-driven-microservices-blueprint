<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Enums\CountryCode;
use App\Infrastructure\Country\CountryFieldsRegistry;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/steps/{country}
 * Returns UI form step definitions (country-specific fields) for the HR employee form.
 * Mirrors Hub service's StepsController pattern.
 */
final class StepsController extends Controller
{
    public function __construct(private readonly CountryFieldsRegistry $registry) {}

    public function show(string $country): JsonResponse
    {
        $country = strtoupper($country);

        abort_if(! $this->registry->supports($country), 404, "No steps configuration for country: {$country}");

        return response()->json([
            'data' => $this->registry->for($country)->steps(),
            'meta' => [
                'country' => $country,
                'label'   => CountryCode::from($country)->label(),
            ],
        ]);
    }
}
