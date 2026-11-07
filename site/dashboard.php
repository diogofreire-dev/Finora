<?php
// site/dashboard.php
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/budget_alert_helper.php';
require_once __DIR__ . '/helpers/banks.php';
require_once __DIR__ . '/helpers/format.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

if (!$uid) { header('Location: auth/login.php'); exit; }

// Emails de alerta de orçamento: só com as notificações ligadas e a opção "Também por email" ativa
require_once __DIR__ . '/helpers/notifications.php';
$notifPrefs = notif_prefs($pdo, (int) $uid);
if ($notifPrefs['_on'] && $notifPrefs['budget_email']) {
    $userStmt = $pdo->prepare("SELECT email, username FROM users WHERE id = :uid");
    $userStmt->execute([':uid' => $uid]);
    $user = $userStmt->fetch();
    if ($user) { checkAndSendBudgetAlerts($pdo, $uid, $user['email'], $user['username']); }
}

$stmt = $pdo->prepare("SELECT COALESCE(category,'Sem Categoria') as category, SUM(amount) as total, COUNT(*) as count FROM transactions WHERE user_id=:uid AND transaction_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01') GROUP BY category ORDER BY total DESC");
$stmt->execute([':uid'=>$uid]); $categoryData = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.*, c.name AS card_name FROM transactions t LEFT JOIN cards c ON c.id=t.card_id WHERE t.user_id=:uid ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT 6");
$stmt->execute([':uid'=>$uid]); $recent = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT c.id,c.name,c.color,c.bank,c.limit_amount,c.balance,c.active,c.created_at, COALESCE(SUM(t.amount),0) as total_spent,COUNT(t.id) as transaction_count FROM cards c LEFT JOIN transactions t ON t.card_id=c.id WHERE c.user_id=:uid GROUP BY c.id ORDER BY c.active DESC,c.created_at DESC");
$stmt->execute([':uid'=>$uid]); $cards = $stmt->fetchAll();

$cardColors = [
    'purple'=>'linear-gradient(135deg,#667eea 0%,#764ba2 100%)',
    'blue'  =>'linear-gradient(135deg,#2196F3 0%,#1565C0 100%)',
    'green' =>'linear-gradient(135deg,#13d168 0%,#0f9c4e 100%)',
    'orange'=>'linear-gradient(135deg,#FF9800 0%,#E65100 100%)',
    'red'   =>'linear-gradient(135deg,#f44336 0%,#c62828 100%)',
    'pink'  =>'linear-gradient(135deg,#E91E63 0%,#880E4F 100%)',
    'teal'  =>'linear-gradient(135deg,#00BCD4 0%,#006064 100%)',
    'indigo'=>'linear-gradient(135deg,#3F51B5 0%,#1A237E 100%)'
];

$stmt = $pdo->prepare("SELECT b.*, COALESCE(SUM(CASE WHEN b.period='monthly' AND t.transaction_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND t.transaction_date<=LAST_DAY(CURDATE()) THEN t.amount WHEN b.period='weekly' AND t.transaction_date>=DATE_SUB(CURDATE(),INTERVAL WEEKDAY(CURDATE()) DAY) AND t.transaction_date<=CURDATE() THEN t.amount WHEN b.period='yearly' AND YEAR(t.transaction_date)=YEAR(CURDATE()) THEN t.amount ELSE 0 END),0) as current_spent FROM budgets b LEFT JOIN transactions t ON t.user_id=b.user_id AND (b.category IS NULL OR t.category=b.category) AND (b.card_id IS NULL OR t.card_id=b.card_id) WHERE b.user_id=:uid AND b.active=1 GROUP BY b.id ORDER BY b.created_at DESC LIMIT 1");
$stmt->execute([':uid'=>$uid]); $mainBudget=$stmt->fetch();

$budgetPercentage = 0;
if ($mainBudget && $mainBudget['amount'] > 0) {
    $budgetPercentage = ($mainBudget['current_spent'] / $mainBudget['amount']) * 100;
}

