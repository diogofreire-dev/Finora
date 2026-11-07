-- Boas-vindas na criação de conta: rendimento mensal (opcional) e se a conta já passou pelo formulário.
-- As contas que já existem ficam com onboarded = 1 (não são mandadas para as boas-vindas).
-- O schema.sql já inclui estas linhas; este ficheiro serve para aplicar só esta mudança.
USE pap;
ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS monthly_income DECIMAL(10,2) NULL AFTER notif_checked_on;
ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS onboarded TINYINT(1) NOT NULL DEFAULT 1 AFTER monthly_income;
