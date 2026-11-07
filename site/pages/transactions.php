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
                // Nunca abaixo de zero: a compra pode já ter sido paga com "Pagar extrato"
                $stmt = $pdo->prepare("UPDATE cards SET balance = GREATEST(balance - :amount, 0) WHERE id = :card_id AND user_id = :uid");
                $stmt->execute([':amount' => $transaction['amount'], ':card_id' => $transaction['card_id'], ':uid' => $uid]);
            }
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $transactionId, ':uid' => $uid]);
            $pdo->commit();
            $message = t('Transação removida com sucesso!');
            $messageType = 'success';
        } else {
            $pdo->rollBack();
            $message = t('Transação não encontrada.');
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = t('Erro ao remover transação.');
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
        $message     = t('{n} linha(s) importada(s) da fatura com sucesso!', ['n' => $imported]);
        $messageType = 'success';
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[transactions] Erro ao importar fatura: ' . $e->getMessage());
        $message     = t('Erro ao importar fatura. Tenta novamente.');
        $messageType = 'danger';
    }
}

// ── FILTROS ──────────────────────────────────────────────────────────────────
$month    = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
$category = trim($_GET['category'] ?? '');
$card_id  = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;
$q        = trim(mb_substr($_GET['q'] ?? '', 0, 80));

$monthTs   = strtotime($month . '-01');
$prevMonth = date('Y-m', strtotime('-1 month', $monthTs));
$nextMonth = date('Y-m', strtotime('+1 month', $monthTs));
$isCurrent = $month === date('Y-m');
$monthLabel = fmt_month_year($monthTs);

// URL desta página com os filtros atuais, alterando só o que for pedido
function tx_url(array $changes = []): string {
    global $month, $category, $card_id, $q;
    $p = array_merge(['month' => $month, 'category' => $category, 'card_id' => $card_id, 'q' => $q], $changes);
    return 'transactions.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null));
}

$stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :uid AND category IS NOT NULL AND category <> '' ORDER BY category");
$stmt->execute([':uid' => $uid]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// ── CONSULTA (só cartões ativos ou sem cartão) ──────────────────────────────
$sql = "
    SELECT t.*, c.name AS card_name
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid
      AND (t.card_id IS NULL OR c.active = 1)
      AND t.transaction_date >= :from AND t.transaction_date < :to
";
$params = [':uid' => $uid, ':from' => date('Y-m-01', $monthTs), ':to' => $nextMonth . '-01'];
if ($category !== '') { $sql .= " AND t.category = :cat"; $params[':cat'] = $category; }
if ($card_id)         { $sql .= " AND t.card_id = :cid"; $params[':cid'] = $card_id; }
if ($q !== '') {
    // Escapar % e _ para a pesquisa ser literal
    $sql .= " AND t.description LIKE :q";
    $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
}
$sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$total    = array_sum(array_column($transactions, 'amount'));
$count    = count($transactions);
$filtered = $category !== '' || $card_id || $q !== '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$hasAnyTransaction = $stmt->fetchColumn() > 0;

$byDay = [];
foreach ($transactions as $t) {
    $byDay[date('Y-m-d', strtotime($t['transaction_date']))][] = $t;
}
function day_label(string $day): string {
    if ($day === date('Y-m-d')) return t('Hoje');
    if ($day === date('Y-m-d', strtotime('-1 day'))) return t('Ontem');
    $ts = strtotime($day);
    return ucfirst(weekday_name((int) date('w', $ts))) . ', ' . fmt_date_long($ts);
}
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Transações - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=9">
  <style>
    .tx { max-width: 1200px; margin: 0 auto; padding-bottom: 56px; }

    /* Título + navegação por mês */
    .tx-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 6px; }
    .tx-head h1 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .tx-month { display: inline-flex; align-items: center; gap: 4px; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 12px; padding: 4px; }
    .tx-month a, .tx-month span { display: inline-flex; align-items: center; justify-content: center; height: 36px; border-radius: 9px; text-decoration: none; }
    .tx-month a { width: 36px; color: var(--fn-text); }
    .tx-month a:hover { background: var(--fn-paper); }
    .tx-month a[aria-disabled="true"] { opacity: .3; pointer-events: none; }
    .tx-month span { min-width: 150px; padding: 0 8px; font-weight: 700; font-size: 15px; color: var(--fn-text); }
    .tx-month span::first-letter { text-transform: uppercase; }

    .tx-summary { margin: 0 0 22px; font-size: 15px; color: var(--fn-muted); }
    .tx-summary strong { color: var(--fn-text); font-weight: 700; }

    /* Filtros */
    .tx-filters { display: flex; flex-direction: column; gap: 12px; margin-bottom: 26px; }
    .tx-filter-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .tx-search { flex: 1 1 260px; position: relative; }
    .tx-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--fn-muted); pointer-events: none; }
    .main-content .tx-search .form-control { padding-left: 40px; }
    .main-content .tx-card-select { flex: 0 1 220px; }
    .tx-chips { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 2px; scrollbar-width: thin; }
    .tx-chip { display: inline-flex; align-items: center; gap: 7px; height: 36px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--fn-line); background: var(--fn-surface); color: var(--fn-text); font-size: 14px; font-weight: 600; white-space: nowrap; text-decoration: none; }
    .tx-chip:hover { border-color: var(--fn-muted); color: var(--fn-text); }
    .tx-chip .dot { width: 8px; height: 8px; border-radius: 50%; }
    .tx-chip[aria-current="true"] { background: var(--fn-accent); border-color: var(--fn-accent); color: #fff; }
    .tx-chip[aria-current="true"] .dot { box-shadow: 0 0 0 2px #fff; }
    .tx-clear { font-size: 14px; font-weight: 600; color: var(--fn-muted); align-self: center; text-decoration: underline; }

    /* Lista agrupada por dia */
    .tx-day + .tx-day { margin-top: 22px; }
    .tx-day-head { display: flex; justify-content: space-between; align-items: baseline; margin: 0 4px 8px; }
    .tx-day-head h2 { margin: 0; font-size: 15px; font-weight: 700; color: var(--fn-text); }
    .tx-day-head span { font-size: 14px; color: var(--fn-muted); }
    .tx-list { list-style: none; margin: 0; padding: 0; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; }
    .tx-item { display: flex; align-items: center; gap: 14px; padding: 13px 16px 13px 18px; }
    .tx-item + .tx-item { border-top: 1px solid var(--fn-line); }
    .tx-ico { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 17px; flex-shrink: 0; }
    .tx-main { flex: 1; min-width: 0; }
    .tx-desc { font-size: 15px; font-weight: 600; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tx-meta { margin-top: 2px; font-size: 13px; color: var(--fn-muted); display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .tx-tag { font-size: 12px; font-weight: 600; padding: 1px 7px; border-radius: 6px; background: rgba(var(--fn-accent-rgb), .1); color: var(--fn-accent); }
    [data-theme="dark"] .tx-tag { color: #c9d4ef; }
    .tx-amt { font-size: 15px; font-weight: 700; color: var(--fn-text); white-space: nowrap; }
    .tx-actions { display: flex; gap: 2px; }
    .tx-actions a, .tx-actions button {
      width: 34px; height: 34px; display: inline-grid; place-items: center; border-radius: 9px;
      border: 0; background: transparent; color: var(--fn-muted); font-size: 15px; cursor: pointer; text-decoration: none;
    }
    .tx-actions a:hover { background: var(--fn-paper); color: var(--fn-text); }
    .tx-actions button:hover { background: #fef3f2; color: #b42318; }
    [data-theme="dark"] .tx-actions button:hover { background: rgba(253,162,155,.1); color: #fda29b; }
    .tx-actions form { margin: 0; }

    .tx-empty { padding: 48px 24px; text-align: center; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; }
    .tx-empty h2 { margin: 0 0 6px; font-size: 18px; font-weight: 700; color: var(--fn-text); }
    .tx-empty p { margin: 0 auto 18px; max-width: 44ch; color: var(--fn-muted); font-size: 15px; }
    .tx-limit { margin: 16px 4px 0; font-size: 13px; color: var(--fn-muted); }

    @media (max-width: 560px) {
      .tx-head h1 { font-size: 26px; }
      .tx-month { width: 100%; justify-content: space-between; }
      .tx-item { padding: 12px 10px 12px 14px; gap: 12px; }
      .tx-actions a, .tx-actions button { width: 30px; }
      .main-content .tx-card-select { flex: 1 1 100%; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='transactions'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="tx">

  <div class="tx-head">
    <h1><?= te('Transações') ?></h1>
    <nav class="tx-month" aria-label="<?= te('Escolher mês') ?>">
      <a href="<?= htmlspecialchars(tx_url(['month' => $prevMonth])) ?>" aria-label="<?= te('Mês anterior') ?>"><i class="bi bi-chevron-left"></i></a>
      <span aria-live="polite"><?= $monthLabel ?></span>
      <a href="<?= htmlspecialchars(tx_url(['month' => $nextMonth])) ?>" aria-label="<?= te('Mês seguinte') ?>"<?= $isCurrent ? ' aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-right"></i></a>
    </nav>
  </div>

  <p class="tx-summary">
    <?php if ($count > 0): ?>
      <?= t($count === 1 ? '{n} transação · {total} no total · média de {avg}' : '{n} transações · {total} no total · média de {avg}',
            ['n' => '<strong>' . $count . '</strong>', 'total' => '<strong>' . fmt_money($total) . '</strong>', 'avg' => fmt_money($total / $count)]) ?>
    <?php else: ?>
      <?= te('Sem transações neste período.') ?>
    <?php endif; ?>
  </p>

  <?php if ($message): ?>
    <div class="s-alert s-alert-<?= $messageType ?>" role="status">
      <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($hasAnyTransaction): ?>
  <form class="tx-filters" method="get" role="search">
    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
    <div class="tx-filter-row">
      <label class="tx-search">
        <span class="visually-hidden"><?= te('Pesquisar pela descrição') ?></span>
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" placeholder="<?= te('Pesquisar pela descrição') ?>" value="<?= htmlspecialchars($q) ?>">
      </label>
      <label class="tx-card-select">
        <span class="visually-hidden"><?= te('Cartão') ?></span>
        <select name="card_id" class="form-select" onchange="this.form.submit()">
          <option value=""><?= te('Todos os cartões') ?></option>
          <?php foreach ($cards as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $card_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <?php if ($categories): ?>
    <div class="tx-chips" aria-label="<?= te('Filtrar por categoria') ?>">
      <a class="tx-chip" href="<?= htmlspecialchars(tx_url(['category' => ''])) ?>" aria-current="<?= $category === '' ? 'true' : 'false' ?>"><?= te('Todas') ?></a>
      <?php foreach ($categories as $cat): ?>
        <a class="tx-chip" href="<?= htmlspecialchars(tx_url(['category' => $cat])) ?>" aria-current="<?= $category === $cat ? 'true' : 'false' ?>">
          <span class="dot" style="background:<?= category_color($cat) ?>"></span><?= te($cat) ?>
        </a>
      <?php endforeach; ?>
      <?php if ($filtered): ?><a class="tx-clear" href="<?= htmlspecialchars(tx_url(['category' => '', 'card_id' => null, 'q' => ''])) ?>"><?= te('Limpar filtros') ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if (empty($transactions)): ?>
    <div class="tx-empty">
      <?php if (!$hasAnyTransaction): ?>
        <h2><?= te('Ainda não registaste nenhuma transação') ?></h2>
        <p><?= te('Regista os teus gastos para veres para onde vai o dinheiro e acompanhares os orçamentos.') ?></p>
        <a href="create_transaction.php" class="btn-s btn-s-primary"><i class="bi bi-plus-lg"></i> <?= te('Registar uma transação') ?></a>
      <?php elseif ($filtered): ?>
        <h2><?= te('Nenhuma transação com estes filtros') ?></h2>
        <p><?= te('Em {month} não há transações que correspondam à pesquisa ou aos filtros escolhidos.', ['month' => $monthLabel]) ?></p>
        <a href="<?= htmlspecialchars(tx_url(['category' => '', 'card_id' => null, 'q' => ''])) ?>" class="btn-s"><?= te('Limpar filtros') ?></a>
      <?php else: ?>
        <h2><?= te('Sem transações em {month}', ['month' => $monthLabel]) ?></h2>
        <p><?= te('Usa as setas para ver outros meses ou regista um gasto deste mês.') ?></p>
        <a href="create_transaction.php" class="btn-s btn-s-primary"><i class="bi bi-plus-lg"></i> <?= te('Registar uma transação') ?></a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($byDay as $day => $items): ?>
      <section class="tx-day" aria-labelledby="d<?= $day ?>">
        <div class="tx-day-head">
          <h2 id="d<?= $day ?>"><?= day_label($day) ?></h2>
          <span><?= fmt_money(array_sum(array_column($items, 'amount'))) ?></span>
        </div>
        <ul class="tx-list">
          <?php foreach ($items as $t):
            $cat = $t['category'] ?: 'Outros';
            $col = category_color($cat);
          ?>
            <li class="tx-item">
              <span class="tx-ico" style="background:<?= category_tint($cat) ?>;color:<?= $col ?>"><i class="bi bi-<?= category_icon($cat) ?>"></i></span>
              <div class="tx-main">
                <div class="tx-desc"><?= htmlspecialchars($t['description'] ?: t('Sem descrição')) ?></div>
                <div class="tx-meta">
                  <span><?= te($cat) ?></span>·
                  <span><?= $t['card_name'] ? htmlspecialchars($t['card_name']) : te('Dinheiro') ?></span>
                  <?php if (($t['source'] ?? '') === 'invoice'): ?><span class="tx-tag"><?= te('Fatura') ?></span><?php endif; ?>
                </div>
              </div>
              <span class="tx-amt">−<?= fmt_money($t['amount']) ?></span>
              <div class="tx-actions">
                <a href="edit_transaction.php?id=<?= $t['id'] ?>" aria-label="<?= te('Editar {name}', ['name' => $t['description']]) ?>" title="<?= te('Editar') ?>"><i class="bi bi-pencil"></i></a>
                <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Apagar esta transação? Não é possível desfazer.')) ?>)">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                  <button type="submit" aria-label="<?= te('Apagar {name}', ['name' => $t['description']]) ?>" title="<?= te('Apagar') ?>"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
    <?php if ($count >= 200): ?>
      <p class="tx-limit"><?= te('A mostrar as 200 transações mais recentes deste mês. Usa a pesquisa ou os filtros para encontrares as restantes.') ?></p>
    <?php endif; ?>
  <?php endif; ?>

</div><!-- /tx -->
</div><!-- /page-content -->
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
