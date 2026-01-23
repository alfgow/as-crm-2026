# TODOs de API (pendientes detectados en pruebas)

## Inquilinos

## Inmuebles

## Asesores

## Pólizas

## Dashboard

## Financieros

Presigned URL Functionality:
I've updated 
src/shared/api/endpoints.ts
 to include a new FILES endpoint.
I've added the 
getPresignedUrl
 function to 
prospectsService.ts
 which requests a temporary public URL for a private S3 file. (Note: This assumes the backend exposes GET /api/v1/files/presigned?key={s3_key}).
