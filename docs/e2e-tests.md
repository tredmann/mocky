# End-to-end tests

Playwright is used for browser-level testing against a running instance of the application. The suite lives in `tests/e2e/`.

## Prerequisites

- Node.js 18+
- Playwright's Chromium browser (installed once via `npx playwright install chromium`)
- A running application server (local dev or Docker)

## Running the suite

```bash
make test-e2e
```

This is equivalent to:

```bash
npx playwright test --project=chromium
```

The `setup` project runs first: it logs in once and saves the authenticated session to `tests/e2e/.auth/session.json`. All subsequent tests reuse that session — no repeated logins.

## Configuration

### Application URL

The suite defaults to `http://localhost:8000` (the `artisan serve` address). Override it with the `APP_URL` environment variable:

```bash
APP_URL=http://localhost:8090 make test-e2e   # Docker default port
```

### Test credentials

The setup step logs in as `admin@admin.com` / `test123` by default. Override with:

| Variable | Default | Description |
|----------|---------|-------------|
| `E2E_EMAIL` | `admin@admin.com` | Login email |
| `E2E_PASSWORD` | `test123` | Login password |

Example:

```bash
E2E_EMAIL=user@example.com E2E_PASSWORD=secret make test-e2e
```

The credentials must belong to a user that already exists in the database. For a fresh local setup, create one via `php artisan tinker`:

```php
App\Models\User::factory()->create(['email' => 'admin@admin.com', 'password' => bcrypt('test123')]);
```

### Playwright config

Top-level settings are in `playwright.config.js` at the project root:

| Option | Value | Notes |
|--------|-------|-------|
| `testDir` | `./tests/e2e` | Where specs are discovered |
| `timeout` | 30 s | Per-test wall-clock limit |
| `fullyParallel` | `false` | Tests run sequentially (single worker) |
| `reporter` | `list` | Plain list output; change to `html` for a visual report |

## File layout

```
tests/e2e/
├── .auth/
│   └── session.json        # gitignored — written by the setup step at runtime
├── setup/
│   └── auth.setup.js       # Logs in once, saves storageState
└── import.spec.js          # Import feature specs

tests/fixtures/
└── minimal-collection.json # Valid native-JSON collection used by the happy-path test

playwright.config.js        # Playwright configuration
```

## Test descriptions

### `import.spec.js` — Native JSON import

All three tests verify that the import flow never produces an HTTP 419 ("page expired") response, which was the bug being guarded against (a `TypeError` in `importCollection` caused by passing a raw array where a `CollectionData` object was expected — Livewire converts unhandled `TypeError`s to 419 in production).

| Test | What it checks |
|------|---------------|
| **imports a valid collection without a page-expired error** | Uploads a well-formed collection fixture, clicks Import, asserts the panel closes and the collection appears in the dashboard table. Fails if any 419 is observed. |
| **shows a validation error for a JSON file missing the name field** | Uploads `{"endpoints":[]}` (no `name`), clicks Import, asserts the inline `"Missing required field: name"` message is displayed. Fails if a 419 is observed instead. |
| **shows a validation error for a non-JSON file** | Uploads a `.txt` file, clicks Import, asserts a Livewire/Flux validation error is displayed. Fails if a 419 is observed instead. |

The happy-path test generates a unique collection name per run (timestamp suffix) so repeated runs do not collide on the slug uniqueness constraint.

## Adding new tests

1. Create a new `*.spec.js` file in `tests/e2e/`.
2. Import `{ test, expect }` from `@playwright/test`.
3. Start each test with `await page.goto('/dashboard')` — authentication is already handled by the shared `storageState`.
4. Add fixture files to `tests/fixtures/` as needed.

## CI notes

- The `tests/e2e/.auth/` directory is gitignored; CI must run the `setup` project (or the full `make test-e2e` command which includes it) before the specs.
- Set `APP_URL`, `E2E_EMAIL`, and `E2E_PASSWORD` as CI environment variables or secrets.
- To install the browser in CI: `npx playwright install --with-deps chromium`.
