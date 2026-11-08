# Deliverable 2: Data Extraction Script

**Project:** CRMS Capstone — Analytics & AI Enhancement
**Document Version:** 1.0
**Date:** July 17, 2026
**Implementation:** `scripts/legacy_to_new_sync.py`

## 2.1 Overview

This deliverable demonstrates how the new Analytics layer reads data from a legacy service without connecting directly to the legacy database. The extraction client uses the existing read-only Vendor Management integration endpoint through the shared API gateway.

The Python file is **not an API gateway**. It is a client/synchronization worker. The gateway remains responsible for routing requests, while the script is responsible for consuming the legacy API response and persisting a normalized source snapshot in the new Analytics database.

The implemented working flow is:

```text
Python synchronization client
        │ GET + X-Internal-Secret
        ▼
Shared API gateway: localhost:5173
        │ /api/vendor/integration/customers
        ▼
Vendor Management service: localhost:8001
        │ reads active business partners with contracts
        ▼
Legacy cms-db
        │ JSON response
        ▼
Python synchronization client
        │ INSERT into target database
        ▼
New cms-analytics-db / aggregated_metrics
```

The client also calls the AI service's existing schema health endpoint. This verifies that the AI database is provisioned without pretending that AI ingestion is already implemented.

## 2.2 Legacy Endpoint Exposed

### Gateway endpoint

```http
GET http://localhost:5173/api/vendor/integration/customers?page=1&per_page=100
Accept: application/json
X-Internal-Secret: <INTERNAL_SERVICE_SECRET>
```

### Direct service endpoint

```http
GET http://localhost:8001/api/integration/customers?page=1&per_page=100
Accept: application/json
X-Internal-Secret: <INTERNAL_SERVICE_SECRET>
```

The script defaults to the gateway URL. The direct URL is available only for troubleshooting and can be selected with `LEGACY_CUSTOMERS_URL`.

### Endpoint behavior

The Vendor Management controller returns only:

- active `business_partners`;
- business partners with at least one associated contract; and
- paginated JSON records.

The route is protected by the `auth.integration.secret` middleware. A missing or invalid secret returns `403 Forbidden`. The script sends only `GET` requests to this endpoint.

## 2.3 Response Data Consumed

The expected response is a Laravel paginator. The script reads the `data` array and follows `last_page` until all pages are consumed.

```json
{
  "current_page": 1,
  "data": [
    {
      "partner_id": 1,
      "bp_code": "BP-0001",
      "partner_name": "Example Business Partner",
      "industry": "Technology",
      "contact_person": "Example Contact",
      "email": "contact@example.test",
      "region": "Luzon",
      "status": "Active"
    }
  ],
  "per_page": 100,
  "last_page": 1,
  "total": 1
}
```

The script validates that every returned record is an object containing `partner_id` before it can be written to Analytics.

## 2.4 Target Database Write

For every legacy customer, the client inserts one row into `cms-analytics-db.aggregated_metrics`:

| Target column | Value |
|---|---|
| `metric_type` | `active_business_partner_snapshot` |
| `source_service` | `vendor-management` |
| `source_record_id` | Legacy `partner_id` |
| `metric_value` | `1` |
| `metric_date` | Current synchronization date |
| `metadata` | JSON containing `sync_run_id` and the API record |

The source ID is a logical reference. Analytics and the legacy service use separate schemas, so no cross-database foreign key is created.

The target write is intentionally separate from the legacy read. The only legacy operation is the authenticated `GET` request; the `INSERT` is performed against the new Analytics database.

## 2.5 AI Service Verification

The script calls:

```http
GET http://localhost:8006/health/schema
```

This endpoint returns table and column existence flags for the AI schema. It does not return AI rows, embeddings, OCR values, or other stored data. This proves that the new AI database is available while keeping the current project scope honest: AI ingestion and model processing are not yet implemented.

## 2.6 Installation and Execution

Install the pinned dependencies:

```powershell
python -m venv .venv
.venv\Scripts\activate
python -m pip install -r scripts/requirements.txt
```

Set secrets without placing them in source code:

```powershell
$env:INTERNAL_SERVICE_SECRET = "<your-internal-service-secret>"
$env:MYSQL_PASSWORD = "<your-mysql-password>"
```

Run an API-only validation first:

```powershell
python scripts/legacy_to_new_sync.py --dry-run
```

Run the complete extraction and Analytics synchronization:

```powershell
python scripts/legacy_to_new_sync.py
```

The output is printed as JSON and saved to `scripts/last_run.json`. The generated report includes the number of legacy records read, target records inserted, AI schema status, and an explicit confirmation that no legacy database write occurred.

## 2.7 Source Code

The complete implementation is stored in:

```text
scripts/legacy_to_new_sync.py
```

The source code demonstrates the important controls directly:

```python
response = requests.get(
    source_url,
    params={"page": page, "per_page": 100},
    headers={
        "Accept": "application/json",
        "X-Internal-Secret": secret,
    },
    timeout=30,
)
```

The Analytics write uses a separate connection:

```python
connection = mysql.connector.connect(
    host=os.getenv("ANALYTICS_DB_HOST", "localhost"),
    port=int(os.getenv("ANALYTICS_DB_PORT", "3307")),
    database=os.getenv("ANALYTICS_DB_NAME", "cms-analytics-db"),
    password=required_env("MYSQL_PASSWORD"),
)
```

No legacy database credentials are used by the script.

## 2.8 Evidence

**Screenshot 1 — Gateway GET request and JSON response:**

{Screenshot Here}

**Screenshot 2 — Script execution output:**

{Screenshot Here}

**Screenshot 3 — Inserted records in `cms-analytics-db.aggregated_metrics`:**

{Screenshot Here}

**Screenshot 4 — AI schema verification response:**

{Screenshot Here}

## 2.9 Scope Limitation

The current project exposes the Vendor Management integration endpoint as the working machine-to-machine example. Contract Management has user-facing read endpoints, but those routes currently require internal JWT authentication and are not used by this script. Adding a Contract Management integration endpoint would be a separate enhancement.
