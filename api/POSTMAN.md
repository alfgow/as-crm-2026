# Colección de Postman - AS CRM 2026

## Configuración de Entorno
Crea un **Environment** en Postman con la variable:
- `base_url`: `http://localhost/as-crm-2026/api/public`

---

## Endpoints

### Estándares y Convenciones

#### 1. Formato de Respuesta General
Todas las respuestas de la API siguen este formato JSON (Wrapper):

```json
{
  "data": mixed,      // Objeto, Array o null con la carga útil
  "meta": {
    "requestId": "hex_string",
    "count": int,     // Opcional: Para listados, número de elementos retornados
    "page": int,      // Opcional: Página actual
    "limit": int,     // Opcional: Límite por página
    "total": int      // Opcional: Total de registros
  },
  "errors": [         // Array de errores (vacío si éxito)
    {
      "code": "VALIDATION_ERROR", // Códigos estándar (UPPER_CASE)
      "field": "email",           // Opcional: Campo específico del error
      "message": "El formato del email no es válido"
    }
  ]
}
```

#### 2. Paginación
Para endpoints de listado (GET), se soportan los parámetros estándar:
- `page`: Número de página (Default: 1).
- `limit`: Resultados por página (Default: 20, Max: 100).

Respuesta de Metadatos de paginación (ejemplo):
```json
"meta": {
  "requestId": "...",
  "page": 1,
  "limit": 20,
  "total": 150,
  "count": 20
}
```

#### 3. Consultas y Filtros
- Los filtros se pasan como query params: `?estado=1&tipo=Clasica`
- Para búsquedas de texto: `?q=termino_busqueda`

#### 4. Catálogos y Enums
Se utilizan identificadores numéricos para estados y tipos clave. El texto es derivado.

**Estado de Póliza (`estado`):**
- `1` : Vigente
- `2` : Concluida
- `3` : Término Anticipado
- `4` : Incumplimiento
*(Nota: En request body SIEMPRE enviar el valor numérico)*

---

### 1. Health Check (Verificar estado)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/health`
- **Body**: None

### 2. Login (Iniciar Sesión)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/auth/login`
- **Headers**: 
  - `Content-Type`: `application/json`
- **Body** (Raw JSON):
  ```json
  {
    "email": "test@arrendamientoseguro.app",
    "password": "password123"
  }
  ```

### 3. Get User Profile (Perfil de Usuario)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/users/me`
- **Headers**:
  - `Authorization`: `Bearer <Pegar AccessToken Aquí>`

### 4. Refresh Token (Renovar Sesión)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/auth/refresh`
- **Headers**: `Content-Type: application/json`
- **Body** (Raw JSON):
  ```json
  {
    "refreshToken": "<Refresh Token>"
  }
  ```

### 5. Logout / Revoke Token
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/auth/logout`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token a revocar>`
- **Description**: Invalida inmediatamente el token Bearer actual (lo añade a una lista negra) y opcionalmente revoca el refresh token si se envía.
- **Body** (Raw JSON - Opcional):
  ```json
  {
    "refreshToken": "<Refresh Token>"
  }
  ```

## Gestión de Usuarios (CRUD)

- **URL**: `{{base_url}}/api/v1/users`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 6. Crear Usuario
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/users`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "nombre_usuario": "Nuevo",
    "apellidos_usuario": "Usuario",
    "usuario": "nuevouser",
    "email": "nuevo@test.com",
    "password": "password123",
    "tipo_usuario": 2,
    "corto_usuario": "NU"
  }
  ```

