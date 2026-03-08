# Event Flow

Complete lifecycle of employee events: which classes fire them, how they cross the service boundary, and how they are consumed, projected, cached, and broadcast.

---

## 1. Origin — Domain Events (HR Service)

Three domain events exist in `hr-service/src/app/Domain/Employee/Events/`:

| Event class | Fired by | Carries |
|---|---|---|
| `EmployeeCreated` | `CreateEmployeeAction` | `Employee $employee` |
| `EmployeeUpdated` | `UpdateEmployeeAction` | `Employee $employee`, `array $changedFields` |
| `EmployeeDeleted` | `DeleteEmployeeAction` | `Employee $employee` |

Each Action class lives in `app/Application/Employee/Actions/`, receives a DTO, calls the repository, then dispatches:

```php
// CreateEmployeeAction::execute()
$employee = $this->repository->create($dto);
Event::dispatch(new EmployeeCreated($employee));
```

`UpdateEmployeeAction` tracks which fields changed by diffing `$employee->only($tracked)` before and after the DB write, passing the result as `$changedFields`.

## 2. Listener — Building the RabbitMQ Message (HR Service)

`AppServiceProvider::boot()` wires listeners:

```php
Event::listen(EmployeeCreated::class, [PublishEmployeeEventToRabbitMQ::class, 'handleCreated']);
Event::listen(EmployeeUpdated::class, [PublishEmployeeEventToRabbitMQ::class, 'handleUpdated']);
Event::listen(EmployeeDeleted::class, [PublishEmployeeEventToRabbitMQ::class, 'handleDeleted']);
```

`PublishEmployeeEventToRabbitMQ` (in `app/Application/Employee/Listeners/`) builds the routing key and payload:

- **Routing key**: `employee.{action}.{country}` — e.g. `employee.created.usa`
  - `{action}` = lowercase of `Created`/`Updated`/`Deleted`
  - `{country}` = lowercase of `$employee->country`
- **Payload** (matches JSON Schema contract):

```json
{
  "event_type": "EmployeeCreated",
  "event_id": "<uuid-v4>",
  "timestamp": "<ISO 8601>",
  "country": "USA",
  "schema_version": "1.0",
  "data": {
    "employee_id": 1,
    "changed_fields": [],
    "employee": {
      "id": 1, "name": "John", "last_name": "Doe",
      "salary": 75000, "country": "USA",
      "ssn": "123-45-6789", "address": "123 Main St",
      "goal": null, "tax_id": null
    }
  }
}
```

The `employeePayload()` method uses `Arr::only()` to whitelist exactly these fields from the model: `id`, `name`, `last_name`, `salary`, `country`, `ssn`, `address`, `goal`, `tax_id`, plus the five `doc_*` fields.

## 3. Publisher — AMQP Transport (HR Service)

`RabbitMQPublisher` (in `app/Infrastructure/Messaging/`) handles the AMQP connection:

- Exchange: `employee_events` (type: `topic`, durable)
- `AMQPMessage` properties:
  - `content_type`: `application/json`
  - `delivery_mode`: `DELIVERY_MODE_PERSISTENT` (survives broker restart)
  - `message_id`: `$payload['event_id']` (the UUID)
- Connection is lazy and reused; disconnects on `__destruct`
- On publish failure: logs error, disconnects (forces reconnect next time), re-throws

## 4. RabbitMQ Broker

```
Exchange: employee_events (topic, durable)
  Routing keys:  employee.created.usa
                 employee.updated.deu
                 employee.deleted.usa

  Binding: employee.#  →  Queue: hub_employee_events

Queue: hub_employee_events (durable, TTL 24h)
  x-dead-letter-exchange: employee_events_dlx

DLX: employee_events_dlx (fanout, durable)
  →  DLQ: hub_employee_events_dlq (durable)
```

## 5. Consumer — Receiving Messages (Hub Service)

`RabbitMQConsumer` (in `hub-service/src/app/Infrastructure/Messaging/`) runs as `php artisan rabbitmq:consume` inside the `hub-consumer` Docker container.

