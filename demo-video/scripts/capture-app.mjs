import { createRequire } from 'node:module';
import { copyFileSync, mkdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require(resolve(process.cwd(), '../frontend/web/node_modules/playwright'));

const BASE = 'http://localhost:5173';
const OUT_DIR = resolve(process.cwd(), 'public/captures');
const CLIP_DIR = resolve(process.cwd(), 'public/clips');
mkdirSync(OUT_DIR, { recursive: true });
mkdirSync(CLIP_DIR, { recursive: true });

const manifest = [];
const clips = [];

async function waitForStableRender(page) {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
  await page.evaluate(() => document.fonts?.ready).catch(() => {});

  const start = Date.now();
  while (Date.now() - start < 20000) {
    const skeletons = await page.locator('.animate-pulse').count().catch(() => 0);
    if (skeletons === 0) break;
    await page.waitForTimeout(450);
  }
  await page.waitForTimeout(1200);
}

async function loginRole(browser, { label, emails, password, portal, recordClipName }) {
  for (const email of emails) {
    const ctxOpts = { viewport: { width: 1920, height: 1080 } };
    if (recordClipName) {
      ctxOpts.recordVideo = { dir: CLIP_DIR, size: { width: 1920, height: 1080 } };
    }
    const context = await browser.newContext(ctxOpts);
    const page = await context.newPage();
    page.setDefaultTimeout(75000);
    const createdAt = Date.now();

    try {
      await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 75000 });
      await waitForStableRender(page);

      if (label === 'admin' && email === emails[0]) {
        await page.screenshot({ path: resolve(OUT_DIR, 'auth-login.png') });
        manifest.push({ file: 'auth-login.png', url: page.url(), ok: true, note: 'Login gateway' });
      }

      await page.fill('#email', email);
      await page.fill('#password', password);
      await Promise.all([
        page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 45000 }).catch(() => {}),
        page.click('button[type="submit"]'),
      ]);
      await waitForStableRender(page);

      if (label === 'admin' && email === emails[0]) {
        await page.screenshot({ path: resolve(OUT_DIR, 'auth-home.png') });
        manifest.push({ file: 'auth-home.png', url: page.url(), ok: true, note: 'Auth portal home' });
      }

      await page.goto(`${BASE}/cms/auth/callback?state=${encodeURIComponent(`/cms/${portal}/dashboard`)}`, {
        waitUntil: 'domcontentloaded',
        timeout: 75000,
      });
      await page.waitForURL((u) => u.pathname.includes(`/cms/${portal}`), { timeout: 45000 }).catch(() => {});
      await waitForStableRender(page);

      if (page.url().includes(`/cms/${portal}`)) {
        console.log(`[login ok] ${label} -> ${email} (${page.url()})`);
        return { context, page, email, createdAt };
      }
      await context.close();
    } catch (err) {
      console.warn(`[login retry] ${label} ${email}: ${err.message}`);
      await context.close();
    }
  }
  throw new Error(`Could not log in as ${label}`);
}

async function captureRoute(page, file, path, postAction) {
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded', timeout: 75000 });
      await waitForStableRender(page);
      if (postAction) await postAction(page);
      const skeletonsLeft = await page.locator('.animate-pulse').count().catch(() => 0);
      const bodyLen = await page.evaluate(() => document.body.innerText.trim().length).catch(() => 0);
      await page.screenshot({ path: resolve(OUT_DIR, file) });
      manifest.push({ file, url: page.url(), ok: bodyLen > 20, skeletonsLeft, attempt });
      console.log(`[capture ok] ${file} -> ${page.url()} (chars=${bodyLen}, skeletons=${skeletonsLeft})`);
      return true;
    } catch (err) {
      console.warn(`[capture retry ${attempt}/3] ${file}: ${err.message}`);
      if (attempt === 3) {
        manifest.push({ file, url: `${BASE}${path}`, ok: false, error: err.message });
      }
    }
  }
  return false;
}

