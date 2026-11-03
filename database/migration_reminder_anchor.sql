-- Dia do mês original de cada lembrete, para os mensais/anuais não escorregarem
-- (ex.: pagamento ao dia 31 → 28 fev → volta ao 31 em março).
ALTER TABLE payment_reminders ADD COLUMN IF NOT EXISTS anchor_day TINYINT UNSIGNED NULL AFTER due_date;
UPDATE payment_reminders SET anchor_day = DAY(due_date) WHERE anchor_day IS NULL;
