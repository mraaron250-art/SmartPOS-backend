# SmartPOS API Guide

The API contract is [`docs/api/openapi.yaml`](api/openapi.yaml) (OpenAPI 3.1). This page explains it in plain words.

- Interactive docs: `GET /api/docs` (Swagger UI)
- Raw contract: `GET /api/docs/openapi.yaml`

---

## 1. Basics

| Item | Rule |
| :--- | :--- |
| Base URL | Local `http://localhost:8000`. Production `https://<service>.onrender.com` |
| Format | JSON in, JSON out |
| IDs | Strings of digits, e.g. `"12"` |
| Money | Numbers with 2 decimals, e.g. `75.0` |
| Time | ISO-8601 with offset, Asia/Manila, e.g. `2026-10-26T13:45:00+08:00` |

### Success envelope

```json
{ "success": true, "data": { "...": "..." }, "message": "Optional human message" }
```

Paginated lists add a `pagination` object:

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": { "page": 1, "limit": 20, "total_records": 57, "total_pages": 3 }
}
```

### Error envelope

```json
{ "success": false, "message": "Insufficient stock for \"Almond Milk 1L\". Available: 2, requested: 5." }
```

| Status | Meaning |
| :--- | :--- |
| 400 | Validation or business-rule error (message says why) |
| 401 | Token missing, invalid or expired |
| 403 | Logged in but missing the permission, or account inactive |
| 404 | Resource not found |
| 405 | Wrong HTTP method for this path |
| 409 | Duplicate value (SKU, barcode, category name, email) |
| 500 | Unexpected server error (details are logged, not returned) |

---

## 2. Authentication

1. `POST /api/auth/login` with `{ "email", "password" }`.
2. The response has `token`, which is a JWT valid for 24 hours. It also sets the HTTP-only cookie `smartpos_token`.
3. Send the token on every request: `Authorization: Bearer <token>`. When no header is sent, the cookie is used.
4. `POST /api/auth/logout` clears the cookie.

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"owner@smartpos.local","password":"password123"}'
```

Demo accounts (seeded):

| Role | Email | Password |
| :--- | :--- | :--- |
| Owner | `owner@smartpos.local` | `password123` |
| Admin | `admin@smartpos.local` | `password123` |
| Staff | `staff@smartpos.local` | `password123` |

> Change these passwords before using the system with real data.

---

## 3. Permissions (RBAC)

Each endpoint needs one permission key. **OWNER passes every check.**

| Role | Default permissions |
| :--- | :--- |
| OWNER | All 39 |
| ADMIN | All except `users.assign_permission` and `system.configuration` |
| STAFF | `pos.view`, `pos.create_sale`, `pos.apply_discount`, `pos.reprint_receipt`, `products.view`, `categories.view`, `inventory.view`, `transactions.view`, `transactions.view_details` |

On every request the server re-loads the user from the database. So a deactivated user is locked out immediately, and permission changes apply on the next request. The `permissions` list in the login response is for the UI only.

---

## 4. Endpoints

