-- Tabela: users
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  email_verified TINYINT(1) DEFAULT 0,
  verification_token VARCHAR(64) NULL,
  verification_expires DATETIME NULL,
  password_reset_token VARCHAR(64) NULL,
  password_reset_expires DATETIME NULL,
  last_password_change DATETIME NULL,
  last_email_change DATETIME NULL,
  pending_email VARCHAR(255) NULL,
  pending_email_token VARCHAR(64) NULL,
  pending_email_expires DATETIME NULL,
  two_factor_enabled TINYINT(1) DEFAULT 0,
  two_factor_secret VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users MODIFY id INT UNSIGNED AUTO_INCREMENT;

CREATE INDEX idx_email_verified ON users(email_verified);
CREATE INDEX idx_verification_token ON users(verification_token);
CREATE INDEX idx_password_reset_token ON users(password_reset_token);
