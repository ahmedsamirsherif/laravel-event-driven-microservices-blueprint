<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Enums\CountryCode;
use App\Infrastructure\Country\CountryFieldsRegistry;
use Illuminate\Http\JsonResponse;

final class CountriesController extends Controller
{
    public function __construct(private readonly CountryFieldsRegistry $registry) {}

    public function index(): JsonResponse
    {
        $countries = array_map(
            fn (string $code) => [
                'code'  => $code,
                'label' => CountryCode::from($code)->label(),
            ],
            $this->registry->supportedCountries(),
        );

        return response()->json(['data' => array_values($countries)]);
    }
}
