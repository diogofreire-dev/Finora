-- Poupanças: dinheiro posto de lado (valor positivo) ou retirado (valor negativo).
-- Cada movimento pode estar ligado a uma meta; se a meta for apagada, o dinheiro fica no fundo geral.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