### 7. Obtener Usuario por ID
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/users/{{user_id}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 8. Actualizar Usuario
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/users/{{user_id}}`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "nombre_usuario": "Nombre Actualizado"
  }
  ```

### 9. Eliminar Usuario
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/users/{{user_id}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Gestión de Arrendadores (CRUD)

### 10. Listar Arrendadores
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/arrendadores`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 11. Crear Arrendador
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/arrendadores`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "nombre_arrendador": "Carlos López",
    "email": "carlos@arrendador.com",
    "celular": "5512345678",
    "rfc": "LOPE800101XXX",
    "estatus": "ACTIVO"
  }
  ```

### 12. Obtener Arrendador por ID
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 13. Actualizar Arrendador
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "celular": "5599887766",
    "comentarios": "Actualizado desde API"
  }
  ```

### 14. Eliminar Arrendador
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Gestión de Asesores (CRUD)

### 15. Listar Asesores
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/asesores`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 16. Crear Asesor
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/asesores`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "nombre_asesor": "Ana Asesora",
    "email": "ana@asesores.com",
    "celular": "5533445566",
    "telefono": "55551234"
  }
  ```

### 17. Obtener Asesor por ID
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/asesores/{{id_asesor}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 18. Actualizar Asesor
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/asesores/{{id_asesor}}`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "celular": "5533333333"
  }
  ```

### 19. Eliminar Asesor
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/asesores/{{id_asesor}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Gestión de Inmuebles (CRUD)

### 20. Listar Inmuebles
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inmuebles`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 21. Crear Inmueble
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/inmuebles`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "id_arrendador": 1,
    "id_asesor": 1,
    "direccion_inmueble": "Av. Reforma 222, CDMX",
    "tipo": "Departamento",
    "renta": "25000",
    "mantenimiento": "Incluido",
    "monto_mantenimiento": "0",
    "deposito": "25000",
    "estacionamiento": 1,
    "mascotas": "Si",
    "comentarios": "Vista panorámica"
  }
  ```

### 22. Obtener Inmueble por ID
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inmuebles/{{id_inmueble}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 23. Actualizar Inmueble
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inmuebles/{{id_inmueble}}`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "renta": "26500",
    "comentarios": "Precio actualizado 2026"
  }
  ```

### 24. Eliminar Inmueble
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/inmuebles/{{id_inmueble}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Gestión de Inquilinos (Universo Completo)

### 25. Listar Inquilinos (Básico)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos`
- **Params**:
  - `search` (Opcional): Texto para filtrar por nombre, email o celular. Ejemplo: `?search=Carlos`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 26. Crear Inquilino (Perfil Completo)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/inquilinos`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON - Nested):
  ```json
  {
    "id_asesor": 1,
    "tipo": "Arrendatario",
    "nombre_inquilino": "Luis Miguel",
    "apellidop_inquilino": "Gallego",
    "email": "luis@sol.com",
    "celular": "5512121212",
    "nacionalidad": "Mexicana",
    "tipo_id": "INE",
    "status": 1,
    "direccion": {
        "calle": "Paseo de las Palmas",
        "num_exterior": "100",
        "colonia": "Lomas",
        "codigo_postal": "11000"
    },
    "trabajo": {
        "empresa": "El Sol Inc.",
        "direccion_empresa": "Sunset Blvd 123",
        "telefono_empresa": "5500000000",
        "puesto": "Cantante",
        "antiguedad": "10 años",
        "sueldo": "500000",
        "nombre_jefe": "Sony Music",
        "tel_jefe": "5599999999"
    }
  }
  ```

### 27. Obtener Perfil Completo Inquilino
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}`
- **Description**: Devuelve objeto inquilino + direccion + trabajo + fiador + historial.
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 28. Actualizar Inquilino (Parcial)
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}`
- **Descripción**: Permite actualizar campos del inquilino principal y de sus relaciones (dirección, trabajo, etc.) en un solo request. Solo envía los campos que deseas cambiar.
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "celular": "5599999999",
    "direccion": {
        "calle": "Calle Nueva 123",
        "codigo_postal": "00000"
    },
    "trabajo": {
        "puesto": "Gerente",
        "sueldo": "750000"
    }
  }
  ```

### 29. Eliminar Inquilino
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Gestión de Pólizas (CRUD)

### 30. Listar Pólizas
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/polizas`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 31. Crear Póliza
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/polizas`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "tipo_poliza": "Plus",
    // id_asesor y id_arrendador se toman automáticamente del inmueble
    "id_inquilino": 1,
    "id_obligado": 0, // Se convertirá a 292 (NO APLICA / Obligado Solidario) si es 0
    "id_fiador": 0,   // Se convertirá a 40 (no / Fiador) si es 0
    "id_inmueble": 1,
    "monto_poliza": 4500.00,
    "estado": 1, 
    "vigencia": "12 meses",
    "comentarios": "Póliza nueva"
  }
  // Nota: tipo_inmueble, monto_renta, fechas y numero_poliza se auto-calculan.
  // Estado: 1=Vigente, 2=Concluida...
  ```

### 32. Obtener Póliza por Número
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/polizas/numero/{{numero}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 33. Actualizar Póliza por Número
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/polizas/numero/{{numero}}`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "estado": 2,
    "comentarios": "Poliza concluida"
  }
  // Estado: 1=Vigente, 2=Concluida, 3=Término Anticipado, 4=Incumplimiento
  ```

### 34. Eliminar Póliza por Número
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/polizas/numero/{{numero}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Gestión de Validaciones (Inquilino)

### 35. Consultar Validaciones
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 36. Actualizar Validaciones
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "proceso_validacion_documentos": 2,
    "validacion_documentos_resumen": "Documentos OK",
    "validacion_documentos_json": { "ine": "ok", "comprobante": "ok" },
    "proceso_validacion_ingresos": 1,
    "comentarios": "Validación en proceso"
  }
  ```

