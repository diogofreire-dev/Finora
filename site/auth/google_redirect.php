<?php
// site/auth/google_redirect.php
// Redireciona o utilizador para a página de login do Google
session_start();
require_once __DIR__ . '/../../config/google_oauth.php';

// Modo vincular conta
if (isset($_GET['link']) && !empty($_SESSION['user_id'])) {
    $_SESSION['google_link_uid'] = $_SESSION['user_id'];
}

// Gerar state anti-CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => GOOGLE_SCOPES,
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;