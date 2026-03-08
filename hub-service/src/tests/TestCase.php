<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force test-safe drivers — phpunit.xml force="true" cannot
        // override Docker OS-level env vars that Laravel reads via getenv().
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);

        // Reset the country module class resolution cache between tests
        // so that test isolation is maintained.
        \App\Infrastructure\Country\CountryClassResolver::clearCache();
    }
}
