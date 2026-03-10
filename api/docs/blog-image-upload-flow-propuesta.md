# Propuesta: Flujo de imágenes para Blog (multipart server-side a S3)

## Contexto y problema actual

Hoy `POST /api/v1/blog` **solo recibe JSON** y guarda `imagen_key` como texto.
No acepta `multipart/form-data`, no procesa `$_FILES` y no sube binarios a S3 desde backend.

Esto obliga al frontend a resolver la subida por fuera del endpoint de blog o a guardar keys sin un flujo uniforme.

## Objetivo

Implementar en Blog el mismo patrón operativo que ya existe para inquilinos/arrendadores:

1. Validar `id`, `tipo`, `file`.
2. Generar `key` con patrón por entidad + id + random.
3. Subir binario a S3 con `MediaUploadService::uploadFromPath(...)`.
4. Persistir metadata (`s3_key`, `mime_type`, `size`, `tipo`) en BD.

---

## Diseño propuesto (recomendado)

### 1) Nuevo endpoint de subida server-side

- **Método**: `POST`
- **Ruta**: `/api/v1/blog/{id}/archivos/upload`
- **Auth**: Bearer (igual que el resto)
- **Content-Type**: `multipart/form-data`
- **form-data**:
  - `file` (binary) **requerido**
  - `tipo` (string) **requerido**
    - valores sugeridos: `portada`, `galeria`, `miniatura`, `otro`

### 2) Algoritmo backend (idéntico al patrón inquilinos)

1. Parsear:
   - `$id = (int)($params['id'] ?? 0)`
   - `$tipo = trim((string)($_POST['tipo'] ?? ''))`
   - `$file = $_FILES['file'] ?? null`
2. Validar:
   - `id > 0`
   - post de blog exista
   - `tipo` no vacío
   - `file` exista y `error` sea 0
3. Construir key:
   - `blog/{id}/{randomHex}.{ext}`
4. Detectar metadata:
   - `mime_type` de archivo
   - `size` en bytes
5. Subir a S3:
   - `MediaUploadService::uploadFromPath('blog', $key, $tmpPath, $mimeType)`
6. Persistir metadata en BD (tabla `blog_archivos`)
7. Si `tipo = portada`, actualizar también `blog_posts.imagen_key = $key` para compatibilidad.
8. Responder `201` con metadata creada.

### 3) Nueva tabla para metadata (paridad con `inquilinos_archivos`)

**Tabla sugerida**: `blog_archivos`

Campos:
- `id` BIGINT PK AI
- `id_blog_post` BIGINT NOT NULL (FK a `blog_posts.id`)
- `tipo` VARCHAR(64) NOT NULL
- `s3_key` VARCHAR(512) NOT NULL
- `mime_type` VARCHAR(128) NULL
- `size` BIGINT NULL
- `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

Índices:
- `idx_blog_archivos_post` (`id_blog_post`)
- `idx_blog_archivos_tipo` (`id_blog_post`, `tipo`)
- `uniq_blog_archivos_key` (`s3_key`) opcional

> Nota: Mantener `blog_posts.imagen_key` evita romper clientes existentes mientras migran al nuevo modelo.

---

## Endpoints complementarios para cerrar el flujo

Para tener un módulo completo (similar a inquilinos):

1. `POST /api/v1/blog/{id}/archivos` (JSON: registrar key ya existente)
2. `GET /api/v1/blog/{id}/archivos` (listar)
3. `PUT /api/v1/blog/{id}/archivos/{archivoId}/upload` (reemplazo multipart)
4. `DELETE /api/v1/blog/{id}/archivos/{archivoId}` (eliminar metadata; opcional borrar S3)

Y para entrega al frontend:

5. `GET /api/v1/blog/{id}/archivos-presignados`
   - reutilizar estrategia de validación + presign URL del bucket `blog`.

---

## Contrato de respuesta sugerido

```json
{
  "data": {
    "id": 123,
    "id_blog_post": 45,
    "tipo": "portada",
    "s3_key": "blog/45/7f9a...c1.jpg",
    "mime_type": "image/jpeg",
    "size": 248120,
    "created_at": "2026-03-02 18:10:00"
  },
  "meta": { "requestId": "..." },
  "errors": []
}
```

Errores esperados:
- `400`: parámetros inválidos / falta `file` o `tipo`
- `404`: blog post no existe
- `500`: upload a S3 falló o no pudo guardar en BD

---

## Plan de implementación incremental (sin romper producción)

### Fase 1 (compatibilidad)

- Crear tabla `blog_archivos`.
- Agregar endpoint `POST /api/v1/blog/{id}/archivos/upload`.
- Si `tipo = portada`, sincronizar `blog_posts.imagen_key`.

### Fase 2 (expansión)

- Agregar endpoints de listar, reemplazar y borrar.
- Agregar endpoint de archivos presignados para lectura.

### Fase 3 (migración opcional)

- Migrar uso frontend de `imagen_key` a `blog_archivos`.
- Mantener `imagen_key` como fallback legacy.

---

## Criterios de aceptación

1. Subir imagen de portada por multipart funciona con un solo request al backend.
2. El archivo queda en bucket `blog` bajo patrón `blog/{id}/{random}.{ext}`.
3. Se guarda metadata (`s3_key`, `mime_type`, `size`, `tipo`) en BD.
4. `GET /api/v1/blog/{id}` sigue devolviendo `imagen_key` para compatibilidad.
5. Errores son consistentes con formato `{data, meta, errors}`.

---

## Ejemplo de consumo (cURL)

```bash
curl -X POST "{{base_url}}/api/v1/blog/45/archivos/upload" \
  -H "Authorization: Bearer <token>" \
  -F "tipo=portada" \
  -F "file=@/ruta/local/imagen.jpg"
```

