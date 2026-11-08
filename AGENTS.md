# CMS Capstone — Codex Rules

## Required References

**Always consult these canonical references before writing any code:**

- **Canonical ONE UI Design System** — `DESIGN.md` (Mandatory shared specification for all 4 subsystems: CMS/CRMS, SERMS, PRS, and TS). **This is the single source of truth for every frontend decision** — colors, typography, spacing, elevation, component anatomy, states, loading patterns, toast copy, badges, and layout. When anything else disagrees with `DESIGN.md`, `DESIGN.md` wins.
- **Vue expert skill** — `frontend/web/skills/vue-expert/SKILL.md` (and its `references/` folder) — Vue 3 / TypeScript architecture and code conventions.
- **Backend tasks** — `LARAVEL-SKILL.md` (Laravel conventions) and `DATABASE_TABLES.md` (schema and cross-service references).

These override any default behavior. Read them at the start of every task.

> `context/frontend-skills/frontend-rule.md` is **legacy/secondary**. It is not required reading and is fully superseded by `DESIGN.md` for design tokens, typography, spacing, and components. Use it only for its general "avoid AI-slop UI" guidance.

## Stack Pinning

- **Frontend (`frontend/web`)**: Vue 3 + Vite + TypeScript + **Tailwind CSS v4** (`@tailwindcss/vite`, configured in `src/style.css` via `@theme` / CSS variables) + **reka-ui / shadcn-vue** primitives + `lucide-vue-next` icons.
- **Do not downgrade to Tailwind CSS 3.4.** The 3.4 pin in `DESIGN.md` applies only to the external `web-interface` project (Auth module), **not** to `frontend/web`.
- **State** lives in composables (`src/composables/use*.ts`, e.g. `useContractStore`, `useAmendmentStore`, `useAuth`). Pinia is **not** installed — do not introduce it.

## Runtime Facts (don't re-derive these every session)

- **Frontend**: `frontend/web` — Vue 3 + Vite, dev server on `5001`, base path `/cms/`, Tailwind **v4**.
- **Gateway**: sibling repo `capstone-auth-module` runs Nginx on `5173` (`/` = Auth UI, `/cms/` = this SPA, `/api/*` = services). Required for login and for every `VITE_*_API_URL`.
- **Start**: `docker compose up -d` — each service auto-runs `composer install --no-scripts`, `php artisan migrate --force`, queue workers, and `php artisan serve`. No manual reinstall after a `git pull`.
- **Data stores**: MySQL `crms-db` (3307) + `cms-analytics-db`, Postgres `cms-ai-db` (5433, pgvector), Redis, Meilisearch.
- **Env files are git-ignored**: root `.env` (compose vars), `services/*/.env`, `frontend/web/.env`. They survive branch switches — do not recreate them.
- **ClamAV** only runs under the `scanning` profile; otherwise uploads fail-open with a `scan_warning`.
- **Seeders** are the only source of demo data (`services/<service>/database/seeders/`).

## Canonical ONE UI Design System (`DESIGN.md`) Directives

All AI agents and developers must strictly follow the specifications in `DESIGN.md`:

1. **Brand Color Palette**:
   - Primary: `#252578` (Brand Navy)
   - Accent: `#2E85D8` (Brand Accent Blue)
   - Deep Surface: `#3A3A85` / Border `#4E4E91` / `#2F2F73`
   - Page Background: `#F8F9FA` / Pure White `#FFFFFF`
   - Semantic Statuses: Error `#EF4444`, Warning/Pending `#F59E0B`, Success/Approved `#10B981`
   - Never inject arbitrary colors or off-brand gradients.
2. **Typography Standards**:
   - **Primary Font**: `Poppins` (Headings, titles, buttons, navigation, table headers, forms).
   - **Secondary Font**: `Montserrat` (Descriptions, helper text, subtitles, table subtext, timestamps).
3. **8px Grid Hierarchy**:
   - Margins & padding must strictly adhere to the 8px grid (`8px`, `16px`, `24px`, `32px`, `48px`). 4px is reserved for micro-badges.
