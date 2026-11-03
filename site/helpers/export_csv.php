<?php
// site/helpers/export_csv.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';

$uid      = $_SESSION['user_id'] ?? null;
$month    = $_GET['month']    ?? '';
$category = $_GET['category'] ?? '';
$card_id  = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;

$sql = "
    SELECT t.transaction_date, t.description, t.category,
           t.amount, c.name AS card_name, t.created_at
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid
";
$params = [':uid' => $uid];

if ($month) {
    $sql .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = :month";
    $params[':month'] = $month;
}
if ($category) {
    $sql .= " AND t.category = :cat";
    $params[':cat'] = $category;
}
if ($card_id) {
    $sql .= " AND t.card_id = :cid";
    $params[':cid'] = $card_id;
}
$sql .= " ORDER BY t.transaction_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proteção contra CSV injection: no Excel/Sheets, uma célula que começa por = + - @ (ou tab/enter)
// é tratada como fórmula — p.ex. =HYPERLINK("https://site-mau","Clica"). O ' à frente diz "isto é texto".
function csv_safe($v): string {
    $v = (string) $v;
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}

$filename = 'finora_transacoes_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, ['data', 'descricao', 'categoria', 'valor', 'cartao', 'criado_em'], ';');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['transaction_date'],
        csv_safe($r['description']),
        csv_safe($r['category'] ?? ''),
        number_format((float)$r['amount'], 2, '.', ''),
        csv_safe($r['card_name'] ?? ''),
        $r['created_at'],
    ], ';');
}

fclose($out);
exit;