## Autenticación API (Clientes)

### 37. API Login (Clientes API)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/auth/api/login`
- **Headers**: `Content-Type: application/json`
- **Body** (Raw JSON):
  ```json
  {
    "client_id": "<client_id>",
    "client_secret": "<client_secret>",
    "audience": "api.arrendamientoseguro.local",
    "scopes": ["read", "write"]
  }
  ```

### 38. API Refresh (Clientes API)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/auth/api/refresh`
- **Headers**: `Content-Type: application/json`
- **Body** (Raw JSON):
  ```json
  {
    "refresh_token": "<refresh_token>"
  }
  ```

## Integraciones / API Clients

### 39. Listar API Clients
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/api-clients`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 40. Crear API Client
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/api-clients`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "name": "Cliente Integración",
    "scopes": ["read", "write"],
    "rate_limit": 60
  }
  ```

### 41. Rotar Secret API Client
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/api-clients/{{client_id}}/rotate-secret`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 42. Listar Integrations Clients (Alias)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/integrations/clients`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 43. Crear Integrations Client (Alias)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/integrations/clients`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 44. Rotar Secret Integrations Client (Alias)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/integrations/clients/{{client_id}}/rotate-secret`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Prospectos

### 45. Ver Código de Prospecto
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/prospectos/code`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 46. Generar Código / Magic Link
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/prospectos/code`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 47. Enviar Emails a Prospectos
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/prospectos/send-emails`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

## Media / Presign

### 48. Presign Single
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/media/presign`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 49. Presign Many
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/media/presign-many`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

## Blog

