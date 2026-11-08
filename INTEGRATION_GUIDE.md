# SBSI Customer Integration API Guide

This document provides instructions on how to query the SBSI customer integration endpoint. It is designed to be easily read and parsed by developers and AI agents.

---

## 1. API Specifications

### Endpoint URL
- **Local / Development**: `http://localhost:5173/api/vendor/integration/customers` (routing through the shared Nginx proxy)
- **Direct Service Port (Bypassing proxy)**: `http://localhost:8001/api/integration/customers`
- **Production**: `https://<sbsi-domain>/api/vendor/integration/customers`

### HTTP Method
`GET`

### Headers
To authenticate, you must provide the shared secret key in the request header.

| Header Name | Value | Description |
| :--- | :--- | :--- |
| `Accept` | `application/json` | Required to receive JSON responses. |
| `X-Internal-Secret` | `cms-internal-secret-key-2026` | The shared secret token. |

---

## 2. Filtering & Pagination

The endpoint automatically applies the following business rules:
1. Returns **only** `business_partners` (excluding suppliers).
2. Returns **only** records where status is `Active`.
3. Returns **only** companies that have at least one existing contract associated with them.

### Query Parameters

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `page` | `integer` | `1` | Page number for paginated results. |
| `per_page` | `integer` | `15` | Number of records per page (max 100). |

---

## 3. Response Structure

### Success Response (`200 OK`)

The response is standard Laravel pagination metadata wrapping the list of customer details.

```json
{
  "current_page": 1,
  "data": [
    {
      "partner_id": 1,
      "bp_code": "BP-0001",
      "partner_name": "Acme Corporation",
      "industry": "Technology",
      "contact_person": "Jane Doe",
      "contact_number": "09171234567",
      "email": "jane.doe@acme.com",
      "address": "123 Tech Lane, Taguig City",
      "region": "Luzon",
      "status": "Active",
      "created_at": "2026-06-15T03:20:00.000000Z",
      "updated_at": "2026-06-15T03:20:00.000000Z"
    }
  ],
  "first_page_url": "http://localhost:5173/api/vendor/integration/customers?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://localhost:5173/api/vendor/integration/customers?page=1",
  "next_page_url": null,
  "path": "http://localhost:5173/api/vendor/integration/customers",
  "per_page": 15,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

### Error Response (`403 Forbidden`)

Returned when the `X-Internal-Secret` header is missing, incorrect, or empty.

```json
{
  "message": "Forbidden. Invalid or missing integration secret."
}
```

---

## 4. Code Examples

### cURL
```bash
curl -X GET "http://localhost:5173/api/vendor/integration/customers?per_page=10" \
     -H "Accept: application/json" \
     -H "X-Internal-Secret: cms-internal-secret-key-2026"
```

### JavaScript / Fetch
```javascript
const fetchCustomers = async () => {
  const url = 'http://localhost:5173/api/vendor/integration/customers?per_page=50';
  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
      'X-Internal-Secret': 'cms-internal-secret-key-2026'
    }
  });

  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }

  const result = await response.json();
  return result.data; // List of active customers
};
```

### Python
```python
import requests

def get_customers():
    url = "http://localhost:5173/api/vendor/integration/customers"
    headers = {
        "Accept": "application/json",
        "X-Internal-Secret": "cms-internal-secret-key-2026"
    }
    
    response = requests.get(url, headers=headers)
    if response.status_code == 200:
        return response.json().get("data", [])
    else:
        print(f"Failed to fetch: {response.status_code}")
        return []
```
