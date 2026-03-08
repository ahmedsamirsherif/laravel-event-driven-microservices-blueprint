<?php

arch('domain does not depend on infrastructure')
    ->expect('App\Domain')
    ->not->toUse('App\Infrastructure');

arch('domain does not use http layer')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Http');

arch('dtos are readonly')
    ->expect('App\Domain\Employee\DTOs')
    ->toBeReadonly();

arch('value objects are readonly')
    ->expect('App\Domain\Employee\ValueObjects')
    ->toBeReadonly();

arch('actions are final')
    ->expect('App\Application\Employee\Actions')
    ->toBeFinal();

arch('repository interfaces in domain are interfaces')
    ->expect('App\Domain\Employee\Repositories')
    ->toBeInterfaces();

arch('domain events do not implement ShouldBroadcast')
    ->expect('App\Domain\Employee\Events')
    ->not->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);

arch('country contracts are interfaces')
    ->expect('App\Domain\Country\Contracts')
    ->toBeInterfaces();

arch('country field classes are final')
    ->expect('App\Domain\Country\USA')
    ->toBeFinal();

arch('country field classes implement CountryFieldsInterface')
    ->expect('App\Domain\Country\USA\USAFields')
    ->toImplement(\App\Domain\Country\Contracts\CountryFieldsInterface::class);

arch('no debug functions used anywhere')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
