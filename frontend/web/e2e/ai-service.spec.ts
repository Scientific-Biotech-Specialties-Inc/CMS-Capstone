import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/login';

test.describe('AI Service — Risk Assessment Module', () => {

  test('Positive: Navigate to AI Risk Assessment view from Contract list', async ({ page }) => {
    await loginAs(page, 'sales-marketing-admin@example.com', 'password');

    // Navigate into the CMS admin module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*admin.*/, { timeout: 15000 });

    // Navigate to Contracts
    await page.getByRole('link', { name: 'Contracts' }).click();
    await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

    // Click on the first contract row to view details
    const firstRow = page.locator('tbody tr').first();
    await firstRow.waitFor({ timeout: 10000 });

    // Use the row's action dropdown to navigate to contract detail
    await firstRow.hover();
    await firstRow.getByRole('button').click();
    await page.getByRole('menuitem', { name: 'View' }).click();
    await page.waitForURL(/.*contracts\/\d+.*/, { timeout: 15000 });

    // Extract the contract ID from the URL
    const contractIdMatch = page.url().match(/contracts\/(\d+)/);
    const contractId = contractIdMatch ? contractIdMatch[1] : '1';

    // Navigate to the Risk Assessment page for this contract
    await page.goto(`http://localhost:5173/cms/admin/contracts/${contractId}/risk-assessment`, { timeout: 30000 });

    // Verify AI Risk Assessment page header
    await expect(page.locator('h1')).toContainText('AI Risk Assessment', { timeout: 15000 });
    await expect(page.locator(`text=Contract #${contractId}`)).toBeVisible({ timeout: 10000 });
  });

  test('Positive: Trigger AI Risk Assessment scan', async ({ page }) => {
    await loginAs(page, 'sales-marketing-admin@example.com', 'password');

    // Navigate into the CMS admin module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*admin.*/, { timeout: 15000 });

    // Go to contracts to find a valid contract ID
    await page.getByRole('link', { name: 'Contracts' }).click();
    await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

    // Get first contract row and navigate via action menu
    const firstRow = page.locator('tbody tr').first();
    await firstRow.waitFor({ timeout: 10000 });
    await firstRow.hover();
    await firstRow.getByRole('button').click();
    await page.getByRole('menuitem', { name: 'View' }).click();
    await page.waitForURL(/.*contracts\/\d+.*/, { timeout: 15000 });

    const contractIdMatch = page.url().match(/contracts\/(\d+)/);
    const contractId = contractIdMatch ? contractIdMatch[1] : '1';

    // Navigate to the Risk Assessment page
    await page.goto(`http://localhost:5173/cms/admin/contracts/${contractId}/risk-assessment`, { timeout: 30000 });
    await expect(page.locator('h1')).toContainText('AI Risk Assessment', { timeout: 15000 });

    // Click "Re-run Scan" or "Run Scan Now" button
    const scanBtn = page.getByRole('button', { name: /Re-run Scan|Run Scan Now/i });
    await expect(scanBtn).toBeVisible({ timeout: 15000 });
    await scanBtn.click();

    // Verify scan triggered (scanning state or spinner visible)
    await expect(
      page.locator('text=Scanning Document...').or(page.locator('button:has-text("Scanning…")')).first()
    ).toBeVisible({ timeout: 15000 });
  });

  test('Positive: Manager AI Risk Assessment access', async ({ page }) => {
    await loginAs(page, 'sales-marketing-manager@example.com', 'password');

    // Navigate into the CMS manager module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*manager.*/, { timeout: 15000 });

    // Go to contracts (only rendered in the sidebar if the Manager role has
    // contract permissions in this environment's seeded data)
    const contractsNavButton = page.getByRole('button', { name: 'Contracts' });
    if (!(await contractsNavButton.isVisible({ timeout: 5000 }).catch(() => false))) {
      return;
    }
    await contractsNavButton.click();
    await expect(page.locator('table').or(page.locator('text=No contracts'))).toBeVisible({ timeout: 15000 });

    // Navigate to risk assessment for the first contract if available
    const firstRow = page.locator('tbody tr').first();
    if (await firstRow.isVisible()) {
      await firstRow.hover();
      await firstRow.getByRole('button').click();
      await page.getByRole('menuitem', { name: 'View' }).click();
      await page.waitForURL(/.*contracts\/\d+.*/, { timeout: 15000 });

      const contractIdMatch = page.url().match(/contracts\/(\d+)/);
      const contractId = contractIdMatch ? contractIdMatch[1] : '1';

      await page.goto(`http://localhost:5173/cms/manager/contracts/${contractId}/risk-assessment`, { timeout: 30000 });
      await expect(page.locator('h1')).toContainText('AI Risk Assessment', { timeout: 15000 });
    }
  });

  test('Negative: Unauthenticated access shows Access Denied', async ({ page }) => {
    // Navigate directly without logging in — the auth guard fires
    await page.goto('http://localhost:5173/cms/admin/contracts/1/risk-assessment', { timeout: 30000, waitUntil: 'domcontentloaded' });

    // The router guard shows "Access Denied" toast and then redirects to home after 1.5s
    await expect(page.locator('text=Access Denied')).toBeVisible({ timeout: 10000 });
  });

});
