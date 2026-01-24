# API Endpoint: Listar Inquilinos con Filtros

## Descripción
Este endpoint permite obtener una lista de inquilinos (prospectos) con filtros opcionales de búsqueda y status.

## Endpoint
```
GET /api/v1/inquilinos
```

## Autenticación
Requiere Bearer Token en el header `Authorization`.

## Query Parameters

| Parámetro | Tipo | Requerido | Descripción | Ejemplo |
|-----------|------|-----------|-------------|---------|
| `search` | string | No | Búsqueda por nombre completo, email o celular | `?search=juan` |
| `status` | string | No | Filtro por status del inquilino | `?status=1` |

### Valores válidos para `status`:

| Valor | Significado | Descripción |
|-------|-------------|-------------|
| `1` | **Nuevo** | Inquilino recién registrado, pendiente de revisión |
| `2` | **En Proceso** | Inquilino en proceso de validación |
| `3` | **Aprobado** | Inquilino aprobado para arrendamiento |
| `4` | **Rechazado** | Inquilino rechazado |

## Ejemplos de Uso

### 1. Listar todos los inquilinos
```bash
GET {{base_url}}/api/v1/inquilinos
```

### 2. Listar solo inquilinos nuevos (status = 1)
```bash
GET {{base_url}}/api/v1/inquilinos?status=1
```

### 3. Buscar inquilinos por nombre
```bash
GET {{base_url}}/api/v1/inquilinos?search=juan
```

### 4. Buscar inquilinos nuevos que contengan "juan"
```bash
GET {{base_url}}/api/v1/inquilinos?status=1&search=juan
```

### 5. Listar inquilinos en proceso
```bash
GET {{base_url}}/api/v1/inquilinos?status=2
```

### 6. Listar inquilinos aprobados
```bash
GET {{base_url}}/api/v1/inquilinos?status=3
```

### 7. Listar inquilinos rechazados
```bash
GET {{base_url}}/api/v1/inquilinos?status=4
```

## Respuesta Exitosa (200 OK)

```json
{
  "data": [
    {
      "id": 123,
      "nombre_inquilino": "Juan",
      "apellidop_inquilino": "Pérez",
      "apellidom_inquilino": "García",
      "email": "juan.perez@example.com",
      "celular": "5512345678",
      "status": "1",
      "fecha_registro": "2026-01-23 15:30:00"
    },
    {
      "id": 124,
      "nombre_inquilino": "María",
      "apellidop_inquilino": "López",
      "apellidom_inquilino": "Martínez",
      "email": "maria.lopez@example.com",
      "celular": "5587654321",
      "status": "1",
      "fecha_registro": "2026-01-23 16:45:00"
    }
  ],
  "meta": {
    "requestId": "req_abc123",
    "count": 2,
    "filters": {
      "search": null,
      "status": "1"
    }
  },
  "errors": []
}
```

## Respuesta de Error - Status Inválido (400 Bad Request)

```json
{
  "data": null,
  "meta": {
    "requestId": "req_xyz789"
  },
  "errors": [
    {
      "code": "bad_request",
      "message": "Invalid status. Allowed values: 1 (Nuevo), 2 (En Proceso), 3 (Aprobado), 4 (Rechazado)"
    }
  ]
}
```

## Notas Importantes

1. **Combinación de Filtros**: Los parámetros `search` y `status` se pueden combinar. Cuando ambos están presentes, se aplican con lógica AND (deben cumplirse ambas condiciones).

2. **Búsqueda Flexible**: El parámetro `search` busca coincidencias parciales (LIKE) en:
   - Nombre completo (concatenación de nombre, apellido paterno y materno)
   - Email
   - Celular

3. **Ordenamiento**: Los resultados siempre se ordenan por ID descendente (más recientes primero).

4. **Valores de Status**: Los valores de status son strings ('1', '2', '3', '4'), no números enteros.

5. **Case Sensitivity**: La búsqueda de texto es case-insensitive (no distingue mayúsculas de minúsculas).

## Casos de Uso Comunes

### Dashboard de Prospectos Nuevos
Para mostrar un dashboard con todos los prospectos que acaban de registrarse:
```bash
GET {{base_url}}/api/v1/inquilinos?status=1
```

### Búsqueda Rápida de Prospecto
Para buscar un prospecto específico por nombre o contacto:
```bash
GET {{base_url}}/api/v1/inquilinos?search=juan
```

### Filtro de Prospectos Aprobados
Para listar solo los inquilinos que ya fueron aprobados:
```bash
GET {{base_url}}/api/v1/inquilinos?status=3
```

### Revisión de Rechazados
Para revisar los inquilinos que fueron rechazados:
```bash
GET {{base_url}}/api/v1/inquilinos?status=4
```

## Código de Ejemplo (JavaScript/Fetch)

```javascript
// Obtener inquilinos nuevos
async function getNewProspects() {
  const response = await fetch('https://next.arrendamientoseguro.app/api/v1/inquilinos?status=1', {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'Content-Type': 'application/json'
    }
  });
  
  const data = await response.json();
  
  if (response.ok) {
    console.log(`Found ${data.meta.count} new prospects`);
    return data.data;
  } else {
    console.error('Error:', data.errors);
    return [];
  }
}

// Buscar inquilinos por nombre
async function searchProspects(searchTerm) {
  const response = await fetch(
    `https://next.arrendamientoseguro.app/api/v1/inquilinos?search=${encodeURIComponent(searchTerm)}`,
    {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${accessToken}`,
        'Content-Type': 'application/json'
      }
    }
  );
  
  return await response.json();
}

// Obtener inquilinos nuevos que coincidan con búsqueda
async function searchNewProspects(searchTerm) {
  const response = await fetch(
    `https://next.arrendamientoseguro.app/api/v1/inquilinos?status=1&search=${encodeURIComponent(searchTerm)}`,
    {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${accessToken}`,
        'Content-Type': 'application/json'
      }
    }
  );
  
  return await response.json();
}
```

## Código de Ejemplo (PHP/cURL)

```php
<?php
function getInquilinosByStatus($accessToken, $status) {
    $url = "https://next.arrendamientoseguro.app/api/v1/inquilinos?status=" . urlencode($status);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

// Uso
$accessToken = 'your_access_token_here';
$nuevosProspectos = getInquilinosByStatus($accessToken, '1');

if ($nuevosProspectos) {
    echo "Total de prospectos nuevos: " . $nuevosProspectos['meta']['count'] . "\n";
    foreach ($nuevosProspectos['data'] as $prospecto) {
        echo "- {$prospecto['nombre_inquilino']} {$prospecto['apellidop_inquilino']} ({$prospecto['email']})\n";
    }
}
?>
```

## Historial de Cambios

| Fecha | Versión | Cambios |
|-------|---------|---------|
| 2026-01-23 | 1.0.0 | Implementación inicial del filtro por status |

---

**Última actualización**: 2026-01-23  
**Mantenedor**: Equipo de Desarrollo API AS-CRM
