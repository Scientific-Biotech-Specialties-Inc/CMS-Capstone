# Project Structure

This document outlines the file structure of the CMS-Capstone project and explains the purpose of the key directories and files.

## High-Level Directory Layout

```text
cms-capstone/              # root directory
├── docker/                 # Custom docker configurations (if any)
├── frontend/               # User interface applications
│   └── web/                # Main Vue 3 / Vite frontend (Tailwind v4)
├── scripts/                # Legacy-to-new data sync client + docs
├── services/               # Backend microservices (Laravel)
│   ├── ai-service/         # Risk assessment, OCR, suggestions (Postgres + pgvector)
│   ├── analytics-service/  # Aggregated metrics and reports
│   ├── contract-management/# Handles contract logic
│   ├── notification/       # Handles system alerts and messages
│   ├── search/             # Handles Meilisearch integration
│   └── vendor-management/  # Handles vendor profiles and data
├── .env                    # Root env for docker-compose (git-ignored, required)
├── docker-compose.yml      # Docker orchestration file for all services
├── AGENTS.md               # Agent rules (DESIGN.md is the frontend source of truth)
├── README.md               # Initialization and usage instructions
└── STRUCTURE.md            # This file
```

---

## Detailed Directory Breakdown

### 1. `frontend/web/`
This directory contains the main web application built using Vue 3, Vite, TypeScript, and Tailwind CSS v4.
- `src/`: Contains the actual source code of the frontend application.
  - `views/` — route-level screens grouped by role (`admin/`, `manager/`, `sales/`, `shared/`), each split into a folder with `index.vue` + sub-components.
  - `components/` — reusable UI, including `components/shared/` primitives.
  - `composables/` — all API access and state (`useAuth`, `useContractStore`, `useNotifications`, …). This is the only layer that talks to the gateway.
  - `router/`, `layouts/`, `types/`, `constants/`, `utils/`.
- `public/`: Static assets like images and favicons that do not require building.
- `skills/vue-expert/`: In-repo Vue 3 conventions skill read by AI agents.
- `package.json`: Lists the frontend dependencies (Vue, Vite, Tailwind v4, reka-ui, Lucide) and scripts (`dev`, `build`, `test:e2e`, `test:unit`).
- `vite.config.ts`: Vite config — dev server on `0.0.0.0:5001` with base path `/cms/`.
- `.env.example`: Template for environment variables (gateway API URLs).

### 2. `services/`
The application logic is split into multiple independent Laravel microservices. Each directory here represents a full Laravel project with its own dependencies, database connection, and API routes.

#### General Structure of a Laravel Service
Inside each service (e.g., `services/vendor-management/`), you will find the standard Laravel structure:
- `app/`: Contains the core code of the service (Controllers, Models, Middleware).
- `config/`: Configuration files for the service.
- `database/`: Migrations and seeders specific to this service's database tables.
- `routes/`: API route definitions (`api.php`).
- `tests/`: Automated tests for the service.
- `Dockerfile`: The specific Docker configuration to build the PHP/Laravel environment for this service.
- `.env.example` / `.env`: Environment variables specific to this service (DB credentials, API keys).

#### Specific Services
- **`vendor-management/`**: Responsible for creating, updating, and managing vendor information (suppliers, business partners, vendor↔contract associations).
- **`contract-management/`**: Responsible for the lifecycle of contracts, documents, approval workflows, and audit logging.
- **`search/`**: Syncs data from other services to **Meilisearch** for fast, global querying.
- **`notification/`**: Listens for events across the system and dispatches emails, in-app notifications, etc.
- **`analytics-service/`**: Aggregates metrics and generates reports in its own MySQL schema (`cms-analytics-db`).
- **`ai-service/`**: Risk assessment, vendor suggestions, OCR extraction, and embeddings on Postgres + pgvector (`cms-ai-db`).

### 3. Root Files
- **`docker-compose.yml`**: The central orchestration file. It defines how all the microservices, the frontend, and the infrastructure (databases, Redis, Meilisearch) run together on the shared `shared-capstone-network`. It is the authoritative source for ports, commands, and profiles.
- **`.env`**: Root environment file consumed by `docker-compose.yml` (MySQL/Postgres credentials, `INTERNAL_SERVICE_SECRET`, `FIELD_ENCRYPTION_KEY`). Git-ignored — must exist locally.
- Per-service `.env` files in `services/*/` and `frontend/web/` are also git-ignored; copy them from the matching `.env.example`.
