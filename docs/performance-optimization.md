# Performance & Optimization

The platform applies targeted optimizations at every layer. Below is a consolidated inventory of every performance-relevant decision in the codebase.

## RabbitMQ Connection Management

The HR publisher keeps a **persistent, lazy connection** with automatic health checking and reconnection. The private `channel()` method checks `$this->connection->isConnected()` before reusing the existing AMQP connection and only creates a new one when the current connection is dead or absent. This avoids opening a fresh TCP + AMQP handshake on every publish while recovering transparently from broker restarts.

The Hub consumer opens a **single long-lived connection** for the entire lifetime of the `rabbitmq:consume` process. Exchange and queue declarations happen once at startup, not per-message. A 1-second `wait()` timeout in the polling loop allows the consumer to check the `$shouldStop` flag frequently enough for clean container shutdown without busy-waiting.

**Publisher connection pattern (HR):**
```
channel()
├── connection alive? → reuse existing channel
├── connection dead?  → disconnect + create new AMQPStreamConnection
└── exchange_declare runs once per connection (idempotent, durable)
```

**Consumer topology (Hub):**
```
consume()
├── single AMQPStreamConnection for process lifetime
├── basic_qos(prefetchCount=1) — one unacked message at a time
├── exchange + queue declared once at startup
└── while(is_consuming && !shouldStop) → wait(1s) polling loop
```

## Caching Strategy

**Version-counter invalidation** replaces mass key deletion. Instead of iterating over potentially hundreds of paginated cache keys (`employees:USA:p1:pp15`, `employees:USA:p2:pp15`, …), the system maintains a single version counter (`employees:{country}:v`) that is incremented on every employee event. Cache keys embed the version number (`employees:USA:v3:p1:pp15`), so incrementing the counter naturally makes all old keys obsolete without any scan or delete loop.

```
Event arrives → increment employees:{country}:v from 3 to 4
Next read    → looks for employees:USA:v4:p1:pp15 → cache miss → fresh query → cache set
Old keys     → employees:USA:v3:* expire naturally via TTL (1 hour)
```

**Per-employee checklist caching** with eager rebuild. Each employee's checklist is cached individually (`checklist:{country}:{employeeId}`). When a relevant event arrives, the specific employee's checklist key and the country-level summary key are explicitly invalidated. The consumer immediately fetches the freshly computed checklist data after pipeline processing, which re-warms the cache within the same event handling cycle — so the next API read is always a cache hit.

**Paginated checklist reuse.** When the checklist controller builds a paginated response, it checks each employee's individual cache key first. On a hit, it skips recomputation entirely. On a miss, it builds the checklist from the already-loaded Eloquent model data (no extra query) and caches it for next time.

**Cache metrics instrumentation.** Every cache read is preceded by a `cache()->has()` check that feeds hit/miss counters into Prometheus, enabling accurate cache-effectiveness monitoring without affecting the `cache()->remember()` behavior.

## Database Indices

Every query path is backed by a purpose-built index. No full table scans occur during normal API or event processing operations.

| Table | Index | Type | Covers |
|-------|-------|------|--------|
| `employees` | `(country)` | regular | Country filter in HR list API |
| `employees` | `(country, created_at)` | composite | Paginated listing with country filter + chronological ordering |
| `employee_media` | `(employee_id, document_type, is_current)` | composite (covering) | Finds current documents for an employee by type in a single index scan |
| `employee_projections` | `(country)` | regular | Hub read-model country filter |
| `employee_projections` | `(country, employee_id)` | composite | Country-scoped employee lookup |
| `employee_projections` | `(employee_id)` | unique | 1:1 mapping guarantee; upsert target |
| `event_log` | `(event_type, country)` | composite | Event filtering and replay queries |
| `event_log` | `(status, received_at)` | composite | Find failed/pending events chronologically for retry commands |
| `event_log` | `(employee_id)` | regular | Trace all events for a single employee |
| `processed_events` | `(event_id)` | unique | Single-query idempotency lookup |

**Column types are sized to their data.** `country` uses `VARCHAR(10)` (ISO3 codes are 3 chars), `ssn` uses `VARCHAR(11)` (exact `###-##-####` length), `tax_id` uses `VARCHAR(20)`, and `salary` uses `DECIMAL(12,2)` for exact currency arithmetic without floating-point drift.

## DDD & Architecture Optimizations

**Domain isolation eliminates accidental coupling overhead.** The Domain layer contains zero infrastructure imports — only repository interfaces, value objects, DTOs, and enums. This means domain logic never triggers unexpected I/O (no hidden database queries, no cache calls, no message publishing). Side effects are explicit and happen only at the Application and Infrastructure boundaries.

**Immutable DTOs and value objects** (`final readonly` classes) enable safe pass-by-reference without defensive copying. `CreateEmployeeDTO`, `UpdateEmployeeDTO`, and `ReceivedEventDTO` are constructed once and never mutated. Value objects (`SSN`, `TaxId`, `Salary`) validate in the constructor, making invalid states unrepresentable at the type level.

**Convention-based country auto-discovery** avoids the overhead of manual service registration and configuration. `CountryClassResolver` uses `ReflectionClass` once during boot to discover country module implementations, then caches every resolved instance in a static `$cache` array. Combined with Laravel's singleton binding for `CountryRegistry` and `CountryFieldsRegistry`, reflection runs exactly once per application lifecycle — all subsequent lookups are O(1) hash-map reads.

