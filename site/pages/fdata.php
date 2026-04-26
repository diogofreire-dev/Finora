<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

function jsonOut($data) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = $_SESSION['user_id'] ?? null;
if (!$uid) jsonOut(['error' => 'Não autenticado.']);

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$type    = $body['t'] ?? 'chat';
$message = trim($body['m'] ?? '');
if ($type === 'chat' && $message === '') jsonOut(['error' => 'Mensagem vazia.']);

try {
    // Categorias do mes atual
    $stmt = $pdo->prepare("
        SELECT COALESCE(category,'Outros') as category,
               ROUND(SUM(amount),2) as total, COUNT(*) as num_transacoes
        FROM transactions
        WHERE user_id = :uid
          AND YEAR(transaction_date) = YEAR(CURDATE())
          AND MONTH(transaction_date) = MONTH(CURDATE())
        GROUP BY category ORDER BY total DESC
    ");
    $stmt->execute([':uid' => $uid]);
    $porCategoria = $stmt->fetchAll();

    // Total do mes atual
    $stmt = $pdo->prepare("
        SELECT ROUND(SUM(amount),2) as total_mes
        FROM transactions
        WHERE user_id = :uid
          AND YEAR(transaction_date) = YEAR(CURDATE())
          AND MONTH(transaction_date) = MONTH(CURDATE())
    ");
    $stmt->execute([':uid' => $uid]);
    $totalMes = $stmt->fetch()['total_mes'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(transaction_date, '%Y-%m') as mes,
               ROUND(SUM(amount),2) as total, COUNT(*) as num_transacoes
        FROM transactions
        WHERE user_id = :uid AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mes ORDER BY mes ASC
    ");
    $stmt->execute([':uid' => $uid]);
    $porMes = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT COALESCE(category,'Outros') as category,
               description, ROUND(amount,2) as amount, transaction_date
        FROM transactions
        WHERE user_id = :uid
          AND YEAR(transaction_date) = YEAR(CURDATE())
          AND MONTH(transaction_date) = MONTH(CURDATE())
        ORDER BY amount DESC LIMIT 30
    ");
    $stmt->execute([':uid' => $uid]);
    $mesAtual = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT description, COALESCE(category,'Outros') as category,
               ROUND(amount,2) as amount, transaction_date
        FROM transactions WHERE user_id = :uid ORDER BY amount DESC LIMIT 5
    ");
    $stmt->execute([':uid' => $uid]);
    $top5 = $stmt->fetchAll();
} catch (Exception $e) {
    jsonOut(['error' => 'Erro BD: ' . $e->getMessage()]);
}

$ctx  = "Es um assistente financeiro da app FreeCard.\n";
$ctx .= "Respondes SEMPRE em Portugues de Portugal.\n";
$ctx .= "Regras: respostas curtas e diretas (max 4 linhas), sem introducoes, sem despedidas, sem asteriscos, usa tu em vez de voce.\n";
$ctx .= "\n=== DADOS ===\n\nMes atual - Total gasto: EUR{$totalMes}\n";
$ctx .= "Mes atual - Por categoria:\n";
foreach ($porCategoria as $r) $ctx .= "  {$r['category']}: EUR{$r['total']} ({$r['num_transacoes']} transacoes)\n";
$ctx .= "\nHistorico mensal (ultimos 6 meses):\n";
foreach ($porMes as $r) $ctx .= "  {$r['mes']}: EUR{$r['total']}\n";
$ctx .= "\nMes atual:\n";
foreach ($mesAtual as $r) $ctx .= "  [{$r['category']}] {$r['description']}: EUR{$r['amount']}\n";
$ctx .= "\nTop 5 gastos:\n";
foreach ($top5 as $r) $ctx .= "  {$r['description']}: EUR{$r['amount']}\n";

if ($type === 'insights') {
    $prompt = $ctx . "\nTAREFA: Escreve 3 insights do mes atual. Cada insight numa linha. Usa **negrito** apenas nos valores em euros e nas categorias. Sem emojis. Sem introducao. Exemplo: Gastaste **EUR1.200** em **Casa** este mes.";
} elseif ($type === 'savings') {
    $prompt = $ctx . "\nTAREFA: Escreve 3 sugestoes de poupanca numeradas. Formato: '1. [acao concreta] - poupa **EURvalor**/mes'. Sem emojis. Maximo 1 linha por sugestao.";
} else {
    $prompt = $ctx . "\nPERGUNTA: " . $message . "\nResponde em 1-3 frases diretas sem introducao. Usa **negrito** nos valores e categorias importantes. Sem emojis.";
}

jsonOut([
    'prompt' => $prompt,
    'apiKey' => GROQ_API_KEY,
]);