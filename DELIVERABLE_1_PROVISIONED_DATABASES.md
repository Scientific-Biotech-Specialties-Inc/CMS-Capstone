# Deliverable 1: Provisioned Databases (Proof of Polyglot Persistence)

**Project:** CRMS Capstone — Contract & Relationship Management System
**Document Version:** 1.0
**Date:** July 17, 2026

---

## 1.1 Overview of Polyglot Persistence

CRMS-capstone is built as a set of independent Laravel microservices (`vendor-management`, `contract-management`, `search`, `notification`, `analytics-service`, `ai-service`) plus a Vue frontend, all wired together in `docker-compose.yml` on a shared Docker network (`shared-capstone-network`). Rather than forcing every service onto a single database engine, the project deliberately uses **polyglot persistence** — pairing each data shape with the storage engine best suited for it.

The stack is grouped into three categories for this deliverable:

| Category | Technology | Engine Type | Status |
|---|---|---|---|
| **Legacy** | MySQL (`cms-db`) | Relational (OLTP) | ✅ Running |
| **Legacy** | MongoDB Atlas (`crms-db`) | Document (NoSQL) | ✅ Running |
| **Legacy** | Meilisearch | Full-Text Search Engine | ✅ Running |
| **New** | PostgreSQL + pgvector (`cms-ai-db`) | Relational + Vector | ✅ Running |
| **New** | MySQL schema (`cms-analytics-db`) | Relational (OLAP-style) | ✅ Running |
| **Cache** | Redis (`cms-redis`) | Key-Value / In-Memory | ✅ Running |

The "Legacy" group represents the original four-service stack (vendor management, contract management, search, notification) that the capstone was originally built on. The "New" group is the Analytics & AI enhancement layer added on top of it (per the `analytics-ai-services` spec), which deliberately reuses the shared MySQL container for its own isolated schema and introduces a brand-new Postgres/pgvector container for AI embeddings and similarity search. Redis is shared infrastructure available to both the legacy services and the new services for caching.

---

## 1.2 Database 1: Legacy Databases

### 1.2.1 MySQL — `cms-db`

| Attribute | Details |
|---|---|
| Database Type | Relational (OLTP) |
| Technology | MySQL 8.0 (Docker image `mysql:8.0`) |
| Container | `cms-mysql` |
| Purpose | Core transactional data for vendor-management, contract-management, search, and notification services (contracts, suppliers, business partners, documents metadata, notifications, audit logs) |
| Status | ✅ Running |
| Host (internal / external) | `mysql` (service name) / `localhost` |
| Port (internal → host) | 3306 → 3307 |
| Database Name | `cms-db` |
| Access | Read/Write (per-service Eloquent connections) |

**Connection String (redacted):**
```
mysql://root:********@localhost:3307/cms-db
```

**Python Connection Example:**
```python
import mysql.connector

conn = mysql.connector.connect(
    host="localhost",
    port=3307,
    user="root",
    password="********",  # REDACTED — see .env MYSQL_PASSWORD
    database="cms-db"
)
```

**Verification:**
```bash
docker compose ps mysql
docker compose exec mysql mysqladmin ping -h localhost
```

**Screenshot:**
{Screenshot Here}

---

### 1.2.2 MongoDB — `crms-db` (MongoDB Atlas)

| Attribute | Details |
|---|---|
| Database Type | Document Store (NoSQL) |
| Technology | MongoDB Atlas (cloud-hosted cluster, driver: `mongodb/laravel`) |
| Purpose | Stores document metadata for uploaded contract files (the `documents` collection) in the `contract-management` service, decoupled from the relational `contracts` table in MySQL |
| Status | ✅ Running (cloud-hosted, not a local container) |
| Cluster | `capstone-cluster.pyexefx.mongodb.net` |
| Database Name | `crms-db` |
| Collection | `documents` |
| Access | Read/Write via `App\Models\Document` (Eloquent-Mongo hybrid model) |

**Connection String (redacted):**
```
mongodb+srv://capstone_user:********@capstone-cluster.pyexefx.mongodb.net/crms-db?appName=Capstone-Cluster
```

**Laravel Model Reference:**
```php
// services/contract-management/app/Models/Document.php
class Document extends BaseDocument
{
    protected $connection = 'mongodb';
    protected $collection = 'documents';
    protected $primaryKey = '_id';
}
```

**Why MongoDB here:** document metadata (file name, path, type, size, scan status) is loosely structured and grows independently of the relational contract schema, making a document store a better fit than adding more nullable columns to a MySQL table.

**Screenshot:**
{Screenshot Here}

---

### 1.2.3 Meilisearch — Full-Text Search Engine

| Attribute | Details |
|---|---|
| Database Type | Search Engine (inverted index) |
| Technology | Meilisearch v1.8 (Docker image `getmeili/meilisearch:v1.8`) |
| Container | `cms-meilisearch` |
| Purpose | Powers fast full-text search over contracts/vendors for the `search` service |
| Status | ✅ Running |
| Host (internal / external) | `meilisearch` (service name) / `localhost` |
| Port | 7700 |
| Access | Read/Write via Meilisearch HTTP API, secured with a master key |

**Connection Details (redacted):**
```
http://localhost:7700
Authorization: Bearer ********  (MEILI_MASTER_KEY)
```

**Verification:**
```bash
curl http://localhost:7700/health
```

**Screenshot:**
{Screenshot Here}

---

## 1.3 Database 2: New Databases (Analytics & AI Enhancement)

### 1.3.1 PostgreSQL + pgvector — `cms-ai-db`

