<?php
// site/helpers/import_json.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/import_rows.php';

$uid = (int) ($_SESSION['user_id'] ?? 0);

$content = import_read_upload($_FILES['json_file'] ?? null, ['json']);

// Profundidade limitada: um backup da Finora tem 3 níveis; JSON muito aninhado é recusado
$backup = json_decode($content, true, 8);
if (!is_array($backup) || !in_array($backup['app'] ?? '', ['Finora', 'FreeCard'], true) || !is_array($backup['transactions'] ?? null)) {
    import_finish('danger', t('Este ficheiro não é um backup da Finora.'));
}
if (count($backup['transactions']) > IMPORT_MAX_ROWS) {
    import_finish('danger', t('O backup tem mais de {n} transações.', ['n' => IMPORT_MAX_ROWS]));
}

$cardMap = import_card_map($pdo, $uid);
$rows = $errors = [];
foreach (array_values($backup['transactions']) as $n => $t) {
    if (!is_array($t)) { $errors[] = 'transação ' . ($n + 1) . ': formato inválido'; continue; }
    [$row, $err] = import_row([
        'date' => $t['transaction_date'] ?? null, 'description' => $t['description'] ?? null,
        'amount' => $t['amount'] ?? null, 'category' => $t['category'] ?? null, 'card' => $t['card_name'] ?? null,
    ], $cardMap);
    if ($err) $errors[] = 'transação ' . ($n + 1) . ': ' . $err;
    else      $rows[] = $row;
}

try {
    // Num restauro as repetidas são sempre ignoradas: restaurar duas vezes não duplica nada
    [$imported, $skipped] = import_save($pdo, $uid, $rows, true);
} catch (Throwable $e) {
    error_log('[import_json] ' . $e->getMessage());
    import_finish('danger', t('O restauro falhou e nada foi gravado. Tenta outra vez.'));
}

import_finish(...import_summary($imported, $skipped, $errors));
