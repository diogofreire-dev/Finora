-- Investimentos: posições registadas à mão (fase 1). Os preços automáticos vêm mais tarde (Bolsa).
CREATE TABLE IF NOT EXISTS investments (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  name           VARCHAR(100) NOT NULL,
  type           ENUM('acoes','etf','cripto','ppr','deposito','obrigacoes','fundo','outro') NOT NULL DEFAULT 'outro',
  ticker         VARCHAR(20)  NULL,
  quantity       DECIMAL(18,8) NOT NULL,
  buy_price      DECIMAL(14,4) NOT NULL,   -- preço médio de compra por unidade (€)
  current_price  DECIMAL(14,4) NOT NULL,   -- preço atual por unidade (€), atualizado à mão
  price_updated_at DATETIME    NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_investments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_investments_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
