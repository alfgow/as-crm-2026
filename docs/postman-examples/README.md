# Ejemplos de Postman - Inquilinos con Filtros

Esta carpeta contiene ejemplos de requests de Postman para el endpoint de inquilinos con los nuevos filtros implementados.

## 📁 Archivos Disponibles

### 1. listar_inquilinos_todos.json
**Endpoint**: `GET /api/v1/inquilinos`  
**Descripción**: Lista todos los inquilinos sin aplicar filtros  
**Uso**: Vista general de todos los prospectos

### 2. listar_inquilinos_nuevos.json
**Endpoint**: `GET /api/v1/inquilinos?status=1`  
**Descripción**: Lista solo inquilinos con status "Nuevo"  
**Uso**: Dashboard de prospectos que requieren atención inmediata

### 3. listar_inquilinos_en_proceso.json
**Endpoint**: `GET /api/v1/inquilinos?status=2`  
**Descripción**: Lista solo inquilinos con status "En Proceso"  
**Uso**: Seguimiento de prospectos en validación

### 4. listar_inquilinos_aprobados.json
**Endpoint**: `GET /api/v1/inquilinos?status=3`  
**Descripción**: Lista solo inquilinos con status "Aprobado"  
**Uso**: Prospectos listos para firma de contrato

### 5. listar_inquilinos_rechazados.json
**Endpoint**: `GET /api/v1/inquilinos?status=4`  
**Descripción**: Lista solo inquilinos con status "Rechazado"  
**Uso**: Revisión de prospectos rechazados

### 6. buscar_inquilinos_nuevos.json
**Endpoint**: `GET /api/v1/inquilinos?status=1&search=juan`  
**Descripción**: Combina filtro por status y búsqueda por texto  
**Uso**: Búsqueda específica dentro de prospectos nuevos

## 🚀 Cómo Usar

### Opción 1: Importar Individual
1. Abre Postman
2. Click en "Import"
3. Selecciona el archivo JSON que desees
4. El request aparecerá en tu colección

### Opción 2: Importar Todos
1. Abre Postman
2. Click en "Import"
3. Selecciona todos los archivos JSON de esta carpeta
4. Todos los requests se importarán a tu colección

## ⚙️ Configuración Requerida

Antes de usar estos requests, configura las siguientes variables de entorno en Postman:

### Variables de Entorno

```json
{
  "base_url": "https://next.arrendamientoseguro.app",
  "access": "tu_token_de_acceso_aqui"
}
```

### Cómo Configurar Variables

1. En Postman, click en el ícono de ⚙️ (Settings) en la esquina superior derecha
2. Selecciona "Manage Environments"
3. Click en "Add" para crear un nuevo entorno
4. Nombra el entorno (ej: "AS-CRM Development")
5. Agrega las variables:
   - `base_url` = `https://next.arrendamientoseguro.app`
   - `access` = (tu token de acceso)
6. Click en "Add" para guardar
7. Selecciona el entorno en el dropdown de la esquina superior derecha

## 📝 Notas Importantes

### Autenticación
- Todos los requests requieren autenticación Bearer Token
- El token se toma de la variable `{{access}}`
- Asegúrate de tener un token válido antes de hacer requests

### Valores de Status
Los valores de status son **strings**, no números:
- ✅ Correcto: `"status": "1"`
- ❌ Incorrecto: `"status": 1`

### Combinación de Filtros
Puedes combinar múltiples filtros en la URL:
```
?status=1&search=juan
```

## 🧪 Pruebas Sugeridas

### Test 1: Verificar Filtro por Status
1. Importa `listar_inquilinos_nuevos.json`
2. Ejecuta el request
3. Verifica que todos los resultados tengan `"status": "1"`

### Test 2: Verificar Búsqueda
1. Importa `buscar_inquilinos_nuevos.json`
2. Cambia el valor de `search` a un nombre que exista en tu BD
3. Ejecuta el request
4. Verifica que los resultados coincidan con la búsqueda

### Test 3: Verificar Combinación
1. Usa `buscar_inquilinos_nuevos.json`
2. Verifica que los resultados cumplan AMBAS condiciones:
   - Status = "1"
   - Nombre/email/celular contiene el término de búsqueda

## 📊 Respuestas Esperadas

### Respuesta Exitosa (200 OK)
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
    }
  ],
  "meta": {
    "requestId": "req_abc123",
    "count": 1,
    "filters": {
      "search": null,
      "status": "1"
    }
  },
  "errors": []
}
```

### Respuesta de Error (400 Bad Request)
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

## 🔍 Debugging

Si encuentras problemas:

1. **Verifica tu token**: Asegúrate de que `{{access}}` tenga un token válido
2. **Revisa la URL base**: Confirma que `{{base_url}}` apunte al servidor correcto
3. **Checa el requestId**: Usa el `requestId` de la respuesta para rastrear en logs
4. **Valida los parámetros**: Asegúrate de usar valores correctos para `status`

## 📚 Documentación Adicional

Para más información, consulta:
- [API Inquilinos - Filtros](../API_INQUILINOS_FILTROS.md)
- [Sistema de Status](../SISTEMA_STATUS_INQUILINOS.md)
- [README Principal](../README.md)

---

**Última actualización**: 2026-01-23  
**Versión**: 1.0.0
