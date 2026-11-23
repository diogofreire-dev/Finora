-- Fundo da app escolhido nas Definições (Aparência): constelacao, liso, quadriculado ou linha.
-- As contas que já existem ficam com 'constelacao', o fundo que já tinham.
-- O schema.sql já inclui esta linha; este ficheiro serve para aplicar só esta mudança.
USE pap;
ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS background VARCHAR(12) NOT NULL DEFAULT 'constelacao' AFTER theme;
