# CMS <-> PRS Integration Guide (Port-Forwarding Ready)

**Subsystems:** CMS (Contract Management System) <-> PRS (Productivity Reports System)  
**Coverage:** Spec 2.1 (Products), Spec 2.2 (Institutions & Locations), Spec 2.3 (Contract Status)

---

## 1. Source of Truth & Identifiers

| Domain | Source of Truth | Canonical Key | Notes |
| :--- | :--- | :--- | :--- |
| **Products (`product_list`)** | **PRS** (synced into CMS `products`) | `prs_product_id` / `product_code` + `serial_number` | Integer `sbu` (`tinyint UN`) maps bidirectionally to `SBU-###`. Blank `product_code` auto-generates `ITM-####`. |
| **Institutions (`institution_list`)** | **CMS** (`business_partners` + `partner_branches`) | `bp_code` (`institution_uid`) + `location_uid` | `address` is required; `class_tier` is `Class A` / `Class B` / `Class C`. CMS omits `latitude`/`longitude` (PRS geocodes). |
| **Contract Status** | **PRS -> CMS** on Closing Activity; **CMS -> PRS** on terminal decision | `(prs_activity_id, revision)` + `event_id` UUID | Replay-safe; inbound PRS updates never echo back to PRS. |

---

## 2. Port-Forwarding & Shared Secret Setup

You can expose **either** the Nginx gateway (`:5173`, prefix `/api/contract`) **or** `contract-management` directly (`:8002`, prefix `/api`) — **all PRS endpoints (including Institutions & Locations)** are available on `:8002` so only one forwarded port is needed.

### Shared Secret (Configured in `.env`)
- **Env vars:** `PRS_TO_CMS_INTEGRATION_KEY` and `PRS_INTEGRATION_KEY`
- **Test key:** `cms-prs-c348dc239f2b7d387c8a1f1e62b5185c663961227c929ec7`
- **Accepted headers:** `Authorization: Bearer <key>` **or** `X-Integration-Secret: <key>` **or** `X-Internal-Secret: <key>`
- **Outbound PRS URLs (paste PRS's forwarded URL into `.env` when ready):**
  - `PRS_BASE_URL=https://<prs-forwarded-url>`
  - `PRS_WEBHOOK_URL=https://<prs-forwarded-url>/api/integrations/cms/contract-outcome`
  - `PRS_PRODUCTS_URL=https://<prs-forwarded-url>/api/integrations/cms/products`
  - `PRS_INSTITUTIONS_URL=https://<prs-forwarded-url>/api/integrations/cms/institutions`

---

## 3. Endpoints (Direct `:8002` / Gateway `:5173/api/contract`)

| Spec | Method & Path | Purpose |
| :--- | :--- | :--- |
| Health | `GET /api/integrations/v1/prs/ping` | Connectivity & secret handshake check |
| **2.1** | `GET /api/integrations/v1/prs/products` | Paginated products in PRS `product_list` schema (`?per_page=100&sbu=1&updated_since=...&search=...`) |
| **2.1** | `POST /api/integrations/v1/prs/products/sync` | Webhook push from PRS (`{ ... }` or `{ "products": [...], "replace": true }`; `product_code` = `ITM-####`, `serial_number` = `SN-YYYY-xxxx`, `sbu_number` = `SBU-###` or `sbu`) |
| **2.1** | `GET /api/integrations/v1/products` | Legacy cursor-paginated catalog feed (`per_page <= 100`) |
| **2.2** | `GET /api/integrations/v1/prs/institutions` | Paginated institutions (`institution_uid`, `institution_name`, `address`, `class_tier`, `is_active`, `locations`) |
| **2.2** | `GET /api/integrations/v1/prs/locations` | Paginated branch locations (`location_uid`, `institution_uid`, `branch_name`, `address`, `sbsi_branch`) |
| **2.2** | `POST /api/integrations/v1/prs/institutions/sync` | Link `prs_institution_id` / `prs_branch_id` or upsert institutions & branches |
| **2.3** | `POST /api/integrations/v1/prs/closing-outcomes` (alias `/prs/contract-status`) | Receive Closing Activity completion & contract status updates from PRS |

---

## 4. PRS -> CMS Contract Status Mapping (2.3)

| Incoming `prs_status` / `outcome` | CMS `approval_status` | CMS `workflow_status` |
| :--- | :--- | :--- |
| `Pending`, `Closing Completed`, `Follow-up` | `Pending` | `null` |
| `Won`, `Approved`, `Active`, `Closed Won` | `Approved` | `SBSI Review` |
| `Signed` | `Approved` | `Signed` |
| `Loss`, `Lost`, `Rejected`, `Cancelled` | `Rejected` | `null` |

---

## 5. Quick Smoke Test (PowerShell / cURL)

```bash
curl -sS -H "Authorization: Bearer cms-prs-c348dc239f2b7d387c8a1f1e62b5185c663961227c929ec7" \
  "http://127.0.0.1:8002/api/integrations/v1/prs/ping"

curl -sS -H "Authorization: Bearer cms-prs-c348dc239f2b7d387c8a1f1e62b5185c663961227c929ec7" \
  "http://127.0.0.1:8002/api/integrations/v1/prs/institutions"
```

To pull products from PRS once `PRS_BASE_URL` / `PRS_PRODUCTS_URL` is set:
```bash
docker compose exec contract-management php artisan prs:sync-products
```
