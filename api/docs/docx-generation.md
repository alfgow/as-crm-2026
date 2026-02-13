# Generación de DOCX (Póliza y Contrato)

## Objetivo
Generar y descargar documentos Word (`.docx`) desde plantillas para:
- póliza,
- contrato.

## Endpoints que consume frontend

### 1) Descargar póliza en DOCX
- **Método:** `GET`
- **URL:** `/api/v1/polizas/numero/{numero}/docx/poliza`
- **Auth:** `Authorization: Bearer <token>`
- **Respuesta:** binario `.docx` (descarga directa)

### 2) Generar contrato en DOCX
- **Método:** `POST`
- **URL:** `/api/v1/polizas/numero/{numero}/docx/contrato`
- **Auth:** `Authorization: Bearer <token>`
- **Content-Type:** `application/json`
- **Body:**
```json
{
  "tipo_contrato": "normal_pf"
}
```
- **Respuesta:** binario `.docx` (descarga directa)

## Tipos de contrato soportados
- `normal_pf`
- `os_pf`
- `fiador_pf`
- `os_fiador_pf`
- `arr_pm_inq_pf`
- `inq_pm_arr_pf`
- `pmoral`
- `normal_pm`
- `os_pm`
- `fiador_pm`
- `os_fiador_pm`

## Flujo interno resumido
1. Validar `numero` y (para contrato) `tipo_contrato`.
2. Buscar póliza (`findContratoByNumero`).
3. Resolver plantilla por mapa centralizado.
4. Confirmar que el archivo de plantilla exista.
5. Leer placeholders `${...}` de la plantilla.
6. Construir reemplazos con datos de póliza/partes.
7. Renderizar DOCX temporal.
8. Devolver descarga directa con `Content-Disposition`.

## Ejemplo práctico frontend (fetch)

```ts
async function descargarContratoDocx(numero: number, tipoContrato: string, token: string) {
  const res = await fetch(`/api/v1/polizas/numero/${numero}/docx/contrato`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ tipo_contrato: tipoContrato })
  });

  // Si backend responde JSON, se asume error estructurado
  const contentType = res.headers.get('content-type') || '';
  if (!res.ok || contentType.includes('application/json')) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err?.errors?.[0]?.message || 'No fue posible generar el contrato');
  }

  const blob = await res.blob();
  const cd = res.headers.get('content-disposition') || '';
  const match = /filename="?([^\";]+)"?/i.exec(cd);
  const filename = match?.[1] || `Contrato_${numero}.docx`;

  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
```

## Configuración de plantillas
Por defecto la API usa `api/plantillas`.
Si quieres otra ubicación, define:

```bash
DOCX_TEMPLATES_PATH=/ruta/absoluta/plantillas
```
