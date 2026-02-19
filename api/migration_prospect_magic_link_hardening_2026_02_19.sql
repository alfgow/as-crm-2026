-- Hardening para links mágicos de prospectos (self:update)
-- Ejecutar manualmente en la base de datos correspondiente.

START TRANSACTION;

-- 1) Guardar hash del token opaco enviado por URL (no guardar token en claro)
ALTER TABLE prospect_update_tokens
  ADD COLUMN magic_token_hash CHAR(64) NULL AFTER jti;

-- 2) Soporte one-time use del link
ALTER TABLE prospect_update_tokens
  ADD COLUMN consumed_at DATETIME NULL AFTER expires_at;

-- 3) Metadatos opcionales de consumo para auditoría
ALTER TABLE prospect_update_tokens
  ADD COLUMN consumed_ip VARCHAR(45) NULL AFTER consumed_at,
  ADD COLUMN consumed_user_agent VARCHAR(255) NULL AFTER consumed_ip;

-- 4) Índices recomendados para lookup y expiración
CREATE INDEX idx_put_magic_token_hash ON prospect_update_tokens (magic_token_hash);
CREATE INDEX idx_put_scope_expires ON prospect_update_tokens (scope, expires_at);
CREATE INDEX idx_put_actor_scope_expires ON prospect_update_tokens (actor_type, actor_id, scope, expires_at);
CREATE INDEX idx_put_consumed_at ON prospect_update_tokens (consumed_at);

COMMIT;

-- Notas de uso esperadas en backend:
-- - Al emitir link: generar token opaco (32 bytes base64url), guardar SHA-256 en magic_token_hash.
-- - Enviar URL con ?t=<token_opaco>.
-- - Validar con SHA-256(token recibido) + scope='self:update' + expires_at > NOW() + consumed_at IS NULL.
-- - En consumo exitoso: set consumed_at=NOW(), consumed_ip, consumed_user_agent.
-- - Invalidar/consumir tokens previos activos del mismo actor al emitir uno nuevo.
