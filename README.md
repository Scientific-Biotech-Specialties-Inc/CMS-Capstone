# CMS Capstone

Contract Management System. This project is built using a microservices architecture with Laravel for the backend services and Vue 3 (Vite) for the frontend.

## Prerequisites

Before you begin, ensure you have the following installed on your machine:
- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

## Project Architecture

The application is composed of independent Laravel microservices plus a Vue 3 SPA, coordinated through `docker-compose.yml` and joined to the external `shared-capstone-network` Docker network. **`docker-compose.yml` is the authoritative source for ports, commands, and profiles.**

### Frontend
- **web**: Vue 3 + Vite + TypeScript SPA, served under the base path `/cms/` (container port `5001`, no build step — runs the Vite dev server).

### Backend Services (Laravel)
- **vendor-management**: Suppliers, business partners, vendor↔contract associations.
- **contract-management**: Contracts, documents, approval/workflow engine, audit logs, queues.
- **search**: Global search backed by **Meilisearch** (not Elasticsearch).
- **notification**: In-app notifications, email logs/preferences, contract-expiry alerts.
- **analytics-service**: Aggregated metrics and reports; own MySQL schema `cms-analytics-db`.
- **ai-service**: Risk assessment, vendor suggestions, OCR, embeddings; Postgres + **pgvector** (`cms-ai-db`).

### Databases & Supporting Infrastructure
- **mysql**: Primary relational store (`crms-db`, `cms-analytics-db`).
- **postgres** (`pgvector/pgvector:pg16`): Vector store for the AI service.
- **redis**: Cache/queues.
- **meilisearch**: Search engine.
- **clamav**: Optional antivirus sidecar — `scanning` profile only.
- **phpmyadmin / pgadmin**: Optional DB GUIs — `gui` profile only.

MongoDB is **not** a local container; if a script needs it, it uses the Atlas `MONGODB_URI` from the root `.env`.

### Shared Gateway (Nginx, separate repo)

A single Nginx reverse proxy lives in the sibling **`capstone-auth-module`** repository and is the recommended entry point. It serves the Auth UI at `/`, this SPA at `/cms/`, and proxies `/api/vendor`, `/api/contract`, `/api/search`, `/api/notification`, `/api/ai`, `/api/analytics`, `/api/auth`. See `SHARED_GATEWAY.md`.

```bash
docker compose -f ../capstone-auth-module/docker-compose.yml up -d
```

Login and most frontend API calls depend on it, because `frontend/web/.env` points every `VITE_*_API_URL` at `http://localhost:5173/api/...`.

---

## 🚀 Getting Started & Initialization

Follow these steps to initialize and run the entire system.

### 1. Environment Setup

Environment files are **git-ignored**, so every fresh clone/branch switch on a new machine needs them created locally.

**a) Root `.env`** — read by `docker-compose.yml`. Required keys:

```dotenv
MYSQL_PASSWORD=...
MYSQL_DATABASE=crms-db

POSTGRES_DB=cms-ai-db
POSTGRES_USER=postgres
POSTGRES_PASSWORD=...

INTERNAL_SERVICE_SECRET=...
FIELD_ENCRYPTION_KEY=...        # base64, 32-byte key
PGADMIN_DEFAULT_EMAIL=admin@admin.com
PGADMIN_DEFAULT_PASSWORD=...
# optional, defaults to "changeme"
MEILI_MASTER_KEY=...
```

**b) Per-service `.env`** — copy from each example (all six services):

```bash
cp services/vendor-management/.env.example   services/vendor-management/.env
cp services/contract-management/.env.example services/contract-management/.env
cp services/search/.env.example              services/search/.env
cp services/notification/.env.example        services/notification/.env
cp services/analytics-service/.env.example   services/analytics-service/.env
cp services/ai-service/.env.example          services/ai-service/.env

# Frontend
cp frontend/web/.env.example frontend/web/.env
```

### ⚠️ Important: Docker Database Configuration
In every backend `.env`, use the Docker-internal hostnames:

- `DB_HOST=mysql` (or `postgres` for `ai-service`) — this is the compose service name
- `DB_PORT=3306` (MySQL) / `5432` (Postgres)
- `DB_DATABASE=crms-db` (`cms-analytics-db` for analytics, `cms-ai-db` for AI)
- `DB_USERNAME=root`, `DB_PASSWORD=<root MYSQL_PASSWORD>`

*Do not use `127.0.0.1` as the `DB_HOST` when running inside Docker.*

### ⚠️ Important: Field encryption key

