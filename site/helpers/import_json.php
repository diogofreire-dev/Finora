<?php
// site/helpers/import_json.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';

$uid = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['json_file'])) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('Nenhum ficheiro enviado.'));
    exit;
}

$file = $_FILES['json_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('Erro ao carregar o ficheiro.'));
    exit;
}

$content = file_get_contents($file['tmp_name']);
$backup  = json_decode($content, true);

if (!$backup || !isset($backup['transactions'])) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('Ficheiro JSON inválido ou incompatível com FreeCard.'));
    exit;
}

// Pre-load user cards for name→id mapping
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$cardMap = [];
foreach ($stmt->fetchAll() as $c) {
    $cardMap[strtolower(trim($c['name']))] = $c['id'];
}

$stmtInsert = $pdo->prepare("
    INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at)
    VALUES (:uid, :cid, :amt, :desc, :cat, :date, NOW())
");

$stmtCheck = $pdo->prepare("
    SELECT COUNT(*) FROM transactions
    WHERE user_id = :uid AND transaction_date = :date
    AND amount = :amt AND description = :desc
");

$imported = 0;
$skipped  = 0;

foreach ($backup['transactions'] as $t) {
    $date   = $t['transaction_date'] ?? '';
    $desc   = $t['description']      ?? '';
    $cat    = $t['category']         ?? null;
    $amount = floatval($t['amount']  ?? 0);
    $cname  = strtolower(trim($t['card_name'] ?? ''));

    if (!$date || !$desc || $amount <= 0) { $skipped++; continue; }

    // Skip duplicates
    $stmtCheck->execute([':uid' => $uid, ':date' => $date, ':amt' => $amount, ':desc' => $desc]);
    if ($stmtCheck->fetchColumn() > 0) { $skipped++; continue; }

    $cardId = isset($cardMap[$cname]) ? $cardMap[$cname] : null;

    $stmtInsert->execute([
        ':uid'  => $uid,
        ':cid'  => $cardId,
        ':amt'  => $amount,
        ':desc' => $desc,
        ':cat'  => $cat,
        ':date' => $date,
    ]);
    $imported++;
}

header('Location: ../pages/dados.php?import_ok=1&imported=' . $imported . '&skipped=' . $skipped);
exit;
