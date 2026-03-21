// @ts-check
import { test as setup } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const AUTH_FILE = path.resolve(__dirname, '../.auth/session.json');

const EMAIL = process.env.E2E_EMAIL ?? 'admin@admin.com';
const PASSWORD = process.env.E2E_PASSWORD ?? 'test123';

setup('authenticate', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
    await page.context().storageState({ path: AUTH_FILE });
});
