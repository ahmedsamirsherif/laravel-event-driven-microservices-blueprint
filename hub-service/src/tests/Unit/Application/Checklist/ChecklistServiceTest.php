<?php

declare(strict_types=1);

use App\Application\Checklist\ChecklistService;
use App\Domain\Employee\Models\EmployeeProjection;
use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use App\Infrastructure\Country\CountryRegistry;
use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

function makeService(?EmployeeProjectionRepositoryInterface $repo = null): ChecklistService
{
    $repo ??= Mockery::mock(EmployeeProjectionRepositoryInterface::class);
    return new ChecklistService(app(CountryRegistry::class), $repo, app(PrometheusMetricsService::class));
}

function fullUsaData(array $overrides = []): array
{
    return array_merge([
        'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
        'salary' => 75000.0, 'country' => 'USA',
        'ssn' => '123-45-6789', 'address' => '123 Main St, New York',
    ], $overrides);
}

function fullDeuData(array $overrides = []): array
{
    return array_merge([
        'id' => 2, 'name' => 'Hans', 'last_name' => 'Mueller',
        'salary' => 65000.0, 'country' => 'DEU',
        'tax_id' => 'DE123456789', 'goal' => 'Improve team productivity',
    ], $overrides);
}

// ━━━ USA assembleChecklist ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('returns 100% for fully complete USA employee with all items complete and null messages', function () {
    $result = makeService()->assembleChecklist(1, 'USA', fullUsaData());

    expect($result['completion_percentage'])->toBe(100)
        ->and($result['employee_id'])->toBe(1)
        ->and($result['country'])->toBe('USA');

    foreach ($result['items'] as $item) {
        expect($item['completed'])->toBeTrue()
            ->and($item['message'])->toBeNull();
    }
});

it('marks salary edge cases correctly', function (mixed $salary, bool $complete) {
    $result = makeService()->assembleChecklist(1, 'USA', fullUsaData(['salary' => $salary]));
    $salaryItem = collect($result['items'])->firstWhere('field', 'salary');

    expect($salaryItem['completed'])->toBe($complete);
    if (!$complete) {
        expect($salaryItem['message'])->toBeString()->not->toBeEmpty();
        expect($result['completion_percentage'])->toBeLessThan(100);
    }
})->with([
    'zero' => [0, false],
    'null' => [null, false],
    'positive' => [0.01, true],
]);

it('marks SSN edge cases correctly', function (mixed $ssn, bool $complete) {
    $result = makeService()->assembleChecklist(1, 'USA', fullUsaData(['ssn' => $ssn]));
    $item = collect($result['items'])->firstWhere('field', 'ssn');

    expect($item['completed'])->toBe($complete);
    if (!$complete) {
        expect($item['message'])->toBeString()->not->toBeEmpty();
    }
})->with([
    'null' => [null, false],
    'invalid format' => ['not-a-ssn', false],
    'no dashes' => ['123456789', false],
    'valid' => ['555-44-3333', true],
]);

it('marks address and empty strings as incomplete', function (string $field, mixed $value) {
    $data = $field === 'address' ? fullUsaData([$field => $value]) : fullDeuData([$field => $value]);
    $country = $field === 'address' ? 'USA' : 'DEU';
    $id = $field === 'address' ? 1 : 2;

    $result = makeService()->assembleChecklist($id, $country, $data);
    $item = collect($result['items'])->firstWhere('field', $field);
    expect($item['completed'])->toBeFalse();
})->with([
    'null address' => ['address', null],
    'empty address' => ['address', ''],
    'null goal' => ['goal', null],
    'empty goal' => ['goal', ''],
]);

// ━━━ DEU assembleChecklist ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('returns 100% for fully complete DEU employee', function () {
    $result = makeService()->assembleChecklist(2, 'DEU', fullDeuData());

    expect($result['completion_percentage'])->toBe(100);
    $incomplete = array_filter($result['items'], fn ($i) => !$i['completed']);
    expect($incomplete)->toBeEmpty();
});

it('marks DEU tax_id edge cases correctly', function (mixed $taxId, bool $complete) {
    $result = makeService()->assembleChecklist(2, 'DEU', fullDeuData(['tax_id' => $taxId]));
    $item = collect($result['items'])->firstWhere('field', 'tax_id');

    expect($item['completed'])->toBe($complete);
})->with([
    'null' => [null, false],
    'invalid' => ['INVALID', false],
    'valid' => ['DE999888777', true],
]);

// ━━━ assembleChecklist: edge cases & formatting ━━━━━━━━━━━━━━━━━━━━━━━━━━

it('returns 0% for all-null employee and completion_percentage is always int', function () {
    $result = makeService()->assembleChecklist(1, 'USA', [
        'id' => 1, 'name' => null, 'last_name' => null,
        'salary' => null, 'country' => null, 'ssn' => null, 'address' => null,
    ]);
    expect($result['completion_percentage'])->toBe(0)->and($result['completion_percentage'])->toBeInt();
});

it('field labels use title case with underscores replaced by spaces', function () {
    $deu = makeService()->assembleChecklist(2, 'DEU', fullDeuData());
    $taxItem = collect($deu['items'])->firstWhere('field', 'tax_id');
    expect($taxItem['label'])->toBe('Tax Id');

    $usa = makeService()->assembleChecklist(1, 'USA', fullUsaData());
    $salaryItem = collect($usa['items'])->firstWhere('field', 'salary');
    expect($salaryItem['label'])->toBe('Salary');
});

