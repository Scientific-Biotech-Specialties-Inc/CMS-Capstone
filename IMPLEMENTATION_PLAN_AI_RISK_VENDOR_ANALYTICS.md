# Implementation Plan — AI Risk Assessment, Approval Gate, Vendor AI Suggestions & Analytics

## Status: Planning complete, no open questions remaining — ready to start executing

This is revision 4 of the plan, per `AGENTS.md`'s Task Workflow rule ("always come up with a plan first before executing"). It covers four backlog items:

1. **US-026** — AI Risk Assessment Summary (Manager reviews before approval)
2. **US-023** — Approve high-risk contracts (mandatory approval gate + SLA escalation)
3. **Vendor AI Suggestions** — Gemini-suggested candidate PH business partners/suppliers, multi-select accept/reject with autofill
4. **Analytics** — system-wide descriptive + diagnostic analytics

References `SBSI_COMPANY_PROFILE.md` for business context grounding.

## Answers received (this revision)

1. **Gemini API key** — provided, now in `services/ai-service/.env` and `.env.example` as `GEMINI_API_KEY` (value only in `.env`, placeholder in `.env.example` — standard secret handling, not committed as a real value in the example file). Model: **`gemini-2.5-flash-lite`** as primary, with a fallback model configured (`GEMINI_MODEL_FALLBACK=gemini-2.5-flash`) so a primary-model outage/rate-limit doesn't hard-fail the pipeline.
2. **Playbook clause library** — no real SBSI template yet; proceeding with the general-terms draft from the prior revision (kept below). AI Risk Assessment will use the Gemini API (confirmed — "I also want GEMINI API to be part of the AI Risk Assessment since 'AI'").
3. **Approach** — RAG, confirmed.
4. **Vendor AI Suggestions** — uses **pgvector**, same as AI Risk Assessment, and lives inside **`ai-service`** (not a separate service — see architecture change below).
5. **SLA window** — **24 hours**, confirmed.
6. **Analytics** — add **descriptive and diagnostic** analytics (not just descriptive), using AI (Gemini) or algorithms where possible. Predictive analytics still not requested — stays out of scope.
7. **Service placement, confirmed:**
   - AI Risk Assessment + Vendor AI Suggestion → **`ai-service`**
   - Analytics (descriptive + diagnostic) → **`analytics-service`**
   - This **replaces** the previous plan's proposal of a separate `vendor-ai-service` — scrapped in favor of putting both Gemini-driven features in the existing `ai-service`, which already has Postgres+pgvector provisioned.
8. **Frontend placement, confirmed** (detailed below): AI Risk Assessment triggers from the Create Contract page (Manager + Sales/"Employee" only, and only when a file is uploaded), toggleable per-user in Profile. Vendor AI Suggestion is a button next to "Add manually" in Vendor Management (Admin only), toggleable in the Admin profile, opening a dedicated suggestion → review/edit flow with a multi-tab system. Analytics is a new sidebar item + page, visible to Admin and Manager only.

## Relationship to the existing `analytics-ai-services` spec

`.kiro/specs/analytics-ai-services/` scaffolded (all tasks checked off, infra/schema only, no business logic):
- `services/analytics-service` (Laravel 13, MySQL `cms-analytics-db`) — `aggregated_metrics`, `reports`, `audit_logs`.
- `services/ai-service` (Laravel 13, Postgres+pgvector `cms-ai-db`) — `risk_assessment_results`, `vendor_suggestions`, `ocr_extractions`, `embeddings`, `audit_logs`.

This plan builds the business logic, endpoints, and frontend on top of that scaffold — no re-scaffolding needed for `ai-service`/`analytics-service`. `contract-management` (approval gate + SLA), `vendor-management` (accepted-suggestion → real vendor creation), and the Vue frontend also get new code.

---

## Feature 1: AI Risk Assessment (US-026) — RAG pipeline inside `ai-service`

**Story:** Manager gets a summary listing every flagged risk with reason + recommended correction to decide approve/reject.
**AC:** lists every flagged clause with severity tag, cites the playbook clause deviated from, gives remediation text, exportable to PDF, reachable from contract record and approval queue.
**DoD:** every flagged risk has reason + remediation; explainable (no black box); PDF export works.

