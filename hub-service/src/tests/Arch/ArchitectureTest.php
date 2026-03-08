<?php

arch('domain does not depend on infrastructure')
    ->expect('App\Domain')
    ->not->toUse('App\Infrastructure');

arch('domain does not use http layer')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Http');

arch('event handlers are final')
    ->expect('App\Application\EventProcessing\Handlers')
    ->toBeFinal()
    ->ignoring('App\Application\EventProcessing\Handlers\EventHandlerInterface')
    ->ignoring('App\Application\EventProcessing\Handlers\InvalidatesCache');

arch('pipeline is final')
    ->expect('App\Application\EventProcessing\Pipeline\EventProcessingPipeline')
    ->toBeFinal();

arch('received event DTO is readonly')
    ->expect('App\Domain\EventProcessing\DTOs\ReceivedEventDTO')
    ->toBeReadonly();

arch('country modules are final')
    ->expect('App\Domain\Country\USA')
    ->toBeFinal();

arch('country modules implement CountryModuleInterface')
    ->expect('App\Domain\Country\USA\USAModule')
    ->toImplement(\App\Domain\Country\Contracts\CountryModuleInterface::class);

arch('country shared classes are final')
    ->expect('App\Domain\Country\Shared')
    ->toBeFinal();

arch('broadcast events implement ShouldBroadcast')
    ->expect('App\Infrastructure\Broadcasting\Events')
    ->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);

arch('no debug functions used anywhere')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('country module interface is an interface')
    ->expect('App\Domain\Country\Contracts')
    ->toBeInterfaces();