`FIELD_ENCRYPTION_KEY` must be **the same value in every service that encrypts columns** — `vendor-management`, `contract-management`, `notification`, `search`, and `analytics-service`. If it is empty, `EncryptionService` logs an error and silently stores plaintext.

### ⚠️ Important: Malware scanning

`CLAMAV_ENABLED` defaults to `true`, but the `clamav` container only starts with the `scanning` profile. For normal development either:

- set `CLAMAV_ENABLED=false` in `services/contract-management/.env`, or
- set `CLAMD_HOST=clamav`, `CLAMD_PORT=3310` and start with `docker compose --profile scanning up -d`.

Otherwise every upload enqueues a scan job that times out and fail-opens with a `scan_warning`.

### 2. Build and Start the Containers

```bash
docker compose up -d --build
```

Every Laravel container's `command` already performs, on each start:

1. `composer install --no-scripts` (dependencies are kept in the `*-vendor` named volumes),
2. `php artisan migrate --force` (after a short DB warm-up sleep),
3. the relevant queue worker(s) and scheduler in the background,
4. `php artisan serve --host=0.0.0.0 --port=8000`.

The `web` container runs `npm install && npm run dev -- --host 0.0.0.0 --port 5001`.

**No manual `composer install` or `php artisan migrate` is required after pulling a branch.** Only run the steps below on a genuinely fresh setup.

### 3. Optional one-time initialization

```bash
# Only if a service .env has an empty APP_KEY
docker compose exec contract-management php artisan key:generate   # repeat per service

# Demo/test data — the only place dummy data lives
docker compose exec vendor-management php artisan db:seed
docker compose exec contract-management php artisan db:seed
docker compose exec analytics-service php artisan db:seed
docker compose exec ai-service php artisan db:seed
docker compose exec notification php artisan db:seed

# Serve uploaded documents from the public disk (if needed)
docker compose exec contract-management php artisan storage:link
```

### 4. Access the Application

Through the shared gateway (**recommended**, requires `capstone-auth-module` running):

- **Login / Auth UI**: `http://localhost:5173/`
- **This SPA**: `http://localhost:5173/cms/`

Direct service ports (useful for debugging; note the `/cms/` base path):

- **Frontend**: `http://localhost:5001/cms/`
- **Vendor API**: `http://localhost:8001`
- **Contract API**: `http://localhost:8002`
- **Search API**: `http://localhost:8003`
- **Notification API**: `http://localhost:8004`
- **Analytics API**: `http://localhost:8005`
- **AI API**: `http://localhost:8006`

---

## 🛠️ Common Docker Commands

Here are some helpful commands for managing your project containers:

**Stop all containers:**
```bash
docker compose down
```

**View logs for all services:**
```bash
docker compose logs -f
```

**View logs for a specific service (e.g., frontend):**
```bash
docker compose logs -f web
```

**Access a container's shell (e.g., vendor-management):**
```bash
docker compose exec vendor-management bash
```

**Run a single artisan command (e.g., clear cache):**
```bash
docker compose exec vendor-management php artisan cache:clear
```

**Install a frontend package (never on the host):**
```bash
docker compose exec web npm install <package>
```

---

## 🛠️ Troubleshooting

### ❌ `SQLSTATE[HY000] [2002] Connection refused`
If you see this error when running migrations, it is usually caused by one of two things:

1.  **Incorrect DB_HOST**: Ensure your `.env` file has `DB_HOST=mysql` (or `postgres` for `ai-service`). When running inside Docker, services refer to each other by their service names, not `127.0.0.1`.
2.  **MySQL is not ready**: MySQL takes about 10-15 seconds to fully initialize the first time the container starts. Even if the container is "Running", the database engine might still be starting up.
    - **Fix**: Wait 20 seconds and try the command again.
    - **Check Logs**: Run `docker compose logs -f mysql` to see if the database is ready for connections.

### ❌ `Access denied for user 'root'@'%'`
Ensure `DB_PASSWORD` in the service `.env` matches `MYSQL_PASSWORD` in the **root** `.env` (both are interpolated into `docker-compose.yml` as `MYSQL_ROOT_PASSWORD`).

### ❌ Frontend loads but every request 404s / login fails
The SPA is served under `/cms/` and its `VITE_*_API_URL` values point at the gateway on `:5173`. Start the sibling Auth project:
```bash
docker compose -f ../capstone-auth-module/docker-compose.yml up -d
```

### ❌ Encrypted columns look like plaintext / `Encryption service not configured`
`FIELD_ENCRYPTION_KEY` is missing in that service's `.env`. Use the same base64 key in `vendor-management`, `contract-management`, `notification`, `search`, and `analytics-service`.