### Standard clause library (draft — general terms, per your instruction; not SBSI-specific yet)

Seeded into a new `playbook_clauses` table. Covers standard commercial-contract risk categories plus a few tailored to SBSI's known business (IVD/lab distribution, service/SLA agreements, LIS/BBIS/MIS licensing) from `SBSI_COMPANY_PROFILE.md`. **Draft, not legal advice** — revisit once/if a real SBSI contract template is available:

| clause_code | category | standard_text (summary) |
|---|---|---|
| PAY-01 | Payment Terms | Payment due within 30 days of invoice; late-payment interest capped; no unilateral payment-term changes without written consent. |
| TERM-01 | Termination | Either party may terminate for material breach with a 30-day cure period; termination for convenience requires 60 days' written notice. |
| LIAB-01 | Limitation of Liability | Liability capped at total fees paid in the preceding 12 months; gross negligence/willful misconduct excluded from the cap. |
| IND-01 | Indemnification | Mutual indemnification for third-party claims from breach, IP infringement, or negligence; no uncapped/one-sided indemnity. |
| WAR-01 | Warranty (Equipment/Reagents) | Goods conform to spec and regulatory approvals (e.g. FDA PH clearance for diagnostics) for a stated period; defect remedy defined. |
| REG-01 | Regulatory Compliance | Imported IVD/diagnostic products hold valid FDA Philippines (or equivalent) registration; ongoing PH import/customs compliance. |
| SLA-01 | Service Level / Technical Support | Response/resolution time commitments for support & calibration services; remedies for missed SLAs. |
| DATA-01 | Data Handling & Confidentiality | Confidentiality for data processed via LIS/BBIS/MIS; retention & breach-notification terms; PH Data Privacy Act (RA 10173) reference. |
| IP-01 | Intellectual Property | Pre-existing IP stays with originating party; no implicit assignment of background IP. |
| RENEW-01 | Renewal & Auto-Renewal | Auto-renewal requires a non-renewal notice window (e.g. 60/90 days); flag silent/indefinite auto-renewal. |
| EXCL-01 | Exclusivity / Non-Compete | Flag overly broad exclusivity/non-compete scope or duration. |
| FORCE-01 | Force Majeure | Standard, mutually-applied force majeure carve-out present. |
| DISP-01 | Dispute Resolution / Governing Law | Governing law/venue specified; flag foreign governing law with no PH venue option. |
| ASSIGN-01 | Assignment | Assignment/subcontracting requires prior written consent; flag unrestricted assignment rights. |

### Data model additions (`ai-service`, Postgres)

- **`playbook_clauses`**: `id`, `clause_code` unique, `title`, `standard_text` text, `category` indexed, `is_active` boolean default true, timestamps.
- **`risk_assessment_findings`** (one row per flagged clause):
  - `id`, `risk_assessment_result_id` (FK → `risk_assessment_results.id`, cascade delete)
  - `clause_reference` string (the actual contract text/section flagged)
  - `severity` indexed — `low`|`medium`|`high`|`critical`
  - `playbook_clause_id` nullable indexed — FK → `playbook_clauses.id`
  - `retrieval_score` decimal(5,4) nullable — cosine similarity, kept for explainability
  - `deviation_reason` text, `recommended_remediation` text, `created_at`
