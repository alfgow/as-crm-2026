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

### 32. Obtener Póliza por ID
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/polizas/{{id_poliza}}`
- **Headers**:
  - `Authorization`: `Bearer <Token>`

### 33. Actualizar Póliza
- **Method**: `PUT`
- **URL**: `{{base_url}}/api/v1/polizas/{{id_poliza}}`
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

### 34. Eliminar Póliza
- **Method**: `DELETE`
- **URL**: `{{base_url}}/api/v1/polizas/{{id_poliza}}`
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

## Integración y Eventos (Automatización)

### 37. Emitir Evento (Interno)
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

### 38. Callback Automatización (n8n → API)
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