**Partial updates in the repository.** The HR `update()` method uses `array_filter(fn ($v) => $v !== null)` on the DTO to send only changed fields to the database, reducing the UPDATE statement payload and avoiding unnecessary column writes.

**Database-side aggregations.** The Hub projection repository delegates `COUNT(*)` and `AVG()` to PostgreSQL instead of loading full result sets into PHP memory. Employee counts and average salary computations never hydrate Eloquent models.

## Event Pipeline Optimizations

**Idempotency via processed_events table.** Every event carries a UUID `event_id`. Before processing, the pipeline executes `ProcessedEvent::where('event_id', $eventId)->exists()` against the unique-indexed column — a single index lookup. Duplicate deliveries are skipped entirely with no side effects.

**Pipeline short-circuits on first matching handler.** The `EventProcessingPipeline` iterates handlers and returns immediately after the first `supports()` match, avoiding unnecessary handler evaluation.

**Atomic upsert for projections.** `updateOrCreate()` performs a single query that either inserts or updates the projection row, eliminating race conditions when concurrent events arrive for the same employee.

**Non-fatal broadcast failures.** WebSocket broadcasting is wrapped in a separate try-catch after the main pipeline processing. A Reverb failure does not trigger a retry of the entire event, preventing unnecessary reprocessing when only the broadcast leg is down.

## Message Reliability

**Persistent messages.** Every published AMQP message uses `DELIVERY_MODE_PERSISTENT`, ensuring messages survive broker restarts.

**Durable exchanges and queues.** Both the main exchange (`employee_events`, topic) and the consumer queue (`hub_employee_events`) are durable. Queue TTL is set to 24 hours.

**Dead letter exchange (DLX) with exponential backoff.** Failed messages are retried up to 3 times with delays of 1s, 2s, and 4s (2^n backoff). Retry count is tracked via a custom `x-retry-count` AMQP header. On max retries exceeded, the message is nack'd without requeue, which routes it to the DLQ (`hub_employee_events_dlq`) via the fanout DLX (`employee_events_dlx`).

**Manual acknowledgment.** The consumer uses `basic_consume` with `no_ack=false`, so messages are only removed from the queue after explicit `ack()` — never lost on crash.

**Graceful shutdown.** PCNTL signal handlers for SIGTERM and SIGINT set a `$shouldStop` flag. The 1-second `wait()` timeout ensures the consumer checks this flag promptly. The current message finishes processing and is ack'd before the connection closes. Docker's `stop_grace_period: 30s` gives enough time for this sequence.

## Docker & Infrastructure

**Multi-stage Dockerfiles** with three stages (`base`, `dev`, `prod`). The base stage installs PHP extensions then removes build tools (`apk del autoconf gcc g++ make`), reducing the layer by ~300 MB. Composer dependencies are copied and installed before source code, so code changes don't invalidate the dependency layer cache.

**Alpine-based images throughout.** PHP, PostgreSQL, Redis, and RabbitMQ all use Alpine variants, keeping total image sizes minimal.

**Optimized autoloading.** `composer dump-autoload --optimize` generates a classmap instead of relying on PSR-4 filesystem lookups at runtime. The prod stage additionally caches Laravel's config, routes, and views.

**PHP opcache** is installed in the base image, caching compiled bytecode and eliminating repeated file parsing.

**Redis AOF persistence** (`--appendonly yes --appendfsync everysec`) balances durability with throughput — at most 1 second of cache data at risk on power failure, with named volumes for persistence across container restarts.

**Health-check dependency chain.** Services declare `depends_on` with `condition: service_healthy` in Docker Compose, ensuring databases and the broker are confirmed healthy before application containers start. This prevents startup race conditions and failed migration attempts.

**Deep health checks.** The Hub health endpoint performs a write-read-delete cycle against Redis (not just a `PING`) and a `SELECT 1` against PostgreSQL, validating actual data-path functionality rather than just connection liveness.

## Observability

**In-memory Prometheus collector.** Both services use `InMemoryStorage` for the metrics registry, avoiding a Redis round-trip on every counter increment. Metrics are rendered on-demand when Prometheus scrapes `/api/metrics`.

**Histogram buckets tuned for API and event workloads.** HTTP request duration uses buckets from 10ms to 5s. Event processing duration uses buckets from 1ms to 1s. Event publishing duration uses separate sub-second buckets. This gives meaningful percentile resolution without excessive cardinality.

**Request ID propagation.** The `AddRequestId` middleware generates or forwards an `X-Request-ID` header and injects it into Monolog's context, making every log line traceable across the request lifecycle. The same ID is returned to the caller for cross-service correlation.

**Endpoint normalization in metrics labels.** The `RecordRequestMetrics` middleware replaces dynamic path segments (`/employees/42`) with parameter placeholders (`/employees/{id}`), preventing cardinality explosion in Prometheus while keeping per-endpoint granularity. The Hub service additionally normalizes country codes in paths.

**Structured, leveled logging.** Every event processing step — receipt, handler dispatch, projection upsert, cache invalidation, broadcast, and completion — is logged with structured context (event_type, employee_id, country, duration_ms, retry_count). Processing failures are logged at `error` level; broadcast failures at `warning` level (non-fatal); routine operations at `info` level.