| Method | Path | Permission | What it does |
| :--- | :--- | :--- | :--- |
| GET | `/api/health` | none | Service + database status |
| POST | `/api/auth/login` | none | Log in, get JWT |
| POST | `/api/auth/logout` | none | Log out |
| GET | `/api/auth/me` | logged in | Current user |
| GET | `/api/products` | `products.view` | List (filters: `category_id`, `status`, `search`, `low_stock`, `page`, `limit`) |
| POST | `/api/products` | `products.create` | Create |
| GET | `/api/products/{id}` | `products.view` | Get by ID or SKU |
| PUT | `/api/products/{id}` | `products.edit` | Partial update |
| DELETE | `/api/products/{id}` | `products.deactivate` | Soft delete (INACTIVE) |
| PATCH | `/api/products/{id}/status` | `products.deactivate` | Set ACTIVE / INACTIVE |
| GET | `/api/categories` | `categories.view` | List (`status`, `search`) |
| POST | `/api/categories` | `categories.create` | Create |
| GET | `/api/categories/{id}` | `categories.view` | Get |
| PUT | `/api/categories/{id}` | `categories.edit` | Update |
| DELETE | `/api/categories/{id}` | `categories.deactivate` | Soft delete |
| GET | `/api/inventory` | `inventory.view` | Stock valuation totals |
| POST | `/api/inventory/adjust` | `inventory.adjust` | STOCK_IN / STOCK_OUT / STOCK_ADJUSTMENT |
| GET | `/api/inventory/history` | `inventory.view_history` | Movement ledger |
| GET | `/api/sales` | `transactions.view` | List sales |
| POST | `/api/sales/checkout` | `pos.create_sale` | Atomic checkout |
| GET | `/api/sales/{id}` | `transactions.view_details` | Get by ID or transaction number |
| POST | `/api/sales/{id}/cancel` | `transactions.cancel` | Cancel + restock |
| POST | `/api/sales/{id}/reprint` | `pos.reprint_receipt` | Reprint receipt |
| GET | `/api/reports/dashboard` | `reports.view` | KPIs |
| GET | `/api/reports/sales` | `reports.view_sales` | Summary + daily totals |
| GET | `/api/reports/product-sales` | `reports.view_sales` | Per-product totals |
| GET | `/api/users` | `users.view` | List users |
| POST | `/api/users` | `users.create` | Create user |
| GET | `/api/users/{id}` | `users.view` | Get user |
| PUT | `/api/users/{id}` | `users.edit` | Update user |
| DELETE | `/api/users/{id}` | `users.deactivate` | Deactivate user |
| GET | `/api/users/{id}/permissions` | `users.view` | Get permissions |
| POST | `/api/users/{id}/permissions` | `users.assign_permission` | Grant permission |
| GET | `/api/approvals` | `approvals.view` | List requests |
| POST | `/api/approvals` | logged in | Submit request |
| POST | `/api/approvals/{id}/approve` | `approvals.approve` | Approve (no self-approval) |
| POST | `/api/approvals/{id}/reject` | `approvals.reject` | Reject |
| GET | `/api/audit-logs` | `system.audit_logs` | Audit trail |

---

## 5. Checkout in detail

```bash
curl -s -X POST http://localhost:8000/api/sales/checkout \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{
        "items": [ { "product_id": "2", "quantity": 2 }, { "product_id": "SNK-001", "quantity": 1 } ],
        "payment_method": "Cash",
        "amount_received": 250,
        "discount": 0,
        "transaction_number": "TXN-OFFLINE-20261026-123456"
      }'
```

What the server guarantees:

1. **No overselling.** All steps run in one PostgreSQL transaction. Stock is decremented with `UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id AND stock_quantity >= :qty`. If any line fails, the whole sale rolls back.
2. **No double charge.** `transaction_number` is unique. When the same number arrives twice, the server returns the first sale and does not deduct stock again. The offline queue depends on this.
3. **Server-side prices.** Unit prices come from the database, never from the request.
4. **Validation.** Checkout rejects an empty cart, a quantity ≤ 0, an inactive product, a discount below zero or above the subtotal, and an `amount_received` below the total.
5. **Records written:** the sale, its sale items, one `SALE` inventory ledger row per item, and one `POS_CHECKOUT` audit log entry.

---

## 6. Compatibility with the old Next.js API

The paths, methods, request bodies and response envelopes are the same as the original `app/api/**` routes. The frontend needs no code changes. These are the only differences:

| Change | Why |
| :--- | :--- |
| IDs are digit strings (`"12"`) instead of Mongo ObjectIds | PostgreSQL `bigint` keys, still serialized as strings |
| `/api/reports/dashboard` adds `today_sales`, `today_transactions`, `total_units`, `avg_sale_value` | The dashboard and reports views read these flat fields. Additive only |
| User responses never include `password_hash` | Security. The old `POST /api/users` leaked it |
| 500 responses hide internal error text | Security. Details go to server logs |
| `POST /api/users` and `PUT /api/users/{id}` require passwords of 8+ characters | Security baseline |
| `POST /api/inventory/adjust` accepts only `STOCK_IN`, `STOCK_OUT`, `STOCK_ADJUSTMENT` | Unknown types used to fall through to "subtract" silently |
| Only an OWNER can create/modify/deactivate OWNER accounts or grant the OWNER role | Prevents privilege escalation by ADMIN |
| Changing a user's role needs `users.assign_role`; changing permissions needs `users.assign_permission`. Non-owners cannot grant permissions they don't hold | RBAC keys are enforced, not just listed |
| A checkout with `discount > 0` needs `pos.apply_discount` | The permission existed but was never checked |
| A product's `category_id` must exist (400 otherwise) | Foreign key integrity in PostgreSQL |
| Editing `stock_quantity` from the product form writes a `STOCK_ADJUSTMENT` ledger row | Every stock change is traceable |
