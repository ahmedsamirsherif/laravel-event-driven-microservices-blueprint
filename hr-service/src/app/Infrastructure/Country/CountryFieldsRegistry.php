<?php

declare(strict_types=1);

namespace App\Infrastructure\Country;

use App\Domain\Country\Contracts\CountryFieldsInterface;
use InvalidArgumentException;

/**
 * Auto-discovering country fields registry.
 *
 * Uses CountryClassResolver::discoverAll() to scan Domain/Country/
 * for classes matching the {Country}Fields naming convention.
 *
 * No manual registration. Adding a country = creating a class in
 * Domain/Country/{Country}/{Country}Fields.php implementing CountryFieldsInterface.
 */
final class CountryFieldsRegistry
{
    /** @var array<string, CountryFieldsInterface> */
    private array $modules;

    private function __construct(array $modules)
    {
        $this->modules = $modules;
    }

    /**
     * Auto-discover all country field modules from the Domain/Country directory.
     *
     * Scans for {Country}Fields classes implementing CountryFieldsInterface.
     * Country is derived from directory/class name — zero manual registration.
     */
    public static function discover(): self
    {
        /** @var array<string, CountryFieldsInterface> $modules */
        $modules = CountryClassResolver::discoverAll('Fields', CountryFieldsInterface::class);

        return new self($modules);
    }

    /**
     * Get the fields module for a specific country.
     *
     * @throws InvalidArgumentException When country is not supported
     */
    public function for(string $country): CountryFieldsInterface
    {
        return $this->modules[$country]
            ?? throw new InvalidArgumentException("Unsupported country: {$country}");
    }

    /**
     * Check if a country is supported.
     */
    public function supports(string $country): bool
    {
        return isset($this->modules[$country]);
    }

    /**
     * Get all supported country codes.
     *
     * @return string[]
     */
    public function supportedCountries(): array
    {
        return array_keys($this->modules);
    }
}
