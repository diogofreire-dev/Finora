<?php
// site/auth.php
// Inclui no topo de páginas que exigem autenticação.
// Uso: require_once __DIR__ . '/auth.php';

session_start();
require_once __DIR__ . '/paths.php';

if (empty($_SESSION['user_id'])) {
    // Volta para o login, lembrando a página pedida (o login valida o ?next=)
    $return = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: ' . site_base_path() . "/auth/login.php?next={$return}");
    exit;
}

// Todas as páginas autenticadas ficam protegidas contra CSRF
require_once __DIR__ . '/csrf.php';
csrf_verify();

// Verificação de dono dos recursos (IDOR) — ver owned_card_id()
require_once __DIR__ . '/ownership.php';

// Formatação em português (fmt_eur, month_name_pt) disponível em todas as páginas
require_once __DIR__ . '/format.php';
require_once __DIR__ . '/categories.php';
