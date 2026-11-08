# CMS Capstone — Claude Code Rules

## Required References

**Always consult these canonical references before writing any code:**

- **Canonical ONE UI Design System** — `DESIGN.md`. **The single source of truth for all frontend visuals**: colors, typography, 8px spacing, elevation, component anatomy, states, skeletons/spinners, toasts, badges, and layout. If any other doc disagrees, `DESIGN.md` wins.
- **Vue expert skill** — `frontend/web/skills/vue-expert/SKILL.md` (and its `references/` folder) — Vue 3 / TypeScript conventions.
- **Backend tasks** — `LARAVEL-SKILL.md` and `DATABASE_TABLES.md`.

> `context/frontend-skills/frontend-rule.md` is **legacy/secondary** and not required reading — `DESIGN.md` supersedes it for all design decisions.

## Stack Pinning

- `frontend/web` = Vue 3 + Vite + TypeScript + **Tailwind CSS v4** (`@tailwindcss/vite`, tokens live in `src/style.css`) + reka-ui/shadcn-vue primitives + `lucide-vue-next`.
- **Do not downgrade to Tailwind 3.4.** That pin applies only to the external `web-interface` (Auth module) project.
- State lives in composables (`src/composables/use*.ts`). Pinia is not installed — do not add it.

## Runtime Facts

- `frontend/web` — Vue 3 + Vite, port `5001`, base path `/cms/`, Tailwind **v4**.
- Gateway lives in the sibling `capstone-auth-module` repo on `5173` (`/cms/` + `/api/*`); required for login and all `VITE_*_API_URL` calls.
- `docker compose up -d` is enough — containers auto-run `composer install`, `artisan migrate`, queue workers, and `artisan serve`.
- Root `.env`, `services/*/.env`, and `frontend/web/.env` are git-ignored and already exist locally — do not recreate them.
- Demo data comes only from `database/seeders/`.

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

## Design System

- Follow `DESIGN.md` for everything visual. No local reinterpretation of tokens.
- Brand colors only: `#252578` (navy), `#2E85D8` (blue), `#2F2F73` (dark navy). No off-brand colors.
- Fonts: **Poppins** primary (headings, buttons, nav, table headers, forms); **Montserrat** secondary (descriptions, helper text, timestamps).
- Avatar palette restricted to the three brand colors.
- Table headers: `bg-black/[0.018]` background, `border-black/[0.04]` bottom border.
- Pagination: use `<TablePagination>` (`src/components/shared/TablePagination.vue`) — a centered `< 1 2 3 4 5 >` control with a sliding 5-page window; out-of-range page numbers are visible but disabled. All tables use 15 records per page (`PAGE_SIZE = 15`, sent as `per_page`).

## Dependencies

Install new packages via Docker only:
```
docker compose exec web npm install <package>
```

## Data & API Integration

**No dummy data in views — this project is API-backed.**

- Views/components load data through `src/composables/use*.ts`, which call the microservices via the shared gateway (`VITE_*_API_URL` in `frontend/web/.env` → `http://localhost:5173/api/...`).
- Sample/test data belongs in `services/<service>/database/seeders/` and `database/factories/` only.
- Only keep explicitly labelled dev fallbacks where a record may not exist yet (e.g. `?workflowMock=`), never as the default path.
- Follow the Laravel response shapes from `LARAVEL-SKILL.md`; surface errors via the standard toast pattern.

## Task Workflow

**For every task the user gives, always come up with a plan first before executing.**

1. State what files will be created or modified.
2. List the specific changes per file (types, props, emits, template additions, etc.).
3. Call out any potential gotchas (TypeScript constraints, overflow clipping, shared component impact, etc.).
4. Wait for implicit or explicit user confirmation, then execute.

Do not write any code until the plan is laid out.
