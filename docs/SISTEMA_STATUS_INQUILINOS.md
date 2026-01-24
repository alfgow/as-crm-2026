# Sistema de Status de Inquilinos

## Descripción General

El sistema de CRM maneja diferentes estados (status) para los inquilinos a lo largo de su ciclo de vida en el proceso de arrendamiento. Este documento describe cada status y su significado.

## Estados Disponibles

### 1. Nuevo (Status = '1')
**Descripción**: Inquilino recién registrado en el sistema.

**Características**:
- Es el status inicial cuando se crea un nuevo inquilino
- Indica que el prospecto aún no ha sido revisado por el equipo
- Requiere atención inmediata del asesor asignado

**Acciones Típicas**:
- Revisar información básica del prospecto
- Validar datos de contacto
- Iniciar proceso de validación de documentos
- Asignar a un asesor si no tiene uno

**Flujo Siguiente**: 
- Nuevo → En Proceso (cuando se inicia la validación)
- Nuevo → Rechazado (si no cumple requisitos básicos)

---

### 2. En Proceso (Status = '2')
**Descripción**: Inquilino en proceso de validación y verificación.

**Características**:
- El prospecto está siendo evaluado activamente
- Se están validando documentos, identidad, ingresos, etc.
- Puede estar en espera de documentación adicional

**Acciones Típicas**:
- Validación de identidad (INE, selfie)
- Validación legal (antecedentes, demandas)
- Validación de ingresos
- Verificación de referencias
- Solicitud de documentos faltantes

**Flujo Siguiente**:
- En Proceso → Aprobado (si pasa todas las validaciones)
- En Proceso → Rechazado (si no cumple algún requisito)
- En Proceso → Nuevo (si se necesita reiniciar el proceso)

---

### 3. Aprobado (Status = '3')
**Descripción**: Inquilino aprobado para arrendamiento.

**Características**:
- Ha pasado todas las validaciones requeridas
- Está listo para firmar contrato
- Cumple con todos los requisitos establecidos

**Acciones Típicas**:
- Generar contrato de arrendamiento
- Coordinar firma de contrato
- Crear póliza de arrendamiento
- Procesar pago inicial

**Flujo Siguiente**:
- Aprobado → (Creación de Póliza)
- Aprobado → En Proceso (si se requiere validación adicional)

---

### 4. Rechazado (Status = '4')
**Descripción**: Inquilino rechazado, no cumple con los requisitos.

**Características**:
- No pasó alguna validación crítica
- No cumple con requisitos mínimos
- Puede tener antecedentes negativos

**Motivos Comunes de Rechazo**:
- Ingresos insuficientes
- Antecedentes legales negativos
- Documentación falsa o alterada
- Referencias negativas
- Historial crediticio malo
- No proporciona documentación requerida

**Acciones Típicas**:
- Documentar motivo de rechazo
- Notificar al prospecto (opcional)
- Archivar expediente

**Flujo Siguiente**:
- Rechazado → Nuevo (si se reconsiderará con nueva información)
- Rechazado → (Archivo permanente)

---

## Diagrama de Flujo de Status

```
┌─────────┐
│  NUEVO  │ (Status = 1)
└────┬────┘
     │
     ├──────────────┐
     │              │
     ▼              ▼
┌────────────┐  ┌───────────┐
│ EN PROCESO │  │ RECHAZADO │ (Status = 4)
└─────┬──────┘  └───────────┘
      │              ▲
      ├──────────────┤
      │
      ▼
┌───────────┐
│ APROBADO  │ (Status = 3)
└───────────┘
      │
      ▼
 [Crear Póliza]
```

## Uso en la API

### Obtener Inquilinos por Status

```bash
# Obtener todos los inquilinos nuevos
GET /api/v1/inquilinos?status=1

# Obtener todos los inquilinos en proceso
GET /api/v1/inquilinos?status=2

# Obtener todos los inquilinos aprobados
GET /api/v1/inquilinos?status=3

# Obtener todos los inquilinos rechazados
GET /api/v1/inquilinos?status=4
```

### Actualizar Status de un Inquilino

```bash
PUT /api/v1/inquilinos/{id}/status
Content-Type: application/json

{
  "status": "2"
}
```

## Métricas y Reportes Sugeridos

### Dashboard de Prospectos
- **Total de Nuevos**: Inquilinos con status = 1
- **En Proceso**: Inquilinos con status = 2
- **Aprobados este mes**: Inquilinos con status = 3 y fecha reciente
- **Tasa de Rechazo**: Porcentaje de inquilinos con status = 4

