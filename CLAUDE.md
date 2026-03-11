# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start all dev processes (server, queue, logs, vite)
composer dev

# Run all tests
php artisan test

# Run a single test file
php artisan test tests/Feature/MockEndpointTest.php

# Run a single test by name
php artisan test --filter "matches condition on json body field"

# Fix code style
./vendor/bin/pint

# Check code style without fixing
./vendor/bin/pint --test

# Static analysis
./vendor/bin/phpstan analyse --memory-limit=512M
```

Tests use an in-memory SQLite database — no setup required.

## Architecture

This is a **configurable mock API server** (REST and SOAP) built with Laravel 12, Livewire 4, Flux (UI components), and Pest.

### Core concept

Users define **endpoints** with a unique slug and a type (`rest` or `soap`). REST endpoints are served at `/mock/{collection}/{slug}`, SOAP endpoints at `/soap/{collection}/{slug}`. The pipeline evaluates conditional responses in priority order before falling back to the default.

### Request flow

```
ANY  /mock/{collection}/{endpoint}/{path?} → MockController::handle()
    → EndpointResolver::resolve(..., type='rest')
    → check is_active, method match
    → ConditionalMatcher: evaluate conditionals in priority order
    → MockRequestLogger::log()
    → return response

POST /soap/{collection}/{endpoint} → SoapController::handle()
    → validate Content-Type contains 'xml' (else 415 SOAP Fault)
    → MockRequestPipeline::handleSoap()
    → EndpointResolver::resolve(..., type='soap')
    → ConditionalMatcher: evaluate conditionals in priority order
    → MockRequestLogger::log()
    → return response (SOAP Fault on errors)
