import { test, expect, Page } from '@playwright/test';
import { loginAs } from './helpers/login';

const GATEWAY = 'http://localhost:5173';
const CONTRACT_API = `${GATEWAY}/api/contract`;
const MANAGER = 'sales-marketing-manager@example.com';
const ADMIN = 'sales-marketing-admin@example.com';

// Equipment Maintenance seeded workflow (id resolved at runtime): single
// sequential Manager step, zero live instances — safe to temporarily
// reshape for the parallel test and restore afterwards.
// PUT with body echoed on failure (backend validation messages matter).
async function expectOk(res: { ok(): boolean; status(): number; text(): Promise<string> }, what: string) {
  if (!res.ok()) {
    throw new Error(`${what} failed: HTTP ${res.status()} — ${await res.text()}`);
  }
}
const SPARE_CATEGORY = 'Equipment Maintenance';
const MANAGER_ROLE_ID = 132;
const ADMIN_ROLE_ID = 131;

function jsonHeaders(): Record<string, string> {
  return {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };
}

// API calls ride the browser context (not page.request): the session
// cookies flow automatically and nginx injects the Bearer header
// upstream. localStorage holds no usable token — never hand-roll auth.
function api(page: Page) {
  return page.context().request;
}

// Best-effort logout from any page, then we can log back in as someone
// else. Portal logout is a button; the CMS sidebar variant is a div.
async function ensureLoggedOut(page: Page) {
  await page.goto(`${GATEWAY}/`, { waitUntil: 'domcontentloaded' });
  const loginLink = page.getByRole('link', { name: 'Log in' });
  if (await loginLink.isVisible({ timeout: 8000 }).catch(() => false)) return;
  await page.getByRole('button', { name: 'Logout' }).click();
  await expect(loginLink).toBeVisible({ timeout: 15000 });
}

async function launchCms(page: Page, expectPath: RegExp) {
  await page.locator('.glass-card', { hasText: 'Contract Management' }).getByRole('button', { name: 'Launch System' }).click();
  await page.waitForURL(expectPath, { timeout: 15000 });
}

async function openTracker(page: Page) {
  await page.getByRole('button', { name: 'View Workflow Tracker' }).click();
  await expect(page.getByRole('dialog', { name: 'Approval Workflow Tracker' })).toBeVisible({ timeout: 10000 });
}

