#!/usr/bin/env python3
"""Read legacy customer data through the API gateway and sync it to Analytics.

This script never connects to or writes to the legacy database. It performs a
GET request against the gateway's read-only integration endpoint, then writes a
source snapshot to the new Analytics database.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import date, datetime, timezone
from pathlib import Path
from typing import Any

import mysql.connector
import requests


DEFAULT_SOURCE_URL = "http://localhost:5173/api/vendor/integration/customers"
DEFAULT_AI_SCHEMA_URL = "http://localhost:8006/health/schema"
OUTPUT_PATH = Path(__file__).with_name("last_run.json")


def load_project_env() -> None:
    """Load simple KEY=VALUE entries from the repository root .env file."""
    env_path = Path(__file__).resolve().parents[1] / ".env"
    if not env_path.exists():
        return

    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip().strip('\\"').strip("'")
        if key:
            os.environ.setdefault(key, value)


def required_env(name: str) -> str:
    value = os.getenv(name)
    if not value:
        raise RuntimeError(f"Missing required environment variable: {name}")
    return value


def fetch_legacy_customers(source_url: str, secret: str) -> list[dict[str, Any]]:
    """Read all pages from the legacy integration endpoint via the gateway."""
    headers = {"Accept": "application/json", "X-Internal-Secret": secret}
    customers: list[dict[str, Any]] = []
    page = 1

    while True:
        response = requests.get(
            source_url,
            params={"page": page, "per_page": 100},
            headers=headers,
            timeout=30,
        )
        response.raise_for_status()
        payload = response.json()

        records = payload.get("data")
        if not isinstance(records, list):
            raise ValueError("Legacy response did not contain a data array")
        for record in records:
            if not isinstance(record, dict) or "partner_id" not in record:
                raise ValueError("Legacy response contains an invalid customer record")
        customers.extend(records)

        last_page = int(payload.get("last_page", page))
        if page >= last_page:
            break
        page += 1

    return customers


def verify_ai_schema(schema_url: str, secret: str) -> dict[str, Any]:
    """Verify AI's schema endpoint without reading AI table row contents."""
    response = requests.get(
        schema_url,
        headers={"Accept": "application/json", "X-Internal-Secret": secret},
        timeout=30,
    )
    response.raise_for_status()
    payload = response.json()
    if payload.get("status") != "ok":
        raise ValueError("AI schema endpoint did not return status=ok")
    return payload


def write_analytics_snapshot(customers: list[dict[str, Any]], run_id: str) -> int:
    """Write legacy snapshots to the new Analytics database only."""
    connection = mysql.connector.connect(
        host=os.getenv("ANALYTICS_DB_HOST", "localhost"),
        port=int(os.getenv("ANALYTICS_DB_PORT", "3307")),
        user=os.getenv("ANALYTICS_DB_USER", "root"),
        password=required_env("MYSQL_PASSWORD"),
        database=os.getenv("ANALYTICS_DB_NAME", "cms-analytics-db"),
    )
    cursor = connection.cursor()
    query = """
        INSERT INTO aggregated_metrics
            (metric_type, source_service, source_record_id, metric_value,
             metric_date, metadata, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
    """
    rows = [
        (
            "active_business_partner_snapshot",
            "vendor-management",
            int(customer["partner_id"]),
            1,
            date.today(),
            json.dumps({"sync_run_id": run_id, "legacy_record": customer}),
        )
        for customer in customers
    ]
    if rows:
        cursor.executemany(query, rows)
    connection.commit()
    inserted = cursor.rowcount
    cursor.close()
    connection.close()
    return inserted


def write_run_report(report: dict[str, Any]) -> None:
    OUTPUT_PATH.write_text(json.dumps(report, indent=2, default=str), encoding="utf-8")


def main() -> int:
    load_project_env()
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", help="Read and validate only; do not write Analytics")
    args = parser.parse_args()

    run_id = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    secret = required_env("INTERNAL_SERVICE_SECRET")
    source_url = os.getenv("LEGACY_CUSTOMERS_URL", DEFAULT_SOURCE_URL)
    ai_schema_url = os.getenv("AI_SCHEMA_URL", DEFAULT_AI_SCHEMA_URL)

    customers = fetch_legacy_customers(source_url, secret)
    ai_schema = verify_ai_schema(ai_schema_url, secret)
    inserted = 0 if args.dry_run else write_analytics_snapshot(customers, run_id)

    report = {
        "run_id": run_id,
        "source_endpoint": source_url,
        "source_http_method": "GET",
        "legacy_records_read": len(customers),
        "analytics_records_inserted": inserted,
        "analytics_write_skipped": args.dry_run,
        "ai_schema_verified": ai_schema.get("status") == "ok",
        "legacy_database_write_performed": False,
        "completed_at_utc": datetime.now(timezone.utc).isoformat(),
    }
    write_run_report(report)
    print(json.dumps(report, indent=2))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (RuntimeError, ValueError, requests.RequestException, mysql.connector.Error) as error:
        print(f"Sync failed: {error}", file=sys.stderr)
        raise SystemExit(1)
