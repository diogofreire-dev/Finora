<?php
/**
 * Configuração do Google OAuth — EXEMPLO
 * Copia este ficheiro para google_oauth.php e preenche com as tuas credenciais.
 *
 * COMO OBTER AS CREDENCIAIS:
 * 1. Vai a https://console.cloud.google.com/
 * 2. Cria um projeto (ou usa um existente)
 * 3. Menu → "APIs e Serviços" → "Credenciais"
 * 4. Clica em "+ Criar credenciais" → "ID do cliente OAuth"
 * 5. Tipo de aplicação: "Aplicação Web"
 * 6. Em "URIs de redirecionamento autorizados", adiciona:
 *    https://TEUDOMINIO.com/site/auth/google_callback.php
 * 7. Copia o "ID do cliente" e o "Segredo do cliente" para baixo
 */

define('GOOGLE_CLIENT_ID',     'SEU_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'SEU_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'https://TEUDOMINIO.com/site/auth/google_callback.php');

// Scopes pedidos ao Google (email e perfil básico)
define('GOOGLE_SCOPES', 'openid email profile');
