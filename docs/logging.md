# Logging

Both services use Laravel's logging system with **structured context** and **stderr output** for Docker-native log collection.

---

## Configuration

| Setting | Value | Source |
|---------|-------|--------|
| Channel | `stderr` | `LOG_CHANNEL` env var in `docker-compose.yml` |
| Level | `debug` | `LOG_LEVEL` env var in `docker-compose.yml` |
| Driver | `monolog` with `StreamHandler` → `php://stderr` | `config/logging.php` → `stderr` channel |
| Format | Laravel default (timestamp + channel + level + message + context JSON) | Monolog default formatter |

All four containers (hr-service, hub-service, hub-consumer, reverb) write to stderr, which Docker captures as JSON logs.

```bash
# CLI — Docker log access
docker compose logs hr-service        # HR logs
docker compose logs hub-consumer      # Consumer pipeline logs
docker compose logs -f                # Follow all services
```

## Available Channels

Both services define these channels in `config/logging.php`. Only `stderr` is active in Docker:

| Channel | Driver | Output |
|---------|--------|--------|
| `stack` | Stack | Combines multiple channels |
| `single` | Monolog | `storage/logs/laravel.log` |
| `daily` | Monolog | Rolling daily log files |
| `stderr` | Monolog | `php://stderr` (active) |
| `syslog` | Syslog | System log |
| `errorlog` | Error log | PHP `error_log()` |
| `null` | Monolog | No output (discard) |

## Request ID Context

Both services inject a `request_id` into every log entry via the `AddRequestId` middleware:

```php
// AddRequestId middleware — runs on every API request
$requestId = $request->header('X-Request-ID') ?? (string) Str::uuid();
Log::withContext(['request_id' => $requestId]);
```

This means every log line produced during a request carries the same `request_id`, enabling end-to-end tracing. The ID is also returned in the response header `X-Request-ID`.

## Log Levels Used

| Level | Count | When Used |
|-------|-------|-----------|
| `error` | 6 | Unrecoverable failures — event publish failure, message processing failure, unhandled exceptions |
| `warning` | 9 | Degraded but recoverable — metrics recording failure, no handler for event, broadcast failure (non-fatal), DLQ routing, health check degraded |
| `info` | 29 | Operational events — employee CRUD, event published/processed, consumer start/stop, handler execution, broadcast sent, retry/requeue, signal received |
| `debug` | 2 | Verbose tracing — employee list served, checklist served (Hub only) |

## HR Service — Log Points

### Controllers

| File | Level | Message | Context |
|------|-------|---------|---------|
| `EmployeeController` | `info` | Employee created | `employee_id`, `country` |
| `EmployeeController` | `info` | Employee updated | `employee_id`, `country`, `changed_fields` |
| `EmployeeController` | `info` | Employee deleted | `employee_id`, `country` |

### Event Publishing

| File | Level | Message | Context |
|------|-------|---------|---------|
| `RabbitMQPublisher` | `info` | Event published | `routing_key`, `event_id` |
| `RabbitMQPublisher` | `error` | Failed to publish event | `routing_key`, `event_id`, `error` |
| `PublishEmployeeEventToRabbitMQ` | `error` | Failed to publish employee event | `event_type`, `employee_id`, `error` |

### Middleware & Exceptions

| File | Level | Message | Context |
|------|-------|---------|---------|
| `AddRequestId` | context | *(sets request_id on all logs)* | `request_id` |
| `RecordRequestMetrics` | `warning` | Request metrics recording failed | `method`, `endpoint`, `status`, `error` |
| `Handler` | `error` | Unhandled exception | `exception` (class name), `message` |

## Hub Service — Log Points

### Consumer Lifecycle

| Level | Message | Context |
|-------|---------|---------|
| `info` | RabbitMQ consumer started | `queue` |
| `info` | RabbitMQ consumer stopped gracefully | — |
| `info` | Received SIGTERM - stopping consumer gracefully | — |
| `info` | Received SIGINT - stopping consumer gracefully | — |

### Event Processing Pipeline

| Level | Message | Context |
|-------|---------|---------|
| `info` | Duplicate event skipped | `event_id`, `event_type` |
| `info` | Dispatching to handler | `handler` (class name), `event_type` |
| `warning` | No handler for event | `event_type` |

### Event Handlers

