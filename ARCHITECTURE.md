# FuelPHP Core Architecture

## Purpose

This repository is the FuelPHP 1.x framework core (`fuelphp/core`). It provides all framework classes: routing, request/response, input, ORM base, database query builder, caching, validation, events, and more. It is used as `fuel/core/` within the FuelPHP scaffold.

## Directory Structure

```
core/
├── classes/                        # All framework classes
│   ├── arr.php                     # Array utility (dot-notation access, pluck, flatten…)
│   ├── asset.php                   # Asset management (CSS, JS, image paths)
│   ├── controller.php              # Base controller class
│   ├── controller/template.php     # Template controller (before/after wrapping)
│   ├── cookie.php                  # Cookie read/write
│   ├── database/                   # Database abstraction layer
│   │   ├── query/builder/          # Fluent query builders (select, insert, update, delete)
│   │   ├── result.php              # Query result wrapper
│   │   └── schema.php              # Schema inspection/modification
│   ├── db.php                      # DB static facade
│   ├── event.php / event/instance.php  # Observer/event system
│   ├── form.php / form/instance.php    # HTML form generation
│   ├── html.php                    # HTML helper
│   ├── inflector.php               # String inflection (pluralize, camelize…)
│   ├── input.php                   # HTTP input (GET, POST, SERVER, COOKIE, headers)
│   ├── model.php                   # Base model class
│   ├── pagination.php              # Pagination helper
│   ├── presenter.php               # View presenter (ViewModel pattern)
│   ├── response.php                # HTTP response (status, headers, body, redirect)
│   ├── route.php                   # Route definition and matching
│   ├── sanitization.php            # Input sanitization
│   ├── session/                    # Session drivers (cookie, redis)
│   ├── validation.php              # Input validation
│   └── viewmodel.php               # ViewModel base
├── config/                         # Default configuration files
│   ├── config.php                  # Core config defaults
│   ├── db.php                      # Database defaults
│   ├── session.php                 # Session defaults
│   └── …                          # (one file per subsystem)
├── lang/en/                        # Default English language strings
├── tasks/                          # CLI task scripts (install, migrate, session)
├── tests/                          # PHPUnit test suite for core classes
├── views/errors/                   # Error page templates
├── base.php                        # Global helpers (call_fuel_func, value, etc.)
├── bootstrap.php                   # Framework bootstrap
└── bootstrap_phpunit.php           # Test bootstrap
```

## Key Design Decisions

- **Static facades with instance backing**: Classes like `Input`, `Response`, `DB` expose static methods that delegate to an underlying instance obtained via `forge()` or `instance()`. This allows both easy static access and testable instance injection.
- **Namespace `Fuel\Core`**: All core classes live in the `Fuel\Core` namespace. The FuelPHP autoloader maps `Fuel\Core\Foo` to `classes/foo.php` (lowercase).
- **`forge()` factory pattern**: Instead of `new`, the convention is `ClassName::forge($args)` which fires events and returns a typed instance.
- **Route compilation**: `Route::compile()` transforms path patterns (`:segment`, `:num`, `:any`, `:everything`, named captures) into regex. Case sensitivity is configurable.
- **`Arr` dot-notation**: `Arr::get($array, 'foo.bar.baz')` traverses nested arrays using `.` as the separator. `Arr::set()`, `Arr::delete()` follow the same convention.
- **Response status array**: `Response::$statuses` maps HTTP status codes to reason phrases inline in the class.

## Extension Points

- Override a core class: place a class of the same name under `fuel/app/classes/` — the autoloader checks app before core.
- Add a session driver: implement the session driver interface and add to the `session.driver` config.
- Add a cache handler: extend `Cache_Handler_Driver` and place under `classes/cache/handler/`.

## Dependency Flow

```
bootstrap.php
  → Autoloader setup (Fuel\Core namespace → classes/)
  → Config loading cascade (core → package → app → environment)
  → Request::forge() → Router → Route::parse()
  → Controller::forge() → action execution
  → Response::forge() → send()
```
