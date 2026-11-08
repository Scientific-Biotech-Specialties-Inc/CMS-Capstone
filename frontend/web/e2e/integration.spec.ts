import { test, expect } from '@playwright/test';

test.describe('PRS & CMS Integration Module', () => {

    test('Positive: Create Activity, submit log, and link contract in CMS', async ({ page }) => {
        const timestamp = Date.now();
        const contractSerial = `SN-2026-${timestamp}`;
        const dynamicNotes = `test - integration to cms ${timestamp}`;

        // 1. Login to Portal
        await page.goto('http://localhost:5173/');
        await expect(page.locator('h1')).toContainText('25 Years of Innovating Diagnostics Solutions');
        await page.getByRole('link', { name: 'Log in' }).click();

        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-officer@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();

        // Wait for successful login redirection
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });

        // 2. Open PRS (Productivity Report System) Module
        await page.locator('.glass-card', { hasText: 'Productivity Report System' }).getByRole('button', { name: 'Launch System' }).click();
        await page.getByRole('link', { name: 'My Activities' }).click();

        // 3. Plan Activity
        await page.getByRole('button', { name: 'Plan Activity' }).click();
        await page.getByRole('textbox', { name: 'Start typing or pick an' }).click();
        await page.locator('div').filter({ hasText: /^PUP Quezon City$/ }).click();
        await page.getByRole('textbox', { name: 'Assigned contact person' }).fill('testing testing');
        await page.getByRole('combobox').first().selectOption('Closing');
        await page.getByRole('combobox').nth(1).selectOption('16:00 - 17:30');
        await page.getByRole('combobox').nth(2).selectOption('SBU 3');
        await page.getByRole('textbox', { name: 'Start typing or pick a product...' }).click();
        await page.getByText('ReagentX').click();
        await page.getByRole('combobox').nth(3).selectOption('Class B');
        await page.getByRole('textbox', { name: 'Write field objectives, items' }).fill(dynamicNotes);
        await page.getByRole('button', { name: 'Save to Itinerary' }).click();

        // 4. View Details of the newly created itinerary card & Submit Log
        const activityCard = page.locator('div').filter({ hasText: dynamicNotes });
        await activityCard.getByRole('button', { name: 'View Details' }).first().click();
        await page.getByRole('button', { name: 'Open in Submit Activity' }).click();

        // Upload verification document
        await page.locator('#multipleFilesInput').setInputFiles('Business Plan Example (1).pdf');
        await page.getByRole('button', { name: 'Submit Activity Log' }).click();
        await page.getByRole('button', { name: 'Yes, Submit Activity' }).click();
        await expect(page.getByRole('main')).toContainText('Submission Successful!', { timeout: 15000 });

        // 5. Navigate to CMS using "CRM / Contract Manager" link (opens in new tab)
        const page1Promise = page.waitForEvent('popup');
        await page.getByRole('button', { name: 'CRM / Contract Manager' }).click();
        const page1 = await page1Promise;

        // Wait for CMS Create Contract page to load
        await page1.waitForURL(/.*contracts\/create.*/, { timeout: 15000 });

        // 6. Complete contract fields (many are prefilled from PRS)
        await page1.getByRole('combobox').first().selectOption('Partnership Agreement');
        await page1.getByRole('textbox', { name: 'e.g. SN-2024-' }).fill(contractSerial);
        await page1.getByRole('combobox').nth(1).selectOption('Luzon');
        await page1.locator('input[type="date"]').first().fill('2026-06-16');
        await page1.locator('input[type="date"]').nth(1).fill('2032-12-16');

        // Upload contract document
        await page1.locator('input[type="file"]').setInputFiles('Business Plan Example (1).pdf');

        await page1.getByRole('button', { name: 'Create Contract' }).click();

        // Expect contract to be successfully created and details loaded
        await expect(page1.getByRole('heading', { name: 'PUP Quezon City' })).toBeVisible({ timeout: 15000 });

        // 7. Navigate to Activity Log and open the correct submitted row
        // NOTE: the activity log table does not show field notes as a column, so we filter
        // by the unique contractSerial which was linked via the CMS tab submission.
        // Fallback: filter by institution name and pick the most recently added row (last in table).
        await page.goto('http://localhost:5173/prs/app/activity-log');
        await page.waitForLoadState('networkidle', { timeout: 15000 });

        // Get the last row matching PUP Quezon City — most recent submission is at the bottom
        const allMatchingRows = page.getByRole('row').filter({ hasText: 'PUP Quezon City' });
        const rowCount = await allMatchingRows.count();
        const lastRow = allMatchingRows.nth(rowCount - 1);
        await lastRow.waitFor({ timeout: 15000 });
        await lastRow.getByRole('button').first().click();

        // 8. Wait for the Sales Log Details modal and assert the contract link is present
        // The modal CONTRACT STATUS section shows a link once a contract has been linked
        await expect(page.getByText('Sales Log Details')).toBeVisible({ timeout: 10000 });
        const contractLink = page.getByRole('link', { name: /Contract #\d+/ });
        await expect(contractLink).toBeVisible({ timeout: 15000 });

        const page2Promise = page.waitForEvent('popup');
        await contractLink.click();
        const page2 = await page2Promise;
        await expect(page2.getByRole('heading', { name: 'PUP Quezon City' })).toBeVisible({ timeout: 15000 });
    });

    test('Negative: Portal Activity Planning validations', async ({ page }) => {
        // 1. Login to Portal
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-officer@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });

        // 2. Open My Activities
        await page.locator('.glass-card', { hasText: 'Productivity Report System' }).getByRole('button', { name: 'Launch System' }).click();
        await page.getByRole('link', { name: 'My Activities' }).click();

        // 3. Plan Activity
        await page.getByRole('button', { name: 'Plan Activity' }).click();

        // 4. Try to submit empty form and check alert
        page.once('dialog', async dialog => {
            expect(dialog.message()).toContain('Please complete the institution, contact person, activity scope, schedule date, and field notes.');
            await dialog.dismiss();
        });
        await page.getByRole('button', { name: 'Save to Itinerary' }).click();

        // 5. Fill initial details but leave product empty for a Closing activity
        await page.getByRole('textbox', { name: 'Start typing or pick an' }).click();
        await page.locator('div').filter({ hasText: /^PUP Quezon City$/ }).click();
        await page.getByRole('textbox', { name: 'Assigned contact person' }).fill('testing testing');
        await page.getByRole('combobox').first().selectOption('Closing');
        await page.getByRole('textbox', { name: 'Write field objectives, items' }).fill('testing details');

        // Check product selection alert
        page.once('dialog', async dialog => {
            expect(dialog.message()).toContain('Please select a product showcase for this planned sales activity.');
            await dialog.dismiss();
        });
        await page.getByRole('button', { name: 'Save to Itinerary' }).click();

        // Cancel modal
        await page.getByRole('button', { name: 'Cancel' }).click();
    });

    test('Negative: Portal Activity Submission PDF requirement', async ({ page }) => {
        // 1. Login to Portal
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-officer@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });

        // 2. Go to My Activities
        await page.locator('.glass-card', { hasText: 'Productivity Report System' }).getByRole('button', { name: 'Launch System' }).click();
        await page.getByRole('link', { name: 'My Activities' }).click();

        // 3. Open Plan Activity Dialog and create a test activity
        const timestamp = Date.now();
        const dynamicNotes = `negative test activity ${timestamp}`;
        await page.getByRole('button', { name: 'Plan Activity' }).click();
        await page.getByRole('textbox', { name: 'Start typing or pick an' }).click();
        await page.locator('div').filter({ hasText: /^PUP Quezon City$/ }).click();
        await page.getByRole('textbox', { name: 'Assigned contact person' }).fill('testing testing');
        await page.getByRole('combobox').first().selectOption('Closing');
        await page.getByRole('textbox', { name: 'Start typing or pick a product...' }).click();
        await page.getByText('ReagentX').click();
        await page.getByRole('textbox', { name: 'Write field objectives, items' }).fill(dynamicNotes);
        await page.getByRole('button', { name: 'Save to Itinerary' }).click();

        // 4. Open in Submit Activity
        const activityCard = page.locator('div').filter({ hasText: dynamicNotes });
        await activityCard.getByRole('button', { name: 'View Details' }).first().click();
        await page.getByRole('button', { name: 'Open in Submit Activity' }).click();

        // 5. Try to submit without uploading attachment
        await page.getByRole('button', { name: 'Submit Activity Log' }).click();

        // Assert validation warning popup
        const alertOverlay = page.locator('.custom-alert-overlay.open');
        await expect(alertOverlay).toBeVisible();
        await expect(alertOverlay.locator('p')).toContainText('Please complete all required verification fields and mount at least one attachment.');
        await alertOverlay.locator('.btn-alert-ack').click();
    });

    test('Negative: CMS Contract Creation validation via Integration', async ({ page }) => {
        // 1. Login to Portal
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-officer@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });

        // 2. Open My Activities
        await page.locator('.glass-card', { hasText: 'Productivity Report System' }).getByRole('button', { name: 'Launch System' }).click();
        await page.getByRole('link', { name: 'My Activities' }).click();

        // 3. Plan and submit an activity log to enable the "CRM / Contract Manager" link
        const timestamp = Date.now();
        const dynamicNotes = `cms verification activity ${timestamp}`;
        await page.getByRole('button', { name: 'Plan Activity' }).click();
        await page.getByRole('textbox', { name: 'Start typing or pick an' }).click();
        await page.locator('div').filter({ hasText: /^PUP Quezon City$/ }).click();
        await page.getByRole('textbox', { name: 'Assigned contact person' }).fill('testing testing');
        await page.getByRole('combobox').first().selectOption('Closing');
        await page.getByRole('textbox', { name: 'Start typing or pick a product...' }).click();
        await page.getByText('ReagentX').click();
        await page.getByRole('textbox', { name: 'Write field objectives, items' }).fill(dynamicNotes);
        await page.getByRole('button', { name: 'Save to Itinerary' }).click();

        const activityCard = page.locator('div').filter({ hasText: dynamicNotes });
        await activityCard.getByRole('button', { name: 'View Details' }).first().click();
        await page.getByRole('button', { name: 'Open in Submit Activity' }).click();

        await page.locator('#multipleFilesInput').setInputFiles('Business Plan Example (1).pdf');
        await page.getByRole('button', { name: 'Submit Activity Log' }).click();
        await page.getByRole('button', { name: 'Yes, Submit Activity' }).click();
        await expect(page.getByRole('main')).toContainText('Submission Successful!', { timeout: 15000 });

        // 4. Open CMS Create Contract in a new tab
        const page1Promise = page.waitForEvent('popup');
        await page.getByRole('button', { name: 'CRM / Contract Manager' }).click();
        const page1 = await page1Promise;
        await page1.waitForURL(/.*contracts\/create.*/, { timeout: 15000 });

        // 5. Cleared SBU validation check
        await page1.getByRole('textbox', { name: 'e.g. SBU-' }).clear();
        await page1.getByRole('button', { name: 'Create Contract' }).click();
        await expect(page1.locator('text=SBU number is required.')).toBeVisible({ timeout: 5000 });

        // Restore SBU and test invalid date validation
        await page1.getByRole('textbox', { name: 'e.g. SBU-' }).fill('SBU 3');
        await page1.locator('input[type="date"]').first().fill('2026-06-16');
        await page1.locator('input[type="date"]').nth(1).fill('2026-06-15');
        await page1.getByRole('button', { name: 'Create Contract' }).click();
        await expect(page1.locator('text=End date must be after start date.')).toBeVisible({ timeout: 5000 });

        // Close the tab
        await page1.close();
    });
});