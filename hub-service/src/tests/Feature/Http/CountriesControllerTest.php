<?php

declare(strict_types=1);

it('returns all supported countries from registry', function () {
    $response = $this->getJson('/api/v1/countries');
    $response->assertOk()
        ->assertJsonStructure(['data' => [['code', 'label']]]);

    $countries = $response->json('data');
    $codes = array_column($countries, 'code');
    expect($codes)->toContain('USA')->toContain('DEU');
});

it('returns country labels that match enum definitions', function () {
    $response = $this->getJson('/api/v1/countries');
    $response->assertOk();

    $countries = collect($response->json('data'));
    $usa = $countries->firstWhere('code', 'USA');
    $deu = $countries->firstWhere('code', 'DEU');

    expect($usa['label'])->toBe('United States');
    expect($deu['label'])->toBe('Germany');
});
