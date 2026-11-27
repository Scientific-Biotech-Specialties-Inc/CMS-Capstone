import { createRequire } from 'node:module';
import { copyFileSync, existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require(resolve(process.cwd(), '../frontend/web/node_modules/playwright'));

const BASE = 'http://localhost:5173';
const CLIP_DIR = resolve(process.cwd(), 'public/clips');
const SHOT_DIR = resolve(process.cwd(), 'public/captures');
const SAMPLE_PDF = resolve(process.cwd(), 'scripts/sample-sbsi-contract.pdf');
mkdirSync(CLIP_DIR, { recursive: true });
mkdirSync(SHOT_DIR, { recursive: true });

const onlyArg = process.argv.find((a) => a.startsWith('--only='));
const onlyFilter = onlyArg ? onlyArg.split('=')[1].split(',').map((s) => s.trim()).filter(Boolean) : null;
const manifestPath = resolve(CLIP_DIR, 'manifest.json');
const clipsManifest = onlyFilter && existsSync(manifestPath) ? JSON.parse(readFileSync(manifestPath, 'utf8')) : [];

function shouldRun(name) {
  return !onlyFilter || onlyFilter.some((f) => name.includes(f));
}

async function waitStable(page, extraMs = 700) {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForLoadState('networkidle', { timeout: 18000 }).catch(() => {});
  await page.evaluate(() => document.fonts?.ready).catch(() => {});
  const start = Date.now();
  while (Date.now() - start < 18000) {
    const skel = await page.locator('.animate-pulse').count().catch(() => 0);
    if (skel === 0) break;
    await page.waitForTimeout(350);
  }
  if (extraMs > 0) await page.waitForTimeout(extraMs);
}

async function moveAndClick(page, locator, pauseAfter = 450) {
  const box = await locator.boundingBox({ timeout: 8000 }).catch(() => null);
  if (box) {
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 14 });
    await page.waitForTimeout(180);
  }
  await locator.click({ timeout: 8000 });
  if (pauseAfter > 0) await page.waitForTimeout(pauseAfter);
}

async function loginState(browser, email, password, portal) {
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await context.newPage();
  page.setDefaultTimeout(60000);
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await waitStable(page, 400);
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 45000 }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  await waitStable(page, 400);
  await page.goto(`${BASE}/cms/auth/callback?state=${encodeURIComponent(`/cms/${portal}/dashboard`)}`, {
    waitUntil: 'domcontentloaded',
    timeout: 60000,
  });
  await page.waitForURL((u) => u.pathname.includes(`/cms/${portal}`), { timeout: 45000 }).catch(() => {});
  await waitStable(page, 800);
  const storageState = await context.storageState();
  await context.close();
  console.log(`[auth ok] ${portal} (${email})`);
  return storageState;
}

