<?php

declare(strict_types=1);

namespace App\Infrastructure\Country;

use App\Domain\Country\Contracts\CountryModuleInterface;
use InvalidArgumentException;

/**
 * Auto-discovering country registry.
 *
 * Uses CountryClassResolver::discoverAll() to scan Domain/Country/
 * for classes matching the {Country}Module naming convention.
 *
 * No manual registration. Adding a country = creating a class in
 * Domain/Country/{Country}/{Country}Module.php implementing CountryModuleInterface.
 */
final class CountryRegistry
{
    /** @var array<string, CountryModuleInterface> */
    private array $modules;

    private function __construct(array $modules)
    {
        $this->modules = $modules;
    }

    /**
     * Auto-discover all country modules from the Domain/Country directory.
     *
     * Scans for {Country}Module classes implementing CountryModuleInterface.
     * Country is derived from directory/class name — zero manual registration.
     */
    public static function discover(): self
    {
        /** @var array<string, CountryModuleInterface> $modules */
        $modules = CountryClassResolver::discoverAll('Module', CountryModuleInterface::class);

        return new self($modules);
    }

    /**
     * Get the module for a specific country.
     *
     * @throws InvalidArgumentException When country is not supported
     */
    public function for(string $country): CountryModuleInterface
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
