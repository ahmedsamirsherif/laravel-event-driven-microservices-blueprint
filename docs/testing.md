# Testing

## Summary

| | |
|---|---|
| Total Tests | 175 |
| HR Service | 47 |
| Hub Service | 128 |
| Test Suites | 5 |

All tests use **Pest PHP 3.8.5** with `pestphp/pest-plugin-arch` for architecture enforcement. Tests run against real PostgreSQL databases (not SQLite) for production parity.

## Run Commands

| Command | Scope |
|---|---|
| `make test` | All tests (HR + Hub) |
| `make test-hr` | HR Service (47 tests) |
| `make test-hub` | Hub Service (128 tests) |
| `make test-unit` | Unit tests only |
| `make test-feature` | Feature (HTTP) tests only |
| `make test-integration` | Integration tests only |
| `make test-arch` | Architecture enforcement tests |
| `make test-contract` | Event schema contract tests |

## How `it()` Works

Every test is defined with `it()`, Pest's wrapper around PHPUnit's `TestCase`. When you write:

```php
it('creates a USA employee', function () {
    $this->postJson('/api/v1/employees', [...])
        ->assertCreated();
});
```

Behind the scenes:

1. Pest creates a **PHPUnit TestCase** class and binds the closure as a method
2. `$this` inside the closure **is** the Laravel `TestCase` instance — giving access to `postJson()`, `assertDatabaseHas()`, etc.
3. The `RefreshDatabase` trait (applied via `uses()`) wraps each test in a **database transaction** that rolls back after the test
4. `Event::fake()` in `beforeEach()` intercepts domain events so RabbitMQ publish listeners never fire during tests

Datasets multiply test cases:

```php
it('rejects invalid inputs', function (array $payload) {
    $this->postJson('/api/v1/employees', $payload)
        ->assertUnprocessable();
})->with([
    'invalid SSN' => [['name' => 'J', ...]],
    'missing tax_id' => [['name' => 'H', ...]],
]);
// Produces 2 separate test runs from 1 it() call
```

## How `arch()` Works

Architecture tests enforce structural rules using **static analysis** (reflection + AST inspection). No application code is executed:

```php
arch('domain does not depend on infrastructure')
    ->expect('App\Domain')
    ->not->toUse('App\Infrastructure');
```

| Assertion | What It Checks |
|---|---|
| `->not->toUse('Namespace')` | Scans `use` statements and string references — no imports from forbidden namespace |
| `->toBeReadonly()` | Confirms all classes in namespace are `readonly class` |
| `->toBeFinal()` | Confirms all classes are `final class` |
| `->toBeInterfaces()` | Confirms all types are `interface` |
| `->toImplement(Interface::class)` | Confirms classes implement the given interface |
| `->not->toBeUsed()` | For function names: scans entire codebase for calls to forbidden functions |

The `not->toBeUsed()` check for `dd`, `dump`, `ray`, `var_dump` catches debug calls left in production code by scanning every PHP file's AST.

## Test Suites

### Unit Tests
Isolated domain logic with no external dependencies.
- **Value Objects**: SSN, TaxId, Salary — validation, formatting, edge cases
- **Actions**: Create/Update/Delete employee — repository mock + event dispatch verification
- **Handlers**: Event processing handlers — projection upsert/delete + cache invalidation
- **Pipeline**: `EventProcessingPipeline` — idempotency, logging, handler dispatch
- **Commands**: `events:stats`, `events:replay`, `events:retry-failed`
- **Country modules**: USAModule, DEUModule — steps, columns, widgets, schema fields

### Feature Tests
Full HTTP request/response cycles with real PostgreSQL.
- Employee CRUD with country-specific validation
- Pagination, filtering, error envelope format
- Health check and Prometheus metrics endpoints
- Checklist, Steps, Schema endpoints (Hub)
- Fortification: rate limiting, forced JSON, request ID, CORS

### Integration Tests
Cross-boundary interactions:
- Event publishing — changed fields detection, deleted snapshot
- Cache behaviour — invalidation, eager rebuild
- Event pipeline — end-to-end processing with projections

### Architecture Tests
Structural rules enforced by Pest Arch plugin (11 HR + 11 Hub):
- Domain layer has no dependency on Infrastructure or HTTP
- DTOs and Value Objects are `readonly`
- Actions and Pipelines are `final`
- Repository interfaces are `interface`
- No `dd`/`dump`/`var_dump` calls in codebase

### Contract Tests
Validate event payloads against the shared JSON Schema 2020-12 contract at [`contracts/employee-event.schema.json`](../contracts/employee-event.schema.json).

## Test Database Setup

Each PostgreSQL container auto-creates a test database via `init-test-db.sh`:

| Service | Database | User |
|---|---|---|
| HR Service | `hr_service_test` | `hr_user` |
| Hub Service | `hub_service_test` | `hub_user` |

`TestCase.php` forces `array` cache driver, `sync` queue driver, and `array` session driver to prevent cross-test contamination.
