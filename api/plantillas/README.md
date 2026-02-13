# Plantillas DOCX

Esta carpeta contiene las plantillas `.docx` para generar pólizas y contratos.

## ¿Se debe crear esta carpeta?
Sí. **Debe existir** en el entorno donde corre la API.

Por defecto, el backend busca aquí:
- `api/plantillas`

Opcionalmente, puedes configurar una ruta distinta con la variable de entorno:
- `DOCX_TEMPLATES_PATH=/ruta/absoluta/a/plantillas`

## Nombres de archivo esperados

### Pólizas
- `Plantilla_Poliza_Clásica.docx`
- `Plantilla_Poliza_Plus.docx`

### Contratos
- `Contrato_Normal_PF 2025.docx`
- `Contrato_ObligadoSolidario_PF 2025.docx`
- `Contrato_Fiador_PF 2025.docx`
- `Contrato_OS_Fiador_PF.docx`
- `Contrato_Arr_PM_Inq_PF.docx`
- `Contrato_Inq_PM_Arr_PF.docx`
- `Contrato_Persona_Moral.docx`
- `Contrato_Normal_PM.docx`
- `Contrato_ObligadoSolidario_PM.docx`
- `Contrato_Fiador_PM.docx`
- `Contrato_OS_Fiador_PM.docx`

> Si falta alguno, el endpoint responderá `template_not_found`.
