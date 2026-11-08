import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/login';

test.describe('Analytics & Intelligence Module', () => {

  test('Positive: Admin analytics view and tab navigation', async ({ page }) => {
    await loginAs(page, 'sales-marketing-admin@example.com', 'password');

    // Navigate into the CMS admin module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*admin.*/, { timeout: 15000 });

    // Navigate to Analytics
    await page.getByRole('link', { name: 'Analytics' }).click();

    // Verify page title
    await expect(page.locator('h1')).toContainText('System Analytics', { timeout: 15000 });

    // Default tab is Descriptive
    await expect(page.locator('button:has-text("Descriptive")').first()).toBeVisible({ timeout: 10000 });

    // Switch to Diagnostic tab
    await page.locator('button:has-text("Diagnostic Health Report")').click();
    await page.waitForTimeout(500);

    // Switch to Predictive tab
    await page.locator('button:has-text("Predictive 30-Day Forecast")').click();
    await page.waitForTimeout(500);
  });

  test('Positive: Manager analytics view and tab navigation', async ({ page }) => {
    await loginAs(page, 'sales-marketing-manager@example.com', 'password');

    // Navigate into the CMS manager module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*manager.*/, { timeout: 15000 });

    // Navigate to Analytics
    await page.getByRole('link', { name: 'Analytics' }).click();

    // Verify page title
    await expect(page.locator('h1')).toContainText('System Analytics', { timeout: 15000 });

    // Switch to Diagnostic tab
    await page.locator('button:has-text("Diagnostic Health Report")').click();
    await page.waitForTimeout(500);
  });

  test('Negative: Direct unauthenticated access shows Access Denied', async ({ page }) => {
    await page.goto('http://localhost:5173/cms/admin/analytics', { timeout: 30000, waitUntil: 'domcontentloaded' });

    // The router guard shows "Access Denied" toast and redirects to home after 1.5s
    await expect(page.locator('text=Access Denied')).toBeVisible({ timeout: 10000 });
  });

});
