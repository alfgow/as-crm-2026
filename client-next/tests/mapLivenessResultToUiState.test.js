const test = require('node:test');
const assert = require('node:assert/strict');
const { mapLivenessResultToUiState } = require('../src/liveness/mapLivenessResultToUiState');

test('SUCCEEDED + audit_images vacías => éxito sin imágenes de auditoría', () => {
  const result = mapLivenessResultToUiState({
    status: 'SUCCEEDED',
    liveness_passed: true,
    audit_images: [],
    session_id: 'sess-1',
    request_id: 'req-1',
    pathname: '/liveness/result',
  });

  assert.equal(result.isApproved, true);
  assert.equal(result.uiState, 'success_without_audit_images');
  assert.equal(result.message, 'validación exitosa');
  assert.equal(result.hasAuditImages, false);
  assert.deepEqual(result.telemetry, {
    session_id: 'sess-1',
    status: 'SUCCEEDED',
    requestId: 'req-1',
    route: '/liveness/result',
  });
});

test('SUCCEEDED + imágenes => éxito normal', () => {
  const result = mapLivenessResultToUiState({
    status: 'SUCCEEDED',
    audit_images: [{ key: 'img-1' }],
    session_id: 'sess-2',
    requestId: 'req-2',
    route: '/resultado/liveness',
  });

  assert.equal(result.isApproved, true);
  assert.equal(result.uiState, 'success');
  assert.equal(result.message, 'validación exitosa');
  assert.equal(result.hasAuditImages, true);
  assert.deepEqual(result.telemetry, {
    session_id: 'sess-2',
    status: 'SUCCEEDED',
    requestId: 'req-2',
    route: '/resultado/liveness',
  });
});

test('EXPIRED/FAILED/IN_PROGRESS/desconocido => mensajes y estado no aprobado', async (t) => {
  await t.test('EXPIRED', () => {
    const result = mapLivenessResultToUiState({
      status: 'EXPIRED',
      liveness_passed: false,
      audit_images: [],
      session_id: 'sess-exp',
      request_id: 'req-exp',
      path: '/liveness/expired',
    });

    assert.equal(result.isApproved, false);
    assert.equal(result.uiState, 'failed');
    assert.equal(result.message, 'sesión expirada');
    assert.deepEqual(result.telemetry, {
      session_id: 'sess-exp',
      status: 'EXPIRED',
      requestId: 'req-exp',
      route: '/liveness/expired',
    });
  });

  await t.test('FAILED', () => {
    const result = mapLivenessResultToUiState({
      status: 'FAILED',
      audit_images: [{ key: 'optional-audit' }],
    });

    assert.equal(result.isApproved, false);
    assert.equal(result.uiState, 'failed');
    assert.equal(result.message, 'validación no exitosa');
  });

  await t.test('IN_PROGRESS', () => {
    const result = mapLivenessResultToUiState({
      status: 'IN_PROGRESS',
    });

    assert.equal(result.isApproved, false);
    assert.equal(result.uiState, 'failed');
    assert.equal(result.message, 'validación en proceso/reintento');
  });

  await t.test('desconocido', () => {
    const result = mapLivenessResultToUiState({
      status: 'ANY_OTHER_STATUS',
    });

    assert.equal(result.isApproved, false);
    assert.equal(result.uiState, 'failed');
    assert.equal(result.message, 'validación en proceso/reintento');
  });
});
