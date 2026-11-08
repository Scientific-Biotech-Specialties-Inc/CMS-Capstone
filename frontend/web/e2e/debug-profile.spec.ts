import { test, expect } from '@playwright/test';

const LOGIN_URL = 'http://localhost:5173/';
const EMAIL = 'sales-marketing-officer@example.com';
const PASSWORD = 'password';

async function loginAndGoToDashboard(page: any) {
    await page.goto(LOGIN_URL);
    await page.getByRole('link', { name: 'Log in' }).click();
    await page.getByRole('textbox', { name: 'Email' }).fill(EMAIL);
    await page.getByRole('textbox', { name: 'Password' }).fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In' }).click();
    await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });

    // Click into the Contract Management System module
    await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
    await page.waitForLoadState('networkidle', { timeout: 15000 });
}

async function openMyProfile(page: any) {
    await page.locator('header').locator('.ring-2').waitFor({ timeout: 15000 });
    await page.locator('header').locator('.ring-2').click();
    await page.getByRole('button', { name: 'My Profile' }).click();
}

async function logout(page: any) {
    await page.locator('header').locator('.ring-2').waitFor({ timeout: 10000 });
    await page.locator('header').locator('.ring-2').click();
    await page.getByRole('button', { name: 'Logout' }).click();
}

test.describe('Profile Module', () => {

    test('Positive: Update personal details and logout', async ({ page }) => {
        await loginAndGoToDashboard(page);
        await openMyProfile(page);

        await page.getByRole('textbox', { name: 'e.g. Shadrack' }).fill('Sales');
        await page.getByRole('textbox', { name: 'e.g. Castro' }).fill('Employee');
        await page.getByRole('textbox', { name: 'e.g. Miguel' }).fill('Testing');
        await page.getByRole('textbox', { name: 'you@sbsi.com' }).fill('sales-marketing-officer@gmail.com');
        await page.getByRole('textbox', { name: '+63 2 8xxx xxxx' }).fill('09914159183');

        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Your personal details have')).toBeVisible({ timeout: 10000 });

        await logout(page);
    });

    test('Negative: Required fields validation', async ({ page }) => {
        await loginAndGoToDashboard(page);
        await openMyProfile(page);

        await page.getByRole('textbox', { name: 'e.g. Shadrack' }).fill('');
        await page.getByRole('textbox', { name: 'e.g. Castro' }).fill('');
        await page.getByRole('button', { name: 'Save changes' }).click();

        await expect(page.locator('text=First name is required.')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('text=Last name is required.')).toBeVisible({ timeout: 5000 });

        await logout(page);
    });

    test('Negative: Invalid email format validation', async ({ page }) => {
        await loginAndGoToDashboard(page);
        await openMyProfile(page);

        await page.getByRole('textbox', { name: 'you@sbsi.com' }).fill('not-a-valid-email');
        await page.getByRole('button', { name: 'Save changes' }).click();

        await expect(page.locator('text=Enter a valid email address.')).toBeVisible({ timeout: 5000 });

        await logout(page);
    });

    test('Negative: Invalid phone number validation', async ({ page }) => {
        await loginAndGoToDashboard(page);
        await openMyProfile(page);

        await page.getByRole('textbox', { name: '+63 2 8xxx xxxx' }).fill('123');
        await page.getByRole('button', { name: 'Save changes' }).click();

        await expect(page.locator('text=Phone number must be 7–11 digits.')).toBeVisible({ timeout: 5000 });

        await logout(page);
    });

});