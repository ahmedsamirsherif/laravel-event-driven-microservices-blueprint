# Grafana Dashboards

Four auto-provisioned dashboards provide operational visibility into the platform. They are loaded from JSON files on first boot — no manual import needed.

Access at `http://localhost:3001` (admin / admin).

## 1. API Performance

Real-time view of HTTP request rates, latency percentiles, and status code distribution across both services.

| Panel | Description |
|---|---|
| Request Rate | Requests per second across services |
| P95 Latency | 95th percentile response time |
| Services UP | Count of healthy services |
| Status Codes | 2xx / 4xx / 5xx breakdown |

## 2. Event Pipeline Monitor

Tracks the event-driven pipeline — published vs processed events, processing latency, and event types.

| Panel | Description |
|---|---|
| Events Published | Total events sent by HR Service |
| Events Processed | Total events consumed by Hub |
| P95 Processing | 95th percentile processing time |
| Event Types | Created, Updated, Deleted breakdown |

## 3. Business Metrics

Business-level visibility into employee operations — creation rates, updates, deletions, country distribution.

## 4. Infrastructure Health

Infrastructure monitoring — service uptime, cache performance, RabbitMQ resource usage.

| Panel | Description |
|---|---|
| Service Status | UP/DOWN for HR, Hub, RabbitMQ |
| Cache Hit Rate | Redis cache efficiency gauge |
| Cache Hits vs Misses | Time-series cache performance |
| RabbitMQ Resources | Memory, connections, queues |

## Dashboard Files

Dashboard JSON files are in `monitoring/grafana/dashboards/` and provisioning config is in `monitoring/grafana/provisioning/`.
