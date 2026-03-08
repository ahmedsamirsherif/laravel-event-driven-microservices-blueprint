<?php

declare(strict_types=1);

namespace App\Infrastructure\Country;

use App\Domain\Shared\Enums\CountryCode;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

/**
 * Convention-based dynamic country class resolver.
 *
 * Naming convention: App\Domain\Country\{Country}\{Country}{Suffix}
 * Country is derived from the directory/class name by stripping the suffix.
 * All resolved classes must implement the specified interface.
 *
 * Uses PHP ReflectionClass for:
 *   - implementsInterface()  → enforces contract at discovery time
 *   - isInstantiable()       → rejects abstract classes/interfaces
 *   - newInstance()           → creates instance without manual `new`
 *
 * Static cache prevents repeated reflection — combined with Laravel's
 * singleton binding, reflection runs exactly once per application lifecycle.
 */
final class CountryClassResolver
{
    /** @var array<string, object> Instance cache to avoid repeated reflection */
    private static array $cache = [];

    /**
     * Resolve a country-specific class by convention.
     *
     * @template T of object
     * @param string          $country    Country code (e.g., "USA", "DEU")
     * @param string          $suffix     Class suffix (e.g., "Module", "FieldRules")
     * @param class-string<T> $interface  Required interface the class must implement
     * @return T
     *
     * @throws InvalidArgumentException When no class found for country
     * @throws RuntimeException         When class doesn't implement interface or isn't instantiable
     */
    public static function resolve(string $country, string $suffix, string $interface): object
    {
        $cacheKey = "{$country}:{$suffix}";

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $className = self::buildClassName($country, $suffix);

        if (! class_exists($className)) {
            throw new InvalidArgumentException(
                "No {$suffix} class found for country: {$country} (expected {$className})"
            );
        }

        $reflection = new ReflectionClass($className);

        if (! $reflection->implementsInterface($interface)) {
            throw new RuntimeException(
                "{$className} must implement {$interface}"
            );
        }

        if (! $reflection->isInstantiable()) {
            throw new RuntimeException("{$className} is not instantiable (abstract or interface?)");
        }

        $instance = $reflection->newInstance();
        self::$cache[$cacheKey] = $instance;

        return $instance;
    }

    /**
     * Try to resolve — returns null instead of throwing.
     *
     * @template T of object
     * @param class-string<T> $interface
     * @return T|null
     */
    public static function tryResolve(string $country, string $suffix, string $interface): ?object
    {
        try {
            return self::resolve($country, $suffix, $interface);
        } catch (InvalidArgumentException|RuntimeException) {
            return null;
        }
    }

    /**
     * Auto-discover all implementations matching a suffix + interface convention.
     *
     * Scans Domain/Country/{CountryName}/ directories. For each subdirectory:
     *   1. Builds expected class name: App\Domain\Country\{DirName}\{DirName}{Suffix}
     *   2. Checks class_exists() (Composer autoloader handles actual loading)
     *   3. Uses ReflectionClass to verify interface + instantiability
     *   4. Derives country from directory name, validates against CountryCode enum
     *
     * Skips 'Contracts' and 'Shared' directories automatically.
     *
     * @template T of object
     * @param string          $suffix     Class suffix to look for
     * @param class-string<T> $interface  Required interface
     * @return array<string, T>           Keyed by country code value
     */
    public static function discoverAll(string $suffix, string $interface): array
    {
        $basePath = app_path('Domain/Country');
        $results  = [];

        foreach (glob($basePath . '/*/') ?: [] as $dir) {
            $dirName = basename($dir);

            // Skip non-country directories
            if (in_array($dirName, ['Contracts', 'Shared'], true)) {
                continue;
            }

            // Validate directory name matches a CountryCode enum case
            $countryCode = CountryCode::tryFrom($dirName);
            if ($countryCode === null) {
                continue; // Unknown directory — not a country module
            }

            $instance = self::tryResolve($dirName, $suffix, $interface);
            if ($instance !== null) {
                $results[$countryCode->value] = $instance;
            }
        }

        return $results;
    }

    /**
     * Build the fully-qualified class name from convention.
     *
     * Convention: App\Domain\Country\{Country}\{Country}{Suffix}
     * Examples:
     *   buildClassName('USA', 'Module')      → App\Domain\Country\USA\USAModule
     *   buildClassName('DEU', 'FieldRules') → App\Domain\Country\DEU\DEUFieldRules
     */
    private static function buildClassName(string $country, string $suffix): string
    {
        return "App\\Domain\\Country\\{$country}\\{$country}{$suffix}";
    }

    /**
     * Clear the instance cache — essential for test isolation.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
