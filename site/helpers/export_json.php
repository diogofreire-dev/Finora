<?php
// site/helpers/export_json.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';

$uid = $_SESSION['user_id'] ?? null;

// Transações
$stmt = $pdo->prepare("
    SELECT t.transaction_date, t.description, t.category, t.amount,
           c.name AS card_name, t.created_at
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid
    ORDER BY t.transaction_date DESC
");
$stmt->execute([':uid' => $uid]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cartões
$stmt = $pdo->prepare("
    SELECT name, color, limit_amount, balance, active, created_at
    FROM cards WHERE user_id = :uid ORDER BY name
");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Orçamentos
$stmt = $pdo->prepare("
    SELECT name, amount, period, category, active, created_at
    FROM budgets WHERE user_id = :uid ORDER BY created_at DESC
");
$stmt->execute([':uid' => $uid]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Utilizador (apenas username, sem dados sensíveis)
$stmt = $pdo->prepare("SELECT username, created_at FROM users WHERE id = :uid");
$stmt->execute([':uid' => $uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$backup = [
    'app'          => 'FreeCard',
    'version'      => '1.0',
    'exported_at'  => date('Y-m-d H:i:s'),
    'user'         => $user,
    'summary'      => [
        'transactions' => count($transactions),
        'cards'        => count($cards),
        'budgets'      => count($budgets),
    ],
    'transactions' => $transactions,
    'cards'        => $cards,
    'budgets'      => $budgets,
];

$filename = 'freecard_backup_' . date('Y-m-d') . '.json';

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
