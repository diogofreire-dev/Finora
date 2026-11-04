<?php
/**
 * Configuração da Brevo (envio de emails) — EXEMPLO
 * Copia este ficheiro para brevo.php e preenche com a tua chave.
 *
 * COMO OBTER A CHAVE:
 * 1. Vai a https://app.brevo.com/settings/keys/api
 * 2. Cria uma chave com acesso "Restrito"
 * 3. Permissões: apenas "E-mails transacionais" → Todos
 */

define('BREVO_API_KEY', 'SUA_CHAVE_BREVO');

// URL base usado nos links dos emails (sem barra no fim)
// Local:     http://localhost/Finora/site
// Produção:  https://<dominio>/site
define('APP_URL', 'http://localhost/Finora/site');
