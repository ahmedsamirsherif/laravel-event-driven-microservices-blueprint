# Country Resolver Lifecycle

This page focuses on the exact runtime path that reaches the country-specific validation modules in the HR service. The key point is that the registry is **bound during boot** but only **discovered lazily** when request validation asks for it.

> **Registry vs resolver:** `CountryFieldsRegistry` is the container-facing registry used by the request layer. It delegates discovery to `CountryClassResolver`, which scans `Domain/Country/*` and instantiates matching classes.

## Request to Registry Trigger

```mermaid
sequenceDiagram
    autonumber
    participant Client
    participant Nginx
    participant Index as public/index.php
    participant Boot as bootstrap/app.php
    participant Laravel as Laravel Application
    participant Provider as AppServiceProvider
    participant Router
    participant Request as StoreEmployeeRequest
    participant Container as Service Container
    participant Registry as CountryFieldsRegistry
    participant Resolver as CountryClassResolver
    participant Modules as Domain/Country/*/*Fields
    participant Controller as EmployeeController@store

    Client->>Nginx: POST /api/v1/employees
    Nginx->>Index: Forward request
    Index->>Index: Load Composer autoload
    Index->>Boot: Bootstrap application
    Boot-->>Index: Application instance
    Index->>Laravel: Handle request

    Laravel->>Provider: Boot service providers
    Provider->>Container: Bind CountryFieldsRegistry singleton
    Note over Provider,Container: Binding happens during boot.<br/>Discovery has not happened yet.

    Laravel->>Router: Match API route
    Router-->>Laravel: EmployeeController@store
    Laravel->>Request: Resolve FormRequest
    Request->>Request: authorize()
    Request->>Request: rules()
    Request->>Container: app(CountryFieldsRegistry::class)

    alt First resolution in this request lifecycle
        Container->>Registry: discover()
        Registry->>Resolver: discoverAll("Fields", CountryFieldsInterface::class)
        Resolver->>Modules: Scan Domain/Country/*
        Modules-->>Resolver: USAFields, DEUFields, ...
        Resolver-->>Registry: Country modules map
        Registry-->>Container: Registry instance
    else Singleton already resolved
        Container-->>Request: Existing registry instance
    end

    Container-->>Request: CountryFieldsRegistry
    Request->>Registry: supportedCountries()
    Request->>Registry: supports(country)
    Request->>Registry: for(country)
    Registry-->>Request: CountryFieldsInterface implementation
    Request->>Request: Merge base rules + country rules
    Request-->>Laravel: Final validation rules

    alt Validation passes
        Laravel->>Controller: store()
    else Validation fails
        Laravel-->>Client: 422 validation response
    end
```

## What Actually Triggers It

1. The registry is registered in the container by `AppServiceProvider`.
2. The HTTP route resolves `StoreEmployeeRequest` before the controller action executes.
3. `StoreEmployeeRequest::rules()` calls `app(CountryFieldsRegistry::class)`.
4. That first container resolution runs `CountryFieldsRegistry::discover()`.
5. The registry uses `CountryClassResolver::discoverAll()` to build the country map.
6. The request then asks the registry for the selected country and merges its validation rules.

## Why This Matters

### Lazy Discovery

No scan runs unless a request path actually needs country-specific behavior.

### Zero Manual Registration

Adding a new country means adding the convention-matching class, not updating a switch or service map.

### Request-Time Validation

The selected country determines the extra rules only when request data is available.

## Related Pages

- [Countries](countries.md) covers the high-level auto-discovery model and how to add a new country.
- [Architecture](architecture.md) shows where the resolver sits in the overall service design.