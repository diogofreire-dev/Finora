-- A cache de NIFs (nif_empresas) é partilhada por todos os utilizadores.
-- Antes, qualquer utilizador podia escrever nela (ao guardar uma fatura ou pelo save_company_nif.php),
-- o que deixava mudar o nome de uma empresa para toda a gente. Essas entradas ficavam com fonte 'manual'
-- (ou sem fonte). Agora só o servidor escreve, com nomes vindos de fontes externas: apagamos o resto.
DELETE FROM nif_empresas WHERE fonte = 'manual' OR fonte IS NULL;
