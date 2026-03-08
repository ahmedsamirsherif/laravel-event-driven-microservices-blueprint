# Architecture Decision Records

All architectural decisions are recorded here. Each ADR captures the context, decision, and consequences for a significant design choice.

**Status:** All Accepted — Date: 2024-02-09

---

## Index

| # | Title | Summary |
|---|---|---|
| [ADR-001](#adr-001-microservices-separation) | Microservices Separation | Two services, separate DBs, CQRS split |
| [ADR-002](#adr-002-event-driven-rabbitmq) | Event-Driven RabbitMQ | Topic exchange, routing keys, DLX/DLQ |
| [ADR-003](#adr-003-ddd-layered-architecture) | DDD Layered Architecture | 4 layers: Domain / Application / Infrastructure / HTTP |
| [ADR-004](#adr-004-value-objects) | Value Objects | Immutable, self-validating domain primitives |
| [ADR-005](#adr-005-redis-caching) | Redis Caching | AOF persistence, eager rebuild, event-driven invalidation |
| [ADR-006](#adr-006-country-strategy-pattern) | Country Strategy Pattern | Auto-discovering registry, Open/Closed extensibility |
| [ADR-007](#adr-007-laravel-reverb-broadcasting) | Laravel Reverb Broadcasting | First-party WebSocket, country-specific channels |
| [ADR-008](#adr-008-pcntl-graceful-shutdown) | PCNTL Graceful Shutdown | SIGTERM handling, zero message loss on deploy |
| [ADR-009](#adr-009-dead-letter-queue) | Dead Letter Queue | DLX + DLQ, 3-attempt exponential backoff retry |
| [ADR-010](#adr-010-prometheus-metrics) | Prometheus Metrics | InMemory storage, `/api/metrics`, Grafana pre-provisioned |
| [ADR-011](#adr-011-event-schema-contract) | Event Schema Contract | JSON Schema 2020-12, contract tests in both services |
| [ADR-012](#adr-012-pest-testing) | Pest Testing | Expressive syntax, arch enforcement, grouped runs |
| [ADR-013](#adr-013-docker-multi-stage) | Docker Multi-Stage | dev + prod targets, minimal production images |

---

## ADR-001: Microservices Separation

**Context:** The system requires managing employees across multiple countries with distinct validation rules. A monolith would couple concerns and make independent scaling impossible.

**Decision:** Split into two services:
- **HR Service** — write-side: CRUD operations, emits domain events
- **Hub Service** — read-side: projections, checklist config, server-driven UI, broadcasting

**Consequences:**
- Independent deployability and scalability
- Separation of write/read responsibilities (CQRS)
- Each service owns its own database — no cross-DB queries possible
- Added operational complexity
- Eventual consistency between services

---

## ADR-002: Event-Driven RabbitMQ

**Context:** HR Service needs to notify Hub Service of employee changes without tight coupling.

**Decision:** Use RabbitMQ with a topic exchange (`employee_events`) and routing keys `employee.{action}.{country}`. This enables country-specific routing, multiple consumers, and message durability.

```
employee.created.usa
employee.updated.deu
employee.deleted.usa
employee.#              ← Hub binds to all
```

**Consequences:**
- Loose coupling between services
- Country-based routing with wildcard binding
- DLX/DLQ for failed message handling (see ADR-009)
- Broker becomes a critical dependency

---

## ADR-003: DDD Layered Architecture

**Context:** Business logic (country-specific rules, employee lifecycle) must be explicit and testable, isolated from the framework.

**Decision:** Four-layer DDD:

| Layer | Contents |
|---|---|
| **Domain** | Entities, Value Objects, Domain Events, Repository Interfaces, Country Contracts |
| **Application** | Actions (use cases), Event Handlers, Listeners, Pipeline |
| **Infrastructure** | Eloquent Repositories, RabbitMQ, Redis, Broadcasting, Metrics |
| **HTTP** | Controllers, Form Requests, API Resources, Middleware |

Rule: `HTTP → Application → Domain ← Infrastructure`; Domain has **zero** dependency on Infrastructure or HTTP.

**Consequences:**
- Domain logic isolated from framework concerns
- Arch tests enforce layer boundaries automatically
- Easy to add new countries via Strategy pattern
- More files and indirection than a plain CRUD approach

---

## ADR-004: Value Objects for Domain Invariants

**Context:** Country-specific fields (SSN, TaxId) have specific formats that must always be valid — invalid state should be unrepresentable.

**Decision:** `readonly` PHP 8.x Value Objects that validate in their constructors:

| Value Object | Validation | Extra |
|---|---|---|
| `SSN` | regex `^\d{3}-\d{2}-\d{4}$` | `masked()` returns `***-**-XXXX` |
| `TaxId` | regex `^DE\d{9}$` | German format |
| `Salary` | `amount >= 0` | |
| `Country` | backed by `CountryCode` enum | |

**Consequences:**
- Self-validating domain model — construction fails on invalid input
- Immutability via `readonly`
- Masking logic encapsulated in `SSN::masked()`
- Must be hydrated from primitives at domain boundaries

---

## ADR-005: Redis Caching with AOF Persistence

**Context:** Hub Service read endpoints must be fast without hitting PostgreSQL on every request.

**Decision:**
- Employee list cache — key: `employees:{country}:page:{n}:per:{m}`, TTL 1 hour
- Checklist cache with **eager rebuild** on event consumption — key: `checklist:{country}:{id}`, TTL 1 hour
- AOF persistence (`appendfsync everysec`) so cache survives Redis restarts

**Consequences:**
- Sub-millisecond read latency for all paginated endpoints
- Checklists always fresh after event processing (eager rebuild means no cold misses)
- Persistent cache survives container restarts
- Cache invalidation complexity on update/delete events

---

## ADR-006: Country Strategy Pattern

**Context:** Each country has different required fields, form steps, widget columns, and schemas. Adding new countries must not require modifying existing code.

**Decision:** Convention-based `CountryClassResolver` that scans `Domain/Country/*/` directories and builds the FQCN `App\Domain\Country\{ISO3}\{ISO3}{Suffix}`, validated via `ReflectionClass`. No manual registration needed.

- **HR Service**: `CountryFieldsInterface` → `USAFields`, `DEUFields`
- **Hub Service**: `CountryModuleInterface` → `USAModule`, `DEUModule`

**Consequences:**
- Open/Closed Principle: add a country by adding one directory with one file
- Steps, columns, schema, and widgets all driven by the same module
- Testable in isolation
- Convention must be documented — misconfigured class names are silently skipped

---

## ADR-007: Laravel Reverb Broadcasting

**Context:** Frontend clients need real-time updates when employees change without an external paid service.

**Decision:** Use Laravel Reverb (first-party WebSocket server, Laravel 11+) instead of Pusher or Soketi. Events broadcast on two channels:
- `employees` — global
- `country.{country}` — country-specific

**Consequences:**
- No external service dependency or account required
- Same `pusher` broadcast driver protocol — zero code changes from standard Laravel broadcasting
- Country-specific channels allow selective frontend subscriptions
- Requires a separate Reverb container/process

---

## ADR-008: PCNTL Graceful Shutdown

**Context:** The RabbitMQ consumer must not drop in-flight messages when Docker sends SIGTERM during a rolling deploy or `docker compose down`.

**Decision:** Use `pcntl_async_signals(true)` with SIGTERM/SIGINT handlers that set a `$shouldStop` flag. The consumer loop checks this flag between messages. Docker configured with `stop_grace_period: 30s`.

**Consequences:**
- Zero message loss during deployments or container stops
- Consumer finishes the current message before stopping
- Requires the `pcntl` PHP extension (included in Docker image via Alpine build)

---

## ADR-009: Dead Letter Queue

**Context:** Messages that fail processing (malformed JSON, transient errors, unhandled exceptions) must not block the main queue indefinitely.

**Decision:**
- DLX: `employee_events_dlx` (fanout)
- DLQ: `hub_employee_events_dlq` (durable)
- 3 retries with exponential backoff: 1s → 2s → 4s
- On max retries exceeded: nack with `requeue=false`, message routes to DLQ automatically

**Consequences:**
- Main queue never blocked by poison messages
- Failed messages inspectable via RabbitMQ Management UI at `localhost:15672`
- `events:retry-failed` Artisan command for manual replay
- DLQ must be monitored — alert rules configured in Prometheus/Grafana

---

## ADR-010: Prometheus Metrics

**Context:** Both services need operational and business metrics exposed for Prometheus scraping.

**Decision:** Use `promphp/prometheus_client_php` with InMemory storage adapter, exposed at `/api/metrics`. Tracks HTTP request counts/durations, events published/processed, cache hit/miss ratios, employee counts by country.

**Consequences:**
- Standard Prometheus text format, compatible with any Prometheus scraper
- No persistent storage needed (InMemory + 15s scrape interval)
- Four Grafana dashboards pre-provisioned from JSON — no manual setup
- Metrics reset on process restart (acceptable for Prometheus pull model)

---

## ADR-011: JSON Schema Contract for Events

**Context:** HR Service (publisher) and Hub Service (consumer) must agree on the event payload structure without sharing a PHP package (which would couple deployments).

**Decision:** Define [`contracts/employee-event.schema.json`](../contracts/employee-event.schema.json) (JSON Schema 2020-12) as the shared contract. Contract tests in both services validate all payloads against this schema. A `schema_version` field is included in every event for future evolution.

**Consequences:**
- Explicit, versioned contract — visible to both services and any future consumers
- Contract tests catch breaking changes before deployment
- `CountryCode` enum is consciously duplicated in each service (avoids shared-library coupling)
- Schema updates require coordinated deployment of both services

---

## ADR-012: Pest PHP as Testing Framework

**Context:** Tests should be readable, expressive, and enforce architectural boundaries automatically.

**Decision:** Use Pest PHP with test groups: `unit`, `feature`, `integration`, `arch`, `contract`. Architecture tests use `arch()->expect()->not->toUse()` to enforce DDD layer boundaries. Tests run against real PostgreSQL (not SQLite) for production parity.

**Consequences:**
- Readable, concise test syntax with descriptive `it()` / `test()` wrappers
- Layer boundaries enforced automatically on every test run
- Groups allow targeted runs: `make test-unit`, `make test-arch`, etc.
- Pest version must be pinned to match PHPUnit compatibility

---

## ADR-013: Multi-Stage Docker Builds

**Context:** Docker images should be optimized — development needs devtools and Xdebug; production needs a minimal attack surface.

**Decision:** Three-stage Dockerfiles (`base` → `dev` → `prod`):
- `base` — PHP 8.3-FPM Alpine + required extensions (`pdo_pgsql`, `pcntl`, `sockets`, `bcmath`, `redis`)
- `dev` — adds Nginx, Composer dev dependencies, source code
- `prod` — no dev deps, opcache enabled, `php artisan optimize`

Hub consumer container has `stop_grace_period: 30s` for PCNTL graceful shutdown (ADR-008).

**Consequences:**
- Production images are smaller with a reduced attack surface
- Dev/prod parity — same base image, same PHP extensions
- Graceful consumer shutdown integrated into container lifecycle
- Slightly more complex Dockerfile maintenance
