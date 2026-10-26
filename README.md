# SmartPOS Backend

REST API for SmartPOS, a web-based point of sale and inventory system.

| | |
| :--- | :--- |
| Language | PHP 8.4 |
| Architecture | Custom MVC (Router → Middleware → Controller → Service → Model) |
| ORM | Eloquent (`illuminate/database`, standalone) |
| Database | PostgreSQL 17 |
| Auth | JWT (24h), HTTP-only cookie or `Authorization: Bearer` |
| Hosting | Render (auto-deploy from GitHub) + Render Postgres |
| Frontend | [SmartPOS-frontend](https://github.com/mraaron250-art/SmartPOS-frontend) (Next.js on Vercel) |

## Project layout

```
public/index.php        Front controller (single entry point)
bootstrap/app.php       Loads env, boots Eloquent, registers routes
app/Core/               Router, Request, Response, Kernel, Migrator
app/Http/Controllers/   One controller per API module
app/Http/Middleware/    CORS, JWT auth, RBAC permission checks
app/Models/             Eloquent models
app/Services/           Business logic (checkout, inventory, reports, audit)
app/Support/            Permissions, validation helpers
config/                 app, database, cors, middleware
database/migrations/    Schema (Eloquent Schema Builder)
database/seeders/       Demo data
routes/api.php          Route table
bin/console             migrate | seed | fresh
docs/                   API contract and documentation
```

## Run locally

Requirements: Docker Desktop.

```bash
docker compose up --build
curl http://localhost:8000/api/health
```

The container runs migrations and seeds the demo data on first start.

## Deploy

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
