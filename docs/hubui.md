# Hub Service Blade UI

The Hub Service ships a fully interactive server-driven SPA at `http://localhost:8002/{country}/{step}`. All navigation steps, table columns, form schemas, and dashboard widgets are served by the API — the Blade views contain zero hardcoded country logic.

## USA Dashboard

The dashboard lists employees by name with real-time checklist completion progress. Clicking any row opens a per-employee breakdown modal showing green checks for completed fields and amber warnings for missing fields with server-provided validation messages.

**Dashboard widgets:**
- Total Employees
- Average Salary
- Completion Rate

## Live Events Panel

The **▶ Simulate Event** button POSTs a random employee to the HR API, triggering the full pipeline:

```
HR API → RabbitMQ → Hub Consumer → Redis cache invalidation → WebSocket broadcast
```

The Live Events panel updates in real time via Laravel Reverb.

## Germany (DEU) — Extra Navigation Step

The DEU country module exposes a third navigation step (**Documentation**) absent from USA. The dashboard replaces the Completion Rate widget with a **Goal Tracking** list showing each employee's career goal.

### Documentation Tab (DEU only)

The Documentation tab tracks five compliance document types per employee. This step is entirely server-driven via `DEUModule::navigationSteps()` — it is invisible for USA without any conditional code in the views.

| Field Key | Label |
|---|---|
| `doc_work_permit` | Work Permit |
| `doc_tax_card` | Tax Card |
| `doc_health_insurance` | Health Ins. |
| `doc_social_security` | Social Sec. |
| `doc_employment_contract` | Contract |

## WebSocket Test Page

Real-time WebSocket connectivity can be tested at `http://localhost:8002/websocket-test.html` — shows connection status and incoming events.
