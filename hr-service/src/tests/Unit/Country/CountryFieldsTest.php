<?php

declare(strict_types=1);

use App\Domain\Country\Contracts\CountryFieldsInterface;
use App\Domain\Country\DEU\DEUFields;
use App\Domain\Country\USA\USAFields;
use App\Domain\Shared\Enums\CountryCode;
use App\Infrastructure\Country\CountryClassResolver;
use App\Infrastructure\Country\CountryFieldsRegistry;

beforeEach(fn () => CountryClassResolver::clearCache());

it('USAFields validates SSN rules and masks in resource output', function () {
    $fields = new USAFields();
    expect($fields)->toBeInstanceOf(CountryFieldsInterface::class)
        ->and($fields->country())->toBe(CountryCode::USA);

    $rules = $fields->storeRules();
    expect($rules)->toHaveKeys(['ssn', 'address']);
    expect(collect($rules['ssn'])->contains(fn ($r) => str_contains((string) $r, 'regex')))->toBeTrue();

    $result = $fields->resourceFields((object) ['ssn' => '123-45-6789', 'address' => '123 Main St']);
    expect($result['ssn'])->toBe('***-**-6789')
        ->and($result['address'])->toBe('123 Main St');
    expect($fields->resourceFields((object) ['ssn' => null, 'address' => 'St'])['ssn'])->toBeNull();
});

it('DEUFields validates tax_id, goal, and doc URL rules', function () {
    $fields = new DEUFields();
    expect($fields->country())->toBe(CountryCode::DEU);

    $rules = $fields->storeRules();
    expect($rules)->toHaveKeys(['tax_id', 'goal', 'doc_work_permit', 'doc_tax_card']);
    expect($rules['tax_id'])->toContain('required');
    foreach (['doc_work_permit', 'doc_tax_card', 'doc_health_insurance'] as $key) {
        expect($rules[$key])->toContain('nullable')->and($rules[$key])->toContain('url');
    }
});

it('resolver discovers by convention and registry provides lookup', function () {
    expect(CountryClassResolver::resolve('USA', 'Fields', CountryFieldsInterface::class))->toBeInstanceOf(USAFields::class);
    expect(CountryClassResolver::tryResolve('France', 'Fields', CountryFieldsInterface::class))->toBeNull();

    $registry = CountryFieldsRegistry::discover();
    expect($registry->supports('USA'))->toBeTrue()
        ->and($registry->supports('DEU'))->toBeTrue()
        ->and($registry->supports('France'))->toBeFalse()
        ->and($registry->for('USA'))->toBeInstanceOf(USAFields::class);
    expect(fn () => $registry->for('France'))->toThrow(InvalidArgumentException::class);
});
