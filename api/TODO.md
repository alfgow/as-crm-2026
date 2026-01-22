# TODOs de API (pendientes detectados en pruebas)

## Inquilinos
- [ ] `PUT /inquilinos/{id}` debe devolver el registro actualizado en `data` para consumo en frontend.
- [ ] Agregar endpoint de borrado masivo de inquilinos (ej. `POST /inquilinos/delete-bulk` con lista de IDs).
- [ ] Revisar `PUT /inquilinos/{id}/validaciones-legal/toggle-demandas`: no alterna el `nuevo_estado` (permanece en `2` tras múltiples llamadas).
- [ ] Corregir error 500 en `GET /inquilinos/slug/{slug}/validacion-identidad` por método faltante `InquilinoRepository::findArchivosIdentidad()`.
- [ ] Revisar `POST /validacion-identidad/procesar`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.
- [ ] Corregir error 500 en `GET /inquilinos/slug/{slug}/validacion-identidad/resultado` por método faltante `InquilinoRepository::findArchivosIdentidad()`.
- [ ] Revisar `GET /validacion-aws/manual`: el endpoint no está disponible (404); confirmar ruta/método y documentar el request esperado.

## Inmuebles
- [ ] `PUT /inmuebles/{id}` debe devolver el registro actualizado en `data` para consumo en frontend.
- [ ] Agregar endpoint de borrado masivo de inmuebles (ej. `POST /inmuebles/delete-bulk` con lista de IDs).

## Asesores
- [ ] Agregar endpoint de borrado masivo de asesores (ej. `POST /asesores/delete-bulk` con lista de IDs).

## Pólizas
- [ ] Ajustar/clarificar endpoint de búsqueda para aceptar `q` o requerir `numero` explícitamente (actualmente responde `numero requerido`).
- [ ] Documentar comportamiento de `POST /polizas/{numero}/renovar` respecto a si concluye automáticamente la póliza anterior.
- [ ] Confirmar y documentar que `POST /polizas/{numero}/renovar` crea una nueva póliza (según respuesta observada con `201 Created`).
- [ ] Normalizar endpoints de pólizas para usar consistentemente `numero` en lugar de `id` (evitar mezcla de identificadores).
- [ ] Eliminar `GET /polizas/{id}/contrato` y dejar solo `GET /polizas/numero/{numero}/contrato` (evitar duplicidad y uso de `id`).

## Dashboard
- [ ] Corregir error 500 en `GET /dashboard` por métodos faltantes (`InquilinoRepository::countNuevos()` y `findNuevosConSelfie()`).

## Financieros
- [ ] Revisar y definir endpoints faltantes para operaciones financieras (ej. CRUD de ventas, reportes por periodo/canal, exportaciones).
- [ ] Migrar `GET /polizas/{id}/contrato` a número de póliza (usar `numero_poliza` en lugar de `id`).
