# 🚀 AS-CRM Core API (2026 Edition)

> **High-Performance Backend System** | *Secure by Design, Scalable by Nature.*

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![JWT Auth](https://img.shields.io/badge/JWT-Auth-critical?style=for-the-badge&logo=json-web-tokens&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)

Welcome to the **AS-CRM API**, a bespoke backend solution engineered for the Arrendamiento Seguro ecosystem. This project abandons the bloat of monolithic frameworks in favor of a lean, SOLID-compliant architecture that puts **performance** and **maintainability** first.

---

## 🏛️ Architecture & Design

We follow a strict **Clean Architecture** approach:

*   **⚡ Core**: The heart of the framework (Router, Request/Response, Database). Lightweight and fast.
*   **🛡️ Middleware**: Interceptors for Security (Cors, Auth) applied before controllers.
*   **🎮 Controllers**: Pure handlers that translate HTTP requests into domain actions.
*   **💾 Repositories**: The *only* layer that talks to the database. SQL lives here.

### Directory Structure
```
api/
├── config/             # Environment & Global Config
├── public/             # Entry Point (index.php)
├── src/
│   ├── Controllers/    # Http Handlers (Auth, User, etc.)
│   ├── Core/           # The Framework Engine
│   ├── Middleware/     # JWT & CORS Barriers
│   └── Repositories/   # SQL & Data Access
├── storage/            # Logs & Temp files
└── .env                # Secrets (Not committed)
```

---

## 🔥 Key Features
### 🔐 Advanced Security
- **JWT Authentication**: Short-lived `Access Tokens` (15m) + Long-lived `Refresh Tokens` (30d).
- **Token Blacklist**: Immediate logout capability via `usuarios_access_token_blacklist`.
- **Refresh Rotation**: Secure session renewal detecting theft reuse.

### 🧠 Smart Policy Engine
- **Auto-Calculation**: Automatic derivation of policy numbers, dates, and effective periods.
- **Intelligent Defaults**: Auto-fill for related entities (`id_obligado`, `id_fiador`) and properties data.
- **Strict Enums**: Numeric state management (1=Vigente, 2=Concluida...) for consistency.

### 🤖 Event-Driven Automation (n8n Integration)
- **Outbox Pattern**: Transactional event emitting via `event_outbox` table. Guaranteed delivery.
- **Worker Dispatcher**: PHP CLI worker (`bin/dispatch_outbox.php`) to push events to n8n webhooks.
- **HMAC Security**: All calls to/from n8n are signed with SHA-256 for integrity verification.
- **Lifecycle Tracking**: Full log of automation execution in `automation_runs`.

### 🔌 Key Endpoints
| Method | Endpoint | Description | Auth |
| :--- | :--- | :--- | :---: |
| `POST` | `/api/v1/auth/login` | Obtain Access/Refresh tokens | ❌ |
| `POST` | `/api/v1/auth/logout` | Revoke tokens (Blacklist) | ✅ |
| `GET` | `/api/v1/polizas` | List Policies (Smart Filters) | ✅ |
| `POST` | `/api/v1/polizas` | Create Policy (Auto-Calculated) | ✅ |
| `POST` | `/api/v1/events/emit` | Manually emit business event | ✅ |
| `POST` | `/api/v1/automations/callbacks/{id}` | Receive n8n result (HMAC) | ⚠️ |

---

## 🛠️ Installation & Setup

### 1. Requirements
*   PHP 8.1 or higher
*   MySQL 8.0
*   Apache mod_rewrite enabled
*   (Optional) Cron/Supervisor for Worker

### 2. Configure Environment
Copy `.env.example` -> `.env` and configure:
```ini
DB_HOST=localhost
DB_NAME=as_db
JWT_ACCESS_SECRET=super_secret
N8N_EVENTS_WEBHOOK_URL=https://n8n.your-domain.com/webhook/...
N8N_HMAC_SECRET=another_secret_for_signing
```

### 3. Database Migration
Run the included SQL script to create all necessary tables (Auth, Outbox, Logs):
`migration_auth_2026_01_14.sql`

This script creates:
- `usuarios_refresh_tokens`
- `usuarios_access_token_blacklist`
- `event_outbox`
- `automation_runs`

### 4. Running the Worker (Automation)
To dispatch events to n8n, run the worker process. Ideally, configure via Supervisor or Cron (every minute):
```bash
php bin/dispatch_outbox.php
```

---

## 👨‍💻 Usage & Standards

### Standards
- **Response Wrapper**: `{ data, meta, errors }`.
- **Pagination**: Use `?page=1&limit=20`.
- **Enums**: Always use numeric IDs for states (e.g., Póliza Status: 1=Vigente).

### Making Requests
**Request Headers:**
```http
Content-Type: application/json
Authorization: Bearer <YOUR_ACCESS_TOKEN>
```

**Example Response:**
```json
{
  "data": { "status": "ok" },
  "meta": { "requestId": "a1b2c3d4", "page": 1, "total": 100 },
  "errors": []
}
```

---

### 📝 Logs
All activity is logged to `storage/logs/api.log` and the database `api_logs` table for full auditability.

---

Built with ❤️ by the **Deepmind Advanced Coding Team**.
