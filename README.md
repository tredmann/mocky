# Mocky

A configurable mock REST API server. Define endpoints through a web UI, then point any HTTP client at them to receive the responses you configured — useful for testing, prototyping, and simulating third-party APIs.

![mocky screenshot](/public/screenshot.png "Mocky Dashboard")


## Features

- **Collections** — group endpoints under a shared URL prefix
- Create endpoints for any HTTP method (GET, POST, PUT, PATCH, DELETE)
- Unique slug per endpoint — share the URL with anyone
- **Conditional responses** — return different status codes and bodies based on request content (query params, headers, JSON body fields, or URL path segments)
- **Request logs** — every incoming request is logged with IP, headers, body, and which response was returned
- **Export / import** — download and restore entire collections (with all endpoints) or individual endpoints as JSON
- **OpenAPI import** — import an OpenAPI 3.x spec (`.json`, `.yaml`, `.yml`) as a collection; path-parameter variants are automatically grouped as `path` conditional responses
- **Postman import** — import a Postman Collection v2.1 JSON; requests sharing the same base path and method are grouped into one endpoint with path-based conditionals
- **Inbox** — drop collection JSON files into a watched folder; view all files on the Inbox page, import them manually with one click, or enable auto-import per user account; works with any Laravel filesystem disk (local, S3, etc.)
- **cURL helper** — generates a ready-to-run curl command for each response, pre-filled with the condition values
- Docker-ready with FrankenPHP

## Getting started

### Local development

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # seeds admin@admin.com / admin

npm run dev
composer dev                 # starts server, queue, and logs
```

### Docker

```bash
docker compose up
```

The app is available at `http://localhost:8090`.

The container runs migrations and caches config/routes/views automatically on startup. The SQLite database and storage are persisted in named volumes.

## Collections

Endpoints are organised into **collections**. Each collection gets its own UUID-based URL prefix (`/mock/{collection-slug}/…`), so you can group related endpoints together.

### Collection export / import

You can export an entire collection — including all its endpoints and their conditional responses — as a single JSON file. This is useful for backing up your setup, migrating to a new instance, or sharing with teammates.

**From the UI:**

- **Export** — click the download icon on the collection detail page to download the JSON file
- **Import** — click the upload icon on the dashboard and choose a format:
  - **Native JSON** — a previously exported Mocky collection file
  - **OpenAPI (JSON / YAML)** — an OpenAPI 3.x spec file (`.json`, `.yaml`, `.yml`)
  - **Postman Collection** — a Postman Collection v2.1 JSON file

**From the CLI:**

```bash
# Export
php artisan collection:export {collection-slug}
php artisan collection:export {collection-slug} --output=my-collection.json

# Import (native)
php artisan collection:import {file}
php artisan collection:import {file} --user=admin@admin.com

# Import from OpenAPI spec
php artisan openapi:import {file}
php artisan openapi:import {file} --user=admin@admin.com

# Import from Postman Collection
php artisan postman:import {file}
php artisan postman:import {file} --user=admin@admin.com
```

The exported format looks like this:

```json
{
  "name": "My API",
  "description": "...",
  "endpoints": [
    {
      "name": "Get user",
      "slug": "get-user",
      "method": "GET",
      "status_code": 200,
      "content_type": "application/json",
      "response_body": "{\"id\": 1}",
      "conditional_responses": [...]
    }
  ]
}
```

### OpenAPI / Postman import grouping

Both importers group requests by **base path + HTTP method**, so a typical REST resource is turned into a small number of endpoints rather than one per operation:

| Operations | Result |
|---|---|
| `GET /users` + `GET /users/{id}` | One `GET users` endpoint — list is the default, single-item is a `path[0] not_equals ""` conditional |
| `POST /users` | One `POST users` endpoint |
| `DELETE /users/{id}` | One `DELETE users` endpoint (the `{id}` variant is promoted to the default) |

Path segments are treated as variables when they are: an OpenAPI template param (`{id}`), a Postman variable (`:id` or `{{id}}`), or a bare numeric value (`1`). Concrete numeric segments (e.g. `/users/1`) produce an `equals "1"` condition; template params produce a `not_equals ""` condition (matches any value).