4. **Icons**:
   - Exclusively import icons from `lucide-vue-next`. Never use external icon fonts or unvetted SVGs.
5. **Loading States**:
   - **Skeleton Loaders**: Mandatory for data fetching and layout rendering (tables, cards, profiles).
   - **Spinner Graphics (`Loader2`)**: Reserved strictly for actions, button submissions, and network uploads.
6. **Toast Notifications**:
   - Fixed Top-Right (`top: 24px; right: 24px; z-index: 1200;`) with a 4px vertical accent stripe (`border-l-4`), auto-dismiss timer of 4 seconds, and timer pauses on hover.
7. **Status Badges**:
   - Must strictly be formatted in **Title Case** (e.g. `Active`, `Pending`, `Approved`, `Rejected`), never ALL-CAPS.
8. **Sidebar Brand Lockup**:
   - Subsystem Name: `CONTRACT` (Poppins Bold uppercase) on top, `MANAGEMENT SYSTEM` (Poppins Regular) underneath.

## Component Architecture

**Always break large view files into sub-components.** Any view or component that grows beyond ~150 lines must be split into a folder-based structure:

```
views/admin/PageName/
  index.vue          ← thin shell: owns all state, logic, and data
  SomeTable.vue      ← receives props, emits events
  SomeDialog.vue     ← self-contained with internal form state
  ...
```

Rules:
- `index.vue` holds `ref`, `reactive`, `computed`, and event handlers. Its template is a short composition of sub-components.
- Sub-components receive data via `defineProps<T>()` and communicate up via `defineEmits<T>()`.
- Dialogs own their internal form state (touched, validation, reset). They emit `submit` with the final data; the parent calls the service through its composable, then refreshes/reconciles the list from the API response.
- Shared types go in `src/types/<domain>.ts` and are imported everywhere — never re-declare interfaces across files.
- Shared UI patterns (toggle switches, setting cards, etc.) go in `src/components/shared/`.

## Vue 3 Rules

- Always use `<script setup lang="ts">` — no Options API, no `defineComponent`.
- Use `ref()` for primitives, `reactive()` for objects/forms.
- Use `provide` / `inject` only for deep trees (e.g. settings tabs sharing a config object). Prefer props+emits for direct parent↔child.
- No `any` casts unless unavoidable; prefer explicit TypeScript types.

## Dependencies

Install new packages via Docker only:
```
docker compose exec web npm install <package>
```

## Data & API Integration

**This project is API-backed — views must not ship hardcoded dummy arrays.**

- All data loads from the Laravel microservices through the composables in `frontend/web/src/composables/` (e.g. `useAuth`, `usePartners`, `useVendorService`, `useContractStore`, `useNotifications`, `useRiskAssessment`, `useAnalytics`). Base URLs come from `VITE_*_API_URL` in `frontend/web/.env`, which points at the shared Nginx gateway (`http://localhost:5173/api/...`).
- **Sample / demo / test data lives only in database seeders** — `services/<service>/database/seeders/` (plus factories in `database/factories/`). Never invent rows inside a view.
- Narrow, explicitly labelled dev fallbacks are allowed only where a backend record may legitimately not exist yet (e.g. `?workflowMock=` scenarios in the workflow tracker, or seeded-id fallbacks in request/amendment detail pages). Keep them defensive and documented — they must not become the default render path.
- Consume Laravel response shapes as documented in `LARAVEL-SKILL.md`: single `{ "data": {...} }`, paginated `{ "data": [...], "links": {...}, "meta": {...} }`, validation `{ "message": "...", "errors": {...} }`.
- Use `useApiCache` / existing composables for caching instead of ad-hoc fetch layers, and surface failures through the standard toast pattern rather than silently rendering placeholder data.

## Task Workflow

**For every task the user gives, always come up with a plan first before executing.**

1. State what files will be created or modified.
2. List the specific changes per file (types, props, emits, template additions, etc.).
3. Call out any potential gotchas (TypeScript constraints, overflow clipping, shared component impact, etc.).
4. Wait for implicit or explicit user confirmation, then execute.

Do not write any code until the plan is laid out.
