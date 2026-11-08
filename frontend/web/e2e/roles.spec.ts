import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/login';

test.describe('Roles & Permissions Module', () => {

  test('Positive: Page loads and role cards are visible', async ({ page }) => {
    await loginAs(page, 'sales-marketing-admin@example.com', 'password');

    // Navigate into the CMS admin module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*admin.*/, { timeout: 15000 });

    // Navigate to Roles & Permissions
    await page.getByRole('link', { name: 'Roles' }).click();
    await expect(page.locator('h1')).toContainText('Roles & Permissions', { timeout: 15000 });

    // Verify role cards visible to a Sales & Marketing Admin (scoped to Manager and Employee roles only)
    await expect(page.locator('button:has-text("MANAGER")').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('button:has-text("EMPLOYEE")').first()).toBeVisible({ timeout: 10000 });
  });

  test('Positive: Select role card, toggle permission, and save changes', async ({ page }) => {
    await loginAs(page, 'sales-marketing-admin@example.com', 'password');

    // Navigate into the CMS admin module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*admin.*/, { timeout: 15000 });

    // Navigate to Roles & Permissions
    await page.getByRole('link', { name: 'Roles' }).click();
    await expect(page.locator('h1')).toContainText('Roles & Permissions', { timeout: 15000 });

    // Click Manager role card
    await page.locator('button:has-text("MANAGER")').first().click();

    // Verify permissions panel heading updates
    await expect(page.locator('text=Manager Role Permissions')).toBeVisible({ timeout: 10000 });

    // Toggle a permission using Select all / Deselect all
    const toggleAllBtn = page.locator('button:has-text("Deselect all"), button:has-text("Select all")').first();
    if (await toggleAllBtn.isVisible()) {
      await toggleAllBtn.click();
    }

    // Save changes
    const saveButton = page.getByRole('button', { name: 'Save Changes' });
    await saveButton.click();

    // Confirmation dialog
    await expect(page.locator('text=Confirm Changes')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Save' }).click();

    // Verify toast confirmation
    await expect(page.locator('text=Permissions saved')).toBeVisible({ timeout: 15000 });
  });

  test('Negative: Unauthenticated user access shows Access Denied', async ({ page }) => {
    await page.goto('http://localhost:5173/cms/admin/roles', { timeout: 30000, waitUntil: 'domcontentloaded' });

    // The router guard shows "Access Denied" toast and redirects to home after 1.5s
    await expect(page.locator('text=Access Denied')).toBeVisible({ timeout: 10000 });
  });

});
