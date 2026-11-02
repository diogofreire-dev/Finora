<?php
// site/auth.php
// Inclui no topo de páginas que exigem autenticação.
// Uso: require_once __DIR__ . '/auth.php';

session_start();

if (empty($_SESSION['user_id'])) {
    // podes redirecionar para login com uma query string para voltar depois
    $return = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    header("Location: /site/auth/login.php?next={$return}");
    exit;
}

// Todas as páginas autenticadas ficam protegidas contra CSRF
require_once __DIR__ . '/csrf.php';
csrf_verify();
