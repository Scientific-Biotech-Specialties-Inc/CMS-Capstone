# Deliverable 3: Data Validation Report

**Project:** CRMS Capstone — Analytics & AI Enhancement
**Document Version:** 1.0
**Report Status:** Pending execution evidence
**Date:** July 17, 2026
**Validation Tool:** `scripts/legacy_to_new_sync.py`

## 3.1 Report Overview

This report defines and records validation for the read-only legacy-to-new data flow. The working implementation extracts active business partners from the existing Vendor Management integration endpoint through the API gateway and stores source snapshots in the Analytics database.

Unlike the sample deliverable, this report does not claim row counts or successful execution before the services and environment have been run. The values marked **To be completed** should be replaced with the values printed by the script and the SQL verification queries.

| Attribute | Details |
|---|---|
| Source service | Vendor Management legacy service |
| Source database | `cms-db` — accessed indirectly through the service API |
| Source endpoint | `GET /api/vendor/integration/customers` |
| Gateway URL | `http://localhost:5173/api/vendor/integration/customers` |
| Target service | Analytics Service |
| Target database | `cms-analytics-db` |
| Target table | `aggregated_metrics` |
| AI verification | `GET http://localhost:8006/health/schema` |
| Legacy database writes | None |
| Current status | To be completed after execution |

## 3.2 Validation Objectives

The validation checks that:

1. The gateway endpoint can be reached with the required internal secret.
2. The source endpoint is accessed using `GET` only.
3. The response contains valid paginator metadata and customer records.
4. All pages are read, not only the first page.
5. Each source record contains a `partner_id` before target persistence.
6. Analytics rows are written to `cms-analytics-db`, not `cms-db`.
7. The number of target rows inserted corresponds to the number of source records processed.
8. The AI service schema endpoint is reachable and reports `status: ok`.
9. No write operation is sent to a legacy service or legacy database.

## 3.3 Execution Procedure

### Step 1 — Start the project services

From the project root:

```powershell
docker compose up -d mysql redis postgres analytics-service ai-service
```

The shared Nginx gateway is maintained by the external Auth project. Start that project as required so that `localhost:5173` routes to Vendor Management.

### Step 2 — Configure the client

```powershell
$env:INTERNAL_SERVICE_SECRET = "<your-internal-service-secret>"
$env:MYSQL_PASSWORD = "<your-mysql-password>"
$env:ANALYTICS_DB_HOST = "localhost"
$env:ANALYTICS_DB_PORT = "3307"
$env:AI_SCHEMA_URL = "http://localhost:8006/health/schema"
```

### Step 3 — Run the dry-run validation

```powershell
python scripts/legacy_to_new_sync.py --dry-run
```

This validates the legacy API and AI schema without inserting Analytics records.

### Step 4 — Run the complete synchronization

```powershell
python scripts/legacy_to_new_sync.py
```

The script writes the JSON execution evidence to:

```text
scripts/last_run.json
```

## 3.4 Expected Execution Evidence

Copy the actual output from `scripts/last_run.json` into the final submission or attach it as a screenshot.

```json
{
  "run_id": "<generated-at-runtime>",
  "source_endpoint": "http://localhost:5173/api/vendor/integration/customers",
  "source_http_method": "GET",
  "legacy_records_read": "<actual count>",
  "analytics_records_inserted": "<actual count>",
  "analytics_write_skipped": false,
  "ai_schema_verified": true,
  "legacy_database_write_performed": false,
  "completed_at_utc": "<generated-at-runtime>"
}
```

## 3.5 Validation Checklist

| Validation | Evidence / command | Expected result | Actual result | Status |
|---|---|---|---|---|
| Gateway routing | `GET localhost:5173/api/vendor/integration/customers` | `200 OK` JSON response | To be completed | ☐ |
| Secret authentication | Request with `X-Internal-Secret` | Request accepted | To be completed | ☐ |
| Invalid secret rejection | Request with invalid secret | `403 Forbidden` | To be completed | ☐ |
| Read-only source access | Script source and run report | Method is `GET`; no legacy write | To be completed | ☐ |
| Pagination | `last_page` processing | All pages consumed | To be completed | ☐ |
| Source shape | `partner_id` validation | Every record passes | To be completed | ☐ |
| Analytics target | Query `cms-analytics-db.aggregated_metrics` | Rows inserted | To be completed | ☐ |
| Source mapping | Inspect `source_service` and `source_record_id` | Vendor service and partner IDs preserved | To be completed | ☐ |
| AI schema | `GET /health/schema` | `status = ok` | To be completed | ☐ |
| Legacy isolation | Inspect connection configuration | No direct legacy DB connection in script | To be completed | ☐ |