| Level | Message | Context |
|-------|---------|---------|
| `info` | EmployeeCreated handled | `employee_id`, `country` |
| `info` | EmployeeUpdated handled | `employee_id`, `country` |
| `info` | EmployeeDeleted handled | `employee_id`, `country` |

### Message Processing & Retries

| Level | Message | Context |
|-------|---------|---------|
| `info` | Event fully processed | `event_type`, `employee_id`, `country`, `duration_ms`, `retry_count` |
| `error` | Failed to process RabbitMQ message | `event_type`, `retry_count`, `error` |
| `info` | Message re-queued for retry | `retry_count`, `delay_ms` |
| `warning` | Message exhausted retries, sending to DLQ | `event_type`, `retry_count` |

### Broadcasting

| Level | Message | Context |
|-------|---------|---------|
| `info` | Broadcast sent via Reverb | `event_type`, `employee_id`, `country`, `channels` |
| `warning` | Broadcast to Reverb failed (non-fatal) | `event_type`, `error` |

### Controllers & Health

| File | Level | Message | Context |
|------|-------|---------|---------|
| `EmployeeController` | `debug` | Employee list served | `country` |
| `ChecklistController` | `debug` | Checklist served | `country` |
| `HealthController` | `warning` | Hub service health check degraded | `checks` |

## Exception Handling

Both services use a custom `Handler` in `app/Exceptions/Handler.php`:

```php
$this->renderable(function (Throwable $e) {
    Log::error('Unhandled exception', [
        'exception' => get_class($e),
        'message'   => $e->getMessage(),
    ]);
    // Returns standardized JSON error envelope
});
```

This catches all unhandled exceptions, logs them at `error` level, and returns a consistent JSON response with the error code and message.

## Event Pipeline Log Trace

A successful employee creation produces this log sequence across services:

```
# HR Service (hr-service container)
[INFO] Employee created                  {"employee_id":1,"country":"USA","request_id":"abc-123"}
[INFO] Event published                   {"routing_key":"employee.created.usa","event_id":"evt-456","request_id":"abc-123"}

# Hub Service (hub-consumer container)
[INFO] Dispatching to handler            {"handler":"EmployeeCreatedHandler","event_type":"EmployeeCreated"}
[INFO] EmployeeCreated handled           {"employee_id":1,"country":"USA"}
[INFO] Broadcast sent via Reverb         {"event_type":"EmployeeCreated","employee_id":1,"country":"USA","channels":["employees","country.USA","checklist.USA"]}
[INFO] Event fully processed             {"event_type":"EmployeeCreated","employee_id":1,"country":"USA","duration_ms":45,"retry_count":0}
```

## Failed Event Log Trace

When processing fails and retries exhaust:

```
# Attempt 1
[ERROR] Failed to process RabbitMQ message  {"event_type":"EmployeeCreated","retry_count":0,"error":"Connection refused"}
[INFO]  Message re-queued for retry         {"retry_count":1,"delay_ms":1000}

# Attempt 2
[ERROR] Failed to process RabbitMQ message  {"event_type":"EmployeeCreated","retry_count":1,"error":"Connection refused"}
[INFO]  Message re-queued for retry         {"retry_count":2,"delay_ms":2000}

# Attempt 3
[ERROR] Failed to process RabbitMQ message  {"event_type":"EmployeeCreated","retry_count":2,"error":"Connection refused"}
[INFO]  Message re-queued for retry         {"retry_count":3,"delay_ms":4000}

# Attempt 4 — max retries exceeded
[ERROR]   Failed to process RabbitMQ message  {"event_type":"EmployeeCreated","retry_count":3,"error":"Connection refused"}
[WARNING] Message exhausted retries, sending to DLQ  {"event_type":"EmployeeCreated","retry_count":3}
```

## Accessing Logs

| Command | What It Shows |
|---------|---------------|
| `docker compose logs hr-service` | HR API logs (CRUD operations, event publishing) |
| `docker compose logs hub-service` | Hub API logs (employee list, checklist, schema endpoints) |
| `docker compose logs hub-consumer` | Event pipeline logs (processing, handlers, retries, broadcasts) |
| `docker compose logs reverb` | WebSocket server logs |
| `docker compose logs -f` | Follow all service logs in real-time |
| `docker compose logs hub-consumer 2>&1 \| grep ERROR` | Filter for errors only |
| `docker compose logs hub-consumer 2>&1 \| grep "event_id"` | Trace a specific event through the pipeline |