Startup:
1. `pcntl_async_signals(true)` + SIGTERM/SIGINT handlers set `$shouldStop = true`
2. Connect to RabbitMQ, declare exchange + DLX + DLQ
3. `basic_qos(prefetch=1)` — one message at a time
4. `basic_consume()` starts the loop; polls with 1s timeout

On message receipt, `processMessage()` runs:

```
1. JSON-decode the body
2. Pass payload array to EventProcessingPipeline::process()
3. On success:
   a. Fetch fresh checklist via ChecklistService (cache already warmed by handler)
   b. Dispatch EmployeeUpdatedBroadcast (ShouldBroadcastNow → Reverb)
   c. Record metrics (events_processed, processing_duration)
   d. msg->ack()
4. On failure:
   a. If retry_count < 3 → re-publish with x-retry-count+1, ack original
   b. If retry_count >= 3 → msg->nack(requeue=false) → routes to DLQ
```

### Retry strategy

| Attempt | Delay | Mechanism |
|---|---|---|
| 1 | 1s | Re-publish with `x-retry-count: 1` header |
| 2 | 2s | Re-publish with `x-retry-count: 2` header |
| 3 | 4s | Re-publish with `x-retry-count: 3` header |
| 4 | — | `nack(requeue=false)` routes to DLQ via DLX |

Retry count is stored in `application_headers` as `x-retry-count`. The `requeueWithDelay()` method acks the original message and publishes a copy with the incremented header.

## 6. Processing Pipeline (Hub Service)

`EventProcessingPipeline` (in `app/Application/EventProcessing/Pipeline/`) is registered as a singleton in `AppServiceProvider` with three handlers piped in order:

```php
$pipeline->pipe($app->make(EmployeeCreatedHandler::class));
$pipeline->pipe($app->make(EmployeeUpdatedHandler::class));
$pipeline->pipe($app->make(EmployeeDeletedHandler::class));
```

`process(array $payload)` runs:

| Step | Action | Table |
|---|---|---|
| 1. Idempotency | `ProcessedEvent::where('event_id', $eventId)->exists()` — skip if true | `processed_events` |
| 2. Log receipt | `EventLog::updateOrCreate(['event_id' => $eventId], [...status=received])` | `event_log` |
| 3. Find handler | Iterate `$handlers`, call `$handler->supports($payload)` | — |
| 4. Execute | `$handler->handle($payload)` | varies |
| 5. Mark done | `ProcessedEvent::create([...])` + `EventLog->update(['status' => 'processed'])` | both |
| On error | `EventLog->update(['status' => 'failed', 'error_message' => ...])` then re-throw | `event_log` |

## 7. Event Handlers (Hub Service)

All in `app/Application/EventProcessing/Handlers/`, all `final`, all implement `EventHandlerInterface`:

```php
interface EventHandlerInterface {
    public function supports(array $payload): bool;
    public function handle(array $payload): void;
}
```

### EmployeeCreatedHandler / EmployeeUpdatedHandler

1. `ProjectionDataBuilder::build($employeeId, $employee, $country)` maps payload to projection columns
2. `EmployeeProjectionRepositoryInterface::upsert($data)` — upserts into `employee_projections`
3. `InvalidatesCache` trait:
   - Bumps `employees:{country}:v` version counter (used for cache-busting paginated lists)
   - Forgets `checklist:{country}:{employeeId}` and `checklist_summary:{country}`
4. Dispatches `EmployeeEventReceived` domain event (internal, not broadcast)

### EmployeeDeletedHandler

Same as above except calls `repository->delete($employeeId)` instead of upsert.

### ProjectionDataBuilder

Maps the raw employee array to a flat structure matching `employee_projections` columns. Includes `raw_data` (the full employee array stored as JSON) for future extensibility.

## 8. Broadcasting — WebSocket (Hub Service)

After the pipeline succeeds, `RabbitMQConsumer` dispatches `EmployeeUpdatedBroadcast`:

```php
Event::dispatch(new EmployeeUpdatedBroadcast(
    eventType:     $eventType,       // "EmployeeCreated"
    country:       $country,         // "USA"
    employeeId:    $employeeId,      // 1
    employeeData:  $payload['data']['employee'],
    eventId:       $payload['event_id'],
    checklistData: $checklistData,   // freshly computed checklist (null for deletes)
));
```

