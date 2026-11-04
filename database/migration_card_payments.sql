-- Pagamentos de extrato: repõem o saldo usado de um cartão sem contar como gasto
-- (não vão para transactions, senão o pagamento aparecia na análise em dobro).
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
