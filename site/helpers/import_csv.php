<?php
// site/helpers/import_csv.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';

$uid = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file'])) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('Nenhum ficheiro enviado.'));
    exit;
}

$file = $_FILES['csv_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('Erro ao carregar o ficheiro.'));
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt'])) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('Formato inválido. Usa um ficheiro .csv'));
    exit;
}

$skipDuplicates = !empty($_POST['skip_duplicates']);

// Read file and detect delimiter
$content = file_get_contents($file['tmp_name']);
// Remove UTF-8 BOM if present
$content = ltrim($content, "\xEF\xBB\xBF");

// Detect delimiter: semicolon or comma
$delimiter = (substr_count($content, ';') > substr_count($content, ',')) ? ';' : ',';

$lines = preg_split('/\r\n|\r|\n/', trim($content));
if (count($lines) < 2) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('O ficheiro está vazio ou só tem o cabeçalho.'));
    exit;
}

// Parse header
$header = str_getcsv(array_shift($lines), $delimiter);
$header = array_map(fn($h) => strtolower(trim($h)), $header);

// Map column positions
$colMap = [];
foreach (['data', 'descricao', 'categoria', 'valor', 'cartao'] as $col) {
    $idx = array_search($col, $header);
    $colMap[$col] = $idx !== false ? $idx : null;
}

if ($colMap['data'] === null || $colMap['valor'] === null || $colMap['descricao'] === null) {
    header('Location: ../pages/dados.php?import_error=' . urlencode('Colunas obrigatórias em falta: data, descricao, valor.'));
    exit;
}

// Pre-load user cards for name→id mapping
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$cardMap = [];
foreach ($stmt->fetchAll() as $c) {
    $cardMap[strtolower(trim($c['name']))] = $c['id'];
}

$imported = 0;
$skipped  = 0;
$errors   = [];

$stmtInsert = $pdo->prepare("
    INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at)
    VALUES (:uid, :cid, :amt, :desc, :cat, :date, NOW())
");

$stmtCheck = $pdo->prepare("
    SELECT COUNT(*) FROM transactions
    WHERE user_id = :uid AND transaction_date = :date
    AND amount = :amt AND description = :desc
");

foreach ($lines as $lineNum => $line) {
    if (trim($line) === '') continue;

    $row = str_getcsv($line, $delimiter);

    $dateRaw = trim($row[$colMap['data']] ?? '');
    $desc    = trim($row[$colMap['descricao']] ?? '');
    $cat     = $colMap['categoria'] !== null ? trim($row[$colMap['categoria']] ?? '') : '';
    $valRaw  = str_replace(',', '.', trim($row[$colMap['valor']] ?? '0'));
    $cardName= $colMap['cartao'] !== null ? strtolower(trim($row[$colMap['cartao']] ?? '')) : '';

    // Validate date
    $date = DateTime::createFromFormat('Y-m-d', $dateRaw);
    if (!$date) $date = DateTime::createFromFormat('d/m/Y', $dateRaw);
    if (!$date) {
        $errors[] = "Linha " . ($lineNum + 2) . ": data inválida '$dateRaw'";
        continue;
    }
    $dateFormatted = $date->format('Y-m-d');

    // Validate amount
    $amount = floatval($valRaw);
    if ($amount <= 0) {
        $errors[] = "Linha " . ($lineNum + 2) . ": valor inválido '$valRaw'";
        continue;
    }

    if (empty($desc)) {
        $errors[] = "Linha " . ($lineNum + 2) . ": descrição em falta";
        continue;
    }

    // Resolve card
    $cardId = null;
    if ($cardName && isset($cardMap[$cardName])) {
        $cardId = $cardMap[$cardName];
    }

    // Skip duplicates
    if ($skipDuplicates) {
        $stmtCheck->execute([':uid' => $uid, ':date' => $dateFormatted, ':amt' => $amount, ':desc' => $desc]);
        if ($stmtCheck->fetchColumn() > 0) { $skipped++; continue; }
    }

    $stmtInsert->execute([
        ':uid'  => $uid,
        ':cid'  => $cardId,
        ':amt'  => $amount,
        ':desc' => $desc,
        ':cat'  => $cat ?: null,
        ':date' => $dateFormatted,
    ]);
    $imported++;
}

$msg = "imported={$imported}&skipped={$skipped}";
if (!empty($errors)) {
    $errSummary = count($errors) . " linha(s) com erros: " . implode('; ', array_slice($errors, 0, 3));
    $msg .= '&import_error=' . urlencode($errSummary);
} else {
    $msg = 'import_ok=1&' . $msg;
}

header('Location: ../pages/dados.php?' . $msg);
exit;
