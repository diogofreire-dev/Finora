-- Limite de tentativas no código de verificação de email (correção de força bruta)
ALTER TABLE users
  ADD COLUMN verification_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER verification_expires,
  ADD COLUMN verification_sent_at  DATETIME NULL AFTER verification_attempts;
