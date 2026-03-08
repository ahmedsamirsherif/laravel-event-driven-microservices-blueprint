# CQRS Pattern

The system implements Command Query Responsibility Segregation — the HR Service handles commands (writes) and the Hub Service handles queries (reads) from event-sourced projections.

## Write Side (HR Service)

```
Client  →  POST /api/v1/employees
        →  Validate (country-specific rules)
        →  Insert into hr-postgres (employees table)
        →  Return 201 Created
        →  Publish EmployeeCreated event to RabbitMQ
             Exchange: employee_events
             Routing key: employee.created.usa
```

## Read Side (Hub Service)

```
RabbitMQ  →  Hub Consumer receives message
          →  Idempotency check (processed_events table)
          →  Upsert projection in hub-postgres
          →  Invalidate + rebuild Redis cache
          →  Broadcast via Reverb WebSocket
          →  Client GET /api/v1/employees/USA reads from projections (cached)
```

## CQRS Mapping

| CQRS Concept | Implementation |
|---|---|
| Command side (writes) | HR Service — owns `employees` table, accepts REST mutations, emits domain events |
| Query side (reads) | Hub Service — owns `employee_projections` table, serves all UI / read APIs |
| Event bus | RabbitMQ topic exchange — decouples write from read, async propagation |
| Projection | `EmployeeProjection` model updated by event handlers — denormalised, read-optimised |
| Read optimisation | Redis versioned cache on top of projections — API reads hit memory, not disk |

## Key Benefits

- **Independence** — if HR Service goes down, Hub continues serving reads from its own projection store
- **Scalability** — read side can be scaled horizontally without affecting the write side
- **Auditability** — every state change passes through the event bus and is logged in `event_log`; projections are fully **replayable**
- **Optimised read models** — projections are pre-computed for query efficiency

## Trade-off

Data is **eventually consistent** — there's a window (typically < 100ms) where HR has a new employee that Hub hasn't projected yet. This is acceptable for a UI-facing onboarding platform where sub-second staleness is invisible to users.
