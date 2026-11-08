import { test, expect } from '@playwright/test';

test.describe('Login Module', () => {

    test('Positive: Sales Marketing Manager successful login and logout', async ({ page }) => {
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-manager@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();

        // Wait for successful login redirection
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });
        await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });

        // Logout
        await page.getByRole('button', { name: 'Logout' }).click();
        await expect(page).toHaveURL('http://localhost:5173/');
    });

    test('Positive: Sales Marketing Admin successful login and logout', async ({ page }) => {
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-admin@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();

        // Wait for successful login redirection
        await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });
        await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });

        // Logout
        await page.getByRole('button', { name: 'Logout' }).click();
        await expect(page).toHaveURL('http://localhost:5173/');
    });

    test('Negative: Login fails with invalid email', async ({ page }) => {
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('invalid-email@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();

        // Should NOT redirect away from login page
        await page.waitForTimeout(2000);
        await expect(page).toHaveURL(/.*login.*/);
    });

    test('Negative: Login fails with invalid password', async ({ page }) => {
        await page.goto('http://localhost:5173/');
        await page.getByRole('link', { name: 'Log in' }).click();
        await page.getByRole('textbox', { name: 'Email' }).fill('sales-marketing-manager@example.com');
        await page.getByRole('textbox', { name: 'Password' }).fill('wrong-password');
        await page.getByRole('button', { name: 'Sign In' }).click();

        // Should NOT redirect away from login page
        await page.waitForTimeout(2000);
        await expect(page).toHaveURL(/.*login.*/);
    });
});