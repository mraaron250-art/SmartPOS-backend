# SmartPOS Backend Architecture

## 1. System view

```
Browser
  │  fetch("/api/...")                     same-origin calls
  ▼
Vercel ── SmartPOS-frontend (Next.js 16)
  │  next.config.js rewrite /api/:path* → ${BACKEND_URL}/api/:path*
  ▼
Render ── SmartPOS-backend (PHP 8.4 + Apache, Docker build from GitHub)
  │  Eloquent ORM (pdo_pgsql)
  ▼
Render ── PostgreSQL 17
```

The frontend proxies `/api` server-side. To the browser, every call is same-origin, so the HTTP-only cookie works and CORS is not involved. The backend still has a CORS allowlist (`CORS_ORIGINS`) for direct clients.

## 2. Request lifecycle (MVC)

```
public/index.php
  └─ bootstrap/app.php      env (.env / real env), Eloquent boot, routes, Kernel
      └─ Kernel::handle()
          ├─ Cors                       (global middleware)
          ├─ Router::match()            method + path → controller, {params}, route middleware
          ├─ Authenticate               ('auth')         JWT from Bearer header or cookie
          ├─ RequirePermission          ('can:<perm>')   RBAC, OWNER bypass
          └─ Controller@action          validates input, calls a Service or Model
               └─ Service               business rules + DB transaction
                    └─ Eloquent Model   PostgreSQL
      └─ Response::send()
```

Any exception becomes `{ success: false, message }`. An `HttpException` keeps its status code; anything else becomes a `500`.

| Layer | Folder | Responsibility |
| :--- | :--- | :--- |
| Model | `app/Models` | Eloquent models: tables, casts, relations, JSON shape (`toArray`) |
| View | JSON (`app/Core/Response`) | The API has no HTML views. The JSON envelope is the view |
| Controller | `app/Http/Controllers` | HTTP in/out, input validation, status codes |
| Service | `app/Services` | Multi-table business logic (checkout, stock, reports, audit) |
| Middleware | `app/Http/Middleware` | CORS, authentication, authorization |
| Core | `app/Core` | Router, Kernel, Request, Response, Migrator |

## 3. Data model

```
users ─┬──< sales ──< sale_items >── products >── categories
       ├──< inventory_transactions >── products
       ├──< approval_requests (requested_by, reviewed_by)
       └──< audit_logs
store_settings (single row)
```

| Table | Key columns / constraints |
| :--- | :--- |
| `users` | `email` UNIQUE (lower-case), `role` (OWNER/ADMIN/STAFF), `status`, `permissions` JSONB |
| `categories` | `name` UNIQUE (case-insensitive), `status` |
| `products` | `sku` UNIQUE, `barcode` UNIQUE (nullable), `stock_quantity` CHECK ≥ 0, money `NUMERIC(12,2)` |
| `sales` | `transaction_number` UNIQUE (idempotency key), totals, `status` |
| `sale_items` | Snapshot of product name, SKU and unit price at sale time |
| `inventory_transactions` | Signed `quantity`, `previous_stock`, `new_stock`, `reference_id` |
| `approval_requests` | Requester, reviewer, status, comment |
| `audit_logs` | Append-only. The app never updates or deletes rows |

Denormalized names (`cashier_name`, `product_name`, `user_name`) are kept on purpose. Receipts and the audit trail stay correct when a product or user is renamed later.

## 4. Concurrency and data safety

1. **Atomic checkout.** `CheckoutService` runs in `DB::transaction()`. Each line does a conditional `UPDATE ... WHERE stock_quantity >= qty`. Zero affected rows means not enough stock, and the whole transaction rolls back. A `CHECK (stock_quantity >= 0)` constraint is a second safety net.
2. **Idempotency.** `sales.transaction_number` is UNIQUE. A duplicate insert from a parallel retry is caught. The service then returns the existing sale.
3. **Stock adjustments** use the same conditional update, so a `STOCK_OUT` can never push stock below zero.
4. **Offline mode** stays in the frontend (`lib/offlineSync.ts`, a localStorage queue). Each queued sale has a `TXN-OFFLINE-*` number, so replays are safe.

## 5. Security

| Area | Measure |
| :--- | :--- |
| Passwords | bcrypt via `password_hash` / `password_verify` (cost 10, compatible with existing `$2a$` hashes) |
| Tokens | HS256 JWT, 24h expiry, secret from `JWT_SECRET` (Render generates it). Cookie: `HttpOnly`, `Secure` in production, `SameSite=Lax` |
| SQL | Eloquent / query builder bindings only. No string-built SQL from user input |
| Errors | 500 responses hide internals (`APP_DEBUG=false`) |
| Runtime | Patched `php:8.4-apache` image, `expose_php=Off`, dangerous functions disabled, `ServerTokens Prod` |
| Headers | `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy` |
| Dependencies | `composer audit` in CI |