### 50. Obtener Blog por Slug
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/blog/slug/{{slug}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Arrendadores (Endpoints adicionales)

### 51. Arrendadores por Asesor
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/asesores/{{id_asesor}}/arrendadores`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 52. Arrendador por Slug
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/arrendadores/slug/{{slug}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 53. Actualizar Asesor de Arrendador
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/asesor`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 54. Actualizar Datos Personales Arrendador
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/datos-personales`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 55. Actualizar Info Bancaria Arrendador
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/info-bancaria`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 56. Actualizar Comentarios Arrendador
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/comentarios`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 57. Listar Archivos Arrendador
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/archivos`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 58. Subir Archivo Arrendador
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/archivos`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 59. Reemplazar Archivo Arrendador
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/archivos/{{archivo_id}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 60. Eliminar Archivo Arrendador
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/archivos/{{archivo_id}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Inmuebles (Endpoints adicionales)

### 61. Inmuebles por Arrendador
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/arrendadores/{{id_arrendador}}/inmuebles`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 62. Inmueble Info
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inmuebles/{{id_inmueble}}/info`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 63. Inmueble Legacy (pk/sk)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inmuebles/legacy/{{pk}}/{{sk}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 64. Inmueble Legacy Info (pk/sk)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inmuebles/legacy/{{pk}}/{{sk}}/info`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 65. Guardar Inmueble vía AJAX
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/inmuebles/guardar-ajax`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Inquilinos (Endpoints adicionales)

### 66. Inquilino por Slug
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 67. Actualizar Datos Personales Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/datos-personales`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 68. Actualizar Status Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/status`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 69. Actualizar Asesor Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/asesor`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 70. Actualizar Dirección Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/direccion`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 71. Actualizar Trabajo Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/trabajo`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 72. Actualizar Fiador Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/fiador`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 73. Actualizar Historial Vivienda Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/historial-vivienda`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`

### 74. Subir Archivo Inquilino
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/archivos`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 75. Reemplazar Archivo Inquilino
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/archivos/{{archivo_id}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 76. Eliminar Archivo Inquilino
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/archivos/{{archivo_id}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 77. Archivos Presignados por Slug
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}/archivos-presignados`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Pólizas (Endpoints adicionales)

### 78. Buscar Pólizas
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/polizas/buscar`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 79. Renta de Póliza
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/polizas/{{numero}}/renta`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 80. Renovar Póliza
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/polizas/{{numero}}/renovar`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Notas**:
  - La renovación clona la póliza base y genera un **nuevo** `numero_poliza`.
  - Responde con `201 Created` cuando la nueva póliza se genera correctamente.
  - **No concluye automáticamente** la póliza anterior; si se requiere, debe actualizarse manualmente su estado.

### 81. Contrato por Número (omitido PDF)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/polizas/numero/{{numero}}/contrato`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 82. Guardar Contrato por Número (omitido PDF)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/polizas/numero/{{numero}}/contrato`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Financieros

### 83. Listar Financieros
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/financieros`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 84. Registro de Venta
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/financieros/registro-venta`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Description**: Vista informativa; el alta de ventas se gestiona en el frontend.

### 84a. Ventas - Crear (pendiente backend)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/financieros/ventas`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "fecha_venta": "2026-01-15",
    "canal_venta": "Arrendamiento Seguro",
    "concepto_venta": "Póliza Clásica",
    "monto_venta": 15000,
    "comision_asesor": "Nombre Asesor",
    "ganancia_neta": 4500
  }
  ```
- **Description**: Endpoint propuesto para alta de ventas (pendiente implementar).

### 84b. Ventas - Actualizar (pendiente backend)
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/financieros/ventas/{{id_venta}}`
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Description**: Endpoint propuesto para editar ventas (pendiente implementar).

### 84c. Ventas - Eliminar (pendiente backend)
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/financieros/ventas/{{id_venta}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Description**: Endpoint propuesto para borrar ventas (pendiente implementar).

### 84d. Ventas - Reporte por periodo (pendiente backend)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/financieros/ventas/periodo?inicio=2026-01-01&fin=2026-01-31`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Description**: Reporte agregado por rango de fechas (pendiente implementar).

### 84e. Ventas - Reporte por canal (pendiente backend)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/financieros/ventas/canal?anio=2026&mes=1`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Description**: Reporte agregado por canal para un mes (pendiente implementar).

### 84f. Ventas - Exportación (pendiente backend)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/financieros/ventas/export?inicio=2026-01-01&fin=2026-01-31`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Description**: Exportación a CSV/XLSX (pendiente implementar).

## Dashboard / Vencimientos

### 85. Dashboard
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/dashboard`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 86. Vencimientos
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/vencimientos`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Validaciones legales / identidad / AWS / IA

### 87. Status Validaciones Legal
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones-legal/status`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 88. Ejecutar Validación Legal
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones-legal/run`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 92. Último Reporte Legal
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones-legal/ultimo`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 93. Historial Legal
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones-legal/historial`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 94. Historial Legal (JSON)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones-legal/historial-json`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 95. Toggle Demandas
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validaciones-legal/toggle-demandas`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 96. Historial Legal por Slug
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}/validaciones-legal/historial`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 97. Validación Identidad por Slug
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}/validacion-identidad`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 98. Procesar Validación Identidad
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/validacion-identidad/procesar`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "slug": "{{slug}}",
    "tipo": "identidad"
  }
  ```

### 99. Resultado Validación Identidad
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}/validacion-identidad/resultado`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 100. Validación AWS (manual)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/ia/validar/{{slug}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 100a. Validación AWS Manual (endpoint directo)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/validacion-aws/manual?slug={{slug}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 100b. Validación AWS Procesar
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/validacion-aws/procesar`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "slug": "{{slug}}"
  }
  ```

### 101. Validación AWS (checks)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}/validacion-aws`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Query**:
  - `check`: `archivos|faces|ocr|parse|kv|match|status|resumen_full`

