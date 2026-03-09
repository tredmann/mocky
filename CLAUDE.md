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

This is a **configurable mock API server** built with Laravel 12, Livewire 4, Flux (UI components), and Pest.

### Core concept

Users define **endpoints** with a unique slug. Any HTTP client can hit `/mock/{slug}` and receive the configured response. The mock controller evaluates conditional responses in priority order before falling back to the default.

### Request flow

```
ANY /mock/{endpoint} → MockController::handle()
    → check is_active, method match
    → evaluate ConditionalResponse::matches() in priority order
    → log to EndpointLog
    → return response
```

Route model binding on `Endpoint` uses `slug` as the key (`getRouteKeyName()`). The route also accepts extra path segments via `/mock/{endpoint}/{path?}` with a wildcard, captured as `$path` and split into an array for path-segment conditions.

### Conditional response matching

Each `ConditionalResponse` has one condition: a **source** (`body`, `query`, `header`, `path`), a **field**, an **operator** (`equals`, `not_equals`, `contains`), and a **value**. Matching logic lives in `ConditionalResponse::matches(Request $request, array $pathSegments)`.

- `body` — uses `data_get()` with dot notation on the JSON body
- `query` — query parameter by name
- `header` — header by name (case-insensitive)
- `path` — segment index (0-based) from the extra URL segments after the slug

### Models

`User` uses UUIDs as primary key (`HasUuids` trait). All related foreign keys (`user_id`, etc.) are `foreignUuid`. `ProfileValidationRules` type-hints `int|string|null` for user IDs to accommodate UUIDs.

### Services

- `EndpointExportService` — serialises an endpoint + its conditional responses to a JSON streamed download
- `EndpointImportService` — creates an endpoint + conditional responses from the exported JSON array; regenerates the slug if it already exists
- `CurlCommandBuilder` — builds a representative curl command for an endpoint's default response or a conditional response. For `not_equals` conditions the example value is `"other"` (not the condition value) so the command actually triggers the condition.
- `OpenApiImportService` — imports an OpenAPI 3.x spec (JSON or YAML); groups operations sharing the same base path + method into one endpoint, turning path-parameter variants (e.g. `GET /pets/{petId}`) into `path` conditional responses on the base endpoint.
- `PostmanImportService` — imports a Postman Collection v2.1 JSON; groups requests with the same base path + method into one endpoint; numeric trailing path segments (e.g. `/users/1`) become `path` conditional responses; `:param`-style segments behave like OpenAPI template params.

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
| `INBOX_IMPORT_USER` | *(first user)* | Email or UUID of the user to assign auto-imports to (fallback) |

**How it works:**

1. The job lists all `.json` files in the configured inbox path via `InboxImportService::listInboxFiles()`.
2. For each file, it computes the MD5 hash of the contents.
3. If the hash already exists in `file_inbox_logs`, the file is skipped (already processed globally).
4. Otherwise it attempts to import via `CollectionImportService` and logs the result as `imported` or `failed`.
5. Files always remain in the inbox — the database is the sole source of truth for what has been processed.

**Auto-import user resolution (scheduled job):**

1. First user with `inbox_auto_import = true` on the `users` table (set via toggle on the Inbox page)
2. `INBOX_IMPORT_USER` env var (email or UUID)
3. First user in the database

**Manual import:** Users can click Import on any file in the Inbox page (`/inbox`). This calls `InboxImportService::processFile($path, $user, force: true)`, bypassing the global MD5 dedup so a user can import a file that was already auto-imported for someone else.

**Constraints:** Max file size is 5 MB. Files must be valid collection export JSON with a `name` field. The schedule uses `withoutOverlapping()` to prevent concurrent runs.

**Key `InboxImportService` methods:**
- `listInboxFiles(): Collection<int, string>` — returns disk-relative paths of all `.json` files
- `processFile(string $filePath, User $user, bool $force = false): ?FileInboxLog` — imports a single file; returns log record or null if unreadable
- `processInbox(): int` — scheduled entry point; resolves user and processes all unprocessed files

**UI:** The Inbox page (`/inbox`) in the sidebar lists all files from the disk with their status (`pending`, `imported`, `failed`), an Import button per file, and an auto-import toggle that saves the `inbox_auto_import` preference for the logged-in user instantly.

### Artisan commands

- `inbox:process` — processes collection JSON files from the inbox folder (also runs on schedule every minute)
- `endpoint:export {slug} {--output=}` — exports an endpoint to a JSON file (defaults to `{slug}.json`)
- `endpoint:import {file} {--user=}` — imports from a JSON file; `--user` accepts email or UUID; defaults to the first user
- `openapi:import {file} {--user=}` — imports an OpenAPI spec (`.json`, `.yaml`, `.yml`); max 5 MB
- `postman:import {file} {--user=}` — imports a Postman Collection JSON; max 5 MB

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
