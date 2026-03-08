# Logging

Logging is part of the runtime contract in this platform, not leftover debug output. The HR API, Hub API, and Hub consumer all emit structured Laravel logs to `stderr`, so Docker and container-native log collection see the same stream.

Messages follow one deliberate shape:

```text
[ClassName][method] Message
```

The message stays static and searchable. Variable data lives in the context array. Caught throwables are passed under the PSR-3 `exception` key.

## Runtime Model

| Item | Value |
| --- | --- |
| Active channel | `stderr` via Monolog `StreamHandler` |
| Default level | `debug` |
| Correlation | `X-Request-ID` shared with `Log::shareContext(...)` |
| Runtime emitters | `hr-service`, `hub-service`, `hub-consumer`, `reverb` |

Both applications still define Laravel's standard fallback channels (`stack`, `single`, `daily`, `syslog`, `errorlog`, `null`), but the Docker runtime path uses `stderr`.

## Conventions

- Keep message text static and scoped.
- Put IDs, country codes, retry counters, durations, cache flags, and queue names in context.
- Pass caught throwables as `['exception' => $e]`.
- Reuse the same `request_id` across the full request lifecycle.

```php
$requestId = $request->header('X-Request-ID') ?? (string) Str::uuid();
Log::shareContext(['request_id' => $requestId]);
```

## Current Inventory

The application currently has `30` log call sites: `16` info, `7` warning, `5` error, and `2` debug. The count excludes console output such as `$this->info(...)` and the two `Log::shareContext(...)` registrations.

| Scope | Levels | Typical messages | Main context |
| --- | --- | --- | --- |
| Cross-cutting | `warning`, `error` | `Request metrics recording failed`, `Unhandled exception` | `request_id`, `method`, `endpoint`, `status`, `exception` |
| HR write side | `info`, `error` | `Employee created`, `Employee updated`, `Employee deleted`, `Event published`, `Failed to publish event` | `employee_id`, `country`, `changed_fields`, `routing_key`, `event_id`, `exception` |
| Hub read side | `debug`, `warning` | `Employee list served`, `Checklist served`, `Hub service health check degraded`, `Consistency check failed` | `country`, `page`, `per_page`, cache hit flags, `checks`, totals |
| Hub event pipeline | `info`, `warning`, `error` | `Duplicate event skipped`, `Dispatching event to handler`, `No handler found for event`, `Broadcast sent via Reverb`, `Failed to process RabbitMQ message`, `Message exhausted retries; routing to DLQ` | `event_type`, `event_id`, `employee_id`, `country`, `retry_count`, `duration_ms`, `exception` |

## Representative Traces

### Successful event

```text
[INFO] [EmployeeController][store] Employee created {"employee_id":1,"country":"USA","request_id":"abc-123"}
[INFO] [RabbitMQPublisher][publish] Event published {"routing_key":"employee.created.usa","event_id":"evt-456","request_id":"abc-123"}
[INFO] [EventProcessingPipeline][process] Dispatching event to handler {"handler":"App\\Application\\EventProcessing\\Handlers\\EmployeeCreatedHandler","event_type":"EmployeeCreated","event_id":"evt-456","country":"USA"}
[INFO] [EmployeeCreatedHandler][handle] Employee created event handled {"employee_id":1,"country":"USA"}
[INFO] [RabbitMQConsumer][processMessage] Broadcast sent via Reverb {"event_type":"EmployeeCreated","employee_id":1,"country":"USA","channels":["employees","country.USA"]}
[INFO] [RabbitMQConsumer][processMessage] Event fully processed {"event_type":"EmployeeCreated","employee_id":1,"country":"USA","duration_ms":45.12,"retry_count":0}
```

The consumer log currently records `employees` and `country.{country}` in its `channels` field. The broadcast event itself also fans out to `checklist.{country}`.

### Failed event

```text
[ERROR] [RabbitMQConsumer][processMessage] Failed to process RabbitMQ message {"event_type":"EmployeeCreated","retry_count":0,"exception":"<normalized Throwable>"}
[INFO] [RabbitMQConsumer][requeueWithDelay] Message re-queued for retry {"retry_count":1,"delay_ms":1000}
[ERROR] [RabbitMQConsumer][processMessage] Failed to process RabbitMQ message {"event_type":"EmployeeCreated","retry_count":1,"exception":"<normalized Throwable>"}
[INFO] [RabbitMQConsumer][requeueWithDelay] Message re-queued for retry {"retry_count":2,"delay_ms":2000}
[ERROR] [RabbitMQConsumer][processMessage] Failed to process RabbitMQ message {"event_type":"EmployeeCreated","retry_count":2,"exception":"<normalized Throwable>"}
[INFO] [RabbitMQConsumer][requeueWithDelay] Message re-queued for retry {"retry_count":3,"delay_ms":4000}
[ERROR] [RabbitMQConsumer][processMessage] Failed to process RabbitMQ message {"event_type":"EmployeeCreated","retry_count":3,"exception":"<normalized Throwable>"}
[WARNING] [RabbitMQConsumer][processMessage] Message exhausted retries; routing to DLQ {"event_type":"EmployeeCreated","retry_count":3}
```

## Accessing Logs

| Command | Purpose |
| --- | --- |
| `docker compose logs hr-service` | HR API writes and publish flow |
| `docker compose logs hub-service` | Hub read endpoints and health |
| `docker compose logs hub-consumer` | Event processing, broadcast, retries, and DLQ flow |
| `docker compose logs reverb` | WebSocket server activity |
| `docker compose logs -f --tail=40 hr-service hub-consumer reverb rabbitmq` | Common live view across the moving parts |
| `docker compose logs -f` | Full live stream |
| `docker compose logs hub-consumer 2>&1 \| grep '\[RabbitMQConsumer\]'` | Consumer-only lines |
| `docker compose logs hub-consumer 2>&1 \| grep '"event_id"'` | Trace one event through the pipeline |
