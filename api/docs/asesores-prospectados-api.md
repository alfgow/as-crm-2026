# Asesores Prospectados API

## Recursos nuevos

### Prospectos
- `GET /api/v1/asesores-prospectados`
- `POST /api/v1/asesores-prospectados`
- `GET /api/v1/asesores-prospectados/{id}`
- `PUT /api/v1/asesores-prospectados/{id}`
- `DELETE /api/v1/asesores-prospectados/{id}`

Payload base:

```json
{
  "nombre": "María Pérez",
  "telefono": "5512345678",
  "estatus": "contactar",
  "fecha": "2026-03-23 12:30:00"
}
```

Notas:
- `telefono` es obligatorio.
- `estatus` acepta: `activo`, `contactar`, `no_contesta`, `descartado`.
- Si no se envía `estatus`, la API asigna `contactar` por default.
- `fecha` es opcional; si no se envía, la API guarda la fecha actual.
- La tabla tiene restricción `UNIQUE` en `telefono`.

### Comentarios
- `GET /api/v1/asesores-prospectados-comentarios`
- `POST /api/v1/asesores-prospectados-comentarios`
- `GET /api/v1/asesores-prospectados-comentarios/{id}`
- `PUT /api/v1/asesores-prospectados-comentarios/{id}`
- `DELETE /api/v1/asesores-prospectados-comentarios/{id}`

Payload base:

```json
{
  "id_prospecto": 10,
  "comentario": "Se llamó y pidió seguimiento el viernes",
  "fecha": "2026-03-23 12:35:00"
}
```

Notas:
- `id_prospecto` y `comentario` son obligatorios.
- `fecha` es opcional; si no se envía, la API guarda la fecha actual.
- Puedes filtrar por prospecto con `GET /api/v1/asesores-prospectados-comentarios?id_prospecto=10`.

### Rutas anidadas útiles para frontend
- `GET /api/v1/asesores-prospectados/{id}/comentarios`
- `POST /api/v1/asesores-prospectados/{id}/comentarios`

## Autorización

Las rutas aceptan:
- token de usuario normal del CRM
- token de cliente API con scopes

Scopes recomendados para este módulo:
- `asesores-prospectados:read`
- `asesores-prospectados:write`
- `asesores-prospectados-comentarios:read`
- `asesores-prospectados-comentarios:write`

Ejemplo para crear un cliente API con acceso exclusivo a este módulo:

```http
POST /api/v1/api-clients
Authorization: Bearer <token_usuario_admin>
Content-Type: application/json
```

```json
{
  "name": "frontend-prospectados",
  "scopes": [
    "asesores-prospectados:read",
    "asesores-prospectados:write",
    "asesores-prospectados-comentarios:read",
    "asesores-prospectados-comentarios:write"
  ],
  "rate_limit": 60
}
```

## Usuario MySQL limitado

Si además quieres un usuario directo de MySQL que solo pueda operar estas dos tablas, el patrón es este:

```sql
CREATE USER 'api_prospectados'@'%' IDENTIFIED BY 'CAMBIA_ESTA_PASSWORD_LARGA';

GRANT SELECT, INSERT, UPDATE, DELETE
ON `dbs15192523`.`asesores-prospectados`
TO 'api_prospectados'@'%';

GRANT SELECT, INSERT, UPDATE, DELETE
ON `dbs15192523`.`asesores-prospectados-comentarios`
TO 'api_prospectados'@'%';

FLUSH PRIVILEGES;
```

Si solo quieres lectura:

```sql
REVOKE INSERT, UPDATE, DELETE
ON `dbs15192523`.`asesores-prospectados`
FROM 'api_prospectados'@'%';

REVOKE INSERT, UPDATE, DELETE
ON `dbs15192523`.`asesores-prospectados-comentarios`
FROM 'api_prospectados'@'%';
```

Importante:
- este usuario MySQL no lo usa automáticamente la API actual
- la API sigue usando el usuario global configurado en `api/config/config.local.php` o `.env`
- por eso, para consumir estos endpoints con permisos acotados, la restricción correcta es el cliente API con scopes
- el usuario MySQL limitado solo sirve si una integración va a conectarse directo a la base o si montamos un servicio aparte usando esas credenciales
