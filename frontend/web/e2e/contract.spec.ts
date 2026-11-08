import { test, expect } from '@playwright/test';

test.describe('Contract Module', () => {

    test('Positive: Create, Edit, and Delete Contract (CRUD)', async ({ page }) => {
        const timestamp = Date.now();
        const contract = {
            vendorName: `Testing Vendor ${timestamp}`,
            updatedVendorName: `Testing Vendor ${timestamp} Updated`,
            type: 'Service Agreement',
            itm: `ITM-${timestamp}`,
            description: 'Network Infrastructure',
            serialNumber: `SN-2026-${timestamp}`,
            sbu: `SBU-${timestamp}`,
            region: 'Visayas',
            startDate: '2026-06-14',
            endDate: '2032-12-14',
        };

        // 1. Login as Sales Marketing Manager
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-manager@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });
        await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });

        // 2. Navigate and Create Contract
        await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
        await page.waitForURL(/.*manager.*/, { timeout: 15000 });
        await page.getByRole('button', { name: 'Contracts' }).click();
        await page.getByRole('button').nth(4).click();
        await page.getByRole('textbox', { name: 'e.g. Globe Telecom' }).fill(contract.vendorName);
        await page.getByRole('combobox').first().selectOption(contract.type);
        await page.getByRole('textbox', { name: 'e.g. ITM-' }).fill(contract.itm);
        await page.getByRole('textbox', { name: 'e.g. Network Infrastructure' }).fill(contract.description);
        await page.getByRole('textbox', { name: 'e.g. SN-2024-' }).fill(contract.serialNumber);
        await page.getByRole('textbox', { name: 'e.g. SBU-' }).fill(contract.sbu);
        await page.getByRole('combobox').nth(1).selectOption(contract.region);
        await page.locator('input[type="date"]').first().fill(contract.startDate);
        await page.locator('input[type="date"]').nth(1).fill(contract.endDate);
        await page.getByRole('button', { name: 'Create Contract' }).click();

        // Confirm creation dialog
        await expect(page.locator('text=Create Contract').first()).toBeVisible({ timeout: 10000 });
        await page.getByRole('button', { name: 'Create' }).click();

        // 3. Edit Contract
        await page.getByRole('button', { name: 'Edit Contract' }).click();
        await page.getByRole('textbox', { name: 'e.g. Globe Telecom' }).fill(contract.updatedVendorName);
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.locator('h1')).toContainText(contract.updatedVendorName, { timeout: 15000 });

        // 4. Logout Manager
        await page.locator('div').filter({ hasText: /^Logout$/ }).first().click();
        await expect(page).toHaveURL('http://localhost:5173/');

        // 5. Login as Sales Marketing Admin to Delete
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-admin@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });
        await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });

        // 6. Delete Contract
        await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
        await page.waitForURL(/.*admin.*/, { timeout: 15000 });
        await page.getByRole('link', { name: 'Contracts' }).click();
        const contractRow = page.getByRole('row').filter({ hasText: contract.updatedVendorName });
        await contractRow.waitFor({ timeout: 15000 });
        await contractRow.hover();
        await contractRow.getByRole('button').click();
        await page.getByRole('menuitem', { name: 'Delete' }).click();
        await expect(page.getByText('Contract removed')).toBeVisible({ timeout: 15000 });

        // 7. Logout Admin
        await page.locator('div').filter({ hasText: /^Logout$/ }).first().click();
        await expect(page).toHaveURL('http://localhost:5173/');
    });

    test('Negative: Form validation checks', async ({ page }) => {
        // 1. Login as Sales Marketing Manager
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-manager@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });

        // 2. Navigate to Create Contract Page
        await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
        await page.waitForURL(/.*manager.*/, { timeout: 15000 });
        await page.getByRole('button', { name: 'Contracts' }).click();
        await page.getByRole('button').nth(4).click();

        // 3. Try to submit empty form
        await page.getByRole('button', { name: 'Create Contract' }).click();

        // 4. Verify field validations are visible
        await expect(page.locator('text=Business partner is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Category is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Item code is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Description is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Serial number is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=SBU number is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Region is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Start date is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=End date is required.')).toBeVisible({ timeout: 5000 });

        // 5. Test invalid date validation (End Date before Start Date)
        await page.locator('input[type="date"]').first().fill('2026-06-15');
        await page.locator('input[type="date"]').nth(1).fill('2026-06-14');
        await page.getByRole('button', { name: 'Create Contract' }).click();
        await expect(page.locator('text=End date must be after start date.')).toBeVisible({ timeout: 5000 });

        // 6. Logout
        await page.locator('div').filter({ hasText: /^Logout$/ }).first().click();
        await expect(page).toHaveURL('http://localhost:5173/');
    });
});