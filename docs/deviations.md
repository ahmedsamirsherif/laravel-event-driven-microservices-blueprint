# Deviations & Architecture Decision Records

Every major design choice in this platform is captured in an Architecture Decision Record (ADR).

## All 13 ADRs

| # | Title | Key Decision |
|---|---|---|
| [001](adr.md#adr-001-microservices-separation) | Microservices Separation | Two independent services with separate databases |
| [002](adr.md#adr-002-event-driven-rabbitmq) | Event-Driven RabbitMQ | Topic exchange for flexible routing, persistent messages |
| [003](adr.md#adr-003-ddd-layered-architecture) | DDD Layered Architecture | Pragmatic DDD — Eloquent permitted in Domain layer |
| [004](adr.md#adr-004-value-objects) | Value Objects | Immutable, self-validating value objects for domain primitives |
| [005](adr.md#adr-005-redis-caching) | Redis Caching | Event-driven invalidation with eager rebuild for checklists |
| [006](adr.md#adr-006-country-strategy-pattern) | Country Strategy Pattern | Auto-discovering registry — adding a country = adding a directory |
| [007](adr.md#adr-007-laravel-reverb-broadcasting) | Laravel Reverb Broadcasting | Native Laravel WebSocket for real-time employee event updates |
| [008](adr.md#adr-008-pcntl-graceful-shutdown) | PCNTL Graceful Shutdown | Signal handling ensures clean consumer shutdown mid-message |
| [009](adr.md#adr-009-dead-letter-queue) | Dead Letter Queue | DLX + DLQ with 3-attempt exponential backoff retry |
| [010](adr.md#adr-010-prometheus-metrics) | Prometheus Metrics | Application + infra metrics auto-scraped into Grafana |
| [011](adr.md#adr-011-event-schema-contract) | Event Schema Contract | JSON Schema 2020-12 as shared integration contract |
| [012](adr.md#adr-012-pest-testing) | Pest Testing | Pest PHP for expressive tests + architecture enforcement |
| [013](adr.md#adr-013-docker-multi-stage) | Docker Multi-Stage | 3-stage Dockerfiles (base / dev / prod) for optimised images |

## Key Decisions in Detail

### ADR-003: Pragmatic DDD

Eloquent models are permitted in the Domain layer. Full DDD (separate repository + domain model objects) was considered too heavy for a two-service project. The Domain layer still owns interface contracts, value objects, and domain events — keeping the codebase lean while maintaining layered architecture benefits.

### ADR-006: Auto-Discovering Country Strategy

Convention-based `CountryClassResolver` replaces manual registration. It scans `Domain/Country/*/` directories, builds the FQCN as `App\Domain\Country\{ISO3}\{ISO3}{Suffix}`, and validates the interface via reflection. **Adding a country = adding a single directory with one file.** No service provider changes, no registry updates required.

### ADR-009: Dead Letter Queue

Failed messages route to a DLQ after 3 retry attempts with exponential backoff (1s → 2s → 4s). This prevents poison messages from blocking the main queue while preserving them for manual inspection and replay. The DLX is a standard fanout exchange — any tooling can bind to it for alerting.

### ADR-011: Event Schema Contract

JSON Schema 2020-12 at `contracts/employee-event.schema.json` serves as the shared integration contract validated by both services in their contract test suites. The `CountryCode` enum is consciously duplicated in each service to avoid shared-library coupling — the schema file is the single source of truth.

## Deliberate Deviations from the Challenge Spec

### 1. Laravel Reverb instead of Pusher/Soketi

Reverb is Laravel's first-party WebSocket server (11+). Same `pusher` protocol, zero external accounts, runs as a container like everything else.

### 2. Two PostgreSQL instances instead of one

Each service gets its own database so cross-service queries are physically impossible — real CQRS separation, not just logical.

### 3. ISO 3166-1 alpha-3 codes instead of plain strings

We use `"DEU"` instead of `"Germany"`. Enum-backed ISO codes are type-safe, work as routing keys, and don't break when display names change.

### 4. Full idempotency pipeline instead of basic error handling

3 retries with backoff, DLX/DLQ for poison messages, `processed_events` dedup table, and `event_log` audit trail. Standard production RabbitMQ patterns.

### 5. Versioned path-parameter API

Spec uses `GET /api/checklists?country=USA`, we use `GET /api/v1/checklist/USA`. Country is a resource scope (not a filter), path segments cache naturally, and `/v1/` gives us a migration path.

### 6. Integration tests mock AMQP transport

Tests inject payloads directly into `EventProcessingPipeline` and verify projection → cache → broadcast without a running broker. Fast, deterministic, CI-friendly.

### 7. README links to docs instead of inlining trade-offs

A concise README that points to organized docs stays in sync better than a monolithic one. All trade-off detail lives here and in the 13 ADRs.

### 8. Logging docs may lag behind code

After refactoring to `Log::shareContext` and `[Class][method]` prefixes, some doc sections may reference older patterns. Code is the source of truth.
