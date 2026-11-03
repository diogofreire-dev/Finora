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

// A conta da sessão tem de continuar a existir. Se foi apagada (ou a base de dados foi
// reposta), a sessão antiga ficava "com login feito" numa conta fantasma e as páginas
// falhavam a meio (ex.: user_settings com chave estrangeira para um utilizador que já não existe).
require_once __DIR__ . '/../../config/db.php';
$__userCheck = $pdo->prepare('SELECT 1 FROM users WHERE id = :id');
$__userCheck->execute([':id' => (int) $_SESSION['user_id']]);
if (!$__userCheck->fetchColumn()) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . site_base_path() . '/auth/login.php');
    exit;
}
unset($__userCheck);

// Todas as páginas autenticadas ficam protegidas contra CSRF
require_once __DIR__ . '/csrf.php';
csrf_verify();

// Verificação de dono dos recursos (IDOR) — ver owned_card_id()
require_once __DIR__ . '/ownership.php';

// Formatação em português (fmt_eur, month_name_pt) disponível em todas as páginas
require_once __DIR__ . '/format.php';
require_once __DIR__ . '/categories.php';