// Cores das categorias: helpers/categories.php (partilhado com as Transações)
$catColor = 'category_color';

$stmt = $pdo->prepare("SELECT r.*, c.name as card_name, CASE WHEN r.due_date<CURDATE() THEN 'overdue' WHEN r.due_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 'upcoming' ELSE 'future' END as status, DATEDIFF(r.due_date,CURDATE()) as days_until FROM payment_reminders r LEFT JOIN cards c ON c.id=r.card_id WHERE r.user_id=:uid AND r.active=1 AND r.due_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) ORDER BY r.due_date ASC LIMIT 5");
$stmt->execute([':uid'=>$uid]); $upcomingReminders=$stmt->fetchAll();
$hasOverdue = (bool) array_filter($upcomingReminders, fn($r) => $r['status'] === 'overdue');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM budgets WHERE user_id=:uid AND active=1");
$stmt->execute([':uid'=>$uid]); $activeBudgetsCount=$stmt->fetchColumn();

// ── Gasto acumulado por dia: este mês e o mês anterior (para o gráfico e a comparação) ──
function dailyTotals(PDO $pdo, int $uid, string $from, string $to): array {
    $s = $pdo->prepare("SELECT DAY(transaction_date) d, SUM(amount) t FROM transactions
                        WHERE user_id = :uid AND transaction_date >= :f AND transaction_date < :t GROUP BY d");
    $s->execute([':uid' => $uid, ':f' => $from, ':t' => $to]);
    return array_column($s->fetchAll(), 't', 'd');
}
function cumulative(array $daily, int $days): array {
    $out = []; $sum = 0;
    for ($d = 1; $d <= $days; $d++) { $sum += (float) ($daily[$d] ?? 0); $out[$d] = $sum; }
    return $out;
}
$today       = (int) date('j');
$daysInMonth = (int) date('t');
$thisStart   = date('Y-m-01');
$prevStart   = date('Y-m-01', strtotime('first day of last month'));
$prevDays    = (int) date('t', strtotime($prevStart));
$thisCum     = cumulative(dailyTotals($pdo, $uid, $thisStart, date('Y-m-01', strtotime('first day of next month'))), $today);
$prevCum     = cumulative(dailyTotals($pdo, $uid, $prevStart, $thisStart), $prevDays);

$monthName    = month_name_pt((int) date('n'));
$prevName     = month_name_pt((int) date('n', strtotime($prevStart)));
$spentToDate  = $thisCum[$today] ?? 0;
$prevToDate   = $prevCum[min($today, $prevDays)] ?? 0;   // mesmo ponto do mês anterior
$diffToDate   = $spentToDate - $prevToDate;

// Gráfico SVG (sem bibliotecas): x = dia do mês, y = gasto acumulado
$chartW = 360; $chartH = 120; $chartPad = 6;
$chartMax = max($prevCum ? max($prevCum) : 0, $spentToDate, 1);
$xAt = fn($d) => round($chartPad + ($d - 1) / max($daysInMonth - 1, 1) * ($chartW - 2 * $chartPad), 1);
$yAt = fn($v) => round($chartH - $chartPad - $v / $chartMax * ($chartH - 2 * $chartPad), 1);
$linePath = function (array $cum, int $upTo) use ($xAt, $yAt) {
    $pts = [];
    foreach ($cum as $d => $v) { if ($d > $upTo) break; $pts[] = $xAt($d) . ',' . $yAt($v); }
    return $pts ? 'M' . implode(' L', $pts) : '';
};
$prevPath = $linePath($prevCum, $daysInMonth);
$thisPath = $linePath($thisCum, $today);

$categoryTotal = array_sum(array_column($categoryData, 'total'));
$activeCards   = array_values(array_filter($cards, fn($c) => $c['active']));
$firstName     = explode(' ', trim($_SESSION['username'] ?? ''))[0] ?: t('olá');
$hour          = (int) date('G');
$greeting      = $hour < 12 ? t('Bom dia') : ($hour < 20 ? t('Boa tarde') : t('Boa noite'));
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Dashboard') ?> - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="css/theme.css">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="stylesheet" href="css/sidebar.css?v=7">
  <style>
    body { font-family: var(--fn-font-ui); color: var(--fn-text); }
    .dsh { display: flex; flex-direction: column; gap: 28px; padding-bottom: 48px; font-variant-numeric: tabular-nums; }

    /* ── Topo: o mês numa frase + gráfico acumulado ── */
    .dsh-hero {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 380px;
      gap: 32px;
      align-items: end;
      padding: 28px 32px;
      background: var(--fn-surface);
      border: 1px solid var(--fn-line);
      border-radius: 20px;
    }
    .dsh-greet { margin: 0 0 10px; font-size: 15px; color: var(--fn-muted); }
    .dsh-headline {
      margin: 0;
      font-family: var(--fn-font-brand);
      font-weight: 700;
      font-size: clamp(28px, 3.4vw, 42px);
      line-height: 1.12;
      letter-spacing: -0.8px;
      color: var(--fn-ink);
      max-width: 22ch;
    }
    .dsh-compare { margin: 14px 0 0; font-size: 15px; color: var(--fn-muted); max-width: 52ch; }
    .dsh-compare strong { color: var(--fn-text); font-weight: 600; }
    .dsh-hero-cta {
      display: inline-flex; align-items: center; gap: 8px; margin-top: 18px;
      height: 42px; padding: 0 18px; border-radius: 10px;
      background: var(--fn-ink); color: #fff; font-weight: 600; font-size: 14px; text-decoration: none;
    }
    .dsh-hero-cta:hover { color: #fff; background: #22325a; }
    [data-theme="dark"] .dsh-hero-cta { background: var(--fn-gold); color: #14213d; }

    .dsh-chart svg { display: block; width: 100%; height: auto; overflow: visible; }
    .dsh-chart .prev { fill: none; stroke: var(--fn-muted); stroke-width: 1.5; stroke-dasharray: 3 4; opacity: .7; }
    .dsh-chart .this { fill: none; stroke: var(--fn-gold); stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
    .dsh-chart .dot  { fill: var(--fn-gold); stroke: var(--fn-surface); stroke-width: 3; }
    .dsh-chart .base { stroke: var(--fn-line); stroke-width: 1; }
    .dsh-legend { display: flex; gap: 16px; margin-top: 10px; font-size: 13px; color: var(--fn-muted); }
    .dsh-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .dsh-legend i { display: inline-block; width: 16px; height: 0; border-top: 3px solid var(--fn-gold); }
    .dsh-legend i.prev { border-top: 2px dashed var(--fn-muted); }

    /* ── Grelha principal ── */
    .dsh-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 24px; align-items: start; }
    .dsh-col { display: flex; flex-direction: column; gap: 24px; min-width: 0; }

    .dsh-panel { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; }
    .dsh-panel-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 18px 20px 4px; }
    .dsh-panel-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--fn-text); }
    .dsh-panel-head a { font-size: 14px; font-weight: 600; color: var(--fn-muted); text-decoration: none; white-space: nowrap; }
    .dsh-panel-head a:hover { color: var(--fn-ink); text-decoration: underline; }
    .dsh-panel-body { padding: 12px 20px 20px; }

    .dsh-empty { padding: 8px 20px 24px; margin: 0; color: var(--fn-muted); font-size: 14px; }
    .dsh-empty a { color: var(--fn-ink); font-weight: 600; }

    /* Categorias: uma barra proporcional + legenda */
    .dsh-catbar { display: flex; height: 14px; border-radius: 7px; overflow: hidden; gap: 2px; background: var(--fn-paper); }
    .dsh-catbar span { display: block; height: 100%; }
    .dsh-catlist { list-style: none; margin: 16px 0 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; }
    .dsh-catlist li { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: 14px; min-width: 0; }
    .dsh-catlist .sw { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .dsh-catlist .nm { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--fn-text); }
    .dsh-catlist .pc { color: var(--fn-muted); font-size: 13px; }
    .dsh-catlist .am { font-weight: 600; color: var(--fn-text); }

    /* Linhas (transações, cartões, pagamentos) */
    .dsh-rows { list-style: none; margin: 0; padding: 4px 0 8px; }
    .dsh-row { display: flex; align-items: center; gap: 14px; padding: 11px 20px; }
    .dsh-row + .dsh-row { border-top: 1px solid var(--fn-line); }
    .dsh-row .main { flex: 1; min-width: 0; }
    .dsh-row .t1 { font-size: 15px; font-weight: 600; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dsh-row .t2 { font-size: 13px; color: var(--fn-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dsh-row .amt { font-size: 15px; font-weight: 700; color: var(--fn-text); white-space: nowrap; }
    .dsh-cat-ico { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font-size: 16px; flex-shrink: 0; }

    .dsh-minicard { width: 46px; height: 30px; border-radius: 6px; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(255,255,255,.18); }
    .dsh-meter { height: 6px; border-radius: 3px; background: var(--fn-paper); overflow: hidden; margin: 7px 0 5px; }
    .dsh-meter span { display: block; height: 100%; border-radius: 3px; background: var(--fn-ink); }
    [data-theme="dark"] .dsh-meter span { background: var(--fn-gold); }
    .dsh-meter span.warn { background: #d97706; }
    .dsh-meter span.over { background: #c2410c; }
    .dsh-flag { font-size: 12px; font-weight: 700; padding: 2px 8px; border-radius: 6px; white-space: nowrap; }
    .dsh-flag.warn { background: #fef3c7; color: #92400e; }
    .dsh-flag.over { background: #ffedd5; color: #9a3412; }
    [data-theme="dark"] .dsh-flag.warn { background: rgba(217,119,6,.18); color: #fcd34d; }
    [data-theme="dark"] .dsh-flag.over { background: rgba(194,65,12,.22); color: #fdba74; }

    .dsh-date { width: 46px; text-align: center; flex-shrink: 0; border-radius: 10px; padding: 5px 0; background: var(--fn-paper); }
    .dsh-date b { display: block; font-size: 17px; line-height: 1.1; color: var(--fn-text); }
    .dsh-date small { font-size: 11px; color: var(--fn-muted); }
    .dsh-date.over { background: #ffedd5; } .dsh-date.over b, .dsh-date.over small { color: #9a3412; }
    [data-theme="dark"] .dsh-date.over { background: rgba(194,65,12,.22); }
    [data-theme="dark"] .dsh-date.over b, [data-theme="dark"] .dsh-date.over small { color: #fdba74; }

    .dsh-budget-top { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .dsh-budget-top b { font-size: 26px; font-family: var(--fn-font-brand); color: var(--fn-ink); }
    .dsh-budget-top span { font-size: 14px; color: var(--fn-muted); }
    .dsh-budget .dsh-meter { height: 10px; border-radius: 5px; margin: 12px 0 8px; }
    .dsh-note { font-size: 14px; color: var(--fn-muted); margin: 0; }
    .dsh-note.over { color: #c2410c; font-weight: 600; }
    [data-theme="dark"] .dsh-note.over { color: #fdba74; }

    @media (max-width: 1100px) {
      .dsh-hero { grid-template-columns: 1fr; }
      .dsh-chart { max-width: 520px; }
    }
    @media (max-width: 900px) {
      .dsh-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
      .dsh-hero { padding: 22px 20px; border-radius: 16px; }
      .dsh-catlist { grid-template-columns: 1fr; }
      .dsh-row { padding: 11px 16px; }
      .dsh-panel-head, .dsh-panel-body { padding-left: 16px; padding-right: 16px; }
    }
  </style>
</head>
<body>

<div class="app-layout">
<?php $activePage='dashboard'; $sidebarPrefix=''; include __DIR__.'/components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="dsh">

  <!-- ── O mês numa frase ── -->
  <section class="dsh-hero" aria-labelledby="dshHeadline">
    <div>
      <p class="dsh-greet"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?></p>
      <?php if ($spentToDate > 0): ?>
        <h1 class="dsh-headline" id="dshHeadline"><?= te('Em {month} já gastaste {amount}', ['month' => $monthName, 'amount' => fmt_money($spentToDate)]) ?></h1>
        <p class="dsh-compare">
          <?php if ($prevToDate == 0): ?>
            <?= te('Até dia {day} de {month} não tinhas gastos registados.', ['day' => $today, 'month' => $prevName]) ?>
          <?php elseif (abs($diffToDate) < 0.005): ?>
            <?= te('Exatamente o mesmo que até dia {day} de {month}.', ['day' => $today, 'month' => $prevName]) ?>
          <?php else: ?>
            <strong><?= te($diffToDate > 0 ? '{amount} mais' : '{amount} menos', ['amount' => fmt_money(abs($diffToDate))]) ?></strong>
            <?= te('do que até dia {day} de {month}.', ['day' => $today, 'month' => $prevName]) ?>
          <?php endif; ?>
        </p>
      <?php else: ?>
        <h1 class="dsh-headline" id="dshHeadline"><?= te('Ainda não registaste gastos em {month}', ['month' => $monthName]) ?></h1>
        <p class="dsh-compare"><?= te('Regista o que vais gastando para veres aqui como corre o mês em comparação com {month}.', ['month' => $prevName]) ?></p>
        <a href="pages/create_transaction.php" class="dsh-hero-cta"><i class="bi bi-plus-lg"></i> <?= te('Registar uma transação') ?></a>
      <?php endif; ?>
    </div>

    <figure class="dsh-chart" style="margin:0">
      <svg viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" role="img"
           aria-label="<?= te('Gasto acumulado: {amount} até dia {day} de {month}, contra {prev} no mesmo ponto de {prevMonth}', ['amount' => fmt_money($spentToDate), 'day' => $today, 'month' => $monthName, 'prev' => fmt_money($prevToDate), 'prevMonth' => $prevName]) ?>">
        <line class="base" x1="0" y1="<?= $chartH - $chartPad ?>" x2="<?= $chartW ?>" y2="<?= $chartH - $chartPad ?>"/>
        <?php if ($prevPath): ?><path class="prev" d="<?= $prevPath ?>"/><?php endif; ?>
        <?php if ($thisPath): ?>
          <path class="this" d="<?= $thisPath ?>"/>
          <circle class="dot" cx="<?= $xAt($today) ?>" cy="<?= $yAt($spentToDate) ?>" r="6"/>
        <?php endif; ?>
      </svg>
      <figcaption class="dsh-legend">
        <span><i></i> <?= ucfirst($monthName) ?></span>
        <span><i class="prev"></i> <?= ucfirst($prevName) ?></span>
      </figcaption>
    </figure>
  </section>

  <div class="dsh-grid">

    <!-- ════ Coluna principal ════ -->
    <div class="dsh-col">

      <section class="dsh-panel" id="dshCategorias" aria-labelledby="hCat">
        <div class="dsh-panel-head">
          <h2 id="hCat"><?= te('Para onde foi o dinheiro') ?></h2>
          <a href="pages/analytics.php"><?= te('Ver análise') ?></a>
        </div>
        <?php if (empty($categoryData)): ?>
          <p class="dsh-empty"><?= te('Quando registares gastos este mês, vês aqui a divisão por categoria.') ?></p>
        <?php else: ?>
          <div class="dsh-panel-body">
            <div class="dsh-catbar" role="img" aria-label="<?= te('Divisão dos gastos de {month} por categoria', ['month' => $monthName]) ?>">
              <?php foreach ($categoryData as $cat): ?>
                <span style="width:<?= round($cat['total'] / $categoryTotal * 100, 2) ?>%;background:<?= $catColor($cat['category']) ?>"></span>
              <?php endforeach; ?>
            </div>
            <ul class="dsh-catlist">
              <?php foreach (array_slice($categoryData, 0, 8) as $cat): ?>
                <li>
                  <span class="sw" style="background:<?= $catColor($cat['category']) ?>"></span>
                  <span class="nm"><?= te($cat['category']) ?></span>
                  <span class="pc"><?= round($cat['total'] / $categoryTotal * 100) ?>%</span>
                  <span class="am"><?= fmt_money($cat['total']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </section>

      <section class="dsh-panel" aria-labelledby="hTx">
        <div class="dsh-panel-head">
          <h2 id="hTx"><?= te('Últimas transações') ?></h2>
          <a href="pages/transactions.php"><?= te('Ver todas') ?></a>
        </div>
        <?php if (empty($recent)): ?>
          <p class="dsh-empty"><?= te('Ainda não tens transações.') ?> <a href="pages/create_transaction.php"><?= te('Registar a primeira') ?></a></p>
        <?php else: ?>
          <ul class="dsh-rows">
            <?php foreach ($recent as $r):
              $cat = $r['category'] ?: 'Outros';
              $col = $catColor($cat);
              $ts  = strtotime($r['transaction_date']);
            ?>
              <li class="dsh-row">
                <span class="dsh-cat-ico" style="background:<?= category_tint($cat) ?>;color:<?= $col ?>"><i class="bi bi-<?= category_icon($cat) ?>"></i></span>
                <div class="main">
                  <div class="t1"><?= htmlspecialchars($r['description'] ?: t('Sem descrição')) ?></div>
                  <div class="t2">
                    <?= te($cat) ?> · <?= date('j', $ts) ?> <?= mb_substr(month_name((int) date('n', $ts)), 0, 3) ?>
                    <?= $r['card_name'] ? ' · ' . htmlspecialchars($r['card_name']) : '' ?>
                  </div>
                </div>
                <span class="amt">−<?= fmt_money($r['amount']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

    </div>

    <!-- ════ Coluna lateral ════ -->
    <div class="dsh-col">

      <?php if (!empty($upcomingReminders) && $notifPrefs['_on']): ?>
      <section class="dsh-panel" aria-labelledby="hPay">
        <div class="dsh-panel-head">
          <h2 id="hPay"><?= $hasOverdue ? te('Pagamentos em atraso e próximos') : te('Próximos pagamentos') ?></h2>
          <a href="pages/reminders.php"><?= te('Lembretes') ?></a>
        </div>
        <ul class="dsh-rows">
          <?php foreach ($upcomingReminders as $r):
            $ts = strtotime($r['due_date']);
            $over = $r['status'] === 'overdue';
            $days = (int) $r['days_until'];
          ?>
            <li class="dsh-row">
              <span class="dsh-date <?= $over ? 'over' : '' ?>"><b><?= date('j', $ts) ?></b><small><?= mb_substr(month_name((int) date('n', $ts)), 0, 3) ?></small></span>
              <div class="main">
                <div class="t1"><?= htmlspecialchars($r['name']) ?></div>
                <div class="t2">
                  <?php if ($over): ?><?= abs($days) > 1 ? te('Em atraso há {n} dias', ['n' => abs($days)]) : te('Em atraso há 1 dia') ?>
                  <?php elseif ($days === 0): ?>Vence hoje
                  <?php else: ?><?= $days > 1 ? te('Vence em {n} dias', ['n' => $days]) : te('Vence em 1 dia') ?><?php endif; ?>
                </div>
              </div>
              <span class="amt"><?= fmt_money($r['amount']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section class="dsh-panel" aria-labelledby="hCards">
        <div class="dsh-panel-head">
          <h2 id="hCards"><?= te('Cartões') ?></h2>
          <a href="pages/cards.php"><?= empty($cards) ? te('Adicionar') : te('Gerir') ?></a>
        </div>
        <?php if (empty($activeCards)): ?>
          <p class="dsh-empty"><?= te('Adiciona os teus cartões para acompanhar o limite e o que ainda tens disponível.') ?> <a href="pages/add_card.php"><?= te('Adicionar cartão') ?></a></p>
        <?php else: ?>
          <ul class="dsh-rows">
            <?php foreach (array_slice($activeCards, 0, 4) as $c):
              $bank     = $BANKS[$c['bank'] ?? ''] ?? null;
              $gradient = ($bank && $bank['gradient']) ? $bank['gradient'] : ($cardColors[$c['color'] ?? 'purple'] ?? $cardColors['purple']);
              $limit    = (float) $c['limit_amount'];
              $pct      = $limit > 0 ? $c['balance'] / $limit * 100 : 0;
              $state    = $pct >= 100 ? 'over' : ($pct >= 80 ? 'warn' : '');
            ?>
              <li class="dsh-row">
                <span class="dsh-minicard" style="background:<?= $gradient ?>"></span>
                <div class="main">
                  <div class="t1"><?= htmlspecialchars($c['name']) ?></div>
                  <?php if ($limit > 0): ?>
                    <div class="dsh-meter"><span class="<?= $state ?>" style="width:<?= min(round($pct, 1), 100) ?>%"></span></div>
                    <div class="t2"><?= te('{amount} disponíveis de {limit}', ['amount' => fmt_money(max($limit - $c['balance'], 0)), 'limit' => fmt_money($limit, 0)]) ?></div>
                  <?php else: ?>
                    <div class="t2"><?= te('Sem limite definido · {amount} usados', ['amount' => fmt_money($c['balance'])]) ?></div>
                  <?php endif; ?>
                </div>
                <?php if ($state === 'over'): ?><span class="dsh-flag over"><?= te('Limite excedido') ?></span>
                <?php elseif ($state === 'warn'): ?><span class="dsh-flag warn"><?= round($pct) ?>%</span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="dsh-panel" aria-labelledby="hBudget">
        <div class="dsh-panel-head">
          <h2 id="hBudget"><?= te('Orçamento') ?></h2>
          <a href="pages/budgets.php"><?= $activeBudgetsCount > 0 ? te('Ver todos') : te('Criar') ?></a>
        </div>
        <?php if (!$mainBudget): ?>
          <p class="dsh-empty"><?= te('Define quanto queres gastar por mês e a Finora avisa-te antes de passares o limite.') ?> <a href="pages/add_budget.php"><?= te('Criar orçamento') ?></a></p>
        <?php else:
          $left  = $mainBudget['amount'] - $mainBudget['current_spent'];
          $state = $budgetPercentage >= 100 ? 'over' : ($budgetPercentage >= 80 ? 'warn' : '');
        ?>
          <div class="dsh-panel-body dsh-budget">
            <div class="dsh-budget-top">
              <b><?= fmt_money($mainBudget['current_spent'], 0) ?></b>
              <span><?= te('de {amount}', ['amount' => fmt_money($mainBudget['amount'], 0)]) ?> · <?= htmlspecialchars($mainBudget['name']) ?></span>
            </div>
            <div class="dsh-meter"><span class="<?= $state ?>" style="width:<?= min(round($budgetPercentage, 1), 100) ?>%"></span></div>
            <?php if ($left < 0): ?>
              <p class="dsh-note over"><?= te('Passaste o orçamento em {amount}.', ['amount' => fmt_money(abs($left))]) ?></p>
            <?php else: ?>
              <p class="dsh-note"><?= te('Ainda podes gastar {amount}', ['amount' => fmt_money($left)]) ?><?= $activeBudgetsCount > 2 ? ' · ' . te('mais {n} orçamentos ativos', ['n' => $activeBudgetsCount - 1]) : ($activeBudgetsCount === 2 ? ' · ' . te('mais 1 orçamento ativo') : '') ?>.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>

    </div>
  </div>

</div><!-- /dsh -->
</div><!-- /page-content -->
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