test.describe('Workflow Engine', () => {
  // Serial: tests share live accounts and one spare workflow — parallel
  // workers would thrash sessions and step on the restore logic.
  test.describe.configure({ mode: 'serial' });

  test('Builder refuses step changes on a workflow with live runs (409, no write)', async ({ page }) => {
    await loginAs(page, ADMIN);
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    await launchCms(page, /.*admin.*/);
    await page.getByRole('link', { name: 'Roles' }).click();
    await expect(page.locator('h1')).toContainText('Roles & Permissions', { timeout: 15000 });

    await page.getByRole('button', { name: 'Approval Workflows' }).click();
    const card = page.locator('div', { hasText: 'Equipment Lease' }).last();
    await expect(card).toBeVisible({ timeout: 10000 });
    await page.locator('div:has-text("Equipment Lease")').last().getByRole('button', { name: 'Edit' }).click();
    await expect(page.getByRole('dialog')).toContainText('Edit Workflow', { timeout: 10000 });

    await page.getByRole('button', { name: 'Add Step' }).click();
    await page.getByRole('button', { name: 'Save as Draft' }).click();

    // Backend refuses with 409 — surfaced as toast + inline error, nothing persists.
    await expect(page.locator('text=Could not save workflow')).toBeVisible({ timeout: 15000 });
    await expect(page.getByRole('dialog').getByText('no longer be changed')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Cancel' }).click();

    // Step count unchanged (Equipment Lease chain still has its 2 steps).
    await expect(page.locator('div:has-text("Equipment Lease")').last()).toContainText('2 step(s)', { timeout: 10000 });
  });

  test('Contract without an engine run shows the empty tracker (legacy fallback)', async ({ page }) => {
    await loginAs(page, MANAGER);
    await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });
    // CTR-4ZGCS7 predates the engine: it can never gain an instance.
    await page.goto(`${GATEWAY}/cms/manager/contracts/CTR-4ZGCS7`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h2')).toContainText('Lesch, Donnelly and Green', { timeout: 15000 });
    await openTracker(page);
    await expect(page.getByRole('dialog', { name: 'Approval Workflow Tracker' }))
      .toContainText('No approval workflow is set for this contract.', { timeout: 10000 });
  });

  test('Parallel rejection cancels the sibling task (short-circuit)', async ({ page }) => {
    test.setTimeout(300000);
    const stamp = Date.now();
    const vendor = `E2E Parallel ${stamp}`;
    let contractCode = '';
    let workflowId = 0;

    // --- Setup (API): reshape the spare category into a parallel chain.
    await loginAs(page, ADMIN);
    await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
    {
      const headers = jsonHeaders();
      const typesRes = await api(page).get(`${CONTRACT_API}/workflows/contract-types`, { headers });
      expect(typesRes.ok()).toBeTruthy();
      const types = (await typesRes.json()).data;
      const entry = types.find((t: any) => t.contract_type_name === SPARE_CATEGORY);
      workflowId = entry.active_workflow.id;
      const putRes = await api(page).put(`${CONTRACT_API}/workflows/${workflowId}`, {
        headers,
        data: {
          contract_type_id: entry.contract_type_id,
          name: entry.active_workflow.name,
          status: 'active',
          resubmit_mode: 'restart',
          steps: [{ order_index: 1, mode: 'parallel', roles: [
            { auth_role_id: MANAGER_ROLE_ID, role_name: 'Manager' },
            { auth_role_id: ADMIN_ROLE_ID, role_name: 'Admin' },
          ] }],
        },
      });
      await expectOk(putRes, 'reshape spare workflow to parallel');
    }

    try {
      // --- Create contract as manager (routes through the parallel step).
      // NOTE: still on the portal here, whose logout is a button (the
      // CMS sidebar variant is a div — used below once inside CMS).
      await page.getByRole('button', { name: 'Logout' }).click();
      await expect(page).toHaveURL(`${GATEWAY}/`);
      await loginAs(page, MANAGER);
      await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });
      // Created via API: identical store path, minus form-widget flakiness
      // (contract creation itself is covered by contract.spec.ts).
      {
        const headers = jsonHeaders();
        const createRes = await api(page).post(`${CONTRACT_API}/contracts`, {
          headers,
          data: {
            bp_name: vendor,
            category: SPARE_CATEGORY,
            item_code: `ITM-${stamp}`.slice(0, 12),
            description: 'Parallel e2e',
            serial_number: `SN-${stamp}`.slice(0, 12),
            sbu_number: 'SBU-1',
            region: 'Luzon',
            start_date: '2026-01-01',
            end_date: '2030-12-31',
            document_ids: [],
          },
        });
        await expectOk(createRes, 'create test contract');
        contractCode = (await createRes.json()).data.contract_code;
      }
      await page.goto(`${GATEWAY}/cms/manager/contracts/${contractCode}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('h2')).toContainText(vendor, { timeout: 20000 });

      // --- Decide via API (no task-decision UI exists): reject the Admin
      // task FIRST while the Manager sibling is still pending — the engine
      // must short-circuit it to Canceled (spec 5.4), not Rejected.
      {
        const headers = jsonHeaders();
        const progress = await (await api(page).get(
          `${CONTRACT_API}/contracts/${contractCode}/approval-progress`, { headers })).json();
        const tasks = progress.data.runs[0].groups[0].tasks;
        expect(tasks).toHaveLength(2);

        await page.locator('div').filter({ hasText: /^Logout$/ }).first().click();
        await expect(page).toHaveURL(`${GATEWAY}/`);
        await loginAs(page, ADMIN);
        await expect(page.locator('nav')).toContainText('Sales Marketing Administrator', { timeout: 15000 });
        const adminHeaders = jsonHeaders();
        const adminTask = tasks.find((t: any) => t.auth_role_id === ADMIN_ROLE_ID);
        const rejectRes = await api(page).post(
          `${CONTRACT_API}/contracts/${contractCode}/approval-tasks/${adminTask.id}/decision`,
          { headers: adminHeaders, data: { decision: 'rejected', comment: 'E2E parallel pricing objection' } });
        expect(rejectRes.ok()).toBeTruthy();

        // --- Assert the tracker: rejected + canceled sibling, comment visible.
        await page.goto(`${GATEWAY}/cms/admin/contracts/${contractCode}`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h2')).toContainText(vendor, { timeout: 15000 });
        await openTracker(page);
        const dialog = page.getByRole('dialog', { name: 'Approval Workflow Tracker' });
        await expect(dialog).toContainText('Rejected', { timeout: 10000 });
        await expect(dialog).toContainText('Canceled', { timeout: 10000 });
        await expect(dialog).toContainText('E2E parallel pricing objection', { timeout: 10000 });
      }
    } finally {
      // --- Cleanup as admin (contract delete + workflow restore need it).
      // Failure point unknown: normalize the session first.
      try {
        await ensureLoggedOut(page);
        await loginAs(page, ADMIN);
        const headers = jsonHeaders();
        if (contractCode) {
          const delRes = await api(page).delete(
            `${CONTRACT_API}/contracts/${contractCode}`, { headers });
          if (![200, 404].includes(delRes.status())) {
            throw new Error(`delete test contract failed: HTTP ${delRes.status()} — ${await delRes.text()}`);
          }
        }
        if (workflowId) {
          const typesRes = await api(page).get(`${CONTRACT_API}/workflows/contract-types`, { headers });
          const types = (await typesRes.json()).data;
          const entry = types.find((t: any) => t.contract_type_name === SPARE_CATEGORY);
          const restoreRes = await api(page).put(`${CONTRACT_API}/workflows/${workflowId}`, {
            headers,
            data: {
              contract_type_id: entry.contract_type_id,
              name: entry.active_workflow?.name || 'Test Approval',
              status: 'active',
              resubmit_mode: 'restart',
              steps: [{ order_index: 1, mode: 'sequential', roles: [
                { auth_role_id: MANAGER_ROLE_ID, role_name: 'Manager' },
              ] }],
            },
          });
          await expectOk(restoreRes, 'restore spare workflow');
        }
      } catch (e) {
        console.error('Workflow e2e cleanup failed — restore Equipment Maintenance manually:', e);
        throw e;
      }
    }
  });

  test('On-hold step renders the empty-role warning (mock F)', async ({ page }) => {
    await loginAs(page, MANAGER);
    await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });
    await page.goto(`${GATEWAY}/cms/manager/contracts/CTR-4ZGCS7?workflowMock=F`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h2')).toContainText('Lesch, Donnelly and Green', { timeout: 15000 });
    await openTracker(page);
    const dialog = page.getByRole('dialog', { name: 'Approval Workflow Tracker' });
    await expect(dialog.getByText('On hold', { exact: true }).first()).toBeVisible({ timeout: 10000 });
    await expect(dialog).toContainText('On hold, no active holder for this role', { timeout: 10000 });
  });

  test('Tracker renders renamed and deleted role badges (mocks C and E)', async ({ page }) => {
    await loginAs(page, MANAGER);
    await expect(page.locator('nav')).toContainText('Sales Marketing Manager', { timeout: 15000 });

    await page.goto(`${GATEWAY}/cms/manager/contracts/CTR-4ZGCS7?workflowMock=C`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h2')).toContainText('Lesch, Donnelly and Green', { timeout: 15000 });
    await openTracker(page);
    await expect(page.getByRole('dialog', { name: 'Approval Workflow Tracker' }))
      .toContainText('Role renamed from Regulatory', { timeout: 10000 });
    await page.keyboard.press('Escape');

    await page.goto(`${GATEWAY}/cms/manager/contracts/CTR-4ZGCS7?workflowMock=E`, { waitUntil: 'domcontentloaded' });
    await openTracker(page);
    const dialog = page.getByRole('dialog', { name: 'Approval Workflow Tracker' });
    await expect(dialog).toContainText('Role deleted', { timeout: 10000 });
    await expect(dialog).toContainText('Previous attempt #1', { timeout: 10000 });
  });
});
