-- Limite de pedidos por IP nas páginas de autenticação (login, registo, recuperar palavra-passe).
-- Guarda só um hash do IP/email, nunca o valor em claro.
CREATE TABLE IF NOT EXISTS auth_throttle (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action     VARCHAR(20) NOT NULL,
  subject    CHAR(64)    NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_throttle (action, subject, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
