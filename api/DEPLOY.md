# Guía de Despliegue en IONOS (Producción)

Para garantizar que la API funcione correctamente y con URLs limpias en producción, sigue estos pasos:

## 1. Configuración de Dominio (DocumentRoot)

En el panel de control de IONOS:
1.  Ve a la gestión de **Dominios y SSL**.
2.  Selecciona el subdominio `crm.arrendamientoseguro.app` (o el que corresponda).
3.  Busca la configuración de **Destino** (Target Directory/DocumentRoot).
4.  Apunta el directorio **Directamente** a la carpeta pública:
    `/as-crm-2026/api/public`

**¿Por qué?**
Esto elimina `/api/public` de la URL final, permitiendo que tus endpoints sean limpios:
*   ✅ `https://crm.arrendamientoseguro.app/api/v1/health`
*   ❌ `https://crm.arrendamientoseguro.app/api/public/api/v1/health`

## 2. Variables de Entorno (.env)

En el servidor de producción, asegúrate de crear un archivo `.env` (o configurar las variables en el panel si lo permite) con estos valores:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.arrendamientoseguro.app

# Base de datos (datos reales de IONOS)
DB_HOST=dbxxx.hosting-data.io
DB_NAME=dbsxxxxx
DB_USER=dbuxxxxx
DB_PASS=*******
DB_PORT=3306

# Seguridad (Generar claves aleatorias largas)
JWT_ACCESS_SECRET=...
JWT_REFRESH_SECRET=...

# CORS (Dominios permitidos para el frontend)
CORS_ALLOW_ORIGINS=https://arrendamientoseguro.app,https://crm.arrendamientoseguro.app

# AWS Rekognition (comparación de rostros y liveness)
AWS_REKOGNITION_ACCESS_KEY=...
AWS_REKOGNITION_SECRET_KEY=...
AWS_REKOGNITION_SESSION_TOKEN=...        # Opcional (credenciales temporales)
AWS_REKOGNITION_REGION=us-east-1
AWS_REKOGNITION_SIMILARITY_THRESHOLD=85  # Opcional

# S3 (bucket donde viven selfie e ine_frontal)
MEDIA_S3_ACCESS_KEY=...
MEDIA_S3_SECRET_KEY=...
MEDIA_S3_SESSION_TOKEN=...               # Opcional (credenciales temporales)
MEDIA_S3_REGION=us-east-1
MEDIA_S3_REGION_COPY=us-east-1           # Opcional (solo para bucket de copia en otra región)
MEDIA_S3_BUCKET_INQUILINOS=...
```

## 2.1 Permisos IAM requeridos (Rekognition)

El usuario/rol asociado a las credenciales debe tener permiso para:

- `rekognition:CompareFaces`
- `rekognition:StartFaceLivenessSession`
- `rekognition:GetFaceLivenessSessionResults`

## 2.2 Acceso de Rekognition al bucket S3

Rekognition debe poder leer el bucket donde viven los archivos `selfie` e `ine_frontal`.
Rekognition leerá el bucket ubicado en **us-east-1**.

Opciones comunes:

- **Mismo account**: usa el mismo usuario/rol con permisos `s3:GetObject` sobre el bucket.
- **Bucket policy**: permite a Rekognition (o al rol) acceder a los objetos requeridos.

## 2.3 Permisos IAM para copias (copia-inquilinos-us)

Para el proceso de copias hacia/desde `copia-inquilinos-us`, asegúrate de asignar estos permisos:

- `s3:GetObject` en el **bucket origen**.
- `s3:PutObject` y `s3:DeleteObject` en el **bucket destino**.

## 3. Verificación

Una vez desplegado, verifica el endpoint de salud:
`https://crm.arrendamientoseguro.app/api/v1/health`

Debería devolver:
```json
{"data":{"status":"ok",...}}
```
