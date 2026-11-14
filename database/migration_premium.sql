-- Finora Pro: subscrição de cada conta (Stripe) e eventos do webhook já tratados.
-- O schema.sql já inclui estas tabelas; este ficheiro serve para aplicar só esta mudança.
USE pap;

-- Uma linha por conta que já passou pelo Stripe (ou recebeu o Pro à mão: status = 'comp').
-- Sem linha, a conta é do plano grátis.
CREATE TABLE IF NOT EXISTS subscriptions (
  user_id                INT UNSIGNED PRIMARY KEY,
  stripe_customer_id     VARCHAR(64)  NULL UNIQUE,
  stripe_subscription_id VARCHAR(64)  NULL UNIQUE,
  status                 VARCHAR(32)  NOT NULL DEFAULT 'none', -- active, trialing, past_due, canceled, … ou comp
  current_period_end     DATETIME     NULL,                    -- até quando está pago
  cancel_at_period_end   TINYINT(1)   NOT NULL DEFAULT 0,      -- cancelou, mas mantém o Pro até ao fim do período
  updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- O Stripe pode enviar o mesmo evento mais de uma vez: guardamos o id para não o tratar duas vezes.
CREATE TABLE IF NOT EXISTS stripe_events (
  id          VARCHAR(255) PRIMARY KEY,
  type        VARCHAR(100) NOT NULL,
  received_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
