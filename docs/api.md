# API Reference

## HR Service — `localhost:8001`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/employees` | List employees (paginated) |
| `POST` | `/api/v1/employees` | Create employee |
| `GET` | `/api/v1/employees/{id}` | Get employee |
| `PUT` | `/api/v1/employees/{id}` | Update employee |
| `DELETE` | `/api/v1/employees/{id}` | Delete employee |
| `GET` | `/api/v1/countries` | Supported country codes & labels |
| `GET` | `/api/health` | Health check |
| `GET` | `/api/metrics` | Prometheus metrics |

OpenAPI docs: `http://localhost:8001/docs/api`

### Country-Specific Validation

**USA employees require:**
- `name`, `last_name`, `salary`, `country`
- `ssn` — regex `^\d{3}-\d{2}-\d{4}$`
- `address` — string

**DEU (Germany) employees require:**
- `name`, `last_name`, `salary`, `country`
- `tax_id` — regex `^DE\d{9}$`
- `goal` — string

## Hub Service — `localhost:8002`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/employees/{country}` | List employees by country |
| `GET` | `/api/v1/employees/{country}/{id}` | Get employee by country |
| `GET` | `/api/v1/checklist/{country}` | Onboarding checklist |
| `GET` | `/api/v1/steps/{country}` | Navigation steps |
| `GET` | `/api/v1/schema/{country}` | Server-driven UI schema |
| `GET` | `/api/health` | Deep health check |
| `GET` | `/api/metrics` | Prometheus metrics |

OpenAPI docs: `http://localhost:8002/docs/api`

## Error Handling

All errors return a consistent envelope:

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Employee not found.",
    "details": {}
  }
}
```

Common codes: `NOT_FOUND` (404), `VALIDATION_ERROR` (422), `INTERNAL_SERVER_ERROR` (500).

## Rate Limits

| Service | Limit |
|---|---|
| HR Service | 60 req/min |
| Hub Service | 120 req/min |