## 3.6 Database Verification Queries

Run the following commands after the synchronization completes.

### Count Analytics rows written for the current date

```powershell
docker compose exec mysql mysql -uroot -p -D cms-analytics-db -e "SELECT COUNT(*) AS analytics_snapshot_rows FROM aggregated_metrics WHERE metric_type = 'active_business_partner_snapshot' AND source_service = 'vendor-management' AND metric_date = CURRENT_DATE;"
```

### Inspect source mapping and stored metadata

```powershell
docker compose exec mysql mysql -uroot -p -D cms-analytics-db -e "SELECT id, source_service, source_record_id, metric_type, metric_date, JSON_EXTRACT(metadata, '$.sync_run_id') AS sync_run_id FROM aggregated_metrics WHERE metric_type = 'active_business_partner_snapshot' ORDER BY id DESC LIMIT 10;"
```

### Confirm the legacy database was not the target

```powershell
docker compose exec mysql mysql -uroot -p -e "SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA IN ('cms-db', 'cms-analytics-db') AND TABLE_NAME = 'aggregated_metrics';"
```

Expected result: `aggregated_metrics` exists in `cms-analytics-db`, not in the legacy `cms-db` schema.

### Confirm the AI schema

```powershell
curl http://localhost:8006/health/schema
```

Expected result:

```json
{
  "status": "ok",
  "schema": {
    "embeddings": {
      "table_exists": true,
      "columns_exist": true
    }
  }
}
```

## 3.7 Source-to-Target Comparison

Complete this table using the counts from the script output and the SQL query above.

| Measure | Source API | Target Analytics | Difference | Result |
|---|---:|---:|---:|---|
| Active business-partner records processed | To be completed | To be completed | To be completed | To be completed |
| Records with a valid `partner_id` | To be completed | To be completed | To be completed | To be completed |
| Records represented by `source_record_id` | N/A | To be completed | N/A | To be completed |

The expected relationship is:

```text
legacy_records_read == analytics_records_inserted
```

If the values differ, investigate pagination, duplicate execution, database connection settings, or a failed target insert before marking the validation as passed.

## 3.8 Read-Only Security Validation

The implementation provides the following controls:

- The source URL is called with `requests.get(...)` only.
- The source request sends `X-Internal-Secret` and does not send database credentials.
- The script has no MySQL connection configured for `cms-db`.
- The only database connection is configured for `cms-analytics-db`.
- The AI request is also a `GET` request to a schema-only health endpoint.
- Secrets are loaded from environment variables and are not embedded in the source code.

**Screenshot — source API request showing `GET` and successful response:**

{Screenshot Here}

**Screenshot — Analytics target rows after synchronization:**

{Screenshot Here}

**Screenshot — AI schema health response:**

{Screenshot Here}

## 3.9 Validation Results

Do not mark these items as passed until the script has been run and the evidence has been captured.

| Area | Status | Notes |
|---|---|---|
| Gateway access | ☐ Pending | Requires external shared gateway to be running |
| Legacy API authentication | ☐ Pending | Validate valid and invalid secret behavior |
| Legacy data extraction | ☐ Pending | Record actual source count |
| Pagination | ☐ Pending | Confirm all pages were consumed |
| Analytics persistence | ☐ Pending | Confirm target rows in `cms-analytics-db` |
| Source-to-target mapping | ☐ Pending | Confirm partner IDs and metadata |
| AI schema availability | ☐ Pending | Confirm `/health/schema` response |
| Legacy write protection | ☐ Pending | Confirm report says `false` |

## 3.10 Limitations and Next Steps

This deliverable intentionally demonstrates the existing Vendor Management integration path because it is the only legacy endpoint currently designed for shared-secret machine-to-machine access.

The current Contract Management API has read routes such as `GET /api/contracts` and `GET /api/contracts/{id}`, but those routes use internal JWT and permission middleware. They are not used by this script. A future enhancement can expose a dedicated read-only Contract Management integration endpoint protected by the same `X-Internal-Secret` pattern.

The AI service currently provides schema scaffolding and a schema health endpoint. The script verifies that schema but does not create embeddings, OCR extractions, risk assessments, or vendor suggestions. Those are future AI processing steps and should be implemented only after their source contract and model behavior are approved.

## 3.11 Conclusion

Once the pending execution evidence is completed, this report will demonstrate that:

1. Legacy data is accessed through an API boundary rather than a direct database connection.
2. The API boundary is read-only for the new Analytics consumer.
3. Data is persisted into the isolated `cms-analytics-db` schema.
4. Source identifiers remain traceable through `source_service` and `source_record_id`.
5. The AI database is available for future processing without overstating the current implementation.
