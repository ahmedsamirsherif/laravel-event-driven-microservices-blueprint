# Country Modules

The platform uses an **auto-discovering convention-based architecture**. Adding a new country requires zero changes to existing code — just create files in the right directories.

## Supported Countries

### 🇺🇸 USA
- SSN + Address required
- 2 extra fields, 3 checklist steps (Basic Info, Compensation, Identity)
- SSN format: `^\d{3}-\d{2}-\d{4}$` — masked as `***-**-XXXX` in responses

### 🇩🇪 DEU (Germany)
- Tax ID + Goal required
- 2 extra fields, 3 checklist steps (Personal Information, Salary, Tax & Goals)
- Tax ID format: `^DE\d{9}$`
- Extra navigation step: **Documentation** (unique to DEU)

## Auto-Discovery

`CountryClassResolver` scans `Domain/Country/*/` directories and builds the class name by convention:

```
App\Domain\Country\{ISO3}\{ISO3}{Suffix}

Examples:
  App\Domain\Country\USA\USAFields    (HR Service)
  App\Domain\Country\USA\USAModule    (Hub Service)
  App\Domain\Country\DEU\DEUFields    (HR Service)
  App\Domain\Country\DEU\DEUModule    (Hub Service)
```

It validates each class implements the correct interface via `ReflectionClass`. The `Contracts` and `Shared` directories are skipped.

## Interfaces

**HR Service** — `CountryFieldsInterface`:
- `country(): CountryCode`
- `storeRules(): array`
- `storeMessages(): array`
- `resourceFields(): array`

**Hub Service** — `CountryModuleInterface`:
- `country(): CountryCode`
- `navigationSteps(): array`
- `tableColumns(): array`
- `dashboardWidgets(): array`
- `schemaFields(): array`
- `validationRules(): array`
- `requiredFields(): array`
- `checklistSteps(): array`

## Adding a New Country (e.g., France — FRA)

1. Add `case FRA = 'FRA'` to `CountryCode` enum (both services)
2. Create `app/Domain/Country/FRA/FRAFields.php` implementing `CountryFieldsInterface` (HR)
3. Create `app/Domain/Country/FRA/FRAModule.php` implementing `CountryModuleInterface` (Hub)
4. Update `contracts/employee-event.schema.json` if new required fields are added
5. Add tests

No existing code needs modification — the resolver auto-discovers the new classes.
