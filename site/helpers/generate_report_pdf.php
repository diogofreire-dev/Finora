<?php
// site/helpers/generate_report_pdf.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';

$uid = $_SESSION['user_id'] ?? null;

// Parâmetros
$type = $_GET['type'] ?? 'full';
$category = $_GET['category'] ?? '';
$card_id = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;
$month = $_GET['month'] ?? '';
$includeCards = isset($_GET['include_cards']);
$includeStats = isset($_GET['include_stats']);

// Buscar utilizador
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = :uid");
$stmt->execute([':uid' => $uid]);
$user = $stmt->fetch();

// Buscar transações
$sql = "
    SELECT t.*, c.name AS card_name
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid
";
$params = [':uid' => $uid];

if ($category) {
    $sql .= " AND t.category = :cat";
    $params[':cat'] = $category;
}
if ($card_id) {
    $sql .= " AND t.card_id = :cid";
    $params[':cid'] = $card_id;
}
if ($month) {
    $sql .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = :month";
    $params[':month'] = $month;
}

$sql .= " ORDER BY t.transaction_date DESC";
if ($type === 'summary') {
    $sql .= " LIMIT 20";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Buscar cartões
$cards = [];
if ($includeCards) {
    $stmt = $pdo->prepare("
        SELECT c.name, c.limit_amount, c.balance, c.active
        FROM cards c
        WHERE c.user_id = :uid
        ORDER BY c.active DESC, c.name
    ");
    $stmt->execute([':uid' => $uid]);
    $cards = $stmt->fetchAll();
}

// Estatísticas
$totalAmount = array_sum(array_column($transactions, 'amount'));
$transactionCount = count($transactions);
$avgAmount = $transactionCount > 0 ? $totalAmount / $transactionCount : 0;

// Stats por categoria
$categoryStats = [];
if ($includeStats) {
    foreach ($transactions as $t) {
        $cat = $t['category'] ?? 'Sem Categoria';
        if (!isset($categoryStats[$cat])) {
            $categoryStats[$cat] = ['total' => 0, 'count' => 0];
        }
        $categoryStats[$cat]['total'] += $t['amount'];
        $categoryStats[$cat]['count']++;
    }
    arsort($categoryStats);
}

$reportTitle = $type === 'full' ? 'Relatório Completo' : 'Relatório Resumido';
$filename = 'finora-relatorio-' . date('Y-m-d') . '.pdf';

$monthLabel = $month ? date('m/Y', strtotime($month . '-01')) : 'Todos os meses';
$filters = [];
if ($month) $filters[] = 'Mês: ' . date('m/Y', strtotime($month . '-01'));
if ($category) $filters[] = 'Categoria: ' . htmlspecialchars($category);
if ($card_id) {
    foreach ($transactions as $t) {
        if ($t['card_id'] == $card_id && $t['card_name']) {
            $filters[] = 'Cartão: ' . htmlspecialchars($t['card_name']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($reportTitle) ?> - Finora</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1f2937;
            background: #f3f4f6;
            padding: 70px 20px 30px;
        }

        .page {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            padding: 28px;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        /* ===== CABEÇALHO ===== */
        .header {
            background: #212121;
            color: #fff;
            padding: 18px 24px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .header h1 { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .header .subtitle { font-size: 10px; opacity: 0.75; margin-top: 2px; }
        .header .report-type { font-size: 13px; font-weight: bold; margin-top: 8px; }
        .header .date { font-size: 9px; opacity: 0.65; margin-top: 2px; }

        /* ===== UTILIZADOR ===== */
        .user-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
            margin-bottom: 16px;
        }
        .user-box .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .user-box .name { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .user-box .filters { font-size: 9px; color: #6b7280; margin-top: 4px; font-style: italic; }

        /* ===== RESUMO ===== */
        .summary-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #1f2937;
        }
        .summary-boxes {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }
        .summary-box {
            flex: 1;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .summary-box .box-label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .summary-box .box-value { font-size: 15px; font-weight: bold; margin-top: 4px; }

        /* ===== SECÇÕES ===== */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 16px 0 6px;
            padding-bottom: 4px;
            border-bottom: 2px solid #212121;
            color: #1f2937;
        }

        /* ===== TABELAS ===== */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 6px;
        }
        thead th {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 5px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tbody td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
        }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-active {
            background: #dcfce7; color: #166534;
            padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: bold;
        }
        .badge-inactive {
            background: #fee2e2; color: #991b1b;
            padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: bold;
        }

        /* ===== RODAPÉ ===== */
        .footer {
            margin-top: 24px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #9ca3af;
        }

        /* ===== BOTÕES (não aparecem no PDF) ===== */
        .print-btn-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .print-btn-bar .bar-title {
            color: #fff;
            font-size: 13px;
            font-weight: bold;
            opacity: 0.85;
        }
        .bar-actions { display: flex; gap: 8px; }
        .btn-print {
            background: #fff;
            color: #1a1a1a;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-print:hover { background: #e5e7eb; }
        .btn-close {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.35);
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-close:hover { background: rgba(255,255,255,0.1); }

        /* ===== PRINT MEDIA ===== */
        @media print {
            body { padding: 0; background: #fff; }
            .print-btn-bar { display: none !important; }
            .page { box-shadow: none; border-radius: 0; padding: 0; max-width: 100%; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
            .section-title { page-break-after: avoid; }
        }

        @page {
            size: A4;
            margin: 15mm 12mm;
        }
    </style>
</head>
<body>

<!-- Barra de botões (só visível no browser, não no PDF) -->
<div class="print-btn-bar">
    <span class="bar-title">Pré-visualização do Relatório</span>
    <div class="bar-actions">
        <button class="btn-print" onclick="window.print()">Guardar / Imprimir PDF</button>
        <button class="btn-close" onclick="history.length > 1 ? history.back() : window.location.href='../pages/dados.php'">✕ Fechar</button>
    </div>
</div>

<div class="page">

<!-- CABEÇALHO -->
<div class="header">
    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h1>Finora</h1>
            <div class="subtitle">Gestão Financeira Pessoal</div>
        </div>
        <div style="text-align:right;">
            <div class="report-type"><?= htmlspecialchars($reportTitle) ?></div>
            <div class="date">Gerado em <?= date('d/m/Y H:i') ?></div>
        </div>
    </div>
</div>

<!-- UTILIZADOR -->
<div class="user-box">
    <div class="label">Utilizador</div>
    <div class="name"><?= htmlspecialchars($user['username']) ?> &lt;<?= htmlspecialchars($user['email']) ?>&gt;</div>
    <?php if (!empty($filters)): ?>
        <div class="filters">Filtros: <?= implode(' | ', $filters) ?></div>
    <?php endif; ?>
</div>

<!-- RESUMO -->
<div class="summary-title">Resumo</div>
<div class="summary-boxes">
    <div class="summary-box">
        <div class="box-label">Total Gasto</div>
        <div class="box-value"><?= fmt_money($totalAmount) ?></div>
    </div>
    <div class="summary-box">
        <div class="box-label">Transações</div>
        <div class="box-value"><?= $transactionCount ?></div>
    </div>
    <div class="summary-box">
        <div class="box-label">Média</div>
        <div class="box-value"><?= fmt_money($avgAmount) ?></div>
    </div>
</div>

<?php if ($includeCards && !empty($cards)): ?>
<!-- CARTÕES -->
<div class="section-title">Cartões</div>
<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>Estado</th>
            <th class="text-right">Gasto / Limite</th>
            <th class="text-right">Disponível</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cards as $card): ?>
            <?php $available = $card['limit_amount'] - $card['balance']; ?>
            <tr>
                <td><?= htmlspecialchars($card['name']) ?></td>
                <td class="text-center">
                    <?php if ($card['active']): ?>
                        <span class="badge-active">ATIVO</span>
                    <?php else: ?>
                        <span class="badge-inactive">INATIVO</span>
                    <?php endif; ?>
                </td>
                <td class="text-right"><?= fmt_money($card['balance']) ?> / <?= fmt_money($card['limit_amount']) ?></td>
                <td class="text-right"><?= fmt_money($available) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($includeStats && !empty($categoryStats)): ?>
<!-- CATEGORIAS -->
<div class="section-title">Gastos por Categoria</div>
<table>
    <thead>
        <tr>
            <th>Categoria</th>
            <th class="text-center">Transações</th>
            <th class="text-right">Total</th>
            <th class="text-right">%</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($categoryStats as $cat => $stats): ?>
            <?php $pct = $totalAmount > 0 ? ($stats['total'] / $totalAmount) * 100 : 0; ?>
            <tr>
                <td><?= htmlspecialchars($cat) ?></td>
                <td class="text-center"><?= $stats['count'] ?></td>
                <td class="text-right"><?= fmt_money($stats['total']) ?></td>
                <td class="text-right"><?= number_format($pct, 1) ?>%</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($transactions)): ?>
<!-- TRANSAÇÕES -->
<div class="section-title">
    <?= $type === 'summary' ? 'Últimas 20 Transações' : 'Transações' ?>
</div>
<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Descrição</th>
            <th>Categoria</th>
            <th>Cartão</th>
            <th class="text-right">Valor</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td class="text-center"><?= date('d/m/Y', strtotime($t['transaction_date'])) ?></td>
                <td><?= htmlspecialchars(mb_substr($t['description'], 0, 40, 'UTF-8')) ?></td>
                <td><?= htmlspecialchars($t['category'] ?? '-') ?></td>
                <td><?= htmlspecialchars($t['card_name'] ?? 'Dinheiro') ?></td>
                <td class="text-right"><?= fmt_money($t['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- RODAPÉ -->
<div class="footer">
    <span>Finora — Gestão Financeira Pessoal</span>
    <span>Gerado em <?= date('d/m/Y \à\s H:i') ?></span>
</div>

</div><!-- /page -->

<script>
// Auto-abre o diálogo de impressão (opcional — comenta se não quiseres)
// window.addEventListener('load', () => setTimeout(() => window.print(), 500));
</script>
</body>
</html>