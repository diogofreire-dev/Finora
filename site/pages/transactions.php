<?php
// site/transactions.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

// ── DELETE ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'delete') {
    $transactionId = intval($_POST['transaction_id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT amount, card_id FROM transactions WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $transactionId, ':uid' => $uid]);
        $transaction = $stmt->fetch();
        if ($transaction) {
            if ($transaction['card_id']) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance - :amount WHERE id = :card_id AND user_id = :uid");
                $stmt->execute([':amount' => $transaction['amount'], ':card_id' => $transaction['card_id'], ':uid' => $uid]);
            }
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $transactionId, ':uid' => $uid]);
            $pdo->commit();
            $message = 'Transação removida com sucesso!';
            $messageType = 'success';
        } else {
            $pdo->rollBack();
            $message = 'Transação não encontrada.';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = 'Erro ao remover transação.';
        $messageType = 'danger';
    }
}

// ── IMPORT FROM INVOICE (fatura → transação) ────────────────────────────────
// Quando uma fatura é confirmada/importada, ela chega aqui via POST action=import_invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'import_invoice') {
    $invoice_id  = intval($_POST['invoice_id'] ?? 0);
    $card_id_imp = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
    try {
        $pdo->beginTransaction();
        // Buscar linhas da fatura que ainda não foram importadas
        $stmt = $pdo->prepare("
            SELECT il.* FROM invoice_lines il
            INNER JOIN invoices i ON i.id = il.invoice_id
            WHERE il.invoice_id = :iid AND i.user_id = :uid AND il.imported = 0
        ");
        $stmt->execute([':iid' => $invoice_id, ':uid' => $uid]);
        $lines = $stmt->fetchAll();

        $imported = 0;
        foreach ($lines as $line) {
            $stmtT = $pdo->prepare("
                INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, source, source_id)
                VALUES (:uid, :cid, :amount, :desc, :category, :tdate, 'invoice', :source_id)
            ");
            $stmtT->execute([
                ':uid'       => $uid,
                ':cid'       => $card_id_imp,
                ':amount'    => $line['amount'],
                ':desc'      => $line['description'],
                ':category'  => $line['category'] ?? null,
                ':tdate'     => $line['transaction_date'] ?? date('Y-m-d'),
                ':source_id' => $line['id'],
            ]);
            $newTxId = $pdo->lastInsertId();

            // Actualizar saldo do cartão
            if ($card_id_imp) {
                $stmtC = $pdo->prepare("UPDATE cards SET balance = balance + :amount WHERE id = :cid AND user_id = :uid");
                $stmtC->execute([':amount' => $line['amount'], ':cid' => $card_id_imp, ':uid' => $uid]);
            }

            // Marcar linha como importada e guardar referência à transação
            $stmtU = $pdo->prepare("UPDATE invoice_lines SET imported = 1, transaction_id = :tid WHERE id = :id");
            $stmtU->execute([':tid' => $newTxId, ':id' => $line['id']]);
            $imported++;
        }

        // Marcar fatura como importada
        if ($imported > 0) {
            $stmtI = $pdo->prepare("UPDATE invoices SET imported = 1, imported_at = NOW() WHERE id = :iid AND user_id = :uid");
            $stmtI->execute([':iid' => $invoice_id, ':uid' => $uid]);
        }

        $pdo->commit();
        $message     = "$imported linha(s) importada(s) da fatura com sucesso!";
        $messageType = 'success';
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[transactions] Erro ao importar fatura: ' . $e->getMessage());
        $message     = 'Erro ao importar fatura. Tenta novamente.';
        $messageType = 'danger';
    }
}

// ── FILTERS ─────────────────────────────────────────────────────────────────
$category = $_GET['category'] ?? '';
$card_id  = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;
$month    = $_GET['month'] ?? date('Y-m');

$stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :uid AND category IS NOT NULL ORDER BY category");
$stmt->execute([':uid' => $uid]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// ── QUERY (só cartões activos OU sem cartão; cartões inativos excluídos) ────
$sql = "
    SELECT t.*, c.name AS card_name
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid
      AND (t.card_id IS NULL OR c.active = 1)
";
$params = [':uid' => $uid];
if ($category) { $sql .= " AND t.category = :cat"; $params[':cat'] = $category; }
if ($card_id)  { $sql .= " AND t.card_id = :cid"; $params[':cid'] = $card_id; }
if ($month)    { $sql .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = :month"; $params[':month'] = $month; }
$sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$total = array_sum(array_column($transactions, 'amount'));

$categoryColors = [
    'Compras'        => '#3498db',
    'Alimentação'    => '#e74c3c',
    'Transporte'     => '#f39c12',
    'Saúde'          => '#1abc9c',
    'Entretenimento' => '#9b59b6',
    'Educação'       => '#34495e',
    'Casa'           => '#e67e22',
    'Outros'         => '#95a5a6',
];
$categoryIcons = [
    'Compras'        => 'cart',
    'Alimentação'    => 'cup-straw',
    'Transporte'     => 'bus-front',
    'Saúde'          => 'heart-pulse',
    'Entretenimento' => 'controller',
    'Educação'       => 'book',
    'Casa'           => 'house-door',
    'Outros'         => 'three-dots',
];

$byDay = [];
foreach ($transactions as $t) {
    $day = date('Y-m-d', strtotime($t['transaction_date']));
    $byDay[$day][] = $t;
}

$diasSemana = ['Sunday'=>'Domingo','Monday'=>'Segunda-feira','Tuesday'=>'Terça-feira','Wednesday'=>'Quarta-feira','Thursday'=>'Quinta-feira','Friday'=>'Sexta-feira','Saturday'=>'Sábado'];
$meses = ['January'=>'janeiro','February'=>'fevereiro','March'=>'março','April'=>'abril','May'=>'maio','June'=>'junho','July'=>'julho','August'=>'agosto','September'=>'setembro','October'=>'outubro','November'=>'novembro','December'=>'dezembro'];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Transações - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary); }
    .settings-wrapper { max-width: 820px; margin: 0 auto; padding: 0 16px 60px; }

    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary); }
    .page-header p { font-size: 13px; color: var(--text-secondary); margin: 0; }

    .settings-group { margin-bottom: 24px; }
    .group-label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-secondary); margin: 0 0 8px 4px; }
    .settings-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; }

    .s-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 20px; }
    .s-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .s-row-label { font-size: 14px; font-weight: 500; color: var(--text-primary); margin: 0; }

    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; }
    .btn-s-primary:hover { background: #2980b9; color: #fff; border-color: #2980b9; }
    .btn-s-danger { border-color: #dc2626; color: #dc2626; background: transparent; }
    .btn-s-danger:hover { background: rgba(239,68,68,.07); }

    .s-alert { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
    .s-alert-success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    [data-theme="dark"] .s-alert-success { color: #6ee7b7; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }

    .s-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); }
    .s-badge-invoice { background: rgba(52,152,219,.12); border-color: rgba(52,152,219,.3); color: #2980b9; }

    .empty-state { padding: 48px 20px; text-align: center; }
    .empty-state i { font-size: 48px; color: var(--border-color); display: block; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; color: var(--text-secondary); margin-bottom: 16px; }

    .t-block { padding: 13px 20px; }
    .t-block:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .t-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }

    .filter-body { padding: 16px 20px; }
    .m-form label { font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; display: block; }
    .m-form .form-control, .m-form .form-select { font-size: 14px; border: 1px solid var(--border-color); border-radius: 9px; padding: 8px 12px; background: var(--bg-primary); color: var(--text-primary); width: 100%; }
    .m-form .form-control:focus, .m-form .form-select:focus { border-color: #3498db; box-shadow: none; outline: none; }
    [data-theme="dark"] .m-form .form-control,
    [data-theme="dark"] .m-form .form-select { background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color); }

    .filter-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
    @media (max-width: 640px) { .filter-grid { grid-template-columns: 1fr 1fr; } .filter-actions { grid-column: 1 / -1; } }
    @media (max-width: 380px)  { .filter-grid { grid-template-columns: 1fr; } }
    @media (max-width: 500px)  { .t-amount-col { flex-direction: column; align-items: flex-end; gap: 6px; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='transactions'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-receipt"></i></div>
    <div style="flex:1">
      <h2>As Minhas Transações</h2>
      <p>Histórico completo de despesas</p>
    </div>
    <a href="create_transaction.php" class="btn-s btn-s-primary">
      <i class="bi bi-plus-circle"></i> Nova
    </a>
  </div>

  <?php if ($message): ?>
    <div class="s-alert s-alert-<?=$messageType?>">
      <i class="bi bi-<?=$messageType==='success'?'check-circle':'exclamation-circle'?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?=htmlspecialchars($message)?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($transactions)): ?>
  <div class="settings-group">
    <p class="group-label">Resumo do período</p>
    <div class="settings-card">
      <div class="s-row">
        <p class="s-row-label">Transações</p>
        <span style="font-size:15px;font-weight:700;color:var(--text-primary)"><?=count($transactions)?></span>
      </div>
      <div class="s-row">
        <p class="s-row-label">Total gasto</p>
        <span style="font-size:15px;font-weight:700;color:#e74c3c">€<?=number_format($total,2)?></span>
      </div>
      <div class="s-row">
        <p class="s-row-label">Média por transação</p>
        <span style="font-size:15px;font-weight:700;color:#3498db">€<?=count($transactions)>0?number_format($total/count($transactions),2):'0.00'?></span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="settings-group">
    <p class="group-label">Filtros</p>
    <div class="settings-card">
      <div class="filter-body">
        <form method="get" class="m-form">
          <div class="filter-grid">
            <div>
              <label><i class="bi bi-calendar"></i> Mês</label>
              <input type="month" name="month" class="form-control" value="<?=htmlspecialchars($month)?>">
            </div>
            <div>
              <label><i class="bi bi-tag"></i> Categoria</label>
              <select name="category" class="form-select">
                <option value="">Todas</option>
                <?php foreach($categories as $cat): ?>
                  <option value="<?=htmlspecialchars($cat)?>" <?=$category===$cat?'selected':''?>><?=htmlspecialchars($cat)?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label><i class="bi bi-credit-card"></i> Cartão</label>
              <select name="card_id" class="form-select">
                <option value="">Todos</option>
                <?php foreach($cards as $c): ?>
                  <option value="<?=$c['id']?>" <?=$card_id==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-actions" style="display:flex;gap:6px">
              <button type="submit" class="btn-s btn-s-primary" style="padding:8px 14px"><i class="bi bi-search"></i> Filtrar</button>
              <a href="transactions.php" class="btn-s" style="padding:8px 12px"><i class="bi bi-x-lg"></i></a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if (empty($transactions)): ?>
    <div class="settings-card">
      <div class="empty-state">
        <i class="bi bi-receipt"></i>
        <p>Nenhuma transação encontrada.<br>Altera os filtros ou cria a tua primeira transação.</p>
        <a href="create_transaction.php" class="btn-s btn-s-primary"><i class="bi bi-plus-circle"></i> Criar Transação</a>
      </div>
    </div>
  <?php else: ?>
    <?php foreach($byDay as $day => $dayTransactions):
      $dayObj    = new DateTime($day);
      $today     = new DateTime();
      $yesterday = (new DateTime())->modify('-1 day');
      if ($dayObj->format('Y-m-d') === $today->format('Y-m-d'))          { $dayLabel = 'Hoje'; }
      elseif ($dayObj->format('Y-m-d') === $yesterday->format('Y-m-d'))  { $dayLabel = 'Ontem'; }
      else { $dayLabel = $diasSemana[$dayObj->format('l')].', '.$dayObj->format('d').' de '.$meses[$dayObj->format('F')]; }
      $dayTotal = array_sum(array_column($dayTransactions, 'amount'));
    ?>
    <div class="settings-group">
      <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 8px 4px">
        <p class="group-label" style="margin:0"><?=htmlspecialchars($dayLabel)?></p>
        <span style="font-size:12px;font-weight:700;color:#e74c3c">-€<?=number_format($dayTotal,2)?></span>
      </div>
      <div class="settings-card">
        <?php foreach($dayTransactions as $t): ?>
        <div class="t-block">
          <div style="display:flex;align-items:center;gap:12px">
            <?php
              $cat   = $t['category'] ?? '';
              $tColor = $categoryColors[$cat] ?? '#95a5a6';
              $tIcon  = $categoryIcons[$cat]  ?? 'receipt';
            ?>
            <div class="t-icon" style="background:<?=$tColor?>1a;color:<?=$tColor?>;border:1px solid <?=$tColor?>33">
              <i class="bi bi-<?=$tIcon?>"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:14px;font-weight:500;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?=htmlspecialchars($t['description'])?>
              </div>
              <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:4px">
                <?php if($t['category']): ?>
                  <span class="s-badge" style="background:<?=$tColor?>15;border-color:<?=$tColor?>30;color:<?=$tColor?>"><i class="bi bi-tag" style="font-size:10px"></i> <?=htmlspecialchars($t['category'])?></span>
                <?php endif; ?>
                <?php if($t['card_name']): ?>
                  <span class="s-badge"><i class="bi bi-credit-card" style="font-size:10px"></i> <?=htmlspecialchars($t['card_name'])?></span>
                <?php else: ?>
                  <span class="s-badge"><i class="bi bi-cash" style="font-size:10px"></i> Dinheiro</span>
                <?php endif; ?>
                <?php if(!empty($t['source']) && $t['source'] === 'invoice'): ?>
                  <span class="s-badge s-badge-invoice"><i class="bi bi-file-earmark-text" style="font-size:10px"></i> Fatura</span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:7px;flex-shrink:0" class="t-amount-col">
              <span style="font-size:15px;font-weight:700;color:#e74c3c">-€<?=number_format($t['amount'],2)?></span>
              <div style="display:flex;gap:5px">
                <a href="edit_transaction.php?id=<?=$t['id']?>" class="btn-s" style="padding:4px 10px;font-size:12px">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="post" style="margin:0" onsubmit="return confirm('Remover esta transação? Esta ação não pode ser revertida.')"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="transaction_id" value="<?=$t['id']?>">
                  <button class="btn-s btn-s-danger" style="padding:4px 10px;font-size:12px" type="submit">
                    <i class="bi bi-trash3"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div><!-- /settings-wrapper -->
</div><!-- /page-content -->
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>