async function clickFirstContractRow(page) {
  const rows = page.locator('tbody tr.cursor-pointer');
  const count = await rows.count().catch(() => 0);
  if (count === 0) return null;
  await rows.first().click();
  await page.waitForURL(/\/contracts\/\d+|\/contracts\/[^/]+$/, { timeout: 25000 }).catch(() => {});
  await waitForStableRender(page);
  return page.url();
}

async function saveRecordedClip(context, page, filename, trimStartSec, durationSec) {
  const video = page.video();
  await context.close();
  if (!video) return;
  const rawPath = await video.path();
  const target = resolve(CLIP_DIR, filename);
  copyFileSync(rawPath, target);
  try { unlinkSync(rawPath); } catch {}
  clips.push({ file: filename, trimStartSec, durationSec });
  console.log(`[clip ok] ${filename} (trimStart=${trimStartSec.toFixed(2)}s, seg=${durationSec.toFixed(2)}s)`);
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  try {
    const warmCtx = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const warmPage = await warmCtx.newPage();
    await warmPage.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => {});
    await warmPage.goto(`${BASE}/cms/`, { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => {});
    await warmCtx.close();

    // 1. Admin captures + live Playwright clip of dashboard & workflows
    const admin = await loginRole(browser, {
      label: 'admin',
      emails: ['sales-marketing-admin@example.com', 'superadmin@sbsi.com'],
      password: 'password',
      portal: 'admin',
      recordClipName: true,
    });

    await captureRoute(admin.page, 'admin-dashboard.png', '/cms/admin/dashboard');
    await captureRoute(admin.page, 'admin-users.png', '/cms/admin/users');
    await captureRoute(admin.page, 'admin-roles.png', '/cms/admin/roles');
    await captureRoute(admin.page, 'admin-roles-workflows.png', '/cms/admin/roles?tab=workflows');

    // Click into WorkflowEditorPage if an Edit or Create Workflow button exists
    const wfBtn = admin.page.getByRole('button', { name: /^(Edit|Create Workflow)$/i }).first();
    if (await wfBtn.count()) {
      await wfBtn.click();
      await admin.page.waitForURL(/\/roles\/workflows\//, { timeout: 20000 }).catch(() => {});
      await waitForStableRender(admin.page);
      await admin.page.screenshot({ path: resolve(OUT_DIR, 'admin-workflow-editor.png') });
      manifest.push({ file: 'admin-workflow-editor.png', url: admin.page.url(), ok: true });
      console.log(`[capture ok] admin-workflow-editor.png -> ${admin.page.url()}`);
    }

    await captureRoute(admin.page, 'admin-partners.png', '/cms/admin/partners');
    await captureRoute(admin.page, 'admin-partners-create.png', '/cms/admin/partners/create');
    await captureRoute(admin.page, 'admin-vendor-suggestions.png', '/cms/admin/vendor-suggestions');
    await captureRoute(admin.page, 'admin-vendor-suggestions-review.png', '/cms/admin/vendor-suggestions/review');
    await captureRoute(admin.page, 'admin-contracts.png', '/cms/admin/contracts');
    await captureRoute(admin.page, 'admin-contracts-create.png', '/cms/admin/contracts/create');
    await captureRoute(admin.page, 'admin-contracts-editor.png', '/cms/admin/contracts/editor');
    await captureRoute(admin.page, 'admin-notifications.png', '/cms/admin/notifications');
    await captureRoute(admin.page, 'admin-audit-log.png', '/cms/admin/audit-log');
    await captureRoute(admin.page, 'admin-system-config.png', '/cms/admin/system-config');

    // Record a smooth live Playwright walkthrough clip on Admin Dashboard -> Workflows
    const clipStart = (Date.now() - admin.createdAt) / 1000;
    await admin.page.goto(`${BASE}/cms/admin/dashboard`, { waitUntil: 'domcontentloaded' });
    await waitForStableRender(admin.page);
    await admin.page.mouse.move(400, 320, { steps: 15 });
    await admin.page.mouse.wheel(0, 420);
    await admin.page.waitForTimeout(900);
    await admin.page.mouse.wheel(0, -420);
    await admin.page.waitForTimeout(700);
    const clipEnd = (Date.now() - admin.createdAt) / 1000;
    await saveRecordedClip(admin.context, admin.page, 'admin-dashboard-scroll.webm', clipStart + 1.5, Math.max(2, clipEnd - clipStart - 1.5));

    // 2. Manager captures + live Contract Detail & AI Risk Assessment
    const manager = await loginRole(browser, {
      label: 'manager',
      emails: ['sales-marketing-manager@example.com', 'manager.sales@sbsi.com'],
      password: 'password',
      portal: 'manager',
    });

    await captureRoute(manager.page, 'manager-dashboard.png', '/cms/manager/dashboard');
    await captureRoute(manager.page, 'manager-contract-requests.png', '/cms/manager/contract-requests');
    await captureRoute(manager.page, 'manager-contracts.png', '/cms/manager/contracts');
    const mgrDetailUrl = await clickFirstContractRow(manager.page);
    if (mgrDetailUrl && /\/contracts\//.test(mgrDetailUrl)) {
      await manager.page.screenshot({ path: resolve(OUT_DIR, 'manager-contract-detail.png') });
      manifest.push({ file: 'manager-contract-detail.png', url: mgrDetailUrl, ok: true });
      console.log(`[capture ok] manager-contract-detail.png -> ${mgrDetailUrl}`);

      const relPath = new URL(mgrDetailUrl).pathname.replace(/\/$/, '') + '/risk-assessment';
      await captureRoute(manager.page, 'manager-risk-assessment.png', relPath);
    }
    await captureRoute(manager.page, 'manager-amendment-requests.png', '/cms/manager/amendment-requests');
    await manager.context.close();

    // 3. Sales / Officer captures + OCR modal + Sign workspace
    const sales = await loginRole(browser, {
      label: 'sales',
      emails: ['sales-marketing-officer@example.com', 'sales@example.com', 'sales.employee1@sbsi.com'],
      password: 'password',
      portal: 'sales',
      recordClipName: true,
    });

    await captureRoute(sales.page, 'sales-dashboard.png', '/cms/sales/dashboard');
    await captureRoute(sales.page, 'sales-contracts.png', '/cms/sales/contracts');
    const salesDetailUrl = await clickFirstContractRow(sales.page);
    if (salesDetailUrl && /\/contracts\//.test(salesDetailUrl)) {
      await sales.page.screenshot({ path: resolve(OUT_DIR, 'sales-contract-detail.png') });
      manifest.push({ file: 'sales-contract-detail.png', url: salesDetailUrl, ok: true });
      console.log(`[capture ok] sales-contract-detail.png -> ${salesDetailUrl}`);

      const signPath = new URL(salesDetailUrl).pathname.replace(/\/$/, '') + '/sign';
      await captureRoute(sales.page, 'sales-contract-sign.png', signPath);
    }

    await captureRoute(sales.page, 'sales-contracts-create.png', '/cms/sales/contracts/create');
    const salesClipStart = (Date.now() - sales.createdAt) / 1000;
    await captureRoute(sales.page, 'sales-contracts-ocr.png', '/cms/sales/contracts/create', async (p) => {
      const ocrBtn = p.getByRole('button', { name: /fill with ocr/i }).first();
      if (await ocrBtn.count()) {
        await ocrBtn.hover();
        await p.waitForTimeout(350);
        await ocrBtn.click();
        await p.waitForTimeout(900);
      }
    });
    const salesClipEnd = (Date.now() - sales.createdAt) / 1000;
    await captureRoute(sales.page, 'sales-contract-amendments.png', '/cms/sales/contract-amendments');
    await saveRecordedClip(sales.context, sales.page, 'sales-ocr-demo.webm', salesClipStart + 1.2, Math.max(2, salesClipEnd - salesClipStart - 1.2));

    writeFileSync(resolve(OUT_DIR, 'manifest.json'), JSON.stringify({ screenshots: manifest, clips }, null, 2));
    console.log(`\nDone! Screenshots: ${manifest.filter((m) => m.ok).length}/${manifest.length}, Clips: ${clips.length}`);
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
