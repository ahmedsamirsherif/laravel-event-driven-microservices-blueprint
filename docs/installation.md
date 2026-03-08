# Installation & Setup

One-command startup. The entire platform — 10 services, migrations, keys — starts with a single Docker Compose command.

## Prerequisites

| Tool | Minimum Version | Check Command |
|---|---|---|
| Docker | 20.10+ | `docker --version` |
| Docker Compose | v2.0+ | `docker compose version` |
| Git | 2.30+ | `git --version` |
| GNU Make | 4.0+ | `make --version` |

## Quick Start

**1. Clone the Repository**

```bash
git clone https://github.com/ahmedsamirsherif/laravel-event-driven-microservices-blueprint.git
cd laravel-event-driven-microservices-blueprint
```

**2. Start the Platform**

This single command builds all images, starts 10 containers, generates APP_KEYs, and runs database migrations:

```bash
docker compose up -d --build
# or
make up
```

**3. Wait for Health Checks**

```bash
docker compose ps
```

Expected output:
```
NAME            STATUS                    PORTS
grafana         running (healthy)         0.0.0.0:3001->3000/tcp
hr-postgres     running (healthy)         0.0.0.0:5433->5432/tcp
hr-service      running (healthy)         0.0.0.0:8001->80/tcp
hub-consumer    running (healthy)
hub-postgres    running (healthy)         0.0.0.0:5434->5432/tcp
hub-service     running (healthy)         0.0.0.0:8002->80/tcp
prometheus      running                   0.0.0.0:9090->9090/tcp
rabbitmq        running (healthy)         0.0.0.0:5672->5672/tcp, 0.0.0.0:15672->15672/tcp
redis           running (healthy)         6379/tcp
reverb          running (healthy)         0.0.0.0:8080->8080/tcp
```

**4. Verify Health Endpoints**

```bash
curl http://localhost:8001/api/health
curl http://localhost:8002/api/health
```

**5. Create Your First Employee**

```bash
curl -X POST http://localhost:8001/api/v1/employees \
  -H "Content-Type: application/json" \
  -d '{"name":"John","last_name":"Doe","salary":75000,"country":"USA","ssn":"123-45-6789","address":"123 Main St"}'

# Wait 2-3 seconds, then verify the Hub received it:
curl http://localhost:8002/api/v1/employees/USA
```

## All 10 Services

| Service | Port | Purpose |
|---|---|---|
| hr-service | 8001 | Employee CRUD API |
| hub-service | 8002 | Onboarding / UI APIs |
| hub-consumer | — | RabbitMQ event consumer |
| reverb | 8080 | WebSocket server |
| hr-postgres | 5433 | HR database |
| hub-postgres | 5434 | Hub database |
| redis | — | Cache store |
| rabbitmq | 5672 / 15672 | Message broker + management UI |
| prometheus | 9090 | Metrics scraping |
| grafana | 3001 | Monitoring dashboards |

## Makefile Commands

| Command | Description |
|---|---|
| `make up` | Build & start all services |
| `make down` | Stop all services |
| `make logs` | Follow container logs |
| `make status` | Show service status |
| `make test` | Run all tests (HR + Hub) |
| `make clean` | Stop, remove volumes & orphans |
| `make fresh` | Fresh migrations (drop + recreate) |
| `make verify` | Curl all API endpoints |

## Credentials

| Service | Username | Password |
|---|---|---|
| RabbitMQ Management | guest | guest |
| Grafana | admin | admin |
| HR PostgreSQL | hr_user | hr_password |
| Hub PostgreSQL | hub_user | hub_password |
