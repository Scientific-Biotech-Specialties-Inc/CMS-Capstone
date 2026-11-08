import { Page, expect } from '@playwright/test';

/**
 * Logs in a user via the UI login form.
 *
 * @param page Playwright Page object
 * @param email User email
 * @param password User password (defaults to 'password')
 */
export async function loginAs(page: Page, email: string, password: string = 'password') {
  await page.goto('http://localhost:5173/');
  await page.getByRole('link', { name: 'Log in' }).click();
  await page.getByRole('textbox', { name: 'Email' }).fill(email);
  await page.getByRole('textbox', { name: 'Password' }).fill(password);
  await page.getByRole('button', { name: 'Sign In' }).click();

  // Wait for login redirection away from login page
  await page.waitForURL(/^(?!.*login).*$/, { timeout: 15000 });
}
