# CMS <-> Technical Service (TS) Integration Guide

**Spec Coverage:** 3.1 Equipment & Customer Information Synchronization  
**Source of Truth:** Contract Management System (`CMS`)

---

## 1. Exposed API Endpoints

All routes are served by `contract-management` (port `8002` locally, or via the Nginx gateway on port `5173`):

| Purpose | Gateway URL (Recommended) | Direct Service URL |
| :--- | :--- | :--- |
| Unified Sync (Equipment + Customer) | `GET /api/contract/integrations/v1/ts/sync` | `GET /api/integrations/v1/ts/sync` |
| Equipment Feed | `GET /api/contract/integrations/v1/ts/equipment` | `GET /api/integrations/v1/ts/equipment` |
| Customers / Vendors Feed | `GET /api/contract/integrations/v1/ts/customers` (alias `/vendors`) | `GET /api/integrations/v1/ts/customers` (alias `/vendors`) |
| Legacy Vendor Customer List | `GET /api/vendor/integration/customers` | `GET /api/integration/customers` (on port `8001`) |
| SBSI Branches Lookup (CMS Internal) | `GET /api/contract/lookups/branches` | `GET /api/lookups/branches` |

*(Internal service aliases `/api/contract/internal/ts/sync`, `/equipment`, `/customers` are also registered under `X-Internal-Secret` middleware.)*

---

## 2. Authentication

TS must send the shared integration key using **either** header:

- `X-Internal-Secret: cms-ts-secret-key-2026-9f8a7b6c5d4e`
- `Authorization: Bearer cms-ts-secret-key-2026-9f8a7b6c5d4e`

Env keys (configured in root `.env` and `services/contract-management/.env`):
- `TS_TO_CMS_INTEGRATION_KEY=cms-ts-secret-key-2026-9f8a7b6c5d4e`
- `INTERNAL_SERVICE_SECRET=cms-internal-secret-key-2026` (also accepted for internal services)
- `TS_SYNC_PUBLIC_READ=false` (set to `true` only if temporarily testing without headers)

Unauthorized or missing headers return `403 Forbidden`.

---

## 3. Query Parameters

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `include_expired` | `boolean` | Default `false`. Set `true` to include **Approved** contracts whose `end_date` is in the past. (`Pending` contracts are never included.) |
| `sbsi_branch` | `string` | Exact match filter (e.g., `?sbsi_branch=Main%20Office`). |
| `serial_number` | `string` | Exact serial number lookup (e.g., `?serial_number=SN-2026-0041`). |
| `updated_since` | `ISO-8601` | Incremental sync filter (e.g., `?updated_since=2026-11-15T00:00:00Z`). |
| `search` | `string` | Matches `contract_code`, `bp_name`, `serial_number`, `item_code`, `equipment_name`, `brand`, `model`, `sbsi_branch`, or vendor name/email/UID on `/customers`. |
| `customer_type` | `string` | Optional filter on `/customers` & `/vendors`: `business_partner` or `supplier`. |
| `per_page` | `integer` | `1..200` (default `50`), plus `page`. |

---

## 4. Payload & Canonical Unique Identifiers

- **Equipment UID:** `EQP-{contract_id}` (unique per contracted machine/unit)
- **Customer UID:** Vendor `bp_code` (or `BP-{partner_id}` / `SUP-{supplier_id}`; fallback `CUST-{HASH12}` for unlinked legacy records)
- **Optional Software Product Field:** `equipment_name` is `null` when omitted for software products.
- **Decrypted Vendor Fields:** `customer_email`, `contact_number`, `contact_person`, and `address` are retrieved live from `vendor-management`'s internal decrypted API (`VENDOR_SERVICE_URL`).

Example `/integrations/v1/ts/sync` item:
```json
{
  "equipment_uid": "EQP-12",
  "serial_number": "SN-2026-0041",
  "customer_uid": "BP-0005",
  "contract_id": 12,
  "contract_code": "CTR-00012",
  "approval_status": "Approved",
  "lifecycle_status": "active",
  "start_date": "2026-01-01",
  "end_date": "2028-01-01",
  "sbsi_branch": "Main Office",
  "equipment": {
    "equipment_uid": "EQP-12",
    "equipment_name": "Sysmex XN-1000 Analyzer",
    "brand": "Sysmex",
    "model": "XN-1000",
    "serial_number": "SN-2026-0041",
    "item_code": "ITM-0041",
    "sbu_number": "SBU-001"
  },
  "customer": {
    "customer_uid": "BP-0005",
    "customer_type": "business_partner",
    "customer_name": "St. Luke's Medical Center",
    "customer_email": "biomed@stlukes.example.com",
    "contact_number": "+63-917-555-0199",
    "sbsi_branch": "Main Office"
  }
}
```

`/integrations/v1/ts/customers` (and `/integrations/v1/ts/vendors`) returns **Business Partners with contracts only** (suppliers with or without contracts and uncontracted vendors are excluded) with `customer_email`, `sbsi_branch`, `sbsi_branches`, `contract_ids`, `equipment_uids`, `serial_numbers`, and `ordered_items` containing the ordered equipment/items per contract.

---

## 5. Cloudflared Tunnel Setup (Cross-Group Testing)

Run Docker services first:
```bash
docker compose up -d
```

Then expose the Nginx gateway (recommended, routes `/api/contract/*` and `/api/vendor/*`):
```bash
cloudflared tunnel --url http://localhost:5173
```

Or expose `contract-management` directly:
```bash
cloudflared tunnel --url http://127.0.0.1:8002
```

Share with the TS team:
1. Base URL: `https://<your-tunnel>.trycloudflare.com/api/contract/integrations/v1/ts` (via gateway) or `https://<your-tunnel>.trycloudflare.com/api/integrations/v1/ts` (direct `:8002`).
2. Header: `X-Internal-Secret: cms-ts-secret-key-2026-9f8a7b6c5d4e`

Quick smoke test against your tunnel:
```bash
curl -sS -H "X-Internal-Secret: cms-ts-secret-key-2026-9f8a7b6c5d4e" \
  "https://<your-tunnel>.trycloudflare.com/api/contract/integrations/v1/ts/sync"
```
