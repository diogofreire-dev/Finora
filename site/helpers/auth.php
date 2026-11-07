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
// Na mesma consulta vêm a moeda e a língua da conta (user_settings pode ainda não ter linha: LEFT JOIN)
$__userCheck = $pdo->prepare('SELECT s.currency, s.language FROM users u LEFT JOIN user_settings s ON s.user_id = u.id WHERE u.id = :id');
$__userCheck->execute([':id' => (int) $_SESSION['user_id']]);
$__userRow = $__userCheck->fetch();
if (!$__userRow) {
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

// Formatação em português (fmt_money na moeda da conta, month_name_pt) disponível em todas as páginas
require_once __DIR__ . '/format.php';
set_user_currency($__userRow['currency'] ?? null);
// Sem linha em user_settings, fica a língua detetada (cookie / browser)
if (!empty($__userRow['language'])) set_lang($__userRow['language']);
unset($__userRow);
require_once __DIR__ . '/categories.php';
