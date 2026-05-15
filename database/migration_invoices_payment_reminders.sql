-- ============================================================
-- FreeCard — Migrações necessárias para as novas funcionalidades
-- Executar 1 vez na base de dados do projeto (MySQL/MariaDB)
-- ============================================================

-- Nota:
-- - As alterações abaixo são idempotentes (IF NOT EXISTS).
-- - Este script assume que a base já existe e que a conexão
--   do utilizador aponta para a base correta.
--
-- Se preferir, pode descomentar:
--   USE pap;

-- ============================================================
-- 1) transactions: adicionar colunas para importação de faturas
-- ============================================================
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS source      VARCHAR(20)  DEFAULT NULL  COMMENT 'origem: invoice, manual, csv, etc',
  ADD COLUMN IF NOT EXISTS source_id   INT          DEFAULT NULL  COMMENT 'id da linha na tabela de origem';

-- ============================================================
-- 2) invoice_lines: colunas para controlo de importação
--    (só necessário se a tabela já existir; se não existir,
--     o CREATE TABLE IF NOT EXISTS abaixo assegura o resto)
-- ============================================================
ALTER TABLE invoice_lines
  ADD COLUMN IF NOT EXISTS imported       TINYINT(1)   DEFAULT 0 COMMENT '1 = já importada como transação',
  ADD COLUMN IF NOT EXISTS transaction_id  INT          DEFAULT NULL COMMENT 'id da transação gerada';

-- ============================================================
-- 3) invoices: controlo de importação total
-- ============================================================
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS imported     TINYINT(1) DEFAULT 0 COMMENT '1 = fatura completamente importada',
  ADD COLUMN IF NOT EXISTS imported_at DATETIME   DEFAULT NULL;

-- ============================================================
-- 4) payment_reminders: separar “paid” de “active”
--    - active=0 => apenas desativação manual
--    - paid=1   => pagamento confirmado
-- ============================================================
ALTER TABLE payment_reminders
  ADD COLUMN IF NOT EXISTS paid TINYINT(1) DEFAULT 0 COMMENT '1 = pagamento confirmado (apenas para recurrence=once)';

-- ============================================================
-- 5) Índice útil para filtrar transações por cartão activo
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_transactions_card_date
  ON transactions (card_id, transaction_date);

-- ============================================================
-- 6) cards: coluna bank (usada pela UI do dashboard/cartões)
-- ============================================================
ALTER TABLE cards
  ADD COLUMN IF NOT EXISTS bank VARCHAR(50) DEFAULT '' COMMENT 'key do banco (ex: revolut, cgd, santander, ...)' ;


-- ============================================================
-- Estruturas completas (caso as tabelas ainda não existam)
-- ============================================================

-- ============================================================
-- Tabela invoices
-- ============================================================
CREATE TABLE IF NOT EXISTS invoices (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT          NOT NULL,
  name           VARCHAR(255) NOT NULL,
  file_path      VARCHAR(500) DEFAULT NULL,
  imported       TINYINT(1)   DEFAULT 0,
  imported_at    DATETIME     DEFAULT NULL,
  created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabela invoice_lines
-- ============================================================
CREATE TABLE IF NOT EXISTS invoice_lines (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id       INT            NOT NULL,
  description      VARCHAR(500)   NOT NULL,
  amount           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  category         VARCHAR(100)   DEFAULT NULL,
  transaction_date DATE           DEFAULT NULL,
  imported         TINYINT(1)     DEFAULT 0,
  transaction_id   INT            DEFAULT NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabela reminder_history
-- ============================================================
CREATE TABLE IF NOT EXISTS reminder_history (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  reminder_id    INT            NOT NULL,
  transaction_id INT            DEFAULT NULL,
  paid_date      DATE           NOT NULL,
  amount         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  notes          TEXT          NULL,
  created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reminder_id) REFERENCES payment_reminders(id) ON DELETE CASCADE,
  FOREIGN KEY (transaction_id) REFERENCES transactions(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

