-- Limite de tentativas de login (correção de força bruta à password)
ALTER TABLE users
  ADD COLUMN failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER password_reset_expires,
  ADD COLUMN login_locked_until    DATETIME NULL AFTER failed_login_attempts;
