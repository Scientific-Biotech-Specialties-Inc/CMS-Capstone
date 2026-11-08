import { test, expect } from '@playwright/test';

test('test', async ({ page }) => {

    // Dynamic test data
    const timestamp = Date.now();
    const vendor = {
        name: `Testing Company ${ timestamp }`,
        updatedName: `Testing Company ${ timestamp } Updated`,
        industry: 'healthcare',
        region: 'Luzon',
        contactPerson: 'Test Person',
        contactNumber: '09914159183',
        email: `test+${ timestamp }@example.com`,
        address: 'Test City',
    };
    const contract = {
        vendorName: `Testing Vendor ${ timestamp }`,
        updatedVendorName: `Testing Vendor ${ timestamp } Updated`,
        type: 'Service Agreement',
        itm: `ITM-${ timestamp }`,
        description: 'Network Infrastructure',
        serialNumber: `SN-2026-${ timestamp }`,
        sbu: `SBU-${ timestamp }`,
        region: 'Visayas',
        startDate: '2026-06-14',
        endDate: '2032-12-14',
    };

//Login as Finance Manager
await page.goto('http://localhost:5173/');
await page.getByRole('link', { name: 'Log in' }).click();
await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-manager@example.com');
await page.getByRole('textbox', { name: 'Password' }).fill('password');
await page.getByRole('button', { name: 'Sign In' }).click();
await page.waitForURL(/^(?!.*login).*$/, { timeout: 10000 });

//Contract Module as Finance Manager
//Create Contract
await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 10000 });
await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
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

//Edit Contract
await page.getByRole('button', { name: 'Edit Contract' }).click();
await page.getByRole('textbox', { name: 'e.g. Globe Telecom' }).fill(contract.updatedVendorName);
await page.getByRole('button', { name: 'Save Changes' }).click();
await expect(page.locator('h1')).toContainText(contract.updatedVendorName);
await page.getByRole('button').nth(3).click();
await page.locator('div').filter({ hasText: /^Logout$/ }).nth(1).click();

//Login as Finance Admin
//Vendor Module as Finance Admin
await page.getByRole('link', { name: 'Log in' }).click();
await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-admin@example.com');
await page.getByRole('textbox', { name: 'Password' }).fill('password');
await page.getByRole('button', { name: 'Sign In' }).click();
await page.waitForURL(/^(?!.*login).*$/, { timeout: 10000 });
await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 10000 });
await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
await page.getByRole('link', { name: 'Business & Suppliers' }).click();

//Create Business Partner
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
await expect(page.getByRole('button', { name: new RegExp(`Active ${ vendor.name }`) })).toBeVisible({ timeout: 20000 });

//Edit Business Partner
const partnerCard = page.getByRole('button', { name: new RegExp(`Active ${ vendor.name }`) });
await partnerCard.hover();
await page.locator('[id^="reka-dropdown-menu-trigger"]').first().click();
await page.getByRole('menuitem', { name: 'View details' }).click();
await page.getByRole('button', { name: 'Edit' }).click();
await page.getByRole('textbox', { name: 'Organization name' }).fill(vendor.updatedName);
await page.getByRole('button', { name: 'Save Changes' }).click();
await expect(page.locator('h1')).not.toContainText('Edit Business Partner', { timeout: 10000 });
await expect(page.locator('h1')).toContainText(vendor.updatedName, { timeout: 10000 });
await page.getByRole('button', { name: 'Back to Partners' }).click();

//Delete Business Partner
const updatedPartnerCard = page.getByRole('button', { name: new RegExp(`Active ${ vendor.updatedName }`) });
await expect(updatedPartnerCard).toBeVisible({ timeout: 10000 });
await updatedPartnerCard.hover();
await page.locator('[id^="reka-dropdown-menu-trigger"]').first().click();
await page.getByRole('menuitem', { name: 'Delete' }).click();
await page.getByRole('button', { name: 'Delete' }).click();

//Go to Contract Module and Delete the Contract
await page.getByRole('link', { name: 'Contracts' }).click();
const contractRow = page.getByRole('row').filter({ hasText: contract.updatedVendorName });
await contractRow.waitFor({ timeout: 10000 });
await contractRow.hover();
await contractRow.getByRole('button').click();

await page.getByRole('menuitem', { name: 'Delete' }).click();
await expect(page.getByText('Contract removed')).toBeVisible({ timeout: 10000 });

//Logout (End of test)
await page.locator('div').filter({ hasText: /^Logout$/ }).nth(1).click();
});