- Reuses the existing `embeddings` table for both playbook clauses and contract chunks via new `entity_type` values `'playbook_clause'` / `'contract_chunk'` (no schema change — it's already a discriminator column).

### RAG pipeline (now includes text extraction — confirmed not to exist yet)

0. **Text extraction (new — no existing pipeline does this):** given `documents.file_path`/`file_type` (PDF or DOCX per Create Contract's accepted formats), extract plain text server-side in `ai-service` before chunking. For PDF: `smalot/pdfparser` (pure-PHP, no external binary dependency, pinned version) is the standard choice for a Docker-based Laravel service without shelling out to `pdftotext`. For DOCX: `PhpOffice/PHPWord`'s reader. Both are well-known, actively maintained packages. If extraction fails (scanned/image-only PDF with no text layer), the scan should fail gracefully with a clear "document has no extractable text" result rather than silently producing an empty assessment — this is a real limitation worth flagging: **scanned/image contracts with no text layer cannot be assessed by this pipeline without a separate OCR step**, which is out of scope here (the existing `ocr_extractions` table is for the *separate*, not-yet-built OCR-autofill feature from the original spec, not phrase-level document OCR for RAG).
1. **Indexing** (once + on playbook change): embed each `playbook_clauses` row via `gemini-embedding-001` (output truncated to 1536 dims — matches the existing `vector(1536)` column; [Google's own guidance recommends 768/1536/3072 truncation sizes](https://developers.googleblog.com/en/gemini-embedding-available-gemini-api/)), store in `embeddings` (`entity_type='playbook_clause'`).
2. **On scan trigger** (see Feature 1 frontend trigger below): run step 0, chunk the extracted text (e.g. ~500-token windows with overlap), embed each chunk, store with `entity_type='contract_chunk'`.
3. **Retrieval**: pgvector cosine-distance query (`ORDER BY embedding <=> :chunk_vector LIMIT k`) against `entity_type='playbook_clause'` to find the top-k relevant clauses per chunk.
4. **Generation**: send chunk + retrieved clause(s) to Gemini with a JSON-schema-constrained prompt asking for a deviation judgment (severity, reason, remediation) if applicable.
5. Write `risk_assessment_findings` rows + one aggregated `risk_assessment_results` row (`risk_score`/`risk_level`) per contract.

### Gemini model configuration (`ai-service`)

```dotenv
GEMINI_API_KEY=<provisioned — already in .env, redacted here>
GEMINI_MODEL_PRIMARY=gemini-2.5-flash-lite
GEMINI_MODEL_FALLBACK=gemini-2.5-flash
GEMINI_EMBEDDING_MODEL=gemini-embedding-001
GEMINI_EMBEDDING_DIMENSIONS=1536
```
`GeminiClient` (new `app/Services/GeminiClient.php`, Guzzle-based against `generativelanguage.googleapis.com` since no official PHP SDK exists) tries `GEMINI_MODEL_PRIMARY` first; on a 429/5xx/timeout it retries once against `GEMINI_MODEL_FALLBACK`, logging which model actually served each request (useful for the diagnostic-analytics tables in Feature 4, and for `gemini_raw_response` auditability).

### Backend endpoints (`ai-service`)

- `POST /contracts/{contractId}/risk-assessment/scan` — triggers the pipeline (queued job; `QUEUE_CONNECTION=database` already configured).
- `GET /contracts/{contractId}/risk-assessment/summary` — latest `risk_assessment_results` + `risk_assessment_findings` + `playbook_clauses` citations.
- `GET /contracts/{contractId}/risk-assessment/summary/pdf` — same via `barryvdh/laravel-dompdf` (pinned version).
- Auth: internal-service-secret pattern + role check (Manager: decision; Sales: view own only).

### Frontend — trigger point, toggle, and reachability (confirmed placement)

**Trigger:** Inside **Create Contract** (`frontend/web/src/views/sales/Contracts/CreateContract.vue` and the equivalent manager `CreateContract.vue`) — the assessment runs **only if the user uploads a document** via the existing `DocumentUpload.vue` section. No document, no scan. Concretely: after a successful document upload + successful contract creation, if the toggle (below) is on for that user, fire `POST /contracts/{contractId}/risk-assessment/scan` and show a "Running AI Risk Assessment…" indicator; land the user on the contract detail page where the summary becomes available once the job completes.

**Toggle:** New "AI Risk Assessment" preference row in `PreferencesCard.vue` (`frontend/web/src/views/admin/Profile/PreferencesCard.vue`, shared across roles) — visible **only for Manager and Sales roles** (your "Employee" = Sales role in this codebase; Admin doesn't create contracts today, so the toggle is scoped to the two roles that actually hit Create Contract). Persisted as a new `ai_risk_assessment_enabled` column on the `notification` service's `email_preferences` table, added via its own migration following the exact pattern of `2026_06_14_000001_add_extra_fields_to_email_preferences_table.php` (which already added `system_alerts_enabled`/`sms_notifications_enabled`/`login_alerts_enabled` the same incremental way) — confirmed as the right fit after reading that table's existing structure.

**Framing note (confirmed):** the assessment is a *recommendation/suggestion*, not a hard gate on its own — it informs the human decision (and feeds Feature 2's approval gate for High/Critical results), but doesn't block contract creation or editing by itself. The UI should read as "here's what we found, please review," not "this contract is broken."

**Severity color convention — RAG (Red/Amber/Green), confirmed as a scoped exception to the brand-colors-only rule:**
- `critical`/`high` → red (`text-red-600`/`bg-red-50 border-red-200`, Tailwind's standard red scale — no custom hex needed)
- `medium` → amber (`text-amber-600`/`bg-amber-50 border-amber-200`)
- `low` → green (`text-emerald-600`/`bg-emerald-50 border-emerald-200`)
- Everything else on these screens (headers, buttons, cards, layout chrome) still uses the brand navy/blue palette — only severity tags/badges/icons/flags use RAG.

**Warning/flag indicator (new, per your instruction):** since the assessment is advisory, flagged risk needs to be *visible at a glance* wherever the contract already appears, not just inside the dedicated summary screen:
- A small flag/warning icon (`lucide-vue-next`'s `Flag` or `AlertTriangle`, colored per the RAG scale above based on the contract's highest-severity finding) is added next to the contract's existing status badge in:
  - `ContractDetailHeader.vue` (`sales/Contracts/ContractDetail/`) — icon + tooltip/label showing highest severity (e.g. "⚠ High Risk — 3 flagged clauses").
  - The contracts table row (`SalesContractsTable.vue`, `ManagerContractsTable.vue`, `ContractsTable.vue` in admin) — a compact icon-only version in an existing or new column, consistent with how `approvalStatusBadge`/`workflowStatusBadge` already render as small pill badges in those tables.
  - The manager approval queue (`manager/ContractRequests/index.vue`) — same compact icon, so Managers can see risk severity before opening a request.
- Clicking the flag navigates to the Feature 1 summary view below (same reachability as the explicit "View AI Risk Assessment" entry point).
- No assessment yet run / no document uploaded → no icon shown (not a lower-severity default; absence means "not assessed," which is a distinct state from "assessed, low risk").

**Summary view + reachability:**
```
frontend/web/src/views/manager/RiskAssessment/
  index.vue                  ← thin shell, fetches summary by contract id
  RiskSummaryHeader.vue      ← overall risk level badge (RAG-colored), contract meta
  RiskFindingsTable.vue      ← severity tag (RAG-colored), clause ref, playbook citation, remediation per row
  RiskExportButton.vue       ← PDF export action
```
- Entry point added next to the existing approve/reject controls in `ContractDetailHeader.vue` (`sales/Contracts/ContractDetail/`) and inside the manager approval queue (`manager/ContractRequests/index.vue`) — same location as the new flag icon above, so both link to the same place.
- Shared types + RAG color/icon maps in `src/types/riskAssessment.ts`, following the `approvalStatusBadge` pattern already in `src/types/contract.ts` (e.g. a `severityBadge: Record<Severity, string>` and `severityIconColor: Record<Severity, string>` lookup).
- API-backed: fetch via a composable in `src/composables/` (e.g. `useRiskAssessment`); demo rows come from `services/ai-service/database/seeders/`, not from hardcoded arrays in the view.

---

## Feature 2: Approve High-Risk Contracts (US-023) — REVISED: gate disabled, advisory-only for now

**Status update (this revision):** per your instruction, the mandatory approval gate described below is now **disabled by default** and does **not** block approval. AI Risk Assessment is advisory only: High/Critical risk shows as a flag/warning on the contract (clickable through to the summary), but a Manager can approve the contract regardless. All of the gate's supporting code (the `contract_approvals` table/model, `HighRiskApprovalGateService`, `ContractApprovalController`, the SLA escalation command) is kept in the codebase, inert behind a config flag (`services.features.high_risk_approval_gate_enabled`, env `HIGH_RISK_APPROVAL_GATE_ENABLED`, defaults `false`), so the original US-023 behavior below can be switched back on later by flipping that flag — no further code changes needed. If the flag is off, the warning flag/pill also respects each user's own "AI Risk Assessment" profile toggle: turning that off hides the flag/pill for that user entirely, in addition to skipping the scan trigger on Create Contract.

**Original AC (dormant while disabled):** Active status locked until approval recorded; approver, timestamp, rationale captured. **Original DoD (dormant):** 0 High Risk contracts reach Active without recorded approval; SLA timer escalation on inaction.

### Data model (`contract-management`, MySQL)

- **`contract_approvals`**: `id`, `contract_id` (FK → `contracts.contract_id`), `risk_level`, `approver_id` (soft ref → users.id), `rationale` text, `decision` (`approved`|`rejected`), `decided_at`, `sla_due_at`, `escalated_at`, timestamps.
- Gate derives "pending high-risk approval" from: latest `risk_assessment_results.risk_level ∈ {high, critical}` (fetched from `ai-service` via internal call) AND no `contract_approvals` row with `decision='approved'` for that contract — avoids a denormalized flag.

### Backend

- Gate added to the existing contract status-transition logic in `contract-management` (needs a read of the actual `app/Actions`/`app/Http/Controllers` transition code before final wiring — not yet opened). Blocks `Active` transition when the condition above holds.
- `POST /contracts/{id}/high-risk-approval` — captures approver (from auth token), rationale (required), timestamp → `contract_approvals` row + `audit_logs` entry.
- **SLA timer — 24 hours, confirmed.** On first flag as High/Critical, set `sla_due_at = flagged_at + 24 hours`. A scheduled command (`php artisan schedule:run`, same cron pattern as other services) runs periodically (e.g. every 15 min) and, for any `contract_approvals` row past `sla_due_at` with no decision, sets `escalated_at` and dispatches a notification.
- **Escalation channel — email only, confirmed.** Uses the existing `notification` service's email pipeline (`email_send_logs`, same `SendXEmail` job pattern already used for e.g. `SendContractExpiryEmail`) — no in-app notification for this specific escalation, no new infra needed. Sent to the pending approver (the Manager who owns the contract); escalating to a different role (e.g. Admin) is not in scope for now since it wasn't requested.

### Frontend

Extends the existing approve/reject UI in `sales/Contracts/ContractDetail/index.vue`, `ContractDetailHeader.vue`, and `manager/ContractRequests/index.vue` rather than a parallel screen: a High-Risk-specific rationale-required modal gates the existing "Approve" action when `contract.riskLevel` is High/Critical. New shared `src/components/shared/LockedStatusBadge.vue` shown on the contract table/detail header while approval is pending.

---

## Feature 3: Vendor AI Suggestions — inside `ai-service`, using pgvector

**Confirmed placement:** lives in `ai-service` (not a separate service), and uses **pgvector** like Risk Assessment. This means vendor suggestion also becomes a retrieval-flavored feature rather than a pure open-ended Gemini prompt: candidate companies Gemini proposes get embedded and compared against embeddings of SBSI's existing `suppliers`/`business_partners` (and against each other across suggestion runs) so near-duplicate suggestions can be detected/deduped and so "similar vendors we already work with" context can be retrieved and fed back into the generation prompt — giving the suggestion step the same grounded, explainable shape as Feature 1, instead of an ungrounded one-shot Gemini call.

**Embedding backfill (new — confirmed no vendor embeddings exist yet):** a one-time Artisan command (`php artisan ai:backfill-vendor-embeddings`) that reads all `suppliers`/`business_partners` rows via internal call to `vendor-management`, embeds a concatenation of their name/industry/region/description via `gemini-embedding-001`, and stores each under `embeddings` with `entity_type='vendor'`, `entity_id` = the source row's id. Re-run (or hook into create/update events later) whenever a new vendor is added, so the retrieval context stays current — for this plan's scope, a manual re-run after adding vendors is sufficient; wiring it to fire automatically on vendor create/update is a natural follow-up, not blocking.

### Data model (`ai-service`, Postgres — reuses `vendor_suggestions`, extends it)

- **`vendor_suggestions`** (already scaffolded) — reused as the "batch" record: `id`, `contract_id` nullable, `requested_by`, `industry_hint`/`region_hint` (add these two columns), `status` (`pending`|`completed`|`failed`), timestamps.
- **`vendor_suggestion_candidates`** (new): `id`, `vendor_suggestion_id` (FK), `candidate_name`, `candidate_industry`, `candidate_region`, `candidate_contact_email` nullable, `candidate_contact_number` nullable, `candidate_address` nullable, `suggestion_score` decimal(5,2) nullable, `suggestion_reason` text, `gemini_raw_response` jsonb nullable, `decision` indexed (`pending`|`accepted`|`dismissed`), `decided_by` nullable, `decided_at` nullable, timestamps.
- `embeddings.entity_type` gains `'vendor_suggestion_candidate'` (reuses existing `suppliers`/`business_partners` embeddings under the existing `'vendor'` entity_type — confirm those are actually being embedded already, or this needs a small backfill job to embed current vendors first).

### Backend (`ai-service`)

- `POST /vendor-suggestions` — `{ industry_hint, region_hint }`. Prompts Gemini (primary `gemini-2.5-flash-lite`, fallback per Feature 1's client) for N candidate PH companies, grounded with SBSI's business context (`SBSI_COMPANY_PROFILE.md` content baked into the system prompt) and, optionally, the retrieved "similar existing vendors" context from pgvector. Requests structured JSON (name, industry, region, plausible contact info, reasoning). Embeds each candidate and writes `vendor_suggestion_candidates` rows.
- `PATCH /vendor-suggestion-candidates/{id}` — `{ decision: 'accepted'|'dismissed' }`.
- On accept: internal call to `vendor-management`'s create-supplier/create-partner endpoint with the candidate's fields (user-edited values, not raw Gemini output — see caveat below), then marks the candidate `accepted`.
- **Caveat, unchanged from prior revision:** Gemini-generated contact details aren't verified data — the frontend must let the user review/edit before final save, matching "autofill" rather than "auto-submit."

### Frontend — full flow (confirmed placement + UX)

**Entry point:** In Vendor Management (`frontend/web/src/views/admin/Partners/index.vue`), a new button labeled **"AI Suggestion"** placed immediately next to the existing manual "Add" button (the `Plus`-icon button in the current header). **Admin-only** — gated the same way the existing add/edit/delete buttons are gated via `hasPermission(...)`, using a new permission key (e.g. `cms.partners.ai_suggest`) rather than hardcoding a role check, consistent with how the rest of this page already reads permissions.

**Toggle:** New "Vendor AI Suggestions" row in `PreferencesCard.vue`, visible only when `role === 'Admin'` (same `isAdmin` computed already used for the existing Admin-only "Login alerts" row) — when off, the "AI Suggestion" button is hidden/disabled on the Partners page. Persisted as `ai_vendor_suggestions_enabled` on `email_preferences`, added in the same migration as `ai_risk_assessment_enabled` above.

**Flow (new route + pages), matching your description exactly:**

```
frontend/web/src/views/admin/VendorSuggestions/
  index.vue                    ← Page 1: suggestion list. Fetches candidates from POST /vendor-suggestions
                                   (or a prior pending batch), shows cards with name/industry/region/score/reason,
                                   checkbox multi-select, "Accept Selected" action.
  VendorSuggestionCard.vue     ← one candidate, checkbox + details
  ReviewSuggestions.vue        ← Page 2: shown after "Accept Selected". Tab bar across the top, one tab
                                   per selected candidate. Each tab = a form (reusing the same fields as
                                   AddPartnerDialog.vue's <AddPartnerForm>) pre-filled from the candidate's
                                   data, editable. User can switch tabs freely (unsaved edits per tab kept
                                   in local state). Saving a tab calls vendor-management's create endpoint
                                   for that one candidate, marks it accepted, and removes that tab from the
                                   bar. Once all tabs are saved, redirect back to /admin/partners.
```

- Routing: `/admin/vendor-suggestions` (list) → `/admin/vendor-suggestions/review` (tabs), added to `router/index.ts` guarded the same way other Admin-only routes are guarded.
- The tab system's per-candidate form state is `reactive()` per open tab (a `Record<candidateId, AddPartnerForm>`), consistent with "Dialogs own their internal form state" — here each *tab* owns its own state instead of a dialog.
- Shared types: extend `src/types/partner.ts` with a `VendorSuggestionCandidate` type, or add `src/types/vendorSuggestion.ts` if it'd bloat `partner.ts` — leaning toward the latter since it's a distinct AI-specific shape (score, reason, raw response) layered on top of the plain `Partner` fields.

---

## Feature 4: Analytics — descriptive + diagnostic, inside `analytics-service`

**Ask:** every data point in the system, descriptive analytics for sure, diagnostic analytics if possible, AI or algorithms where it helps. Predictive still out of scope (not requested this round).

### Descriptive analytics (aggregation — "what happened")

Same metric domains as the prior revision, aggregated into `aggregated_metrics`/`reports`:

| Domain | Metric examples | Source |
|---|---|---|
| Contracts | total/by status/category/region, expiring-soon, avg time-to-approval, renewal pipeline | contract-management |
| Risk/AI | contracts scanned, flagged (by severity), pass/fail rate, avg risk score, most-cited playbook clauses, OCR success rate | ai-service |
| Vendors | supplier/partner counts, by region/industry, active/inactive/suspended, AI-suggestion acceptance rate | vendor-management, ai-service |
| Users/Workflow | counts by role, audit activity volume, approval SLA compliance/escalation counts | auth, all services' audit_logs, contract_approvals |
| Notifications | sent, delivery success/failure, read rates | notification service |

### Diagnostic analytics (the "why" layer — new this revision)

Diagnostic analytics explains *why* a descriptive metric moved, by correlating it against other dimensions already sitting in the aggregated data. Two implementation tracks, both algorithmic (no ML training needed) plus one Gemini-assisted layer:

1. **Algorithmic diagnostics** (`analytics-service`, plain PHP/SQL, no external AI call):
   - **Root-cause breakdowns**: e.g. "risk-flag rate rose this month" → group flagged contracts by category/region/vendor and surface the segment(s) driving the increase (simple group-by + delta-vs-baseline comparison against the prior period).
   - **Correlation checks**: e.g. "which playbook clause is most correlated with contracts that got rejected" — co-occurrence counts between `risk_assessment_findings.playbook_clause_id` and `contract_approvals.decision='rejected'`.
   - **SLA bottleneck analysis**: which approver/stage is driving approval-time increases (reuses the existing `WorkflowBottleneckChart.vue` concept, but backed by real `contract_approvals`/audit-log timestamps instead of the current hardcoded dummy stage durations).
   - These are deterministic, explainable, and cheap — no LLM call, so they run on every scheduled aggregation pass.
2. **AI-assisted diagnostics** (Gemini, optional narrative layer): once the algorithmic breakdown above produces a structured "what changed" result, send that structured summary (not raw data) to Gemini and ask for a one-paragraph plain-language explanation ("Risk flags rose 18% this month, driven mainly by Mindanao-region supplier contracts citing SLA-01 deviations") to display alongside the chart. This keeps the AI role narrow (summarization/explanation of already-computed facts) rather than having Gemini itself compute the diagnosis — safer and cheaper than re-deriving numbers via LLM.

### Backend (`analytics-service`)

- Scheduled jobs (`php artisan schedule:run`, matching other services' cron pattern) pull from each service via internal-service-secret-authenticated HTTP calls (not cross-database joins, preserving the existing spec's schema-isolation design) and write `aggregated_metrics` rows.
- New `diagnostic_insights` table (new migration): `id`, `metric_type`, `period_start`/`period_end`, `finding_summary` text (the algorithmic root-cause result, structured), `ai_narrative` text nullable (the Gemini explanation, when enabled), `generated_at`.
- `GET /analytics/summary`, `GET /analytics/diagnostics` endpoints for the frontend.
- Gemini calls here reuse the same `GeminiClient` pattern from `ai-service` — either duplicated into `analytics-service` (simplest, matches how each service is otherwise self-contained) or called cross-service via an internal endpoint on `ai-service`. Leaning toward **duplicating the thin client** rather than adding an inter-service dependency, since it's a ~50-line Guzzle wrapper, not a shared library — but flagging this as a minor architecture choice you can override.

### Frontend — new page + sidebar item (confirmed placement)

- New sidebar entry **"Analytics"** added to `AdminLayout.vue` (in the existing "Main" or a new group) and `ManagerLayout.vue` — **not** added to `SalesLayout.vue`, since it's Admin/Manager only per your instruction.
- New page:
```
frontend/web/src/views/admin/Analytics/         (shared structure reused by a manager/Analytics/index.vue that imports the same sub-components, same pattern as how Profile/Dashboard already share sub-components across roles)
  index.vue                  ← thin shell, tab or section switcher: Descriptive / Diagnostic
  DescriptiveMetricsGrid.vue  ← KPI cards + charts per domain table above
  DiagnosticInsightsPanel.vue ← root-cause breakdown cards + AI narrative text where available
```
- Route guard: `/admin/analytics` and `/manager/analytics`, both requiring the respective role (following the existing router guard pattern — needs a look at `router/index.ts`'s guard implementation before final wiring, not yet opened in this revision).
- Charts follow the existing `@unovis/vue` + brand-color convention.

---

## Findings from checking the actual codebase (resolves 3 of the prior open questions)

- **`documents.scan_status`/`scan_result` is ClamAV malware scanning** (`app/Services/MalwareScannerService.php`, `app/Jobs/ScanUploadedDocument.php`) — values are `pending`/`clean`/`infected`/`unavailable`/`skipped`, and `scan_result` holds a virus name when infected. **This is not OCR/text extraction.** No contract-text-extraction pipeline exists anywhere in the codebase today. Feature 1's RAG pipeline therefore needs a new text-extraction step (PDF/DOCX → plain text) built from scratch before chunking/embedding can happen — this is now folded into Feature 1's scope below rather than left as an open question.
- **`email_preferences` (notification service) is confirmed as the right extension point** for both new toggles. It already holds several per-user boolean settings (`email_notifications_enabled`, `contract_expiry_alerts`, `system_alerts_enabled`, `sms_notifications_enabled`, `login_alerts_enabled`) added incrementally via their own migrations (see `2026_06_14_000001_add_extra_fields_to_email_preferences_table.php`), with a matching `UpdateEmailPreference` action, request, resource, and `useEmailPreferences` frontend composable already round-tripping this exact shape. Adding `ai_risk_assessment_enabled` and `ai_vendor_suggestions_enabled` via the same incremental-migration pattern is a direct, low-risk fit — no new table/service needed.
- **No vendor embeddings exist yet** (`services/vendor-management` has no embedding code, and `ai-service`'s `embeddings` table has no seed data). Feature 3 needs a one-time backfill job that embeds existing `suppliers`/`business_partners` rows under `entity_type='vendor'` before the "compare against existing vendors" retrieval step can work — folded into Feature 3's scope below.

## Open questions — resolved this revision

1. **Escalation channel** — email only, confirmed. See Feature 2 above.
2. **Severity color mapping** — going with **RAG (Red/Amber/Green)** status colors, confirmed. This is a deliberate, scoped exception to `AGENTS.md`'s brand-colors-only rule: severity/risk indicators use the industry-standard RAG convention (red = high/critical, amber = medium, green = low) instead of the three brand navy/blue shades, since risk severity needs to be instantly scannable and RAG is the universal convention for exactly that. All other UI in the AI Risk Assessment screens (headers, buttons, layout chrome) still follows the brand palette — only the severity tags/badges/icons themselves use RAG. Documented as an explicit exception rather than a silent rule violation.

No open questions remain blocking Features 1 and 2. Feature 3/4 have no outstanding questions either — build order below.

## Suggested build order

1. **Feature 2 (approval gate + 24h SLA timer)** — self-contained, no Gemini dependency, can start immediately.
2. **Feature 1 (text extraction + playbook seed + RAG pipeline + summary/PDF + Create Contract trigger + profile toggle)** — biggest lift; unblocked now that the Gemini key/model config is in place and the text-extraction gap is scoped in.
3. **Feature 3 (vendor embedding backfill + Vendor AI Suggestion inside `ai-service` + Vendor Management UI flow)** — unblocked now.
4. **Feature 4 (descriptive + diagnostic analytics)** — goes last since it aggregates from the others' data.
