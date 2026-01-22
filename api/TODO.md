# TODOs de API (pendientes detectados en pruebas)

## Inquilinos
- [ ] `PUT /inquilinos/{id}` debe devolver el registro actualizado en `data` para consumo en frontend.
- [ ] Agregar endpoint de borrado masivo de inquilinos (ej. `POST /inquilinos/delete-bulk` con lista de IDs).

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
