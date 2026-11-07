-- Esquema completo da Finora. Seguro de correr numa base que já existe: nunca apaga nada
-- (CREATE ... IF NOT EXISTS). Para recomeçar do zero, apaga a base à mão, de propósito.
CREATE DATABASE IF NOT EXISTS pap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pap;

-- 1. USERS
CREATE TABLE IF NOT EXISTS users (
  id                    INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  username              VARCHAR(50)  NOT NULL,          -- nome de apresentação, pode repetir
  email                 VARCHAR(255) NOT NULL UNIQUE,
  password_hash         VARCHAR(255) NOT NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  role                  ENUM('user','admin') NOT NULL DEFAULT 'user',
  email_verified        TINYINT(1)   DEFAULT 0,
  verification_token    VARCHAR(64)  NULL,
  verification_expires  DATETIME     NULL,
  verification_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  verification_sent_at  DATETIME     NULL,
  password_reset_token  VARCHAR(64)  NULL,
  password_reset_expires DATETIME   NULL,
  failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  login_locked_until    DATETIME     NULL,
  last_password_change  DATETIME     NULL,
  last_email_change     DATETIME     NULL,
  pending_email         VARCHAR(255) NULL,
  pending_email_token   VARCHAR(64)  NULL,
  pending_email_expires DATETIME     NULL,
  two_factor_enabled    TINYINT(1)   DEFAULT 0,
  two_factor_secret     VARCHAR(255) NULL,
  -- Google OAuth (adicionado via google_callback.php)
  google_id             VARCHAR(128) NULL DEFAULT NULL,
  avatar_path           VARCHAR(255) NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_email_verified       ON users(email_verified);
CREATE INDEX IF NOT EXISTS idx_verification_token   ON users(verification_token);
CREATE INDEX IF NOT EXISTS idx_password_reset_token ON users(password_reset_token);
CREATE INDEX IF NOT EXISTS idx_google_id            ON users(google_id);

-- 2. CARDS
CREATE TABLE IF NOT EXISTS cards (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id      INT UNSIGNED    NOT NULL,
  name         VARCHAR(100)    NOT NULL,
  limit_amount DECIMAL(10,2)   DEFAULT 0,
  balance      DECIMAL(10,2)   DEFAULT 0,
  color        VARCHAR(20)     DEFAULT 'purple',
  bank         VARCHAR(50)     DEFAULT '',             -- chave do banco (revolut, cgd, …) para o aspeto do cartão
  active       TINYINT(1)      DEFAULT 1,
  created_at   DATETIME        DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_cards_user_active ON cards(user_id, active);

-- 2b. CARD_PAYMENTS (pagar o extrato: repõe o saldo usado sem contar como gasto)
CREATE TABLE IF NOT EXISTS card_payments (
  id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id    INT UNSIGNED  NOT NULL,
  card_id    INT UNSIGNED  NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  paid_on    DATE          NOT NULL,
  created_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_card_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_card_payments_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
  INDEX idx_card_payments_card (card_id, paid_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TRANSACTIONS
CREATE TABLE IF NOT EXISTS transactions (
  id               INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id          INT UNSIGNED   NOT NULL,
  card_id          INT UNSIGNED   NULL,
  amount           DECIMAL(10,2)  NOT NULL,
  description      VARCHAR(255),
  category         VARCHAR(100)   DEFAULT NULL,
  transaction_date DATE           NOT NULL,
  source           VARCHAR(20)    DEFAULT NULL,        -- origem: qr, invoice, csv…
  source_id        INT            DEFAULT NULL,        -- NIF (qr) ou id da linha de origem
  created_at       DATETIME       DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_transactions_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_transaction_date       ON transactions(transaction_date);
CREATE INDEX IF NOT EXISTS idx_transactions_user_date ON transactions(user_id, transaction_date);
CREATE INDEX IF NOT EXISTS idx_transactions_category  ON transactions(category);
CREATE INDEX IF NOT EXISTS idx_transactions_card_date ON transactions(card_id, transaction_date);

-- 4. USER_SETTINGS
CREATE TABLE IF NOT EXISTS user_settings (
  id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL UNIQUE,
  theme         VARCHAR(10)  DEFAULT 'light',
  currency      VARCHAR(3)   DEFAULT 'EUR',
  language      VARCHAR(5)   DEFAULT 'pt-PT',
  notifications TINYINT(1)   DEFAULT 1,
  notif_prefs   JSON         NULL,               -- tipos de notificação ligados/desligados
  notif_checked_on DATE      NULL,               -- última verificação diária
  monthly_income DECIMAL(10,2) NULL,             -- rendimento mensal líquido (opcional, pedido nas boas-vindas)
  onboarded     TINYINT(1)   NOT NULL DEFAULT 1, -- 0 = conta nova que ainda não passou pelas boas-vindas
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. BUDGETS
CREATE TABLE IF NOT EXISTS budgets (
  id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id    INT UNSIGNED  NOT NULL,
  name       VARCHAR(100)  NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  period     ENUM('monthly','weekly','yearly') NOT NULL DEFAULT 'monthly',
  category   VARCHAR(100)  NULL,
  card_id    INT UNSIGNED  NULL,
  start_date DATE          NOT NULL,
  end_date   DATE          NULL,
  active     TINYINT(1)    DEFAULT 1,
  created_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_budgets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_budgets_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_budgets_user_active ON budgets(user_id, active);

-- 6. BUDGET_ALERTS
CREATE TABLE IF NOT EXISTS budget_alerts (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  budget_id    INT UNSIGNED  NOT NULL,
  user_id      INT UNSIGNED  NOT NULL,
  alert_type   ENUM('warning','exceeded') NOT NULL,
  percentage   DECIMAL(5,2)  NOT NULL,
  amount_spent DECIMAL(10,2) NOT NULL,
  triggered_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
  acknowledged TINYINT(1)    DEFAULT 0,
  CONSTRAINT fk_alerts_budget FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
  CONSTRAINT fk_alerts_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_alerts_user_unack ON budget_alerts(user_id, acknowledged);

-- 7. PAYMENT_REMINDERS
CREATE TABLE IF NOT EXISTS payment_reminders (
  id                     INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id                INT UNSIGNED  NOT NULL,
  name                   VARCHAR(255)  NOT NULL,
  amount                 DECIMAL(10,2) NOT NULL,
  category               VARCHAR(100)  NULL,
  card_id                INT UNSIGNED  NULL,
  due_date               DATE          NOT NULL,
  anchor_day             TINYINT UNSIGNED NULL,          -- dia original (mensais/anuais não escorregam)
  recurrence             ENUM('once','weekly','monthly','yearly') NOT NULL DEFAULT 'once',
  active                 TINYINT(1)    DEFAULT 1,
  paid                   TINYINT(1)    DEFAULT 0,      -- pagamento confirmado (lembretes únicos)
  notify_days_before     INT           DEFAULT 3,
  notify_method          ENUM('email','site','both') DEFAULT 'email',
  last_notification_sent DATETIME      NULL,
  last_paid_date         DATE          NULL,
  next_due_date          DATE          NULL,
  created_at             DATETIME      DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reminders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reminders_card FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_reminders_user_due ON payment_reminders(user_id, due_date);
CREATE INDEX IF NOT EXISTS idx_reminders_active   ON payment_reminders(active);

-- 8. REMINDER_HISTORY
CREATE TABLE IF NOT EXISTS reminder_history (
  id             INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  reminder_id    INT UNSIGNED  NOT NULL,
  transaction_id INT UNSIGNED  NULL,
  paid_date      DATE          NOT NULL,
  amount         DECIMAL(10,2) NOT NULL,
  notes          TEXT          NULL,
  created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_reminder    FOREIGN KEY (reminder_id)    REFERENCES payment_reminders(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_history_reminder  ON reminder_history(reminder_id);
CREATE INDEX IF NOT EXISTS idx_history_paid_date ON reminder_history(paid_date);

-- Faturas importadas linha a linha (usado em transactions.php)
CREATE TABLE IF NOT EXISTS invoices (
  id          INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(255) NOT NULL,
  file_path   VARCHAR(500) DEFAULT NULL,
  imported    TINYINT(1)   DEFAULT 0,
  imported_at DATETIME     DEFAULT NULL,
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_lines (
  id               INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  invoice_id       INT UNSIGNED  NOT NULL,
  description      VARCHAR(500)  NOT NULL,
  amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  category         VARCHAR(100)  DEFAULT NULL,
  transaction_date DATE          DEFAULT NULL,
  imported         TINYINT(1)    DEFAULT 0,
  transaction_id   INT UNSIGNED  DEFAULT NULL,
  CONSTRAINT fk_invoice_lines_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(50)  NOT NULL,
  dedupe_key VARCHAR(80)  NULL,                 -- o mesmo aviso só entra uma vez (ver helpers/notifications.php)
  title      VARCHAR(255) NOT NULL,
  message    TEXT         NOT NULL,
  data       JSON         NULL,
  is_read    TINYINT(1)   DEFAULT 0,
  read_at    DATETIME     NULL,
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notif_dedupe (user_id, dedupe_key),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_notifications_user_unread ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_type        ON notifications(type);
CREATE INDEX IF NOT EXISTS idx_notifications_created     ON notifications(created_at);

-- 10. NIF_EMPRESAS
CREATE TABLE IF NOT EXISTS nif_empresas (
  nif          VARCHAR(9)   NOT NULL,
  nome_empresa VARCHAR(255) NOT NULL,
  id_categoria INT          NULL,
  fonte        ENUM('manual','vies','nifpt','fallback') DEFAULT 'manual',
  verified     TINYINT(1)   DEFAULT 0,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (nif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_nif_fonte    ON nif_empresas(fonte);
CREATE INDEX IF NOT EXISTS idx_nif_verified ON nif_empresas(verified);

-- 11. NIF_CACHE  (cache de pesquisas ao nif.pt)
CREATE TABLE IF NOT EXISTS nif_cache (
  nif        VARCHAR(20)  NOT NULL,
  name       VARCHAR(255) NOT NULL,
  fetched_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. FUTURE_GOALS  (objetivos de poupança)
CREATE TABLE IF NOT EXISTS future_goals (
  id             INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id        INT UNSIGNED  NOT NULL,
  name           VARCHAR(120)  NOT NULL,
  target_amount  DECIMAL(10,2) NOT NULL,
  monthly_income DECIMAL(10,2) NOT NULL DEFAULT 0,
  target_date    DATE          NOT NULL,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_goals_user ON future_goals(user_id);

-- 13. INVESTMENTS  (posições registadas à mão; preços automáticos numa fase seguinte)
CREATE TABLE IF NOT EXISTS investments (
  id               INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id          INT UNSIGNED  NOT NULL,
  name             VARCHAR(100)  NOT NULL,
  type             ENUM('acoes','etf','cripto','ppr','deposito','obrigacoes','fundo','outro') NOT NULL DEFAULT 'outro',
  ticker           VARCHAR(20)   NULL,
  quantity         DECIMAL(18,8) NOT NULL,
  buy_price        DECIMAL(14,4) NOT NULL,
  current_price    DECIMAL(14,4) NOT NULL,
  price_updated_at DATETIME      NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_investments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_investments_user ON investments(user_id);

-- 14. AI_REQUESTS  (limite de pedidos ao assistente IA por utilizador)
CREATE TABLE IF NOT EXISTS ai_requests (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_ai_requests_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. AUTH_THROTTLE  (limite por IP no login, registo e recuperação; guarda só hashes)
CREATE TABLE IF NOT EXISTS auth_throttle (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action     VARCHAR(20) NOT NULL,
  subject    CHAR(64)    NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_throttle (action, subject, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. SAVINGS  (poupanças: positivo = pôr de lado, negativo = tirar; meta opcional)
CREATE TABLE IF NOT EXISTS savings (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED  NOT NULL,
  goal_id    INT UNSIGNED  NULL,
  amount     DECIMAL(10,2) NOT NULL,
  note       VARCHAR(120)  NULL,
  saved_on   DATE          NOT NULL,
  created_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_savings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_savings_goal FOREIGN KEY (goal_id) REFERENCES future_goals(id) ON DELETE SET NULL,
  INDEX idx_savings_user_date (user_id, saved_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. MARKET_QUOTES, FX_RATES, WATCHLIST  (Bolsa: cache de cotações/câmbio só escrita pelo servidor; lista por utilizador)
CREATE TABLE IF NOT EXISTS market_quotes (
  symbol         VARCHAR(20)   NOT NULL PRIMARY KEY,
  name           VARCHAR(120)  NULL,
  currency       CHAR(3)       NOT NULL,
  price          DECIMAL(18,6) NOT NULL,          -- na moeda original
  price_eur      DECIMAL(18,6) NOT NULL,
  prev_close_eur DECIMAL(18,6) NULL,              -- fecho anterior, para a variação do dia
  spark          TEXT          NULL,              -- JSON com os fechos do último mês (já em EUR)
  fetched_at     DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fx_rates (
  currency     CHAR(3)       NOT NULL PRIMARY KEY,
  eur_per_unit DECIMAL(18,8) NOT NULL,
  fetched_at   DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS watchlist (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  symbol     VARCHAR(20)  NOT NULL,
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_watch (user_id, symbol),
  CONSTRAINT fk_watch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dados de exemplo para nif_empresas
-- (principais cadeias portuguesas por categoria)

-- Supermercados / Alimentação
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('500961353', 'CONTINENTE HIPERMERCADOS SA',          1, 'vies'),
  ('500961360', 'CARREFOUR PORTUGAL SA',                1, 'vies'),
  ('500183757', 'PINGO DOCE - DISTRIBUIÇÃO ALIMENTAR',  1, 'vies'),
  ('500785470', 'LIDL & COMPANHIA',                     1, 'vies'),
  ('502048137', 'ALDI PORTUGAL',                        1, 'vies'),
  ('506757370', 'MERCADONA SA - SUCURSAL EM PORTUGAL',  1, 'vies'),
  ('504884505', 'INTERMARCHÉ - IMOPORTUGAL LDA',        1, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- Restaurantes / Fast Food
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('501907372', 'MCDONALD''S PORTUGAL LDA',             2, 'vies'),
  ('501652289', 'BURGER KING PORTUGAL LDA',             2, 'vies'),
  ('503264032', 'PIZZA HUT PORTUGAL LDA',               2, 'vies'),
  ('507957547', 'NANDO''S PORTUGAL LDA',                2, 'vies'),
  ('506867824', 'TELEPIZZA PORTUGAL SA',                 2, 'vies'),
  ('508762830', 'HABIBI RESTAURAÇÃO LDA',               2, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- Combustível / Gasolineiras
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('500077030', 'GALP ENERGIA PORTUGAL SA',             3, 'vies'),
  ('500735898', 'BP PORTUGAL SA',                       3, 'vies'),
  ('500189923', 'REPSOL PORTUGUESA SA',                 3, 'vies'),
  ('501234567', 'PRIO ENERGY SA',                       3, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- Farmácias
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('503284745', 'WELLS - HEALTH & BEAUTY STORES SA',   4, 'vies'),
  ('504880584', 'FARMÁCIAS HOLON SA',                   4, 'vies'),
  ('509423460', 'FARMÁCIAS PORTUGUESAS ASSOCIADAS',     4, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- Telecomunicações / Internet
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('500273170', 'MEO - SERVIÇOS DE COMUNICAÇÕES E MULTIMÉDIA SA', 5, 'vies'),
  ('502155347', 'NOS COMUNICAÇÕES SA',                  5, 'vies'),
  ('508984087', 'VODAFONE PORTUGAL SA',                  5, 'vies'),
  ('509947655', 'NOWO COMMUNICATIONS SA',               5, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- Transportes
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('500193006', 'CP - COMBOIOS DE PORTUGAL EPE',        6, 'vies'),
  ('500735140', 'METROPOLITANO DE LISBOA EPE',          6, 'vies'),
  ('500854300', 'CARRIS - METROPOLITANA DE LISBOA SA',  6, 'vies'),
  ('500247557', 'TAP AIR PORTUGAL SA',                  6, 'vies'),
  ('507957986', 'UBER PORTUGAL LDA',                    6, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- Lojas / Retalho
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('500961358', 'EL CORTE INGLÉS SA',                   7, 'vies'),
  ('500249290', 'FNAC PORTUGAL LDA',                    7, 'vies'),
  ('502762330', 'WORTEN EQUIPAMENTOS PARA O LAR SA',    7, 'vies'),
  ('503884777', 'ZARA PORTUGAL - CONFECÇÕES SA',        7, 'vies'),
  ('508856888', 'H&M HENNES & MAURITZ SA',              7, 'vies'),
  ('500271647', 'LEROY MERLIN PORTUGAL SA',             7, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- Utilities (EDP, água, gás)
INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte) VALUES
  ('500697256', 'EDP - ENERGIAS DE PORTUGAL SA',        8, 'vies'),
  ('503264032', 'ENDESA ENERGIA SA SUCURSAL PORTUGAL',  8, 'vies'),
  ('500509987', 'GALP GÁS NATURAL DISTRIBUIÇÃO SA',     8, 'vies'),
  ('500115932', 'ÁGUAS DE PORTUGAL SA',                 8, 'vies'),
  ('500077058', 'EPAL - EMPRESA PORTUGUESA DAS ÁGUAS LIVRES SA', 8, 'vies')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = VALUES(fonte);

-- ─────────────────────────────────────────────────────────────────────────────
-- Colunas acrescentadas depois de a tabela já existir. O CREATE TABLE IF NOT EXISTS
-- não mexe numa tabela que já existe, por isso uma base antiga recebe-as aqui.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS monthly_income DECIMAL(10,2) NULL AFTER notif_checked_on;
-- DEFAULT 1: as contas que já existem não são mandadas para as boas-vindas.
-- O código põe 0 explicitamente quando cria as definições de uma conta nova.
ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS onboarded TINYINT(1) NOT NULL DEFAULT 1 AFTER monthly_income;