### 102. Validación AWS (checks POST)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}/validacion-aws`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 104. Archivos AWS por Slug
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/inquilinos/slug/{{slug}}/validacion-aws/archivos`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 105. Ingresos PDF Simple (AWS)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/inquilinos/{{id_inquilino}}/validacion-aws/ingresos-pdf-simple`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106. IA - Vista
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106a. IA - Modelos
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/modelos`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106b. IA - Modelos Disponibles
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/modelos-disponibles`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106c. IA - Ventas
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas?anio=2026&mes=1`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106d. IA - Ventas Total por Periodo
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/total?anio=2026&mes=1`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106e. IA - Ventas por Canal
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/canal?anio=2026&mes=1`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106f. IA - Ventas por Modelo
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/modelo?anio=2026&mes=1`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106g. IA - Ventas por Fecha
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/fecha?inicio=2026-01-01&fin=2026-01-31`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106h. IA - Ventas por Usuario
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/usuario?anio=2026&mes=1`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106i. IA - Ventas por Proceso
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/proceso?anio=2026&mes=1`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106j. IA - Ventas por Proceso (Periodo)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/proceso/periodo?inicio=2026-01-01&fin=2026-01-31`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106k. IA - Ventas por Proceso (Usuario)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/proceso/usuario?anio=2026&mes=1&usuario=Nombre%20Asesor`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 106l. IA - Ventas por Canal (Periodo)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/ventas/canal/periodo?inicio=2026-01-01&fin=2026-01-31`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 107. IA - Chat
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/ia/chat`
- **Headers**:
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "prompt": "Buscar inquilino Juan Perez",
    "model": "direct"
  }
  ```

### 108. IA - Historial
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/historial`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 109. IA - Ver Historial
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/ia/historial/{{id}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

## Integración y Eventos (Automatización)

### 110. Emitir Evento (Interno)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/events/emit`
- **Description**: Endpoint para registrar un evento de negocio en la Outbox.
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "eventType": "tenant.validation.requested",
    "aggregateType": "inquilino",
    "aggregateId": "123",
    "data": { "tipo": "verificamex" }
  }
  ```
- **Response**:
  ```json
  {
    "data": { "correlationId": "evt_..." },
    ...
  }
  ```

### 111. Callback Automatización (n8n → API)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/automations/callbacks/{{correlation_id}}`
- **Description**: Endpoint llamado por n8n al finalizar (éxito o fallo) para actualizar la bitácora y el estado del evento.
- **Headers**:
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer <Token>`
- **Body** (Raw JSON):
  ```json
  {
    "status": "succeeded",
    "n8n_workflow": "Policy Renewal Due",
    "n8n_execution_id": "12345",
    "result": { "messageId": "abc-123", "targets": ["email@test.com"] },
    "error_message": null
  }
  ```