### Inbox

Instead of importing through the UI or CLI, you can drop collection JSON files into a watched folder and manage them from the **Inbox** page (`/inbox`) in the sidebar.

**Setup:**

Add these to your `.env` (all optional — defaults work out of the box):

```env
INBOX_DISK=local           # Laravel filesystem disk (local, s3, etc.)
INBOX_PATH=inbox           # Directory on the disk to scan
INBOX_IMPORT_USER=         # Email or UUID of the user to own auto-imports (see below)
```

Make sure the Laravel scheduler is running:

```bash
php artisan schedule:work   # development
# or add to crontab for production:
# * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

**How it works:**

1. Place one or more collection `.json` files in the inbox folder (e.g. `storage/app/private/inbox/`)
2. Visit `/inbox` in the sidebar — all files in the folder are listed with their import status (`pending`, `imported`, or `failed`)
3. Click **Import** next to any file to import it immediately into your account
4. Enable **Auto-import for my account** toggle on the Inbox page — the scheduled job (runs every minute) will automatically import new files for you

**Auto-import user resolution (scheduled job):**

1. Any user with "Auto-import for my account" enabled (set via the toggle on `/inbox`)
2. `INBOX_IMPORT_USER` env var (email or UUID)
3. First user in the database

Files always remain in the inbox — the database is the sole source of truth for what has been processed. A file with the same MD5 hash is never auto-imported twice. Manual imports via the button always proceed. Max file size is 5 MB.

You can also trigger the scheduled job manually:

```bash
php artisan inbox:process
```

## How it works

Each endpoint gets a unique slug and a configured HTTP method. When a request arrives at `/mock/{slug}`, the controller:

1. Checks the endpoint is active and the method matches
2. Evaluates **conditional responses** in priority order — the first matching condition wins
3. Falls back to the **default response** if nothing matches
4. Logs the request regardless of outcome

### Conditional responses

A condition is made up of four parts:

| Part | Options |
|------|---------|
| Source | `query`, `header`, `body`, `path` |
| Field | param name, header name, JSON field (dot notation), or path segment index |
| Operator | `equals`, `not_equals`, `contains` |
| Value | the value to match against |

**Examples:**

- Return 404 when the `id` body field equals `0`
- Return 401 when the `Authorization` header does not contain `Bearer`
- Return a different payload when `?version=2` is in the query string
- Match `/mock/my-api/users` by setting source `path`, field `0`, value `users`

## Artisan commands

### Collections

```bash
# Export a collection with all endpoints
php artisan collection:export {collection-slug}
php artisan collection:export {collection-slug} --output=my-collection.json

# Import a native Mocky collection
php artisan collection:import {file}
php artisan collection:import {file} --user=admin@admin.com

# Import from an OpenAPI spec (.json, .yaml, .yml)
php artisan openapi:import {file}
php artisan openapi:import {file} --user=admin@admin.com

# Import from a Postman Collection JSON
php artisan postman:import {file}
php artisan postman:import {file} --user=admin@admin.com
```

### Inbox

```bash
# Process inbox files manually (also runs automatically every minute)
php artisan inbox:process
```

### Endpoints

```bash
# Export a single endpoint
php artisan endpoint:export {collection-slug} {endpoint-slug}
php artisan endpoint:export {collection-slug} {endpoint-slug} --output=my-endpoint.json

# Import a single endpoint into a collection
php artisan endpoint:import {file}
php artisan endpoint:import {file} --user=admin@admin.com --collection={collection-slug}
```

The `--user` option accepts an email or UUID. If omitted, the first user in the database is used. Duplicate slugs are automatically resolved by appending a numeric suffix.

## Running tests

```bash
php artisan test
php artisan test --coverage
php artisan test tests/Feature/MockControllerTest.php
php artisan test --filter "matches condition on query parameter"
```

```bash
./vendor/bin/pint           # fix code style
./vendor/bin/phpstan analyse --memory-limit=512M
```
