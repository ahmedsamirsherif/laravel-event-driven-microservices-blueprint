# Seed Data

The platform includes a comprehensive seed script that exercises the complete employee lifecycle — create, read, update, delete — with realistic data for both USA and Germany.

## Usage

```bash
# Default: 1500 USA + 1500 DEU employees
bash seed-employees.sh

# Custom counts
bash seed-employees.sh 100 100

# Quick test
bash seed-employees.sh 10 10
```

## Five Lifecycle Phases

| Phase | Description |
|---|---|
| Phase 1 — Create | Generates employees with checklist coverage tiers: 0%, 33%, 67%, 100% field completion |
| Phase 2 — Read | Queries Hub endpoints to verify event propagation and projections |
| Phase 3 — Update | Updates 30% of employees with new salaries and field changes |
| Phase 4 — Delete | Deletes 10% of employees to test deletion event flow |
| Phase 5 — Verify | Counts final totals across both services and validates consistency |

## Data Generation

| Feature | Details |
|---|---|
| Parallel Execution | Up to 100 concurrent requests (`PARALLEL=100`) |
| Retry Logic | Up to 3 retries per request with exponential backoff |
| US Names | 50 first names × 50 last names pool |
| DE Names | 50 first names × 50 last names pool (German names) |
| Addresses | Pool of 50 realistic US addresses |
| Goals | Pool of 50 professional development goals |
| SSN Format | `XXX-XX-XXXX` (random but valid format) |
| Tax ID Format | `DEXXXXXXXXX` (German tax ID format) |
| Salary Range | $30,000 — $200,000 (random) |

## Checklist Coverage Tiers

Employees are created with varying field completeness to test the onboarding checklist at different coverage levels:

| Tier | Fields Filled | Coverage |
|---|---|---|
| Tier 1 | Name only (missing required fields) | ~0% |
| Tier 2 | Name + salary | ~33% |
| Tier 3 | Name + salary + partial country fields | ~67% |
| Tier 4 | All fields complete | 100% |
