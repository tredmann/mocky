# Mocky

A configurable mock REST API server. Define endpoints through a web UI, then point any HTTP client at them to receive the responses you configured — useful for testing, prototyping, and simulating third-party APIs.

## Features

- Create endpoints for any HTTP method (GET, POST, PUT, PATCH, DELETE)
- Unique slug per endpoint — share the URL with anyone
- **Conditional responses** — return different status codes and bodies based on request content (query params, headers, JSON body fields, or URL path segments)
- **Request logs** — every incoming request is logged with IP, headers, body, and which response was returned
- **Export / import** endpoints as JSON files
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

### Export an endpoint

```bash
php artisan endpoint:export {slug}
php artisan endpoint:export {slug} --output=my-endpoint.json
```

Exports the endpoint definition (including all conditional responses) to a JSON file. Defaults to `{slug}.json` in the current directory.

### Import an endpoint

```bash
php artisan endpoint:import {file}
php artisan endpoint:import {file} --user=admin@admin.com
php artisan endpoint:import {file} --user={uuid}
```

Imports an endpoint from a previously exported JSON file and assigns it to a user. The `--user` option accepts an email address or a UUID. If omitted, the first user in the database is used.

If the slug from the file already exists, a new UUID slug is generated automatically.

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