```

The `/mock/` route only resolves `type='rest'` endpoints; the `/soap/` route only resolves `type='soap'` endpoints — they are fully isolated. Both routes are outside the auth middleware group and excluded from CSRF verification and the `RedirectIfNoUsers` middleware.

The `/mock/` route accepts extra path segments via `/{path?}` wildcard, captured as `$path` and split into an array for path-segment conditions.

### Conditional response matching

Each `ConditionalResponse` has one condition: a **source** (`body`, `query`, `header`, `path`, `soap_action`, `soap_body`), a **field**, an **operator** (`equals`, `not_equals`, `contains`), and a **value**. Matching logic lives in `ConditionalMatcher::evaluate()`, injecting `SoapBodyParser` and `SoapActionExtractor`.

- `body` — uses `data_get()` with dot notation on the JSON body
- `query` — query parameter by name
- `header` — header by name (case-insensitive)
- `path` — segment index (0-based) from the extra URL segments after the slug
- `soap_action` — SOAP action string; extracted from `SOAPAction` header (SOAP 1.1) or `action=` param in Content-Type (SOAP 1.2) by `SoapActionExtractor`
- `soap_body` — dot-notation path into the SOAP Body element (e.g. `GetUser.userId`), parsed namespace-agnostically by `SoapBodyParser` using `localName`

### Models

`User` uses UUIDs as primary key (`HasUuids` trait). All related foreign keys (`user_id`, etc.) are `foreignUuid`. `ProfileValidationRules` type-hints `int|string|null` for user IDs to accommodate UUIDs.

`Endpoint` has a `type` column (`string`, default `'rest'`) cast to the `EndpointType` enum (`EndpointType::Rest` / `EndpointType::Soap`). SOAP endpoints expose a `soap_url` attribute and an `isSoap()` helper. The `EndpointFactory` defaults `type` to `'rest'`.

### Services

- `EndpointExportService` — serialises an endpoint + its conditional responses to a JSON streamed download
- `EndpointImportService` — creates an endpoint + conditional responses from the exported JSON array; regenerates the slug if it already exists
- `CurlCommandBuilder` — builds a representative curl command for an endpoint's default response or a conditional response. For `not_equals` conditions the example value is `"other"` (not the condition value) so the command actually triggers the condition. For SOAP endpoints it builds `curl` with a SOAP envelope body and `Content-Type: text/xml`.
- `OpenApiImportService` — imports an OpenAPI 3.x spec (JSON or YAML); groups operations sharing the same base path + method into one endpoint, turning path-parameter variants (e.g. `GET /pets/{petId}`) into `path` conditional responses on the base endpoint.
- `PostmanImportService` — imports a Postman Collection v2.1 JSON; groups requests with the same base path + method into one endpoint; numeric trailing path segments (e.g. `/users/1`) become `path` conditional responses; `:param`-style segments behave like OpenAPI template params.
- `SoapBodyParser` — extracts a value from a SOAP XML body using dot notation (e.g. `GetUser.userId`); namespace-agnostic via `localName`; returns `null` on any failure.
- `SoapActionExtractor` — extracts the SOAP action string from a request; checks `SOAPAction` header first (SOAP 1.1, strips quotes), then `action=` param in Content-Type (SOAP 1.2); returns `null` if absent.
- `WsdlImportService` — parses a WSDL 1.1 document; creates one `EndpointData` (type `soap`) per SOAP binding; maps each operation to a `soap_action` (or `soap_body`) conditional response with a skeleton response envelope; default response is a generic SOAP Fault.

### Import grouping logic

Both OpenAPI and Postman importers share the same path-splitting strategy:

1. Strip template variables (`{id}`, `{{base_url}}`, `:param`) from the path.
2. If the trailing segment looks like a variable (template param or numeric), pop it; the remaining path becomes the `base_slug`.
3. Operations/requests are grouped by `(base_slug, method)`.
4. Within a group, the item with no path variable is the default endpoint; items with a path variable become `path` conditional responses:
   - **Concrete value** (e.g. `1`) → `path[0] equals "1"`
   - **Template param** (e.g. `{id}`, `:id`) → `path[0] not_equals ""`

### Inbox

A scheduled job (`inbox:process`) runs every minute, scanning a configurable filesystem disk for collection JSON files to import automatically. Users can also import files manually from the UI.

**Configuration** (`config/inbox.php`, all overridable via `.env`):

| Env var | Default | Description |
|---------|---------|-------------|
| `INBOX_DISK` | `local` | Laravel filesystem disk name (local, s3, etc.) |
| `INBOX_PATH` | `inbox` | Directory path on the disk to scan for files |

**How it works:**

1. The job lists all `.json` files in the configured inbox path via `InboxImportService::listInboxFiles()`.
2. It fetches all users with `inbox_auto_import = true`. If none exist, it logs a warning and stops.
3. For each auto-import user and each file, it computes the MD5 hash and checks if that user has already processed this file (per-user dedup via `file_inbox_logs`).
4. If not yet processed for that user, it imports via `CollectionImportService` and logs the result as `imported` or `failed`.
5. Files always remain in the inbox — the database is the sole source of truth for what has been processed.

**Auto-import users (scheduled job):**

Only users with `inbox_auto_import = true` on the `users` table receive auto-imported files. Enable this via the toggle on the Inbox page. If no user has it enabled, the scheduler does nothing. Multiple users can have it enabled and each gets their own independent import of each file.

**Manual import:** Users can click Import on any file in the Inbox page (`/inbox`). This calls `InboxImportService::processFile($path, $user, force: true)`, bypassing the per-user MD5 dedup so a user can re-import a file they already processed.

**Constraints:** Max file size is 5 MB. Files must be valid collection export JSON with a `name` field. The schedule uses `withoutOverlapping()` to prevent concurrent runs.

**Key `InboxImportService` methods:**
- `listInboxFiles(): Collection<int, string>` — returns disk-relative paths of all `.json` files
- `processFile(string $filePath, User $user, bool $force = false): ?FileInboxLog` — imports a single file; returns log record or null if unreadable
- `processInbox(): int` — scheduled entry point; resolves user and processes all unprocessed files

**UI:** The Inbox page (`/inbox`) in the sidebar lists all files from the disk with their status (`pending`, `imported`, `failed`), an Import button per file, and an auto-import toggle that saves the `inbox_auto_import` preference for the logged-in user instantly.

### Artisan commands

- `inbox:process` — processes collection JSON files from the inbox folder (also runs on schedule every minute)
- `endpoint:export {slug} {--output=}` — exports an endpoint to a JSON file (defaults to `{slug}.json`)
- `endpoint:import {file} {--user=}` — imports from a JSON file; `--user` (email or UUID) is **required**
- `openapi:import {file} {--user=}` — imports an OpenAPI spec (`.json`, `.yaml`, `.yml`); max 5 MB; `--user` is **required**
- `postman:import {file} {--user=}` — imports a Postman Collection JSON; max 5 MB; `--user` is **required**
- `wsdl:import {file} {--user=}` — imports a WSDL 1.1 file as a SOAP collection; max 5 MB; `--user` is **required**

### UI structure

Livewire single-file components live in `resources/views/pages/`. The layout (`layouts::app`) is applied automatically via Livewire's `component_layout` config — **do not wrap page components in `<x-layouts::app>`**.

Page flow: Dashboard → Detail (`endpoints.show`) → Edit (`endpoints.edit`). Logs accessible from the detail page. Inbox (`/inbox`) accessible from the sidebar.

### Code style

Pint uses the Laravel preset with `fully_qualified_strict_types` enabled — FQCNs in type hints are automatically converted to `use` imports on `pint` runs.

Eloquent relationships must include generic PHPDoc for Larastan, e.g.:
```php
/** @return HasMany<ConditionalResponse, $this> */
public function conditionalResponses(): HasMany
```
