# Legacy-to-New Data Synchronization Client

## Purpose

`legacy_to_new_sync.py` demonstrates the project's read-only polyglot persistence flow. It is a synchronization client, **not an API gateway**.

The client performs these operations:

1. Sends `GET` requests to the existing Vendor Management integration endpoint through the shared API gateway.
2. Uses `X-Internal-Secret` for service-to-service authentication.
3. Reads paginated active business-partner records from the legacy service.
4. Verifies the AI service schema through its health endpoint.
5. Writes a source snapshot to the new `cms-analytics-db` database.

The legacy database is never connected to directly and no legacy write request is made.

## Data Flow

```text
Python sync client
  GET /api/vendor/integration/customers
              |
              v
Shared Nginx gateway :5173
              |
              v
Vendor Management :8001
              |
              v
Legacy cms-db / business_partners
              |
              v
JSON response to Python client
              |
              v
Analytics cms-analytics-db / aggregated_metrics
```

The gateway configuration is maintained by the external Auth project. This repository documents the gateway URL and provides the extraction client that consumes it.

## Endpoints

| Purpose | Method | URL |
|---|---:|---|
| Legacy customer data through gateway | `GET` | `http://localhost:5173/api/vendor/integration/customers` |
| Legacy customer data directly | `GET` | `http://localhost:8001/api/integration/customers` |
| AI schema verification | `GET` | `http://localhost:8006/health/schema` |

The client defaults to the gateway URL. The direct service URL may be used for local troubleshooting by setting `LEGACY_CUSTOMERS_URL`.

## Requirements and Setup

Python 3.10 or newer is recommended.

```bash
python -m venv .venv
.venv\Scripts\activate
python -m pip install -r scripts/requirements.txt
```

Do not place passwords or service secrets in this file. Set them as environment variables instead.

## Configuration

PowerShell example:

```powershell
$env:INTERNAL_SERVICE_SECRET = "<your-internal-service-secret>"
$env:MYSQL_PASSWORD = "<your-mysql-password>"
$env:ANALYTICS_DB_HOST = "localhost"
$env:ANALYTICS_DB_PORT = "3307"
$env:AI_SCHEMA_URL = "http://localhost:8006/health/schema"
```

When the client runs inside the Docker network, use `mysql` and the internal service ports instead:

```powershell
$env:ANALYTICS_DB_HOST = "mysql"
$env:ANALYTICS_DB_PORT = "3306"
$env:LEGACY_CUSTOMERS_URL = "http://gateway-or-proxy/api/vendor/integration/customers"
```

## Usage

Run the complete read-and-write flow:

```bash
python scripts/legacy_to_new_sync.py
```

Perform the API and schema checks without writing to Analytics:

```bash
python scripts/legacy_to_new_sync.py --dry-run
```

The script prints a JSON summary and creates `scripts/last_run.json`. That file is generated evidence and should not contain secrets.

## Expected behavior

A successful run reports:

- `source_http_method` as `GET`;
- the number of legacy records read;
- the number of Analytics records inserted;
- `ai_schema_verified` as `true`; and
- `legacy_database_write_performed` as `false`.

The Analytics rows use `source_service = vendor-management`, `source_record_id = partner_id`, and JSON metadata containing the original API record and synchronization run ID. These are logical references; no cross-database foreign key is created.