// ━━━ Step grouping ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('checklist steps have correct structure and completion percentages', function () {
    $complete = makeService()->assembleChecklist(1, 'USA', fullUsaData());
    expect($complete['steps'])->toHaveCount(2);

    foreach ($complete['steps'] as $step) {
        expect($step)->toHaveKeys(['id', 'label', 'fields', 'step_completion_percentage']);
        expect($step['step_completion_percentage'])->toBe(100);
    }

    $empty = makeService()->assembleChecklist(1, 'USA', [
        'id' => 1, 'name' => null, 'last_name' => null,
        'salary' => null, 'country' => null, 'ssn' => null, 'address' => null,
    ]);
    foreach ($empty['steps'] as $step) {
        expect($step['step_completion_percentage'])->toBe(0);
    }
});

it('partial step completion calculates correctly', function () {
    $usa = makeService()->assembleChecklist(1, 'USA', fullUsaData(['address' => null]));
    $identityStep = collect($usa['steps'])->firstWhere('label', 'Identity & Address');
    expect($identityStep['step_completion_percentage'])->toBe(50);

    $deu = makeService()->assembleChecklist(2, 'DEU', fullDeuData(['goal' => null]));
    $taxGoalsStep = collect($deu['steps'])->firstWhere('label', 'Tax & Goals');
    expect($taxGoalsStep['step_completion_percentage'])->toBe(50);
});

// ━━━ getChecklist caching ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('getChecklist caches result and returns cached on second call', function () {
    $proj = new EmployeeProjection(fullUsaData());
    $proj->exists = true;

    $repo = Mockery::mock(EmployeeProjectionRepositoryInterface::class);
    $repo->shouldReceive('findByEmployeeId')->once()->with(1)->andReturn($proj);

    $service = makeService($repo);
    $first = $service->getChecklist(1, 'USA');
    $second = $service->getChecklist(1, 'USA');

    expect(Cache::has('checklist:USA:1'))->toBeTrue()
        ->and($first)->toEqual($second);
});

it('getChecklist returns empty checklist for non-existent employee', function () {
    $repo = Mockery::mock(EmployeeProjectionRepositoryInterface::class);
    $repo->shouldReceive('findByEmployeeId')->once()->with(9999)->andReturn(null);

    $result = makeService($repo)->getChecklist(9999, 'USA');
    expect($result['completion_percentage'])->toBe(0)->and($result['items'])->toBeEmpty();
});

// ━━━ getSummary ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('getSummary returns correct stats and caches result', function () {
    $complete   = new EmployeeProjection(fullUsaData(['employee_id' => 1]));
    $incomplete = new EmployeeProjection(fullUsaData(['employee_id' => 2, 'ssn' => null]));

    $repo = Mockery::mock(EmployeeProjectionRepositoryInterface::class);
    $repo->shouldReceive('allByCountry')->once()->with('USA')
        ->andReturn(new EloquentCollection([$complete, $incomplete]));

    $service = makeService($repo);
    $summary = $service->getSummary('USA');

    expect($summary['total_employees'])->toBe(2)
        ->and($summary['complete_employees'])->toBe(1)
        ->and($summary['overall_percentage'])->toBe(50.0);

    $service->getSummary('USA'); // second call hits cache
    expect(Cache::has('checklist_summary:USA'))->toBeTrue();
});

it('getSummary returns zeros for empty country', function () {
    $repo = Mockery::mock(EmployeeProjectionRepositoryInterface::class);
    $repo->shouldReceive('allByCountry')->once()->with('USA')->andReturn(new EloquentCollection([]));

    $summary = makeService($repo)->getSummary('USA');
    expect($summary['total_employees'])->toBe(0)
        ->and($summary['complete_employees'])->toBe(0)
        ->and($summary['overall_percentage'])->toBe(0);
});

// ━━━ getPaginatedChecklists ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('getPaginatedChecklists returns correct pagination and checklist data', function () {
    $employees = array_map(fn ($i) => new EmployeeProjection(fullUsaData(['employee_id' => $i])), range(1, 5));
    $paginator = new LengthAwarePaginator(array_slice($employees, 0, 3), 5, 3, 1);

    $repo = Mockery::mock(EmployeeProjectionRepositoryInterface::class);
    $repo->shouldReceive('paginateByCountry')->once()->with('USA', 1, 3)->andReturn($paginator);

    $result = makeService($repo)->getPaginatedChecklists('USA', 1, 3);

    expect($result['pagination']['total'])->toBe(5)
        ->and($result['pagination']['per_page'])->toBe(3)
        ->and($result['pagination']['last_page'])->toBe(2)
        ->and($result['checklists'])->toHaveCount(3)
        ->and($result['checklists'][0])->toHaveKey('completion_percentage');
});

it('getPaginatedChecklists uses cached checklist when available', function () {
    $emp = new EmployeeProjection(fullUsaData(['employee_id' => 1]));
    $cachedData = ['employee_id' => 1, 'country' => 'USA', 'completion_percentage' => 100, 'items' => [], 'steps' => []];
    Cache::put('checklist:USA:1', $cachedData, 3600);

    $paginator = new LengthAwarePaginator([$emp], 1, 15, 1);
    $repo = Mockery::mock(EmployeeProjectionRepositoryInterface::class);
    $repo->shouldReceive('paginateByCountry')->once()->andReturn($paginator);

    $result = makeService($repo)->getPaginatedChecklists('USA', 1, 15);
    expect($result['checklists'][0])->toEqual($cachedData);
});
