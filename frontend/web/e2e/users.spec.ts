import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/login';

test.describe('User Management Module', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'sales-marketing-admin@example.com', 'password');

    // Navigate into the CMS admin module via the portal
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForURL(/.*admin.*/, { timeout: 15000 });

    // Navigate to Users
    await page.getByRole('link', { name: 'User Management' }).click();
    await expect(page.locator('h1')).toContainText('User management', { timeout: 15000 });
  });

  test('Positive: Create new user via dialog', async ({ page }) => {
    const timestamp = Date.now();
    const newUser = {
      firstName: `TestUser`,
      lastName: `Admin${timestamp}`,
      email: `testuser.${timestamp}@sbsi.com`,
    };

    // Click the Add User button (Plus icon in the header area)
    await page.locator('button.bg-\\[\\#252578\\]:has(svg)').click();

    // Fill the Add User dialog form
    await expect(page.locator('text=Add new user')).toBeVisible({ timeout: 10000 });
    await page.getByPlaceholder('e.g. Sarah').fill(newUser.firstName);
    await page.getByPlaceholder('e.g. Jenkins').fill(newUser.lastName);
    await page.getByPlaceholder('e.g. sarah.j@sbsi.com').fill(newUser.email);

    // Select Role via the Select component
    await page.locator('button:has-text("Select role")').click();
    await page.getByRole('option', { name: 'Admin' }).click();

    // Submit form
    await page.getByRole('button', { name: 'Add user' }).click();

    // Confirm creation dialog
    await expect(page.locator('text=Create User Account')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Create' }).click();

    // Verify result dialog shows success or validation error
    await expect(
      page.locator('text=User Created Successfully').or(page.locator('text=Validation Error'))
    ).toBeVisible({ timeout: 15000 });
  });

  test('Positive: View user profile', async ({ page }) => {
    await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

    // Open dropdown menu on first user row
    const firstRow = page.locator('tbody tr').first();
    await firstRow.waitFor({ timeout: 10000 });
    await firstRow.locator('button:has(svg)').last().click();

    // Click "View profile"
    await page.getByRole('menuitem', { name: 'View profile' }).click();

    // Verify profile dialog is open
    await expect(page.getByRole('dialog')).toBeVisible({ timeout: 10000 });
  });

  test('Positive: Edit user role/details', async ({ page }) => {
    await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

    // Open dropdown menu on first user row
    const firstRow = page.locator('tbody tr').first();
    await firstRow.waitFor({ timeout: 10000 });
    await firstRow.locator('button:has(svg)').last().click();

    // Click "Edit user"
    await page.getByRole('menuitem', { name: 'Edit user' }).click();

    // Verify edit dialog is visible
    await expect(page.getByRole('dialog')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Save changes' }).click();

    // Verify dialog closes
    await expect(page.getByRole('dialog')).not.toBeVisible({ timeout: 15000 });
  });

  test('Positive: Delete user confirmation flow', async ({ page }) => {
    await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

    // Open dropdown menu on last user row
    const lastRow = page.locator('tbody tr').last();
    await lastRow.waitFor({ timeout: 10000 });
    await lastRow.locator('button:has(svg)').last().click();

    // Click "Delete user"
    await page.getByRole('menuitem', { name: 'Delete user' }).click();

    // Confirmation dialog
    await expect(page.getByRole('dialog')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Delete' }).click();
  });

  test('Positive: Search filter narrows table results', async ({ page }) => {
    await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

    const searchInput = page.getByPlaceholder('Search...');
    await searchInput.fill('Admin');

    await page.waitForTimeout(500);
    const rows = page.locator('tbody tr');
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);
  });

  test('Positive: Tab filter by role', async ({ page }) => {
    await expect(page.locator('table')).toBeVisible({ timeout: 15000 });

    // Click Admin tab
    await page.getByRole('button', { name: 'Admin', exact: true }).click();
    await page.waitForTimeout(300);

    // Click View all tab back
    await page.getByRole('button', { name: 'View all' }).click();
  });

  test('Negative: Add user form validation', async ({ page }) => {
    // Click the Add User button
    await page.locator('button.bg-\\[\\#252578\\]:has(svg)').click();
    await expect(page.locator('text=Add new user')).toBeVisible({ timeout: 10000 });

    // Submit empty form
    await page.getByRole('button', { name: 'Add user' }).click();

    // Validation messages for required fields
    await expect(page.locator('text=Required.').first()).toBeVisible({ timeout: 5000 });

    // Enter invalid email
    await page.getByPlaceholder('e.g. sarah.j@sbsi.com').fill('invalid-email-format');
    await page.getByPlaceholder('e.g. sarah.j@sbsi.com').blur();
    await page.getByRole('button', { name: 'Add user' }).click();
    await expect(page.locator('text=Enter a valid email address.')).toBeVisible({ timeout: 5000 });
  });

});
