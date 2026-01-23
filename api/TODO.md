# TODOs de API (pendientes detectados en pruebas)

## Inquilinos
- [ ] Revisar `GET /ia/modelos`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/modelos-disponibles`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/modelo`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/fecha`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/usuario`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/proceso`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/total`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/canal`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/proceso/periodo`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/proceso/usuario`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Revisar `GET /ia/ventas/canal/periodo`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.

## Inmuebles

## Asesores

## Pólizas
- [ ] Documentar comportamiento de `POST /polizas/{numero}/renovar` respecto a si concluye automáticamente la póliza anterior.
- [ ] Confirmar y documentar que `POST /polizas/{numero}/renovar` crea una nueva póliza (según respuesta observada con `201 Created`).
- [ ] Normalizar endpoints de pólizas para usar consistentemente `numero` en lugar de `id` (evitar mezcla de identificadores).
- [ ] Eliminar `GET /polizas/{id}/contrato` y dejar solo `GET /polizas/numero/{numero}/contrato` (evitar duplicidad y uso de `id`).

## Dashboard

## Financieros
- [ ] Revisar y definir endpoints faltantes para operaciones financieras (ej. CRUD de ventas, reportes por periodo/canal, exportaciones).
- [ ] Migrar `GET /polizas/{id}/contrato` a número de póliza (usar `numero_poliza` en lugar de `id`).

Presigned URL Functionality:
I've updated 
src/shared/api/endpoints.ts
 to include a new FILES endpoint.
I've added the 
getPresignedUrl
 function to 
prospectsService.ts
 which requests a temporary public URL for a private S3 file. (Note: This assumes the backend exposes GET /api/v1/files/presigned?key={s3_key}).
