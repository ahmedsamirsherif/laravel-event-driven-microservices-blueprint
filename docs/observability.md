# Observability

The platform provides complete observability through Prometheus metrics scraping and Grafana dashboards, covering application performance, business metrics, event pipeline health, and infrastructure status.

## Prometheus Metrics

| Metric | Type | Service |
|---|---|---|
| `app_http_requests_total` | Counter | Both |
| `app_http_request_duration_seconds` | Histogram | Both |
| `app_events_published_total` | Counter | HR |
| `app_events_processed_total` | Counter | Hub |
| `app_event_processing_errors_total` | Counter | Hub |
| `app_event_processing_duration_seconds` | Histogram | Hub |
| `app_cache_hits_total` | Counter | Hub |
| `app_cache_misses_total` | Counter | Hub |
| `app_employee_count_by_country` | Gauge | Hub |
| `app_checklist_completion_rate` | Gauge | Hub |

## Prometheus Scrape Configuration

```yaml
scrape_configs:
  - job_name: hr-service
    metrics_path: /api/metrics
    static_configs:
      - targets: ['hr-service:80']

  - job_name: hub-service
    metrics_path: /api/metrics
    static_configs:
      - targets: ['hub-service:80']

  - job_name: rabbitmq
    metrics_path: /metrics
    static_configs:
      - targets: ['rabbitmq:15692']
```

Access Prometheus at `http://localhost:9090`.

## Grafana Dashboards

Five auto-provisioned dashboards loaded from JSON files on first boot, no manual import needed.

Access at `http://localhost:3001` (admin / admin). See [grafana.md](grafana.md) for details on each dashboard.
