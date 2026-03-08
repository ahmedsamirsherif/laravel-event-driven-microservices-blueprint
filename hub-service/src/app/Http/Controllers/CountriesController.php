<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Enums\CountryCode;
use App\Infrastructure\Country\CountryRegistry;
use Illuminate\Http\JsonResponse;

final class CountriesController extends Controller
{
    public function __construct(
        private readonly CountryRegistry $registry,
    ) {}

    /**
     * GET /api/v1/countries
     * Returns all supported countries discovered from the CountryRegistry.
     */
    public function index(): JsonResponse
    {
        $countries = collect($this->registry->supportedCountries())
            ->map(fn (string $code) => [
                'code'  => $code,
                'label' => CountryCode::from($code)->label(),
            ]);

        return response()->json([
            'data' => $countries->values(),
        ]);
    }
}
