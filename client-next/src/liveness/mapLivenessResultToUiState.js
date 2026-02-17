function normalizeStatus(payload) {
  const rawStatus = payload?.status ?? payload?.liveness_status ?? '';
  return String(rawStatus).toUpperCase();
}

function resolvePassed(payload, status) {
  if (typeof payload?.liveness_passed === 'boolean') {
    return payload.liveness_passed;
  }
  return status === 'SUCCEEDED';
}

function mapLivenessResultToUiState(payload) {
  const status = normalizeStatus(payload);
  const isApproved = resolvePassed(payload, status) || status === 'SUCCEEDED';
  const auditImages = Array.isArray(payload?.audit_images) ? payload.audit_images : [];
  const hasAuditImages = auditImages.length > 0;

  if (isApproved && !hasAuditImages) {
    return {
      status,
      isApproved: true,
      hasAuditImages: false,
      auditImages,
      uiState: 'success_without_audit_images',
      message: 'validación exitosa, sin imágenes de auditoría disponibles',
    };
  }

  if (isApproved) {
    return {
      status,
      isApproved: true,
      hasAuditImages: true,
      auditImages,
      uiState: 'success',
      message: 'validación exitosa',
    };
  }

  return {
    status,
    isApproved: false,
    hasAuditImages,
    auditImages,
    uiState: 'failed',
    message: 'validación no aprobada',
  };
}

module.exports = {
  mapLivenessResultToUiState,
};
