// @ts-check
import { test, expect } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const FIXTURES = path.resolve(__dirname, '../fixtures');

/** Open the import panel on the dashboard. */
async function openImportPanel(page) {
    await page.goto('/dashboard');
    await page.locator('[wire\\:click="$set(\'showImport\', true)"]').click();
    await page.locator('input[type="file"]').waitFor();
}

/** Build a collection fixture buffer with a unique name to avoid slug collisions. */
function uniqueCollectionBuffer() {
    const base = JSON.parse(
        fs.readFileSync(path.join(FIXTURES, 'minimal-collection.json'), 'utf8'),
    );
    const suffix = Date.now();
    base.name = `E2E Test Collection ${suffix}`;
    base.slug = `e2e-test-collection-${suffix}`;
    return Buffer.from(JSON.stringify(base));
}

// ---------------------------------------------------------------------------
// Native JSON import
// ---------------------------------------------------------------------------

test.describe('Native JSON import', () => {
    test('imports a valid collection without a page-expired error', async ({ page }) => {
        // Collect any 419 responses — there should be none.
        const status419 = [];
        page.on('response', res => {
            if (res.status() === 419) status419.push(res.url());
        });

        await openImportPanel(page);

        // The format selector should default to "Native JSON".
        await expect(page.locator('select[wire\\:model\\.live="importType"]')).toHaveValue('native');

        // Upload a unique-slug collection fixture so repeated runs don't collide.
        const buf = uniqueCollectionBuffer();
        const name = JSON.parse(buf.toString()).name;

        await page.locator('input[type="file"]').setInputFiles({
            name: 'collection.json',
            mimeType: 'application/json',
            buffer: buf,
        });

        // Wait for the Livewire file upload round-trip to complete.
        await page.waitForResponse(res =>
            res.url().includes('/upload-file') && res.status() === 200,
        );

        // Click the Import button.
        await page.locator('button:has-text("Import")').last().click();

        // The import panel should close — meaning the import succeeded.
        await expect(page.locator('input[type="file"]')).toHaveCount(0, { timeout: 10_000 });

        // No 419 should have occurred.
        expect(status419).toHaveLength(0);

        // The new collection should appear in the dashboard table.
        await expect(page.locator(`text=${name}`)).toBeVisible();
    });

    test('shows a validation error for a JSON file missing the name field', async ({ page }) => {
        const status419 = [];
        page.on('response', res => {
            if (res.status() === 419) status419.push(res.url());
        });

        await openImportPanel(page);

        // Upload JSON that is missing the required "name" field.
        await page.locator('input[type="file"]').setInputFiles({
            name: 'no-name.json',
            mimeType: 'application/json',
            buffer: Buffer.from(JSON.stringify({ endpoints: [] })),
        });

        await page.waitForResponse(res =>
            res.url().includes('/upload-file') && res.status() === 200,
        );

        await page.locator('button:has-text("Import")').last().click();

        // The inline error message should appear — not a 419 page-expired alert.
        await expect(page.locator('text=Missing required field: name')).toBeVisible({ timeout: 10_000 });
        expect(status419).toHaveLength(0);
    });

    test('shows a validation error for a non-JSON file', async ({ page }) => {
        const status419 = [];
        page.on('response', res => {
            if (res.status() === 419) status419.push(res.url());
        });

        await openImportPanel(page);

        // Upload a plain-text file — the mimes:json rule should reject it.
        await page.locator('input[type="file"]').setInputFiles({
            name: 'not-json.txt',
            mimeType: 'text/plain',
            buffer: Buffer.from('hello world'),
        });

        // Livewire always accepts the raw upload; rejection happens at validate().
        await page.waitForResponse(res =>
            res.url().includes('/upload-file') && res.status() === 200,
        );

        await page.locator('button:has-text("Import")').last().click();

        // A validation error for importFile should be visible.
        await expect(
            page.locator('[data-flux-error], .text-red-500, [data-error]').first(),
        ).toBeVisible({ timeout: 10_000 });
        expect(status419).toHaveLength(0);
    });
});
