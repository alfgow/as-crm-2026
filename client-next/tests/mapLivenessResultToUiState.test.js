const test = require('node:test');
const assert = require('node:assert/strict');
const { mapLivenessResultToUiState } = require('../src/liveness/mapLivenessResultToUiState');

test('SUCCEEDED + audit_images vacías => éxito sin imágenes de auditoría', () => {
  const result = mapLivenessResultToUiState({
    status: 'SUCCEEDED',
    liveness_passed: true,
    audit_images: [],
  });

  assert.equal(result.isApproved, true);
  assert.equal(result.uiState, 'success_without_audit_images');
  assert.equal(result.message, 'validación exitosa, sin imágenes de auditoría disponibles');
  assert.equal(result.hasAuditImages, false);
});

test('SUCCEEDED + imágenes => éxito normal', () => {
  const result = mapLivenessResultToUiState({
    status: 'SUCCEEDED',
    audit_images: [{ key: 'img-1' }],
  });

  assert.equal(result.isApproved, true);
  assert.equal(result.uiState, 'success');
  assert.equal(result.message, 'validación exitosa');
  assert.equal(result.hasAuditImages, true);
});

test('EXPIRED/FAILED => estado no aprobado', async (t) => {
  await t.test('EXPIRED', () => {
    const result = mapLivenessResultToUiState({
      status: 'EXPIRED',
      liveness_passed: false,
      audit_images: [],
    });

    assert.equal(result.isApproved, false);
    assert.equal(result.uiState, 'failed');
  });

  await t.test('FAILED', () => {
    const result = mapLivenessResultToUiState({
      status: 'FAILED',
      audit_images: [{ key: 'optional-audit' }],
    });

    assert.equal(result.isApproved, false);
    assert.equal(result.uiState, 'failed');
  });
});
