# TODOs de API (pendientes detectados en pruebas)

## Inquilinos
- [ ] Revisar `GET /ia/ventas/proceso/periodo`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/proceso/usuario`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.

## Inmuebles

## Asesores

## Pólizas

## Dashboard

## Financieros
- [ ] Revisar y definir endpoints faltantes para operaciones financieras (ej. CRUD de ventas, reportes por periodo/canal, exportaciones).

Presigned URL Functionality:
I've updated 
src/shared/api/endpoints.ts
 to include a new FILES endpoint.
I've added the 
getPresignedUrl
 function to 
prospectsService.ts
 which requests a temporary public URL for a private S3 file. (Note: This assumes the backend exposes GET /api/v1/files/presigned?key={s3_key}).
