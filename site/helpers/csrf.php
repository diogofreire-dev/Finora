<?php
// site/helpers/csrf.php
// Proteção CSRF: cada sessão tem um token secreto que um site externo não consegue ler.
// Todos os pedidos POST têm de o enviar (campo csrf_token ou cabeçalho X-CSRF-Token).

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Campo escondido para pôr dentro de cada <form method="post">
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// Recusa qualquer POST sem o token certo
function csrf_verify(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Pedido inválido. Atualiza a página e tenta novamente.');
    }
}
