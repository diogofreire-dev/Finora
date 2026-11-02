-- Dados de exemplo para desenvolvimento
-- Password do utilizador demo: ValidPass123!

-- Utilizador demo
INSERT INTO users (username, email, password_hash, email_verified, last_password_change, last_email_change) VALUES
('demo', 'demo@finora.pt', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW());

-- Configuracoes
INSERT INTO user_settings (user_id, theme, notifications) VALUES
(1, 'light', 1);

-- Cartoes
INSERT INTO cards (user_id, name, limit_amount, balance, color) VALUES
(1, 'Visa Principal', 1500.00, 450.00, 'blue'),
(1, 'Mastercard Compras', 1000.00, 320.00, 'green'),
(1, 'Amex Viagens', 2000.00, 0.00, 'purple');

-- Transacoes
INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date) VALUES
(1, 1, 45.00, 'Supermercado Continente', 'Alimentacao', CURDATE()),
(1, 1, 12.50, 'Cafe', 'Alimentacao', CURDATE()),
(1, 2, 89.90, 'Roupa na Zara', 'Compras', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(1, 2, 15.00, 'Cinema', 'Entretenimento', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(1, NULL, 50.00, 'Combustivel', 'Transporte', DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(1, 1, 120.00, 'Ginasio', 'Saude', DATE_SUB(CURDATE(), INTERVAL 5 DAY));

-- Orcamento
INSERT INTO budgets (user_id, name, amount, period, start_date) VALUES
(1, 'Orcamento Mensal', 1000.00, 'monthly', DATE_FORMAT(CURDATE(), '%Y-%m-01'));

-- Lembretes de pagamento
INSERT INTO payment_reminders (user_id, name, amount, category, card_id, due_date, recurrence, notify_days_before) VALUES
(1, 'Netflix', 15.99, 'Entretenimento', 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'monthly', 3),
(1, 'Ginasio', 35.00, 'Saude', 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'monthly', 3),
(1, 'Renda', 500.00, 'Casa', NULL, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'monthly', 7),
(1, 'Spotify', 9.99, 'Entretenimento', 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'monthly', 3);

-- Notificacoes de exemplo
INSERT INTO notifications (user_id, type, title, message) VALUES
(1, 'payment_reminder', 'Pagamento Proximo', 'O pagamento da Netflix vence em 5 dias.'),
(1, 'budget_alert', 'Alerta de Orcamento', 'Atingiste 80% do teu orcamento mensal.');
