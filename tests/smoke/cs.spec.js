const { test, expect } = require('@playwright/test');

const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASSWORD || 'password';

async function login(page) {
  await page.goto('/wp-login.php');
  await page.getByLabel('Username or Email Address').fill(ADMIN_USER);
  await page.getByLabel('Password').fill(ADMIN_PASS);
  await page.getByRole('button', { name: 'Log In' }).click();
  await expect(page).toHaveURL(/wp-admin|\/ops\//);
}

test('Slate Ops CS + Monitor smoke load without runtime errors', async ({ page }) => {
  const errors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });

  await login(page);

  const csRoute = '/ops/cs';
  await page.goto(csRoute);

  await expect(page.getByText('Customer Service')).toBeVisible();
  await expect(page.getByRole('searchbox')).toBeVisible();
  await expect(page.getByRole('button', { name: /All Jobs/i })).toBeVisible();
  await expect(page.getByRole('button', { name: /By Tech/i })).toBeVisible();

  const appJsErrors = errors.filter((entry) => /app\.js|\/ops\/cs|slate-ops/i.test(entry));
  expect(appJsErrors, `Console errors detected: ${appJsErrors.join('\n')}`).toEqual([]);

  await page.goto('/slate-ops-monitor/');
  await expect(page).toHaveURL(/slate-ops-monitor/);
  await expect(page.locator('body')).toBeVisible();
});
