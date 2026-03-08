<?php

declare(strict_types=1);

use App\Application\EventProcessing\Handlers\InvalidatesCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->invalidator = new class {
        use InvalidatesCache;
        public function run(int $employeeId, string $country): void { $this->invalidateCache($employeeId, $country); }
    };
});

it('bumps version stamp and forgets checklist keys', function () {
    cache()->put('checklist:USA:42', ['some' => 'data'], 3600);
    cache()->put('checklist_summary:USA', ['total' => 5], 3600);

    $this->invalidator->run(42, 'USA');

    expect((int) cache()->get('employees:USA:v'))->toBe(1);
    expect(cache()->has('checklist:USA:42'))->toBeFalse();
    expect(cache()->has('checklist_summary:USA'))->toBeFalse();

    $this->invalidator->run(42, 'USA');
    expect((int) cache()->get('employees:USA:v'))->toBe(2);
});

it('scopes invalidation to correct country', function () {
    cache()->put('employees:USA:v', 3, 86400);
    cache()->put('employees:DEU:v', 7, 86400);
    cache()->put('checklist:USA:1', ['x'], 3600);
    cache()->put('checklist:DEU:1', ['y'], 3600);
    cache()->put('checklist_summary:USA', ['a'], 3600);
    cache()->put('checklist_summary:DEU', ['b'], 3600);

    $this->invalidator->run(1, 'USA');

    expect((int) cache()->get('employees:USA:v'))->toBe(4);
    expect((int) cache()->get('employees:DEU:v'))->toBe(7);
    expect(cache()->has('checklist:USA:1'))->toBeFalse();
    expect(cache()->has('checklist:DEU:1'))->toBeTrue();
    expect(cache()->has('checklist_summary:USA'))->toBeFalse();
    expect(cache()->has('checklist_summary:DEU'))->toBeTrue();
});
