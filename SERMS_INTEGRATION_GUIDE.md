# CMS ↔ SERMS Integration Guide

This guide is for **SERMS developers** integrating with **CMS (Contract Management System)** across the three shared workflows:

1. **1.1** Contract-Tied Expense Attribution & Real-Time Burn Rate
2. **1.2** Vendor Master Data & TIN Cross-Validation
3. **1.3** Recurring Expense-to-Contract Request

---

## Integration Flow Overview

| Integration | Direction | What SERMS Does | What CMS Does |
| :--- | :--- | :--- | :--- |
| **1.1 Burn Rate & Net Margin** | SERMS ↔ CMS | • Dropdown of active contracts on expenses<br>• Saves `contract_id` & `client_name`<br>• Exposes `GET /api/internal/expenses/by-contract` | • Exposes `GET /api/contract/active` (with `contract_value`)<br>• Pulls SERMS expenses in Analytics<br>• Shows **Burn Rate %** & **True Net Margin** |
| **1.2 Vendor TIN Validation** | SERMS → CMS | • Extracts vendor name & TIN via OCR<br>• Auto-tags accredited vendors & suggests categories<br>• Flags unlisted/flagged vendors for audit<br>• Submits new verified merchants | • Exposes `GET /api/vendor/internal/vendors/lookup`<br>• Exposes `POST /api/vendor/internal/vendors/suggest`<br>• Lets admins onboard them in **Vendor Suggestions** |
| **1.3 Recurring Expense → Contract** | SERMS → CMS | • Detects recurring one-off purchases<br>• Generates purchase history summary<br>• Accounting forwards summary to CMS | • Exposes `POST /api/contract/internal/contract-requests/from-serms`<br>• Creates a **Pending Contract Request** (`source_system = SERMS`) with `supporting_expense_summary` |

---

## Authentication & Base URLs

All internal service-to-service endpoints use the shared `X-Internal-Secret` header.

| Environment | Contract Management Base URL | Vendor Management Base URL |
| :--- | :--- | :--- |
| **Gateway (`localhost:5173`)** | `http://localhost:5173/api/contract` | `http://localhost:5173/api/vendor` |
| **Docker Network (recommended for backend-to-backend)** | `http://contract-management:8000/api` | `http://vendor-management:8000/api` |

### Required Headers
```http
Accept: application/json
Content-Type: application/json
X-Internal-Secret: <INTERNAL_SERVICE_SECRET>
```

> Missing or invalid `X-Internal-Secret` returns `401 Unauthorized`.

---

## 1.1 Contract-Tied Expense Attribution & Real-Time Burn Rate

### A. Fetch Active Contracts (SERMS → CMS)
Use this endpoint to populate the searchable contract/client dropdown on SERMS Reimbursements and Cash Advances.

- **Endpoint:** `GET /api/contract/active` (gateway) or `GET /api/active` (direct to `contract-management`)
- **Query Parameters:**
  - `search` *(optional, string)* — filters by `contract_code`, `client_name`, `item_code`, or `sbu_number`
  - `limit` *(optional, integer, default `100`, max `200`)*

#### Example Request
```bash
curl -X GET "http://localhost:5173/api/contract/active?search=Acme" \
  -H "Accept: application/json" \
  -H "X-Internal-Secret: <INTERNAL_SERVICE_SECRET>"
```

#### Response (`200 OK`)
```json
{
  "data": [
    {
      "contract_id": 10,
      "contract_code": "CTR-ABC123",
      "client_name": "Acme Biotech Corp",
      "category": "Service Agreement",
      "sbu_number": "SBU-100",
      "item_code": "ITM-100",
      "contract_value": 250000.0,
      "start_date": "2026-01-01",
      "end_date": "2026-12-31",
      "status": "Approved"
    }
  ]
}
```

### B. Expose Contract Expenses to CMS Analytics (CMS → SERMS)
CMS Analytics Service (`SERMS_SERVICE_URL` in `services/analytics-service/.env`) calls SERMS to compute **Burn Rate %** and **True Net Contract Margin**:

- **Expected SERMS Endpoint:** `GET {SERMS_SERVICE_URL}/api/internal/expenses/by-contract`
- **Auth Header:** `X-Internal-Secret: <INTERNAL_SERVICE_SECRET>`

#### Expected Response Shape (`200 OK`)
```json
{
  "data": [
    {
      "contract_id": 10,
      "total_expenses": 25000.0,
      "breakdown": {
        "travel": 10000.0,
        "spare_parts": 10000.0,
        "field_service": 5000.0,
        "other": 0.0
      }
    }
  ]
}
```

---

## 1.2 Vendor Master Data & TIN Cross-Validation

### A. Lookup Vendor by TIN & Name (SERMS → CMS)
Call this after SERMS Receipt OCR extracts the merchant's TIN and/or vendor name.

