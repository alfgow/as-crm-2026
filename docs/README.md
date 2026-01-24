# Documentación API AS-CRM 2026

Bienvenido a la documentación de la API del sistema CRM de Arrendamiento Seguro.

## 📚 Índice de Documentación

### Endpoints Principales

1. **[API Inquilinos - Filtros](./API_INQUILINOS_FILTROS.md)**
   - Endpoint completo para listar inquilinos con filtros
   - Incluye filtro por status y búsqueda por texto
   - Ejemplos de uso en JavaScript y PHP

2. **[Sistema de Status de Inquilinos](./SISTEMA_STATUS_INQUILINOS.md)**
   - Explicación detallada de cada status
   - Flujos y transiciones permitidas
   - Mejores prácticas y reglas de negocio

## 🚀 Inicio Rápido

### Autenticación

Todos los endpoints requieren autenticación mediante Bearer Token:

```bash
Authorization: Bearer {access_token}
```

### Base URL

```
https://next.arrendamientoseguro.app
```

### Ejemplo Básico

```bash
# Obtener inquilinos nuevos
curl -X GET "https://next.arrendamientoseguro.app/api/v1/inquilinos?status=1" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json"
```

## 📋 Recursos Principales

### Inquilinos (Prospectos)

Los inquilinos son los prospectos que solicitan arrendamiento. El sistema maneja su ciclo de vida completo desde el registro hasta la aprobación o rechazo.

**Endpoints principales**:
- `GET /api/v1/inquilinos` - Listar inquilinos (con filtros opcionales)
- `GET /api/v1/inquilinos/{id}` - Obtener inquilino por ID
- `POST /api/v1/inquilinos` - Crear nuevo inquilino
- `PUT /api/v1/inquilinos/{id}` - Actualizar inquilino
- `PUT /api/v1/inquilinos/{id}/status` - Actualizar status
- `DELETE /api/v1/inquilinos/{id}` - Eliminar inquilino

**Status disponibles**:
- `1` - Nuevo
- `2` - En Proceso
- `3` - Aprobado
- `4` - Rechazado

Ver [Sistema de Status de Inquilinos](./SISTEMA_STATUS_INQUILINOS.md) para más detalles.

## 🔍 Filtros y Búsqueda

### Filtro por Status

Obtener solo inquilinos con un status específico:

```bash
GET /api/v1/inquilinos?status=1
```

### Búsqueda por Texto

Buscar inquilinos por nombre, email o celular:

```bash
GET /api/v1/inquilinos?search=juan
```

### Combinación de Filtros

Combinar múltiples filtros:

```bash
GET /api/v1/inquilinos?status=1&search=juan
```

Ver [API Inquilinos - Filtros](./API_INQUILINOS_FILTROS.md) para ejemplos completos.

## 📊 Casos de Uso Comunes

### 1. Dashboard de Prospectos Nuevos

Mostrar todos los prospectos que requieren atención inmediata:

```javascript
const response = await fetch(
  'https://next.arrendamientoseguro.app/api/v1/inquilinos?status=1',
  {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  }
);

const { data, meta } = await response.json();
console.log(`${meta.count} prospectos nuevos`);
```

### 2. Búsqueda de Prospecto

Buscar un prospecto específico:

```javascript
const searchTerm = 'juan';
const response = await fetch(
  `https://next.arrendamientoseguro.app/api/v1/inquilinos?search=${searchTerm}`,
  {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  }
);
```

### 3. Actualizar Status de Prospecto

Cambiar el status de un prospecto de "Nuevo" a "En Proceso":

```javascript
const response = await fetch(
  'https://next.arrendamientoseguro.app/api/v1/inquilinos/123/status',
  {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ status: '2' })
  }
);
```

## 🛠️ Herramientas de Desarrollo

### Postman Collection

El proyecto incluye una colección de Postman completa:

```
API-AS-2026.postman_collection.json
```

Importa este archivo en Postman para tener acceso a todos los endpoints documentados.

### Variables de Entorno

Configura estas variables en tu entorno de desarrollo:

```json
{
  "base_url": "https://next.arrendamientoseguro.app",
  "access": "your_access_token_here"
}
```

## 📝 Formato de Respuestas

Todas las respuestas de la API siguen este formato estándar:

### Respuesta Exitosa

```json
{
  "data": [...],
  "meta": {
    "requestId": "req_abc123",
    "count": 10,
    "filters": {
      "search": null,
      "status": "1"
    }
  },
  "errors": []
}
```

### Respuesta de Error

```json
{
  "data": null,
  "meta": {
    "requestId": "req_xyz789"
  },
  "errors": [
    {
      "code": "bad_request",
      "message": "Invalid status value"
    }
  ]
}
```

## 🔐 Seguridad

### Autenticación

La API utiliza JWT (JSON Web Tokens) para autenticación:

1. Obtén un token mediante el endpoint de login
2. Incluye el token en el header `Authorization` de cada request
3. Los tokens expiran después de cierto tiempo
4. Usa el endpoint de refresh para renovar tokens

### Mejores Prácticas

- ✅ Nunca compartas tu access token
- ✅ Almacena tokens de forma segura
- ✅ Implementa refresh token automático
- ✅ Usa HTTPS en producción
- ✅ Valida siempre las respuestas del servidor

## 📈 Métricas y Monitoreo

### Campos de Metadata

Cada respuesta incluye metadata útil:

- `requestId`: ID único de la petición (útil para debugging)
- `count`: Número de resultados retornados
- `filters`: Filtros aplicados en la consulta

### Logging

Todas las peticiones se registran en el servidor para auditoría y debugging.

## 🐛 Debugging

### Códigos de Error Comunes

| Código HTTP | Significado | Solución |
|-------------|-------------|----------|
| 400 | Bad Request | Verifica los parámetros enviados |
| 401 | Unauthorized | Verifica tu token de autenticación |
| 404 | Not Found | Verifica que el recurso existe |
| 500 | Server Error | Contacta al equipo de desarrollo |

### Tips de Debugging

1. **Verifica el requestId** en la respuesta para rastrear la petición en logs
2. **Revisa el campo errors** para mensajes de error detallados
3. **Valida el formato** de tus parámetros antes de enviar
4. **Usa Postman** para probar endpoints antes de implementar

## 📞 Soporte

Para soporte técnico o preguntas sobre la API:

- **Documentación**: Revisa los archivos en `/docs`
- **Postman Collection**: Usa `API-AS-2026.postman_collection.json`
- **Equipo de Desarrollo**: Contacta al equipo interno

## 🔄 Historial de Versiones

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0.0 | 2026-01-23 | Implementación de filtro por status en inquilinos |

## 📚 Documentos Adicionales

- [API Inquilinos - Filtros](./API_INQUILINOS_FILTROS.md) - Guía completa de filtros
- [Sistema de Status](./SISTEMA_STATUS_INQUILINOS.md) - Explicación del sistema de status

---

**Última actualización**: 2026-01-23  
**Versión de la API**: 1.0  
**Mantenedor**: Equipo de Desarrollo API AS-CRM