| Attribute | Details |
|---|---|
| Database Type | Relational + Vector (similarity search) |
| Technology | PostgreSQL 16 with pgvector extension (Docker image `pgvector/pgvector:pg16`) |
| Container | `cms-postgres` |
| Purpose | Dedicated database for the `ai-service`: risk assessment results, vendor suggestions, OCR extractions, and `embeddings` (1536-dim `vector` column) for document/vendor similarity search |
| Status | ✅ Running |
| Host (internal / external) | `postgres` (service name) / `localhost` |
| Port (internal → host) | 5432 → 5433 |
| Database Name | `cms-ai-db` |
| Access | Read/Write via `ai-service` (Laravel, `pdo_pgsql`) |

**Why a new container instead of reusing MySQL:** MySQL 8.0 has no mature native vector column type comparable to pgvector's `vector(n)` type, so a dedicated Postgres+pgvector instance was introduced specifically for embedding-based similarity search — this is the clearest example of "new" polyglot persistence in the project, since it's a technology none of the legacy services use.

**Connection String (redacted):**
```
postgresql://postgres:********@localhost:5433/cms-ai-db
```

**Python Connection Example:**
```python
import psycopg2

conn = psycopg2.connect(
    host="localhost",
    port=5433,
    user="postgres",
    password="********",  # REDACTED — see .env POSTGRES_PASSWORD
    dbname="cms-ai-db"
)
```

**Key table — `embeddings`:**
```sql
CREATE EXTENSION IF NOT EXISTS vector;

-- embedding column: pgvector, fixed 1536 dimensions
-- entity_type: 'document' or 'vendor'
-- entity_id: soft reference to documents/suppliers/business_partners
```

**Verification:**
```bash
docker compose exec postgres pg_isready -U postgres -d cms-ai-db
```

**Screenshot:**
{Screenshot Here}

---

### 1.3.2 MySQL Schema — `cms-analytics-db`

| Attribute | Details |
|---|---|
| Database Type | Relational (analytical/reporting) |
| Technology | MySQL 8.0 — same `cms-mysql` container, isolated schema |
| Purpose | Dedicated schema for the `analytics-service`: `aggregated_metrics` and `reports` tables, sourced from contract-management, vendor-management, notification, search, and ai-service data |
| Status | ✅ Running |
| Host (internal / external) | `mysql` (service name) / `localhost` |
| Port (internal → host) | 3306 → 3307 |
| Database Name | `cms-analytics-db` |
| Access | Read/Write via `analytics-service` |

**Why reuse the MySQL container:** Analytics data is still relational and low-volume enough that a second MySQL instance wasn't justified. Isolating it into its own schema (`cms-analytics-db`) rather than mixing it into `cms-db` keeps analytical/reporting writes separate from the operational OLTP tables, while avoiding a second database engine for this layer.

**Connection String (redacted):**
```
mysql://root:********@localhost:3307/cms-analytics-db
```

**Schema Creation (idempotent, run at container startup):**
```sql
CREATE DATABASE IF NOT EXISTS `cms-analytics-db`;
```

**Screenshot:**
{Screenshot Here}

---

## 1.4 Database 3: Cache — Redis

| Attribute | Details |
|---|---|
| Database Type | Key-Value / In-Memory Cache |
| Technology | Redis 7 (Docker image `redis:7`) |
| Container | `cms-redis` |
| Purpose | Shared caching layer available to both the legacy service stack (contract-management, vendor-management, notification) and the new analytics/AI layer, intended for caching frequently-read data and AI/analytics results to reduce repeated computation |
| Status | ✅ Running |
| Host (internal / external) | `redis` (service name) / `localhost` |
| Port | 6379 |
| Access | Read/Write |

**Connection String (redacted):**
```
redis://localhost:6379
```

**Python Connection Example:**
```python
import redis

r = redis.Redis(
    host="localhost",
    port=6379,
    db=0,
    decode_responses=True
)
```

**PHP (Laravel) Connection:**
```dotenv
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Verification:**
```bash
docker compose exec redis redis-cli ping
# PONG
```

**Screenshot:**
{Screenshot Here}

> Note: Redis is provisioned and reachable by every service on `shared-capstone-network`. In the current legacy services, `CACHE_STORE`/`QUEUE_CONNECTION` are configured to `database` rather than `redis` — Redis connection variables are set and the service is running, positioning it as the shared caching layer for both the legacy stack and the new analytics/AI enhancement as that caching is implemented.

---

## 1.5 Polyglot Persistence Summary

| # | Group | Technology | Type | Purpose | Status | Access |
|---|---|---|---|---|---|---|
| 1 | Legacy | MySQL (`cms-db`) | Relational (OLTP) | Core transactional data (contracts, vendors, notifications, audit logs) | ✅ Running | Read/Write |
| 2 | Legacy | MongoDB Atlas (`crms-db`) | Document (NoSQL) | Contract document metadata | ✅ Running | Read/Write |
| 3 | Legacy | Meilisearch | Search Engine | Full-text search over contracts/vendors | ✅ Running | Read/Write |
| 4 | New | PostgreSQL + pgvector (`cms-ai-db`) | Relational + Vector | AI risk assessment, OCR, vendor suggestions, embeddings/similarity search | ✅ Running | Read/Write |
| 5 | New | MySQL schema (`cms-analytics-db`) | Relational (analytical) | Aggregated metrics & generated reports | ✅ Running | Read/Write |
| 6 | Cache | Redis (`cms-redis`) | Key-Value Cache | Shared caching layer for legacy + new services | ✅ Running | Read/Write |

**Conclusion:** The project demonstrates polyglot persistence across three distinct paradigms — relational (MySQL, Postgres), document (MongoDB), search-index (Meilisearch), vector (pgvector), and key-value cache (Redis) — deliberately matching each engine to the data shape and access pattern it serves best, rather than forcing all services onto a single database technology.
