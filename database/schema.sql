-- FreeCard Database Schema

DROP DATABASE IF EXISTS pap;
CREATE DATABASE pap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pap;

-- ========================================
-- 1. USERS
-- ========================================
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
  two_factor_secret VARCHAR(255) NULL,
  avatar_path VARCHAR(255) NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users MODIFY id INT UNSIGNED AUTO_INCREMENT;
CREATE INDEX idx_email_verified ON users(email_verified);
CREATE INDEX idx_verification_token ON users(verification_token);
CREATE INDEX idx_password_reset_token ON users(password_reset_token);

-- ========================================
-- 2. CARDS
-- ========================================
CREATE TABLE IF NOT EXISTS cards (
  id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  limit_amount DECIMAL(10,2) DEFAULT 0,
  balance DECIMAL(10,2) DEFAULT 0,
  color VARCHAR(20) DEFAULT 'purple',
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE cards MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE cards ADD CONSTRAINT fk_cards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CREATE INDEX idx_cards_user_active ON cards(user_id, active);

-- ========================================
-- 3. TRANSACTIONS
-- ========================================
CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  card_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255),
  category VARCHAR(100) DEFAULT NULL,
  transaction_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE transactions MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE transactions ADD CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE transactions ADD CONSTRAINT fk_transactions_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL;
CREATE INDEX idx_transaction_date ON transactions(transaction_date);
CREATE INDEX idx_transactions_user_date ON transactions(user_id, transaction_date);
CREATE INDEX idx_transactions_category ON transactions(category);

-- ========================================
-- 4. USER_SETTINGS
-- ========================================
CREATE TABLE IF NOT EXISTS user_settings (
  id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  theme VARCHAR(10) DEFAULT 'light',
  currency VARCHAR(3) DEFAULT 'EUR',
  language VARCHAR(5) DEFAULT 'pt-PT',
  notifications TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE user_settings MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE user_settings ADD CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- ========================================
-- 5. BUDGETS
-- ========================================
CREATE TABLE IF NOT EXISTS budgets (
  id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  period ENUM('monthly', 'weekly', 'yearly') NOT NULL DEFAULT 'monthly',
  category VARCHAR(100) NULL,
  card_id INT UNSIGNED NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE budgets MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE budgets ADD CONSTRAINT fk_budgets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE budgets ADD CONSTRAINT fk_budgets_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL;
CREATE INDEX idx_budgets_user_active ON budgets(user_id, active);

-- ========================================
-- 6. BUDGET_ALERTS
-- ========================================
CREATE TABLE IF NOT EXISTS budget_alerts (
  id INT UNSIGNED PRIMARY KEY,
  budget_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  alert_type ENUM('warning', 'exceeded') NOT NULL,
  percentage DECIMAL(5,2) NOT NULL,
  amount_spent DECIMAL(10,2) NOT NULL,
  triggered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  acknowledged TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE budget_alerts MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE budget_alerts ADD CONSTRAINT fk_alerts_budget FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE;
ALTER TABLE budget_alerts ADD CONSTRAINT fk_alerts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CREATE INDEX idx_alerts_user_unack ON budget_alerts(user_id, acknowledged);

-- ========================================
-- 7. PAYMENT_REMINDERS
-- ========================================
CREATE TABLE IF NOT EXISTS payment_reminders (
  id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  category VARCHAR(100) NULL,
  card_id INT UNSIGNED NULL,
  due_date DATE NOT NULL,
  recurrence ENUM('once', 'weekly', 'monthly', 'yearly') NOT NULL DEFAULT 'once',
  active TINYINT(1) DEFAULT 1,
  notify_days_before INT DEFAULT 3,
  notify_method ENUM('email', 'site', 'both') DEFAULT 'email',
  last_notification_sent DATETIME NULL,
  last_paid_date DATE NULL,
  next_due_date DATE NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payment_reminders MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE payment_reminders ADD CONSTRAINT fk_reminders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE payment_reminders ADD CONSTRAINT fk_reminders_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL;
CREATE INDEX idx_reminders_user_due ON payment_reminders(user_id, due_date);
CREATE INDEX idx_reminders_active ON payment_reminders(active);

-- ========================================
-- 8. REMINDER_HISTORY
-- ========================================
CREATE TABLE IF NOT EXISTS reminder_history (
  id INT UNSIGNED PRIMARY KEY,
  reminder_id INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NULL,
  paid_date DATE NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE reminder_history MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE reminder_history ADD CONSTRAINT fk_history_reminder FOREIGN KEY (reminder_id) REFERENCES payment_reminders(id) ON DELETE CASCADE;
ALTER TABLE reminder_history ADD CONSTRAINT fk_history_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL;
CREATE INDEX idx_history_reminder ON reminder_history(reminder_id);
CREATE INDEX idx_history_paid_date ON reminder_history(paid_date);

-- ========================================
-- 9. NOTIFICATIONS
-- ========================================
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  data JSON NULL,
  is_read TINYINT(1) DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE notifications MODIFY id INT UNSIGNED AUTO_INCREMENT;
ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, is_read);
CREATE INDEX idx_notifications_type ON notifications(type);
CREATE INDEX idx_notifications_created ON notifications(created_at);

-- ========================================
-- 10. Sistema NIF → Empresa
-- ========================================
CREATE TABLE IF NOT EXISTS nif_empresas (
    id INT NOT NULL AUTO_INCREMENT,
    nif VARCHAR(20) NOT NULL,
    nome_empresa VARCHAR(255) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nif (nif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 11. Fatura
-- ========================================