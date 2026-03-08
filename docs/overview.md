# Platform Overview

An Event-Driven Multi-Country Employee Onboarding Platform built as a Senior Backend Engineer coding challenge. Two independent Laravel 12 microservices communicate asynchronously through RabbitMQ, with real-time WebSocket updates, Redis caching, and full observability through Prometheus + Grafana.

| | |
|---|---|
| Docker Services | 10 |
| Tests Passing | 175 |
| Microservices | 2 |
| ADRs | 13 |
| Countries | 2 |
| Grafana Dashboards | 4 |

## Key Features

| Feature | Description |
|---|---|
| Event-Driven Architecture | RabbitMQ topic exchange with country-suffixed routing keys, DLQ, and exponential backoff retry |
| Multi-Country Support | Auto-discovering country modules — add a new country with zero changes to existing files |
| CQRS + Projections | HR Service writes, Hub Service reads event-sourced projections — independent scaling |
| Real-Time WebSocket | Laravel Reverb broadcasts employee events to connected clients instantly |
| Redis Caching | Event-driven invalidation with eager rebuild for checklists — ~100% cache hit rate |
| 175 Tests | Unit, Feature, Integration, Architecture, and Contract test suites via Pest PHP |

## Technology Stack

| Component | Technology | Version |
|---|---|---|
| Language / Framework | PHP / Laravel | 8.3 / 12.53 |
| Database | PostgreSQL | 16-alpine |
| Message Broker | RabbitMQ | 3.13-management |
| Cache | Redis | 7-alpine |
| WebSocket | Laravel Reverb | Built-in |
| Metrics | Prometheus | latest |
| Dashboards | Grafana | v12.4 |
| Testing | Pest PHP | 3.8.5 |
| API Docs | Scramble (OpenAPI) | dedoc/scramble |
| Container Runtime | Docker Compose | Multi-stage |

## Service Health

Both services expose a deep health check endpoint that reports database, cache, and message broker status.

```bash
# HR Service
curl http://localhost:8001/api/health
# {
#   "status": "ok",
#   "service": "hr-service",
#   "checks": { "database": {"status":"ok","driver":"pgsql"}, "rabbitmq": {"status":"configured"} }
# }

# Hub Service
curl http://localhost:8002/api/health
# {
#   "status": "ok",
#   "service": "hub-service",
#   "checks": { "database": {"status":"ok"}, "cache": {"status":"ok","store":"redis"}, "rabbitmq": {"status":"configured"} }
# }
```
