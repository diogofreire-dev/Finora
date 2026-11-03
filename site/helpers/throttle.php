<?php
// site/helpers/throttle.php
// Limite de pedidos para as páginas de autenticação. O bloqueio por conta (login.php)
// não chega: um atacante pode experimentar 1 palavra-passe em milhares de contas, ou
// usar o registo/recuperação para encher a caixa de email de alguém.

// IP do cliente. Só REMOTE_ADDR: o X-Forwarded-For vem do cliente e pode ser inventado.
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Quantas vezes $action aconteceu para $subject nos últimos $minutes (contado pelo MySQL).
function throttle_count(PDO $pdo, string $action, string $subject, int $minutes): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM auth_throttle
                           WHERE action = :a AND subject = :s AND created_at > NOW() - INTERVAL ' . (int) $minutes . ' MINUTE');
    $stmt->execute([':a' => $action, ':s' => hash('sha256', $subject)]);
    return (int) $stmt->fetchColumn();
}

function throttle_record(PDO $pdo, string $action, string $subject): void {
    $pdo->prepare('INSERT INTO auth_throttle (action, subject) VALUES (:a, :s)')
        ->execute([':a' => $action, ':s' => hash('sha256', $subject)]);
    // Limpeza ocasional para a tabela não crescer para sempre
    if (random_int(1, 50) === 1) {
        $pdo->exec('DELETE FROM auth_throttle WHERE created_at < NOW() - INTERVAL 1 DAY');
    }
}

// Regista e devolve true se ainda estiver dentro do limite; false se já passou.
function throttle_allow(PDO $pdo, string $action, string $subject, int $max, int $minutes): bool {
    if (throttle_count($pdo, $action, $subject, $minutes) >= $max) return false;
    throttle_record($pdo, $action, $subject);
    return true;
}
