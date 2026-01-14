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
- **Refresh Rotation**: Detection of token theft via reuse chains.
- **Secure Handling**: Passwords hashed, tokens hashed in DB.

### 🔌 API Endpoints

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :---: |
| `GET` | `/api/v1/health` | System status check | ❌ |
| `POST` | `/api/v1/auth/login` | Obtain Access/Refresh tokens | ❌ |
| `POST` | `/api/v1/auth/refresh` | Rotate Access token | ❌ |
| `POST` | `/api/v1/auth/logout` | Revoke Refresh token | ❌ |
| `GET` | `/api/v1/users/me` | Get current user profile | ✅ |

---

## 🛠️ Installation & Setup

### 1. Requirements
*   PHP 8.1 or higher
*   MySQL 8.0
*   Apache mod_rewrite enabled

### 2. Configure Environment
Copy the example environment file and configure your database credentials:
```bash
cp .env.example .env
```
Edit `.env`:
```ini
DB_HOST=localhost
DB_NAME=as_db
JWT_ACCESS_SECRET=your_super_secret_key
```

### 3. Database Migration
Run this SQL to enable the secure Refresh Token system:
```sql
CREATE TABLE `usuarios_refresh_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `jti` varchar(36) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_jti` (`user_id`, `jti`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 👨‍💻 Usage

### Making Requests
The API accepts and returns **JSON**. Ensure you send the correct headers:

**Request Headers:**
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer <YOUR_ACCESS_TOKEN>
```

**Example Response:**
```json
{
  "data": {
    "status": "ok",
    "ts": "2026-01-14T12:00:00-06:00"
  },
  "meta": {
    "requestId": "a1b2c3d4"
  },
  "errors": []
}
```

---

### 📝 Logs
All activity is logged to `storage/logs/api.log` and the database `api_logs` table for full auditability.

---

Built with ❤️ by the **Deepmind Advanced Coding Team**.
