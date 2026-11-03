<?php
// site/helpers/import_csv.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/import_rows.php';

$uid = (int) ($_SESSION['user_id'] ?? 0);

$content = import_read_upload($_FILES['csv_file'] ?? null, ['csv', 'txt']);

// Ponto e vírgula (Excel em PT) ou vírgula
$firstLine = strtok($content, "\r\n") ?: '';
$delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

$lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($content)), fn($l) => trim($l) !== ''));
if (count($lines) < 2) import_finish('danger', 'O ficheiro está vazio ou só tem o cabeçalho.');
if (count($lines) - 1 > IMPORT_MAX_ROWS) import_finish('danger', 'O ficheiro tem mais de ' . IMPORT_MAX_ROWS . ' linhas. Divide-o em partes mais pequenas.');

$header = array_map(fn($h) => mb_strtolower(trim($h)), str_getcsv(array_shift($lines), $delimiter));
$col = [];
foreach (['data', 'descricao', 'categoria', 'valor', 'cartao'] as $name) {
    $i = array_search($name, $header, true);
    $col[$name] = $i === false ? null : $i;
}
if ($col['data'] === null || $col['descricao'] === null || $col['valor'] === null) {
    import_finish('danger', 'Faltam colunas obrigatórias: data, descricao e valor. Usa o modelo da página.');
}

$cardMap = import_card_map($pdo, $uid);
$rows = $errors = [];
foreach ($lines as $n => $line) {
    $cells = str_getcsv($line, $delimiter);
    $get = fn($name) => $col[$name] === null ? null : ($cells[$col[$name]] ?? null);
    [$row, $err] = import_row([
        'date' => $get('data'), 'description' => $get('descricao'), 'amount' => $get('valor'),
        'category' => $get('categoria'), 'card' => $get('cartao'),
    ], $cardMap);
    if ($err) $errors[] = 'linha ' . ($n + 2) . ': ' . $err;
    else      $rows[] = $row;
}

try {
    [$imported, $skipped] = import_save($pdo, $uid, $rows, !empty($_POST['skip_duplicates']));
} catch (Throwable $e) {
    error_log('[import_csv] ' . $e->getMessage());
    import_finish('danger', 'A importação falhou e nada foi gravado. Tenta outra vez.');
}

import_finish(...import_summary($imported, $skipped, $errors));
