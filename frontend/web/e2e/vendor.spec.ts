import { test, expect } from '@playwright/test';

test.describe('Vendor Module', () => {

    test('Positive: Create, Edit, and Delete Vendor (CRUD)', async ({ page }) => {
        const timestamp = Date.now();
        const vendor = {
            name: `Testing Company ${timestamp}`,
            updatedName: `Testing Company ${timestamp} Updated`,
            industry: 'healthcare',
            region: 'Luzon',
            contactPerson: 'Test Person',
            contactNumber: '09914159183',
            email: `test+${timestamp}@example.com`,
            address: 'Test City',
        };

        // 1. Login as Sales Marketing Administrator
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-admin@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });
        await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });

        // 2. Open Business & Suppliers
        await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
        await page.waitForURL(/.*admin.*/, { timeout: 15000 });
        await page.getByRole('link', { name: 'Business & Suppliers' }).click();

        // 3. Create Business Partner
        await page.locator('#add-partner-btn').click();
        await page.getByRole('textbox', { name: 'Organization name' }).fill(vendor.name);
        await page.getByRole('textbox', { name: 'e.g. Healthcare' }).fill(vendor.industry);
        await page.getByRole('combobox').first().selectOption(vendor.region);
        await page.getByRole('textbox', { name: 'Full name' }).fill(vendor.contactPerson);
        await page.getByRole('textbox', { name: '09xxxxxxxxx' }).fill(vendor.contactNumber);
        await page.getByText('Contact DetailsContact Person').click();
        await page.getByRole('textbox', { name: 'contact@company.com' }).fill(vendor.email);
        await page.getByRole('textbox', { name: 'Street, City' }).fill(vendor.address);
        await page.getByRole('button', { name: 'Add Partner' }).click();
        await expect(page.getByRole('button', { name: new RegExp(`Active ${vendor.name}`) })).toBeVisible({ timeout: 20000 });

        // 4. Edit Business Partner
        const partnerCard = page.getByRole('button', { name: new RegExp(`Active ${vendor.name}`) });
        await partnerCard.hover();
        await partnerCard.locator('[id^="reka-dropdown-menu-trigger"]').click();
        await page.getByRole('menuitem', { name: 'View details' }).click();
        await page.getByRole('button', { name: 'Edit' }).click();
        await page.getByRole('textbox', { name: 'Organization name' }).fill(vendor.updatedName);
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page.locator('h1')).not.toContainText('Edit Business Partner', { timeout: 15000 });
        await expect(page.locator('h1')).toContainText(vendor.updatedName, { timeout: 15000 });
        await page.getByRole('button', { name: 'Back to Partners' }).click();

        // 5. Delete Business Partner
        const updatedPartnerCard = page.getByRole('button', { name: new RegExp(`Active ${vendor.updatedName}`) });
        await expect(updatedPartnerCard).toBeVisible({ timeout: 15000 });
        await updatedPartnerCard.hover();
        await updatedPartnerCard.locator('[id^="reka-dropdown-menu-trigger"]').click();
        await page.getByRole('menuitem', { name: 'Delete' }).click();
        await page.getByRole('button', { name: 'Delete' }).click();

        // 6. Logout
        await page.locator('div').filter({ hasText: /^Logout$/ }).first().click();
        await expect(page).toHaveURL('http://localhost:5173/');
    });

    test('Negative: Vendor form validation checks', async ({ page }) => {
        // 1. Login as Sales Marketing Administrator
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-admin@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });

        // 2. Open Business & Suppliers
        await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
        await page.waitForURL(/.*admin.*/, { timeout: 15000 });
        await page.getByRole('link', { name: 'Business & Suppliers' }).click();

        // 3. Navigate to Add Partner Page
        await page.locator('#add-partner-btn').click();

        // 4. Click Add Partner on empty form to trigger validations
        await page.getByRole('button', { name: 'Add Partner' }).click();

        // 5. Check validation messages
        await expect(page.locator('text=Name is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Required.').first()).toBeVisible({ timeout: 5000 }); // Multiple fields have "Required." label

        // 6. Test invalid Email validation
        await page.getByRole('textbox', { name: 'contact@company.com' }).fill('invalid-email-format');
        await page.getByRole('button', { name: 'Add Partner' }).click();
        await expect(page.locator('text=Enter a valid email address.')).toBeVisible({ timeout: 5000 });

        // 7. Test invalid Phone number validation
        await page.getByRole('textbox', { name: '09xxxxxxxxx' }).fill('123');
        await page.getByRole('button', { name: 'Add Partner' }).click();
        await expect(page.locator('text=Phone number must be 7–11 digits.')).toBeVisible({ timeout: 5000 });

        // 8. Logout
        await page.locator('div').filter({ hasText: /^Logout$/ }).first().click();
        await expect(page).toHaveURL('http://localhost:5173/');
    });
});