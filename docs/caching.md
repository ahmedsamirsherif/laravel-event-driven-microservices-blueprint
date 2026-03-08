# Caching Strategy

Redis 7-alpine with AOF persistence provides the caching layer. Cache invalidation is event-driven — triggered automatically when the `EventProcessingPipeline` processes employee events.

## Cache Keys

| Pattern | TTL | Purpose | Invalidation |
|---|---|---|---|
| `employees:{country}:page:{n}:per:{m}` | 1 hour | Paginated employee lists | Any employee event for the country |
| `checklist:{country}:{employeeId}` | 1 hour | Per-employee checklist | Employee event + eager rebuild |

## Eager Rebuild

Checklist caches use **eager rebuild** — after invalidation, the new value is immediately recalculated and cached. This means the next API request always hits a warm cache.

## Cache Flow

```
Employee Event
    └── EventProcessingPipeline
            ├── Invalidate employee cache (pages 1-100 for country)
            └── Invalidate + eagerly rebuild checklist cache

API Request
    ├── Cache Hit  → Redis (sub-millisecond)
    └── Cache Miss → PostgreSQL → store in Redis
```

## Redis Configuration

- **Persistence**: AOF (`appendonly yes --appendfsync everysec`)
- **Named volume**: `redis-data` — data survives container restarts
- **Laravel driver**: `redis` (default cache store in Hub Service)
- **Cache hit rate**: ~100% for normal API usage after events warm the cache
