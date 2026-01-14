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
```

## 3. Verificación

Una vez desplegado, verifica el endpoint de salud:
`https://crm.arrendamientoseguro.app/api/v1/health`

Debería devolver:
```json
{"data":{"status":"ok",...}}
```
