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
- **Import** — click the upload icon on the dashboard to import a collection from a previously exported file

**From the CLI:**

```bash
# Export
php artisan collection:export {collection-slug}
php artisan collection:export {collection-slug} --output=my-collection.json

# Import
php artisan collection:import {file}
php artisan collection:import {file} --user=admin@admin.com
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

# Import a collection from a JSON file
php artisan collection:import {file}
php artisan collection:import {file} --user=admin@admin.com
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
