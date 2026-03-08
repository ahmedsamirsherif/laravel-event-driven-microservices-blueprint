# System Architecture

The platform follows Pragmatic Domain-Driven Design with a 4-layer architecture. Each microservice maintains its own database and communicates exclusively through events.

## DDD Layer Structure

```
app/
├── Domain/           # Business logic, models, events, value objects, interfaces
├── Application/      # Use cases, actions, event handlers, pipeline
├── Infrastructure/   # External concerns — RabbitMQ, Redis, broadcasting, metrics
└── Http/             # Controllers, requests, middleware
```

### Layer Dependencies

```
HTTP → Application → Domain ← Infrastructure
```

- `Domain` has **no** dependency on Infrastructure or HTTP
- `Infrastructure` implements Domain interfaces
- `Application` orchestrates Domain via use cases

## Architecture Rules

These rules are enforced by Pest Architecture tests on every run:

- Domain does NOT depend on Infrastructure or HTTP layers
- DTOs and Value Objects are `readonly`
- Actions and Pipelines are `final` classes
- Domain events do NOT implement `ShouldBroadcast`
- Repository interfaces live in Domain, implementations in Infrastructure
- No debug functions (`dd`, `dump`, `var_dump`) in the codebase

## Directory Structure per Service

```
app/
├── Domain/
│   ├── Country/
│   │   ├── Contracts/        CountryFieldsInterface / CountryModuleInterface
│   │   ├── Shared/           SharedSteps, SharedColumns, SharedWidgets
│   │   ├── USA/              USAFields (HR) / USAModule (Hub)
│   │   └── DEU/              DEUFields (HR) / DEUModule (Hub)
│   ├── Employee/
│   │   ├── Models/           Eloquent models
│   │   ├── Events/           Domain events
│   │   ├── DTOs/             readonly DTOs
│   │   ├── ValueObjects/     SSN, TaxId, Salary, Country
│   │   └── Repositories/     Repository interfaces
│   └── Shared/
│       └── Enums/            CountryCode (USA, DEU)
├── Application/
│   └── Employee/
│       ├── Actions/          CreateEmployee, UpdateEmployee, DeleteEmployee, ListEmployees
│       ├── Listeners/        PublishEmployeeEventToRabbitMQ (HR)
│       └── Handlers/         EmployeeCreated/Updated/DeletedHandler (Hub)
├── Infrastructure/
│   ├── Country/              CountryClassResolver, CountryFieldsRegistry, CountryRegistry
│   ├── Messaging/            RabbitMQPublisher (HR) / RabbitMQConsumer (Hub)
│   ├── Cache/                EmployeeCacheManager, ChecklistCacheManager
│   ├── Broadcasting/         EmployeeUpdatedBroadcast
│   ├── Metrics/              PrometheusMetricsService
│   └── Repositories/         EloquentEmployeeRepository
└── Http/
    ├── Controllers/
    ├── Requests/
    ├── Resources/
    └── Middleware/           ForceJsonResponse, CORS
```
