-- Elimina duplicados historicos de Magic Link / OTP para self:update
-- Conserva solo el registro mas reciente por correo.
-- Ejecutar manualmente en la base de datos correspondiente.

START TRANSACTION;

DELETE current_token
FROM prospect_update_tokens AS current_token
INNER JOIN prospect_update_tokens AS newer_token
  ON newer_token.email = current_token.email
 AND newer_token.scope = 'self:update'
 AND current_token.scope = 'self:update'
 AND newer_token.id > current_token.id;

COMMIT;
