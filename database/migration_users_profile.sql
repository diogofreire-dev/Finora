-- Antes estas alterações corriam no settings.php em CADA visita à página.
-- Alterar a estrutura da base de dados é trabalho de migração: corre-se uma vez, à mão.
-- Só é preciso numa base de dados antiga; o schema.sql já as inclui.

ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id   VARCHAR(128) NULL DEFAULT NULL;

-- O username é só o nome que aparece no ecrã; duas pessoas podem chamar-se "Ana Silva".
-- (Dá erro se o índice já não existir — nesse caso não há nada a fazer.)
ALTER TABLE users DROP INDEX username;
