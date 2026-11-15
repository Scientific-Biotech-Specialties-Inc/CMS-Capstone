# CMS product feed for PRS

PRS can pull catalog data from `GET /api/integrations/v1/products`. Forward CMS contract-service port `8002` and append this path to the forwarded base URL.

1. Apply the CMS contract-service migrations.
2. Add real catalog records through the CMS Admin API. The catalog starts empty.
3. For a token-free port-forward test, set `PRS_CATALOG_PUBLIC_READ=true` in the CMS Compose environment and recreate `contract-management`. Set it back to `false` after the test. For private service calls, use `PRS_CATALOG_ALLOWED_NETWORKS` or a server-side bearer token configured as `PRS_TO_CMS_INTEGRATION_KEY`.
4. Check `curl.exe http://127.0.0.1:8002/api/integrations/v1/products` before sharing the forwarded URL.

The feed returns `product_id`, `product_name`, `item_code`, `sbu_number`, `description`, `category`, `manufacturer`, `model`, and `is_active`. It contains catalog fields only. Send `?per_page=100` for the largest page. Pass the returned `next_cursor` as `cursor` until it is null. Upsert by `product_id` and retain inactive items for historical references.

CMS admins can create and update items at `POST /api/integrations/v1/products` and `PATCH /api/integrations/v1/products/{product}`. Required create fields are `product_name`, `sbu_number`, `description`, and `category`. Optional fields are `item_code`, `manufacturer`, `model`, and `is_active`. Omitting `item_code` allocates a free `ITM-####` code.

The CMS Closing receiver is available for later integration at `POST /api/integrations/v1/prs/closing-outcomes`. It requires a bearer token and accepts `event_id`, `prs_activity_id`, `revision`, and `outcome`. Institution and branch validation requires a PRS directory endpoint configured through `PRS_INSTITUTIONS_URL`.
