# 🏢 Arrendamiento Seguro CRM API (2026)

> **Backend de Alto Rendimiento** | *Seguro, Escalable y Diseñado a Medida para IONOS.*

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![JWT Auth](https://img.shields.io/badge/JWT-Auth-critical?style=for-the-badge&logo=json-web-tokens&logoColor=white)
![Status](https://img.shields.io/badge/Status-Producción-success?style=for-the-badge)

Bienvenido a la **API Core de Arrendamiento Seguro**, la columna vertebral del ecosistema CRM. Este sistema ha sido construido "a mano" (Custom Core) para garantizar máxima velocidad, control total sobre la seguridad y una integración fluida con **n8n** y hostings compartidos profesionales.

---

## 🌐 Entorno de Producción

| Configuración | Valor |
| :--- | :--- |
| **URL Base** | `https://next.arrendamientoseguro.app/api/v1` |
| **DocumentRoot** | `/api/public/` (Enlazado internamente) |
| **Timezone** | `America/Mexico_City` |

---

## 🔐 Seguridad e Integridad

Hemos implementado un modelo de seguridad de grado bancario adaptado a nuestras necesidades:

1.  **JWT Dual**: Tokens de Acceso (15 min) y Tokens de Refresco (30 días).
2.  **Blacklist Activa**: Capacidad de revocar accesos instantáneamente (Logout forzado).
3.  **Rotación de Tokens**: Detección de robo de sesiones mediante reutilización de tokens.
4.  **Protección de Archivos**: Sistema `.htaccess` personalizado para bloquear acceso a `.env`, `src` y `git`.
5.  **HMAC Signatures**: Todas las comunicaciones con **n8n** están firmadas criptográficamente (`SHA-256`) para evitar suplantaciones.

---

## 📡 Endpoints Disponibles

La API es totalmente RESTful y devuelve siempre JSON estandarizado: `{ data, meta, errors }`.

### 👤 Autenticación
| Método | Endpoint | Descripción | Requiere Auth |
| :--- | :--- | :--- | :---: |
| `POST` | `/auth/login` | Iniciar sesión (Email/Pass). Devuelve Access + Refresh. | ❌ |
| `POST` | `/auth/refresh` | Renovar Access Token usando Refresh Token. | ❌ |
| `POST` | `/auth/logout` | Cerrar sesión (Invalida tokens actuales). | ✅ |

### 🏢 Gestión de Negocio (CRUD)
Todos estos endpoints soportan operaciones estándar: `GET /` (Listar), `POST /` (Crear), `GET /{id}` (Ver), `PUT /{id}` (Editar), `DELETE /{id}` (Borrar).

*   **Users** (`/users`): Gestión de administradores y operadores del CRM.
*   **Arrendadores** (`/arrendadores`): Propietarios de los inmuebles.
*   **Inquilinos** (`/inquilinos`): Arrendatarios y su información legal.
*   **Inmuebles** (`/inmuebles`): Propiedades registradas.
*   **Asesores** (`/asesores`): Agentes inmobiliarios asociados.
*   **Pólizas** (`/polizas`): La entidad central. Calcula vigencias automáticamente.
    *   *Nota*: El sistema autocalcula fechas y valida relaciones (Fiadores, Obligados) al crear.

### ✅ Validaciones
*   `GET/PUT /inquilinos/{id}/validaciones`: Gestionar el estado de validación de un inquilino específico.

### ⚙️ Sistema y Automatización
| Método | Endpoint | Descripción |
| :--- | :--- | :--- |
| `GET` | `/health` | Chequeo de estado (Verifica PHP + Conexión DB). |
| `POST` | `/events/emit` | Emitir manualmente un evento de negocio (ej. `poliza.creada`). |
| `POST` | `/automations/callbacks/{correlationId}` | Webhook de retorno para n8n (Resultados asíncronos). |

---

## 🚀 Despliegue en IONOS

Debido a las restricciones de seguridad de IONOS (Shared Hosting), utilizamos una **Estrategia de Configuración Híbrida**:

### 1. Archivos Críticos
*   **`.htaccess`**: En la raíz de `/api/`. Redirige tráfico a `/public/` y bloquea acceso a archivos sensibles.
*   **`config.local.php`**: Archivo **NO versionado** que debe existir manualmente en producción (`api/config/config.local.php`).

### 2. Configuración Manual (Producción)
Si necesitas cambiar credenciales en producción, **NO edites `.env`** (es ignorado o inseguro en este entorno). Edita directamente:

`api/config/config.local.php`

```php
return [
  'env'   => 'production',
  'debug' => false,
  'db'    => [ /* Tus credenciales reales de IONOS */ ],
  // ...
];
```

### 3. Logs y Debugging
*   Los errores fatales se registran en `storage/logs/api.log`.
*   Si recibes un **Error 500**, verifica primero los permisos de carpetas (deben ser `755`) y archivos (`644`).

---

## ⚡ Estándares de Consumo

**Headers Obligatorios:**
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer <TU_TOKEN_DE_ACCESO>
```

**Ejemplo de Respuesta Exitosa:**
```json
{
  "data": {
    "id": 150,
    "nombre": "Juan Pérez",
    "status": 1
  },
  "meta": {
    "requestId": "req_123xyz...",
    "timestamp": "2026-01-18T12:00:00Z"
  },
  "errors": []
}
```

---

<p align="center">
  <sub>Built with ❤️ by <strong>Deepmind Advanced Coding Team</strong> for <strong>Arrendamiento Seguro</strong>.</sub>
</p>
