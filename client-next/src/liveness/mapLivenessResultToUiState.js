function normalizeStatus(payload) {
  const rawStatus = payload?.status ?? payload?.liveness_status ?? '';
  return String(rawStatus).toUpperCase();
}

function resolveMessageByStatus(status) {
  switch (status) {
    case 'SUCCEEDED':
      return 'validación exitosa';
    case 'EXPIRED':
      return 'sesión expirada';
    case 'FAILED':
      return 'validación no exitosa';
    case 'IN_PROGRESS':
    default:
      return 'validación en proceso/reintento';
  }
}

function resolveTelemetry(payload, status) {
  const route = payload?.route ?? payload?.pathname ?? payload?.path ?? null;
  const requestId = payload?.requestId ?? payload?.request_id ?? null;

  return {
    session_id: payload?.session_id ?? null,
    status,
    requestId,
    route,
  };
}

function resolvePassed(payload, status) {
  if (typeof payload?.liveness_passed === 'boolean') {
    return payload.liveness_passed;
  }
  return status === 'SUCCEEDED';
}

function mapLivenessResultToUiState(payload) {
  const status = normalizeStatus(payload);
  const statusMessage = resolveMessageByStatus(status);
  const telemetry = resolveTelemetry(payload, status);
  const isApproved = resolvePassed(payload, status) || status === 'SUCCEEDED';
  const auditImages = Array.isArray(payload?.audit_images) ? payload.audit_images : [];
  const hasAuditImages = auditImages.length > 0;

  if (isApproved && !hasAuditImages) {
    return {
      status,
      isApproved: true,
      hasAuditImages: false,
      auditImages,
      telemetry,
      uiState: 'success_without_audit_images',
      message: statusMessage,
    };
  }

  if (isApproved) {
    return {
      status,
      isApproved: true,
      hasAuditImages: true,
      auditImages,
      telemetry,
      uiState: 'success',
      message: statusMessage,
    };
  }

  return {
    status,
    isApproved: false,
    hasAuditImages,
    auditImages,
    telemetry,
    uiState: 'failed',
    message: statusMessage,
  };
}

module.exports = {
  mapLivenessResultToUiState,
};