### KPIs Importantes
1. **Tiempo promedio en status "Nuevo"**: Mide la velocidad de respuesta del equipo
2. **Tiempo promedio en status "En Proceso"**: Mide la eficiencia del proceso de validación
3. **Tasa de Conversión**: (Aprobados / Total) * 100
4. **Tasa de Rechazo**: (Rechazados / Total) * 100

## Mejores Prácticas

### Para Asesores
1. **Revisar diariamente los inquilinos nuevos** (status = 1)
2. **Actualizar status regularmente** según el progreso del prospecto
3. **Documentar motivos de rechazo** en el campo de comentarios
4. **Mantener comunicación** con prospectos en proceso

### Para Administradores
1. **Monitorear inquilinos en status "Nuevo"** que lleven más de 24 horas sin atención
2. **Revisar casos en "En Proceso"** que lleven más de 7 días
3. **Analizar patrones de rechazo** para mejorar el proceso de pre-calificación
4. **Generar reportes semanales** de conversión por asesor

### Para Desarrolladores
1. **Validar siempre el status** antes de actualizar (usar valores permitidos: '1', '2', '3', '4')
2. **Registrar cambios de status** en un log de auditoría
3. **Enviar notificaciones** cuando cambie el status de un inquilino
4. **Implementar reglas de negocio** para transiciones de status permitidas

## Reglas de Negocio

### Transiciones Permitidas

| Desde | Hacia | Permitido | Notas |
|-------|-------|-----------|-------|
| Nuevo (1) | En Proceso (2) | ✅ | Transición normal |
| Nuevo (1) | Rechazado (4) | ✅ | Rechazo inmediato |
| Nuevo (1) | Aprobado (3) | ⚠️ | No recomendado, saltar proceso |
| En Proceso (2) | Aprobado (3) | ✅ | Transición normal |
| En Proceso (2) | Rechazado (4) | ✅ | Transición normal |
| En Proceso (2) | Nuevo (1) | ⚠️ | Solo si se reinicia proceso |
| Aprobado (3) | En Proceso (2) | ⚠️ | Solo si se requiere validación adicional |
| Aprobado (3) | Rechazado (4) | ⚠️ | Caso excepcional |
| Rechazado (4) | Nuevo (1) | ⚠️ | Solo con nueva información |
| Rechazado (4) | En Proceso (2) | ❌ | No permitido directamente |

**Leyenda**:
- ✅ Permitido y recomendado
- ⚠️ Permitido pero requiere justificación
- ❌ No recomendado

## Notificaciones Automáticas (Sugeridas)

### Para el Inquilino
- **Nuevo → En Proceso**: "Hemos recibido tu solicitud y estamos revisando tu información"
- **En Proceso → Aprobado**: "¡Felicidades! Tu solicitud ha sido aprobada"
- **En Proceso → Rechazado**: "Lamentamos informarte que tu solicitud no fue aprobada"

### Para el Asesor
- **Nuevo inquilino asignado**: "Tienes un nuevo prospecto asignado"
- **Inquilino en proceso > 7 días**: "El prospecto X lleva más de 7 días en proceso"
- **Documentos pendientes**: "El prospecto X tiene documentos pendientes"

### Para Administradores
- **Inquilinos nuevos sin atender > 24h**: Alerta diaria
- **Tasa de rechazo alta**: Alerta semanal si supera umbral
- **Prospectos estancados**: Reporte semanal de casos en proceso > 14 días

## Campos Relacionados

Además del campo `status`, estos campos son relevantes para el seguimiento:

- `fecha`: Fecha de registro del inquilino
- `id_asesor`: Asesor asignado al prospecto
- `validaciones`: Objeto con resultados de validaciones
- `comentarios`: Notas del asesor sobre el prospecto

## Consultas SQL Útiles

```sql
-- Contar inquilinos por status
SELECT status, COUNT(*) as total
FROM inquilinos
GROUP BY status;

-- Inquilinos nuevos sin atender (más de 24 horas)
SELECT *
FROM inquilinos
WHERE status = '1'
  AND fecha < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Inquilinos en proceso por mucho tiempo (más de 7 días)
SELECT *
FROM inquilinos
WHERE status = '2'
  AND fecha < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Tasa de conversión del último mes
SELECT 
  COUNT(CASE WHEN status = '3' THEN 1 END) as aprobados,
  COUNT(CASE WHEN status = '4' THEN 1 END) as rechazados,
  COUNT(*) as total,
  ROUND(COUNT(CASE WHEN status = '3' THEN 1 END) * 100.0 / COUNT(*), 2) as tasa_aprobacion
FROM inquilinos
WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

**Última actualización**: 2026-01-23  
**Versión**: 1.0.0  
**Mantenedor**: Equipo de Desarrollo API AS-CRM