- **Endpoint:** `GET /api/vendor/internal/vendors/lookup` (gateway) or `GET /api/internal/vendors/lookup` (direct to `vendor-management`)
- **Query Parameters:**
  - `tin` *(string, optional if `name` is provided)* — hyphens are normalized automatically
  - `name` *(string, optional if `tin` is provided)*

#### Example Request
```bash
curl -X GET "http://localhost:5173/api/vendor/internal/vendors/lookup?tin=123-456-789-000&name=SBSI%20Lab" \
  -H "Accept: application/json" \
  -H "X-Internal-Secret: <INTERNAL_SERVICE_SECRET>"
```

#### Response (`200 OK` — Matched Accredited Vendor)
```json
{
  "data": {
    "matched": true,
    "match_type": "tin",
    "requires_audit": false,
    "is_accredited": true,
    "is_flagged": false,
    "status": "Active",
    "default_expense_category": "Spare Parts",
    "supplier_id": 14,
    "supplier_name": "SBSI Lab Supplies Inc.",
    "tin_number": "123-456-789-000"
  }
}
```

#### Response (`200 OK` — Unlisted Vendor)
```json
{
  "data": {
    "matched": false,
    "match_type": null,
    "requires_audit": true,
    "is_accredited": false,
    "is_flagged": false,
    "status": null,
    "default_expense_category": null,
    "supplier_id": null,
    "supplier_name": null,
    "tin_number": null
  }
}
```

**SERMS Routing Guidance:**
- `is_accredited === true && !requires_audit`: Auto-tag as **Accredited SBSI Vendor** and pre-fill `default_expense_category`.
- `requires_audit === true` (unlisted, inactive, unaccredited, or `is_flagged === true`): Flag for secondary accounting audit and apply conditional approval routing.

### B. Submit Newly Verified Merchant as Suggested Vendor (SERMS → CMS)
When SERMS Accounting verifies a new merchant not yet in CMS, submit it for supplier onboarding.

- **Endpoint:** `POST /api/vendor/internal/vendors/suggest` (gateway) or `POST /api/internal/vendors/suggest` (direct)

#### Request Body
```json
{
  "vendor_name": "New Field Service Co.",
  "tin_number": "555-666-777-000",
  "industry": "Field Engineering",
  "contact_person": "Juan Dela Cruz",
  "contact_number": "09171234567",
  "email": "billing@newfield.ph",
  "address": "Makati City",
  "region": "Luzon",
  "default_expense_category": "Field Service",
  "source_receipt_id": "RCP-9001",
  "submitted_by": 42
}
```

#### Responses
- `201 Created`: Created as `Pending` in CMS **Vendor Suggestions** for admin onboarding.
- `200 OK`: Deduplicated against an already-pending suggestion with the same TIN.
- `409 Conflict`: Vendor TIN already exists in CMS `suppliers`.

---

## 1.3 Recurring Expense-to-Contract Request

When SERMS detects recurring one-off purchases for the same supplier/service and Accounting forwards the purchase history summary to CMS:

- **Endpoint:** `POST /api/contract/internal/contract-requests/from-serms` (gateway) or `POST /api/internal/contract-requests/from-serms` (direct to `contract-management`)

#### Request Body
```json
{
  "vendor_name": "Metro Calibration Services",
  "tin_number": "111-222-333-000",
  "service_description": "Quarterly instrument calibration (recurring 4x in SERMS)",
  "category": "Service Agreement",
  "region": "Luzon",
  "occurrence_count": 4,
  "total_spend": 80000.0,
  "period_start": "2026-01-01",
  "period_end": "2026-10-31",
  "source_reference_id": "SERMS-REC-42",
  "forwarded_by": 42,
  "expense_items": [
    { "date": "2026-02-10", "amount": 20000.0, "reference": "RMB-101" },
    { "date": "2026-05-12", "amount": 20000.0, "reference": "RMB-204" }
  ]
}
```

#### Response (`201 Created`)
```json
{
  "data": {
    "contract_id": 88,
    "contract_code": "CTR-X9K2M1",
    "client_name": "Metro Calibration Services",
    "contract_value": 80000.0,
    "source_system": "SERMS",
    "source_reference_id": "SERMS-REC-42",
    "supporting_expense_summary": {
      "vendor_name": "Metro Calibration Services",
      "tin_number": "111-222-333-000",
      "occurrence_count": 4,
      "total_spend": 80000.0,
      "period_start": "2026-01-01",
      "period_end": "2026-10-31",
      "expense_items": [
        { "date": "2026-02-10", "amount": 20000.0, "reference": "RMB-101" },
        { "date": "2026-05-12", "amount": 20000.0, "reference": "RMB-204" }
      ]
    },
    "approval_status": "Pending"
  }
}
```