async function recordClip(browser, name, storageState, fn) {
  if (!shouldRun(name)) return;
  const ctxOpts = {
    viewport: { width: 1920, height: 1080 },
    recordVideo: { dir: CLIP_DIR, size: { width: 1920, height: 1080 } },
  };
  if (storageState) ctxOpts.storageState = storageState;
  const context = await browser.newContext(ctxOpts);
  const page = await context.newPage();
  page.setDefaultTimeout(60000);
  const startedAt = Date.now();
  let trimStartSec = 0;
  let durationSec = 5;

  const markStart = () => {
    trimStartSec = Math.max(0, (Date.now() - startedAt) / 1000);
  };
  const markEnd = () => {
    durationSec = Math.max(2, (Date.now() - startedAt) / 1000 - trimStartSec);
  };

  try {
    await fn(page, { markStart, markEnd, startedAt });
  } catch (err) {
    console.warn(`[clip warn] ${name}: ${err.message}`);
    markEnd();
  }

  const video = page.video();
  await context.close();
  if (video) {
    const raw = await video.path();
    const out = resolve(CLIP_DIR, name);
    copyFileSync(raw, out);
    try { unlinkSync(raw); } catch {}
    const idx = clipsManifest.findIndex((c) => c.file === name);
    const entry = { file: name, trimStartSec: Number(trimStartSec.toFixed(2)), durationSec: Number(durationSec.toFixed(2)) };
    if (idx >= 0) clipsManifest[idx] = entry;
    else clipsManifest.push(entry);
    console.log(`[clip saved] ${name} trim=${trimStartSec.toFixed(2)}s len=${durationSec.toFixed(2)}s`);
  }
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  try {
    // Warm up gateway & SPA
    const warm = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const wp = await warm.newPage();
    await wp.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 75000 }).catch(() => {});
    await wp.goto(`${BASE}/cms/`, { waitUntil: 'domcontentloaded', timeout: 75000 }).catch(() => {});
    await warm.close();

    const adminState = onlyFilter && !['clip-02', 'clip-03', 'clip-04', 'clip-05', 'clip-07', 'clip-10'].some(shouldRun)
      ? null
      : await loginState(browser, 'sales-marketing-admin@example.com', 'password', 'admin');
    const managerState = onlyFilter && !['clip-06', 'clip-08', 'clip-09'].some(shouldRun)
      ? null
      : await loginState(browser, 'sales-marketing-manager@example.com', 'password', 'manager');
    const ceoState = onlyFilter && !shouldRun('clip-09')
      ? null
      : await loginState(browser, 'ceo@example.com', 'password', 'admin').catch(() => null);

    // 1. Auth & Roles interactive clip
    await recordClip(browser, 'clip-01-auth-roles.webm', null, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 400);
      markStart();
      await page.locator('#email').click();
      await page.locator('#email').type('sales-marketing-admin@example.com', { delay: 25 });
      await page.locator('#password').click();
      await page.locator('#password').type('password', { delay: 30 });
      await moveAndClick(page, page.locator('button[type="submit"]'), 1200);
      await waitStable(page, 600);
      await page.goto(`${BASE}/cms/admin/roles`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 600);
      const mgrCard = page.getByText('Manager', { exact: true }).first();
      if (await mgrCard.count()) await moveAndClick(page, mgrCard, 700);
      const salesCard = page.getByText('Sales', { exact: true }).first();
      if (await salesCard.count()) await moveAndClick(page, salesCard, 700);
      markEnd();
    });

    // 2. Analytics Dashboard interactive clip
    await recordClip(browser, 'clip-02-analytics.webm', adminState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/admin/dashboard`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 900);
      markStart();
      const diagTab = page.getByRole('button', { name: /Diagnostic/i }).first();
      if (await diagTab.count()) {
        await moveAndClick(page, diagTab, 1200);
        await waitStable(page, 600);
      }
      const marginsTab = page.getByRole('button', { name: /Contract Margins/i }).first();
      if (await marginsTab.count()) {
        await moveAndClick(page, marginsTab, 1200);
        await waitStable(page, 600);
      }
      const overviewTab = page.getByRole('button', { name: /Overview/i }).first();
      if (await overviewTab.count()) {
        await moveAndClick(page, overviewTab, 900);
      }
      await page.mouse.wheel(0, 480);
      await page.waitForTimeout(900);
      await page.screenshot({ path: resolve(SHOT_DIR, 'admin-dashboard.png') });
      markEnd();
    });

    // 3. Vendor Management: Create Partner live
    await recordClip(browser, 'clip-03-vendor-create.webm', adminState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/admin/partners`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 800);
      await page.screenshot({ path: resolve(SHOT_DIR, 'admin-partners.png') });
      markStart();
      await page.mouse.move(1340, 265, { steps: 14 });
      await page.goto(`${BASE}/cms/admin/partners/create`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 600);

      const uniqueName = `BioDiagnostix Cebu ${new Date().getMinutes()}${new Date().getSeconds()}`;
      await page.getByPlaceholder('Organization name').fill(uniqueName);
      await page.getByPlaceholder('e.g. Healthcare').fill('Clinical Diagnostics');
      const selects = page.locator('select');
      if ((await selects.count()) >= 3) {
        await selects.nth(0).selectOption('Visayas');
        await selects.nth(2).selectOption('Class A');
      }
      await page.getByPlaceholder('Full name').fill('Elena Reyes');
      await page.getByPlaceholder('e.g. 09123456789').fill('09171234567');
      await page.getByPlaceholder('contact@company.com').fill('elena@biodiagnostix.ph');
      await page.getByPlaceholder('Street, City').fill('Banilad, Cebu City');

      const addBtn = page.getByRole('button', { name: /Add Partner/i }).first();
      await moveAndClick(page, addBtn, 1800);
      await waitStable(page, 1000);
      await page.screenshot({ path: resolve(SHOT_DIR, 'admin-partners-after-create.png') });
      markEnd();
    });

    // 4. AI Vendor Suggestions: Trigger, Select, Review & Onboard
    await recordClip(browser, 'clip-04-ai-vendor.webm', adminState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/admin/vendor-suggestions`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 900);
      await page.getByText('Loading…').waitFor({ state: 'detached', timeout: 15000 }).catch(() => {});
      const checks = page.locator('input[type="checkbox"]');
      await checks.first().waitFor({ timeout: 3500 }).catch(() => {});

      markStart();
      const hint = page.getByPlaceholder('e.g. clinical diagnostics');
      if (await hint.count()) {
        await hint.click();
        await hint.fill('');
        await hint.type('clinical diagnostics', { delay: 25 });
      }
      if ((await checks.count()) === 0) {
        const getBtn = page.getByRole('button', { name: /Get Suggestions/i }).first();
        if (await getBtn.count()) await moveAndClick(page, getBtn, 900);
        await checks.first().waitFor({ timeout: 55000 }).catch(() => {});
      }
      await page.waitForTimeout(800);

      if (await checks.count()) {
        const c1 = checks.nth(0);
        await moveAndClick(page, c1, 650);
        if (!(await c1.isChecked().catch(() => false))) await c1.check({ force: true });
        if ((await checks.count()) > 1) {
          const c2 = checks.nth(1);
          await moveAndClick(page, c2, 600);
          if (!(await c2.isChecked().catch(() => false))) await c2.check({ force: true });
        }
      } else {
        const selectAll = page.getByRole('button', { name: /Select All/i }).first();
        if (await selectAll.count()) await moveAndClick(page, selectAll, 600);
      }

      await page.screenshot({ path: resolve(SHOT_DIR, 'admin-vendor-suggestions-live.png') });
      const acceptBtn = page.getByRole('button', { name: /Accept Selected/i }).first();
      if ((await acceptBtn.count()) && (await acceptBtn.isEnabled().catch(() => false))) {
        await moveAndClick(page, acceptBtn, 1100);
        await page.waitForURL(/\/vendor-suggestions\/review/, { timeout: 15000 }).catch(() => {});
        await waitStable(page, 1000);
        const emailInput = page.getByPlaceholder('contact@company.com');
        if ((await emailInput.count()) && !(await emailInput.inputValue())) {
          await emailInput.fill('procurement@sbsipartner.ph');
        }
        const addrInput = page.getByPlaceholder('Street, City');
        if ((await addrInput.count()) && !(await addrInput.inputValue())) {
          await addrInput.fill('Makati City, Metro Manila');
        }
        await page.mouse.wheel(0, 220);
        await page.waitForTimeout(600);
        await page.screenshot({ path: resolve(SHOT_DIR, 'admin-vendor-suggestions-review-live.png') });
        const saveVendorBtn = page.getByRole('button', { name: /Save (Partner|Supplier)/i }).first();
        if ((await saveVendorBtn.count()) && (await saveVendorBtn.isEnabled().catch(() => false))) {
          await moveAndClick(page, saveVendorBtn, 1600);
          await waitStable(page, 1200);
        }
      }
      markEnd();
    });

    // 5. Create Contract: OCR Import + AI Contract Generator + Editor + Create
    await recordClip(browser, 'clip-05-contract-create-ai.webm', adminState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/admin/contracts/create`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 1000);
      const uniqueNum = String(Date.now());
      const uniqueSuffix = uniqueNum.slice(-4);
      const uniqueSbu = String(700 + (Number(uniqueSuffix) % 290));
      markStart();

      // A. Open Fill with OCR and upload sample PDF inside dialog
      const ocrBtn = page.getByRole('button', { name: /Fill with OCR/i }).first();
      if (await ocrBtn.count()) {
        await moveAndClick(page, ocrBtn, 700);
        const dialog = page.getByRole('dialog');
        const fileInput = dialog.locator('input[type="file"]').first();
        if (await fileInput.count()) {
          await fileInput.setInputFiles(SAMPLE_PDF);
          await page.waitForTimeout(700);
          await page.screenshot({ path: resolve(SHOT_DIR, 'manager-ocr-selected.png') });
          const extractBtn = dialog.getByRole('button', { name: /Extract & Autofill/i }).first();
          if (await extractBtn.count()) {
            await moveAndClick(page, extractBtn, 900);
            await Promise.race([
              dialog.waitFor({ state: 'detached', timeout: 22000 }),
              page.getByText(/OCR scan completed/i).waitFor({ timeout: 22000 }),
            ]).catch(() => {});
            await page.waitForTimeout(450);
          }
        }
        const closeBtn = dialog.getByRole('button', { name: /Cancel/i }).first();
        if ((await dialog.isVisible().catch(() => false)) && (await closeBtn.count())) {
          await closeBtn.click({ timeout: 5000 }).catch(() => {});
          await page.waitForTimeout(350);
        }
      }

      // B. Ensure all required fields & Equipment Items (Brand/Model required by isValid)
      const bpInput = page.getByPlaceholder('e.g. Globe Telecom', { exact: true });
      if (await bpInput.count()) {
        await bpInput.click({ timeout: 5000 });
        await bpInput.fill("St. Luke's Medical Center");
        const firstOpt = page.getByRole('button', { name: /St\. Luke/i }).first();
        if (await firstOpt.isVisible().catch(() => false)) {
          await firstOpt.click({ timeout: 5000 }).catch(() => {});
        } else {
          await bpInput.press('Tab').catch(() => {});
        }
      }
      const descInput = page.getByPlaceholder('e.g. Network Infrastructure', { exact: true });
      if ((await descInput.count()) && !(await descInput.inputValue())) {
        await descInput.fill('Automated Hematology Analyzer & Reagents');
      }
      const itmInput = page.getByPlaceholder('0041', { exact: true });
      if (await itmInput.count()) await itmInput.fill(uniqueSuffix);
      const snInput = page.getByPlaceholder('2026-0041', { exact: true });
      if (await snInput.count()) await snInput.fill(`2026${uniqueSuffix}`);
      const sbuInput = page.getByPlaceholder('001', { exact: true });
      if (await sbuInput.count()) await sbuInput.fill(uniqueSbu);

      const eqNameInput = page.getByPlaceholder('e.g., Sysmex XN-1000 Hematology Analyzer', { exact: true }).first();
      if ((await eqNameInput.count()) && !(await eqNameInput.inputValue())) {
        await eqNameInput.fill('Sysmex XN-1000 Hematology Analyzer');
      }
      const brandInput = page.getByPlaceholder('e.g., Sysmex', { exact: true }).first();
      if ((await brandInput.count()) && !(await brandInput.inputValue())) {
        await brandInput.fill('Sysmex');
      }
      const modelInput = page.getByPlaceholder('e.g., XN-1000', { exact: true }).first();
      if ((await modelInput.count()) && !(await modelInput.inputValue())) {
        await modelInput.fill('XN-1000');
      }

      async function pickSelect(placeholderText, preferredText = null) {
        const trig = page.getByRole('combobox').filter({ hasText: placeholderText }).first();
        if (!(await trig.isVisible().catch(() => false))) return;
        await trig.click({ timeout: 5000 });
        await page.waitForTimeout(250);
        const listbox = page.locator('[role="listbox"]').last();
        const opt = preferredText
          ? listbox.getByRole('option', { name: preferredText }).first()
          : listbox.getByRole('option').first();
        if (await opt.isVisible().catch(() => false)) {
          await opt.click({ timeout: 5000 });
        } else {
          await page.keyboard.press('ArrowDown');
          await page.keyboard.press('Enter');
        }
        await page.waitForTimeout(200);
      }
      await pickSelect('Select category');
      await pickSelect('Select branch', 'Main Office');
      await pickSelect('Select region', 'Luzon');

      const dateInputs = page.locator('input[type="date"]');
      if ((await dateInputs.count()) >= 2) {
        await dateInputs.nth(0).fill('2026-12-01');
        await dateInputs.nth(1).fill('2027-11-30');
      }

      // Wait for OCR upload scan before triggering generator / submit
      await page.getByText(/Scanning file/i).waitFor({ state: 'detached', timeout: 15000 }).catch(() => {});

      // C. Launch AI Contract Generator -> Editor -> Attach to Contract
      const genBtn = page.getByRole('button', { name: /Generate Contract|Edit Generated Contract/i }).first();
      if (await genBtn.count()) {
        await genBtn.scrollIntoViewIfNeeded().catch(() => {});
        await moveAndClick(page, genBtn, 850);
        const confirmDialog = page.locator('[role="dialog"]').last();
        await confirmDialog.waitFor({ state: 'visible', timeout: 6000 }).catch(() => {});
        const confirmGen = confirmDialog.getByRole('button', { name: /Generate Contract|Open Editor/i }).first();
        if (await confirmGen.isVisible().catch(() => false)) {
          await moveAndClick(page, confirmGen, 950);
          await page.waitForURL(/\/contracts\/editor/, { timeout: 25000 }).catch(() => {});
          await page.getByText(/Generating Contract Agreement/i).waitFor({ state: 'detached', timeout: 55000 }).catch(() => {});
          await waitStable(page, 1400);
          await page.mouse.wheel(0, 360);
          await page.waitForTimeout(750);
          await page.mouse.wheel(0, -360);
          await page.waitForTimeout(550);
          await page.screenshot({ path: resolve(SHOT_DIR, 'manager-contract-editor-live.png') });
          const attachBtn = page.getByRole('button', { name: /Attach & Save Contract|Attach to Contract/i }).first();
          if ((await attachBtn.isVisible().catch(() => false)) && (await attachBtn.isEnabled().catch(() => false))) {
            await moveAndClick(page, attachBtn, 1600);
            await page.waitForURL(/\/contracts\/create/, { timeout: 25000 }).catch(() => {});
            await waitStable(page, 1200);
          }
        }
      }

      // D. Submit Create Contract
      await page.getByText(/Scanning file/i).waitFor({ state: 'detached', timeout: 15000 }).catch(() => {});
      const createBtn = page.getByRole('button', { name: /^Create Contract$/i }).first();
      if (await createBtn.count()) {
        await createBtn.scrollIntoViewIfNeeded().catch(() => {});
        if (await createBtn.isEnabled().catch(() => false)) {
          await moveAndClick(page, createBtn, 900);
          await page.waitForURL(/\/admin\/contracts(?!\/create)/, { timeout: 20000 }).catch(() => {});
          await waitStable(page, 1400);
        }
      }
      markEnd();
    });

    // 6. AI Risk Assessment: Real Completed Findings on CTR-EFKN5B (117)
    await recordClip(browser, 'clip-06-ai-risk.webm', managerState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/manager/contracts/117/risk-assessment`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 1200);
      markStart();
      await page.screenshot({ path: resolve(SHOT_DIR, 'manager-risk-assessment-live.png') });
      await page.waitForTimeout(1600);
      for (const dy of [360, 420, 420, -600, -600]) {
        await page.mouse.wheel(0, dy);
        await page.waitForTimeout(1400);
      }
      const exportBtn = page.getByRole('button', { name: /Export PDF/i }).first();
      if (await exportBtn.count()) {
        await moveAndClick(page, exportBtn, 1400);
      }
      markEnd();
    });

    // 7. Approval & Execution Workflow Builder
    await recordClip(browser, 'clip-07-workflows.webm', adminState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/admin/roles?tab=workflows`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 900);
      markStart();
      const editBtn = page.getByRole('button', { name: /^Edit$/i }).first();
      if (await editBtn.count()) {
        await moveAndClick(page, editBtn, 900);
        await waitStable(page, 900);
        const parallelBtn = page.getByRole('button', { name: /^Parallel$/i }).first();
        if (await parallelBtn.count()) await moveAndClick(page, parallelBtn, 600);
        const seqBtn = page.getByRole('button', { name: /^Sequential$/i }).first();
        if (await seqBtn.count()) await moveAndClick(page, seqBtn, 600);
        await page.screenshot({ path: resolve(SHOT_DIR, 'admin-workflow-editor-live.png') });
      }
      markEnd();
    });

    // 8. Contract Approval (Manager approves CTR-7DW2J9 / 119)
    await recordClip(browser, 'clip-08-approval.webm', managerState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/manager/contract-requests`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 900);
      markStart();
      await page.waitForTimeout(700);
      await page.goto(`${BASE}/cms/manager/contracts/CTR-7DW2J9`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 900);
      await page.screenshot({ path: resolve(SHOT_DIR, 'manager-approval-detail-live.png') });

      const approveTaskBtn = page.locator('section[aria-labelledby="approval-actions-title"] button', { hasText: 'Approve' }).first();
      if (await approveTaskBtn.count()) {
        await approveTaskBtn.scrollIntoViewIfNeeded();
        await moveAndClick(page, approveTaskBtn, 700);
        const modalApprove = page.getByRole('dialog').getByRole('button', { name: /^Approve$/i }).first();
        if (await modalApprove.count()) {
          await moveAndClick(page, modalApprove, 1600);
          await waitStable(page, 900);
        }
      } else {
        const headerApprove = page.getByRole('button', { name: /Approve Contract/i }).first();
        if (await headerApprove.count()) {
          await moveAndClick(page, headerApprove, 600);
          const confirmBtn = page.getByRole('dialog').getByRole('button', { name: /Approve/i }).first();
          if (await confirmBtn.count()) await moveAndClick(page, confirmBtn, 1400);
        }
      }
      markEnd();
    });

    // 9. Execution Workflow: Acceptance -> For Signing -> In-App E-Sign
    await recordClip(browser, 'clip-09-execution-signing.webm', managerState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/manager/contracts/CTR-7DW2J9`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 900);
      markStart();

      // Advance SBSI Review -> Client Review -> For Signing if actions available
      for (const _step of [1, 2]) {
        const submitExec = page.locator('section[aria-labelledby="execution-title"] button').first();
        if (await submitExec.count()) {
          await submitExec.scrollIntoViewIfNeeded();
          await moveAndClick(page, submitExec, 600);
          const dlgSubmit = page.getByRole('dialog').locator('button[type="submit"]').first();
          if (await dlgSubmit.count()) {
            await moveAndClick(page, dlgSubmit, 1200);
            await waitStable(page, 600);
          }
          const confirmExec = page.getByRole('button', { name: /Confirm Submission/i }).first();
          if (await confirmExec.count()) {
            await moveAndClick(page, confirmExec, 600);
            const dlgConf = page.getByRole('dialog').locator('button[type="submit"]').first();
            if (await dlgConf.count()) {
              await moveAndClick(page, dlgConf, 1200);
              await waitStable(page, 600);
            }
          }
        }
      }

      // Open SignContract workspace on CTR-7DW2J9 (if For Signing) or show signed CTR-EFKN5B execution history
      await page.goto(`${BASE}/cms/manager/contracts/CTR-7DW2J9/sign`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 1200);
      await page.screenshot({ path: resolve(SHOT_DIR, 'manager-sign-workspace-live.png') });
      const nameField = page.getByPlaceholder('Enter your full name');
      if (await nameField.count()) {
        await nameField.fill('Maya Dela Cruz');
        const canvas = page.locator('canvas').first();
        const cbox = await canvas.boundingBox().catch(() => null);
        if (cbox) {
          await page.mouse.move(cbox.x + 30, cbox.y + cbox.height / 2);
          await page.mouse.down();
          await page.mouse.move(cbox.x + 95, cbox.y + cbox.height / 2 - 18, { steps: 8 });
          await page.mouse.move(cbox.x + 170, cbox.y + cbox.height / 2 + 12, { steps: 8 });
          await page.mouse.up();
          await page.waitForTimeout(400);
        }
      }
      await page.goto(`${BASE}/cms/manager/contracts/CTR-EFKN5B`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 800);
      await page.mouse.wheel(0, 520);
      await page.waitForTimeout(700);
      markEnd();
    });

    // 10. Search, Notifications, Audit Log & System Config
    await recordClip(browser, 'clip-10-ops.webm', adminState, async (page, { markStart, markEnd }) => {
      await page.goto(`${BASE}/cms/admin/contracts`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 700);
      markStart();
      const searchBox = page.getByPlaceholder(/Search contracts/i).first();
      if (await searchBox.count()) {
        await searchBox.click();
        await searchBox.type('Philippine', { delay: 40 });
        await page.waitForTimeout(700);
      }

      await page.goto(`${BASE}/cms/admin/notifications`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 700);
      const emailTab = page.getByRole('button', { name: /Email Log/i }).first();
      if (await emailTab.count()) await moveAndClick(page, emailTab, 700);
      const allTab = page.getByRole('button', { name: /^All/i }).first();
      if (await allTab.count()) await moveAndClick(page, allTab, 600);

      await page.goto(`${BASE}/cms/admin/audit-log`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 700);
      await page.mouse.wheel(0, 260);
      await page.waitForTimeout(500);

      await page.goto(`${BASE}/cms/admin/system-config`, { waitUntil: 'domcontentloaded' });
      await waitStable(page, 700);
      const saveCfg = page.getByRole('button', { name: /Save Changes/i }).first();
      if (await saveCfg.count()) {
        await moveAndClick(page, saveCfg, 1000);
      }
      markEnd();
    });

    void ceoState;
    writeFileSync(resolve(CLIP_DIR, 'manifest.json'), JSON.stringify(clipsManifest, null, 2));
    console.log(`\nDone! Recorded ${clipsManifest.length} interactive clips.`);
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