`EmployeeUpdatedBroadcast` implements `ShouldBroadcastNow` (bypasses queue, sends immediately to Reverb):

- **Channels**: `employees` (global), `country.{country}`, `checklist.{country}`
- **Event name**: `employee.updated`
- **Payload**: `event_type`, `country`, `employee_id`, `employee_data`, `event_id`, `checklist_completion`, `timestamp`

Reverb runs in its own container (`php artisan reverb:start --host=0.0.0.0 --port=8080`) and pushes to connected WebSocket clients.

## 9. Data Flow Diagram

```
Client                  HR Service                 RabbitMQ                Hub Consumer              Hub DB                  Reverb/WS
  |                        |                          |                       |                       |                       |
  |-- POST /employees ---->|                          |                       |                       |                       |
  |                        |-- INSERT employees ----->|                       |                       |                       |
  |                        |                          |                       |                       |                       |
  |                        |-- Event::dispatch ------>|                       |                       |                       |
  |                        |   EmployeeCreated        |                       |                       |                       |
  |                        |                          |                       |                       |                       |
  |                        |-- basic_publish -------->|                       |                       |                       |
  |                        |   employee.created.usa   |                       |                       |                       |
  |<-- 201 Created --------|                          |                       |                       |                       |
  |                        |                          |-- deliver msg ------->|                       |                       |
  |                        |                          |                       |-- idempotency check ->|                       |
  |                        |                          |                       |-- log event --------->|                       |
  |                        |                          |                       |-- upsert projection ->|                       |
  |                        |                          |                       |-- invalidate cache    |                       |
  |                        |                          |                       |-- mark processed ---->|                       |
  |                        |                          |                       |                       |                       |
  |                        |                          |                       |-- broadcast ----------------------------------------->|
  |                        |                          |                       |   EmployeeUpdatedBroadcast (ShouldBroadcastNow)      |
  |                        |                          |<-- ack ---------------|                       |                       |
  |                        |                          |                       |                       |                       |
  |<--------------------------------------------- WS push: employee.updated --------------------------------------------|
```

## 10. Data Contract

The shared contract is defined in [`contracts/employee-event.schema.json`](../contracts/employee-event.schema.json) (JSON Schema 2020-12).

Both services have contract tests validating payloads against this schema. The `schema_version` field (`"1.0"`) is included in every event for future evolution. `CountryCode` enum is consciously duplicated in each service to avoid shared-library coupling.

## 11. Key Classes Reference

| Class | Service | Layer | Role |
|---|---|---|---|
| `EmployeeCreated/Updated/Deleted` | HR | Domain | Domain events (plain DTOs) |
| `PublishEmployeeEventToRabbitMQ` | HR | Application | Listener: builds payload, delegates to publisher |
| `RabbitMQPublisher` | HR | Infrastructure | AMQP transport: connection, exchange, publish |
| `RabbitMQConsumer` | Hub | Infrastructure | AMQP consumer: polling loop, retry, DLQ, broadcast dispatch |
| `EventProcessingPipeline` | Hub | Application | Orchestrator: idempotency, logging, handler dispatch, completion |
| `EmployeeCreatedHandler` | Hub | Application | Upserts projection, invalidates cache |
| `EmployeeUpdatedHandler` | Hub | Application | Upserts projection, invalidates cache |
| `EmployeeDeletedHandler` | Hub | Application | Deletes projection, invalidates cache |
| `ProjectionDataBuilder` | Hub | Application | Maps event payload to `employee_projections` row |
| `InvalidatesCache` (trait) | Hub | Application | Bumps version counter, clears checklist cache |
| `EmployeeUpdatedBroadcast` | Hub | Infrastructure | `ShouldBroadcastNow` event for Reverb WebSocket |
| `ChecklistService` | Hub | Application | Computes checklist from projection + country module rules |
| `EventLog` | Hub | Domain | Audit log model (`event_log` table) |
| `ProcessedEvent` | Hub | Domain | Idempotency model (`processed_events` table) |
| `ReceivedEventDTO` | Hub | Domain | Typed DTO with `fromArray()`/`fromJson()` static constructors |
| `EmployeeEventReceived` | Hub | Domain | Internal domain event (not broadcast) |
