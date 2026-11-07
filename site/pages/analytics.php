<?php
// site/analytics.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$year    = !empty($_GET['year'])    ? intval($_GET['year'])    : date('Y');
$card_id = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;

// Ano de comparação (escolhido pelo utilizador, default = ano anterior)
$compareYear = !empty($_GET['compare_year']) ? intval($_GET['compare_year']) : ($year - 1);

// Ano e mês para Top 5 (independentes do filtro principal)
$top5Year  = !empty($_GET['top5_year'])  ? intval($_GET['top5_year'])  : $year;
$top5Month = !empty($_GET['top5_month']) ? intval($_GET['top5_month']) : 0; // 0 = todos os meses

// Anos disponíveis (apenas cartões activos)
$stmt = $pdo->prepare("
    SELECT DISTINCT YEAR(t.transaction_date) as year
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid AND (t.card_id IS NULL OR c.active = 1)
    ORDER BY year DESC
");
$stmt->execute([':uid' => $uid]);
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($availableYears)) $availableYears = [date('Y')];

// Cartões activos
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// ── Filtro base (exclui cartões inativos) ────────────────────────────────────
$params = [':uid' => $uid, ':year' => $year];
$cardJoin   = "LEFT JOIN cards c ON c.id = t.card_id";
$activeWhere = "AND (t.card_id IS NULL OR c.active = 1)";
$cardFilter = $card_id ? " AND t.card_id = :cid" : "";
if ($card_id) $params[':cid'] = $card_id;

// Total por categoria
$stmt = $pdo->prepare("
    SELECT COALESCE(t.category,'Outros') as category, SUM(t.amount) as total, COUNT(*) as count
    FROM transactions t $cardJoin
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:year $activeWhere $cardFilter
    GROUP BY t.category ORDER BY total DESC
");
$stmt->execute($params);
$categoryTotals = $stmt->fetchAll();
$totalYear = array_sum(array_column($categoryTotals, 'total'));

// Estatísticas gerais
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_transactions, AVG(t.amount) as avg_amount,
           MAX(t.amount) as max_amount, MIN(t.amount) as min_amount
    FROM transactions t $cardJoin
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:year $activeWhere $cardFilter
");
$stmt->execute($params);
$stats = $stmt->fetch();

// Gastos por mês
$stmt = $pdo->prepare("
    SELECT MONTH(t.transaction_date) as month, SUM(t.amount) as total
    FROM transactions t $cardJoin
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:year $activeWhere $cardFilter
    GROUP BY MONTH(t.transaction_date) ORDER BY month
");
$stmt->execute($params);
$monthlyRows = $stmt->fetchAll();
$monthlyTotals = array_fill(1, 12, 0);
foreach ($monthlyRows as $r) $monthlyTotals[intval($r['month'])] = floatval($r['total']);

// Gastos mensais por categoria
$stmt = $pdo->prepare("
    SELECT MONTH(t.transaction_date) as month, COALESCE(t.category,'Outros') as category, SUM(t.amount) as total
    FROM transactions t $cardJoin
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:year $activeWhere $cardFilter
    GROUP BY MONTH(t.transaction_date), t.category ORDER BY month
");
$stmt->execute($params);
$catMonthlyData = [];
foreach ($stmt->fetchAll() as $r) {
    $cat = $r['category'];
    if (!isset($catMonthlyData[$cat])) $catMonthlyData[$cat] = array_fill(1, 12, 0);
    $catMonthlyData[$cat][intval($r['month'])] = floatval($r['total']);
}
$catMonthlyOrdered = [];
foreach (array_column($categoryTotals, 'category') as $cat) {
    if (isset($catMonthlyData[$cat])) {
        $catMonthlyOrdered[$cat] = array_values($catMonthlyData[$cat]);
    }
}

// Gastos por cartão
$stmt = $pdo->prepare("
    SELECT c.name, COALESCE(SUM(t.amount),0) as total
    FROM cards c LEFT JOIN transactions t ON t.card_id=c.id AND YEAR(t.transaction_date)=:year " . ($card_id ? "AND c.id=:cid " : "") . "
    WHERE c.user_id=:uid AND c.active=1 GROUP BY c.id HAVING total>0 ORDER BY total DESC
");
$stmt->execute($params);
$cardSpending = $stmt->fetchAll();

// ── TOP 5 com filtros independentes ──────────────────────────────────────────
$top5Params = [':uid' => $uid, ':top5year' => $top5Year];
$top5MonthFilter = '';
if ($top5Month > 0) {
    $top5MonthFilter = " AND MONTH(t.transaction_date) = :top5month";
    $top5Params[':top5month'] = $top5Month;
}
$top5CardFilter = $card_id ? " AND t.card_id = :cid2" : "";
if ($card_id) $top5Params[':cid2'] = $card_id;

$stmt = $pdo->prepare("
    SELECT t.*, c.name as card_name FROM transactions t
    LEFT JOIN cards cc ON cc.id = t.card_id
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:top5year
      AND (t.card_id IS NULL OR cc.active=1)
      $top5MonthFilter $top5CardFilter
    ORDER BY t.amount DESC LIMIT 5
");
$stmt->execute($top5Params);
$topTransactions = $stmt->fetchAll();

// Total do período do Top 5 (para % correcta)
$stmtTot = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount),0) as total FROM transactions t
    LEFT JOIN cards cc ON cc.id = t.card_id
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:top5year
      AND (t.card_id IS NULL OR cc.active=1)
      $top5MonthFilter $top5CardFilter
");
$stmtTot->execute($top5Params);
$totalForTop5 = floatval($stmtTot->fetch()['total']);

// Mês com mais gastos
$maxMonthVal = max($monthlyTotals);
$maxMonth = array_search($maxMonthVal, $monthlyTotals);

// ── COMPARAÇÃO ANUAL ─────────────────────────────────────────────────────────
$paramsPrev = [':uid' => $uid, ':year' => $compareYear];
if ($card_id) $paramsPrev[':cid'] = $card_id;

$stmtPrev = $pdo->prepare("
    SELECT MONTH(t.transaction_date) as month, SUM(t.amount) as total
    FROM transactions t $cardJoin
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:year $activeWhere $cardFilter
    GROUP BY MONTH(t.transaction_date) ORDER BY month
");
$stmtPrev->execute($paramsPrev);
$prevMonthlyRows = $stmtPrev->fetchAll();
$prevMonthlyTotals = array_fill(1, 12, 0);
foreach ($prevMonthlyRows as $r) $prevMonthlyTotals[intval($r['month'])] = floatval($r['total']);

$currentMonthNum = date('n');
$totalCurrent = array_sum($monthlyTotals);
$totalPrev    = array_sum($prevMonthlyTotals);
$hasPrevData  = $totalPrev > 0;

$biggestDrop = null; $biggestRise = null;
for ($m = 1; $m <= $currentMonthNum; $m++) {
    $cur = $monthlyTotals[$m]; $prv = $prevMonthlyTotals[$m];
    if ($cur == 0 && $prv == 0) continue;
    $diff = $cur - $prv;
    if ($prv > 0 && $cur > 0) { $pctChange = (($cur - $prv) / $prv) * 100; $isNew = false; }
    elseif ($prv == 0 && $cur > 0) { $pctChange = PHP_FLOAT_MAX; $isNew = true; }
    elseif ($prv > 0 && $cur == 0) { $pctChange = -100.0; $isNew = false; }
    else continue;
    if ($pctChange > 0 || $isNew) {
        if ($biggestRise === null || $pctChange > $biggestRise['pct'] || ($pctChange == $biggestRise['pct'] && $diff > $biggestRise['diff'])) {
            $biggestRise = ['month' => $m, 'pct' => $pctChange, 'diff' => $diff, 'new' => $isNew];
        }
    }
    if (!$isNew && $pctChange < 0) {
        if ($biggestDrop === null || $pctChange < $biggestDrop['pct']) {
            $biggestDrop = ['month' => $m, 'pct' => $pctChange, 'diff' => $diff];
        }
    }
}

$monthNames      = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$monthNamesShort = ['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];

// ── Comparação justa: no ano atual só se comparam os meses que já passaram ──
$isCurrentYear = (int) $year === (int) date('Y');
$cutMonth      = $isCurrentYear ? (int) date('n') : 12;
$curToDate     = array_sum(array_slice($monthlyTotals, 0, $cutMonth));
$prevToDate    = array_sum(array_slice($prevMonthlyTotals, 0, $cutMonth));
$diffPct       = $prevToDate > 0 ? ($curToDate - $prevToDate) / $prevToDate * 100 : null;
$periodLabel   = $isCurrentYear ? 'até ' . month_name_pt($cutMonth) : 'no ano inteiro';

$minYear = min($availableYears);
$maxYear = max(max($availableYears), (int) date('Y'));

// URL desta página mudando só o que for pedido
function an_url(array $changes): string {
    global $year, $card_id, $compareYear, $top5Month;
    $p = array_merge(['year' => $year, 'card_id' => $card_id, 'compare_year' => $compareYear,
                      'top5_month' => $top5Month], $changes);
    return 'analytics.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0));
}

$stmtAllY = $pdo->prepare("SELECT DISTINCT YEAR(transaction_date) as y FROM transactions WHERE user_id=:uid ORDER BY y DESC");
$stmtAllY->execute([':uid' => $uid]);
$allYears = $stmtAllY->fetchAll(PDO::FETCH_COLUMN);

$cardTotal = array_sum(array_column($cardSpending, 'total'));
$maxCatTotal = $categoryTotals ? max(array_column($categoryTotals, 'total')) : 0;
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Análise - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=9">
  <style>
    .an { max-width: 1200px; margin: 0 auto; padding-bottom: 56px; }
    .an-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
    .an-head h1 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .an-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .an-year { display: inline-flex; align-items: center; gap: 4px; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 12px; padding: 4px; }
    .an-year a, .an-year span { display: inline-flex; align-items: center; justify-content: center; height: 36px; border-radius: 9px; text-decoration: none; }
    .an-year a { width: 36px; color: var(--fn-text); }
    .an-year a:hover { background: var(--fn-paper); }
    .an-year a[aria-disabled="true"] { opacity: .3; pointer-events: none; }
    .an-year span { min-width: 64px; font-weight: 700; color: var(--fn-text); }
    .main-content .an-controls .form-select { width: auto; min-width: 200px; }

    /* Frase principal */
    .an-hero { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; padding: 26px 28px; margin-bottom: 24px; }
    .an-hero h2 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: clamp(24px, 3vw, 34px); letter-spacing: -0.6px; color: var(--fn-ink); line-height: 1.15; }
    .an-hero p { margin: 10px 0 0; font-size: 15px; color: var(--fn-muted); }
    .an-hero p strong { color: var(--fn-text); }
    .an-facts { display: flex; gap: 28px; flex-wrap: wrap; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--fn-line); }
    .an-facts div { font-size: 13px; color: var(--fn-muted); }
    .an-facts strong { display: block; font-size: 18px; color: var(--fn-text); }

    .an-panel { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; padding: 20px 22px 22px; }
    .an-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .an-panel-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--fn-text); }
    .an-panel-head p { margin: 2px 0 0; font-size: 13px; color: var(--fn-muted); }
    .main-content .an-panel-head .form-select { width: auto; min-height: 38px; padding-top: 6px; padding-bottom: 6px; font-size: 14px; }
    .an-chart { position: relative; height: 280px; }
    .an-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 24px; margin-top: 24px; align-items: start; }

    .an-insights { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
    .an-insight { border: 1px solid var(--fn-line); border-radius: 12px; padding: 12px 14px; font-size: 14px; color: var(--fn-muted); }
    .an-insight strong { display: block; color: var(--fn-text); font-size: 15px; }
    .an-insight i { margin-right: 4px; }

    /* Listas com barras (categorias e cartões) */
    .an-bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
    .an-bars .row1 { display: flex; justify-content: space-between; gap: 10px; font-size: 14px; margin-bottom: 6px; }
    .an-bars .nm { display: inline-flex; align-items: center; gap: 8px; color: var(--fn-text); font-weight: 600; }
    .an-bars .nm i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
    .an-bars .val { color: var(--fn-text); font-weight: 600; white-space: nowrap; }
    .an-bars .val small { color: var(--fn-muted); font-weight: 400; margin-left: 6px; }
    .an-track { height: 8px; border-radius: 4px; background: var(--fn-paper); overflow: hidden; }
    .an-track span { display: block; height: 100%; border-radius: 4px; }

    .an-top { list-style: none; margin: 0; padding: 0; }
    .an-top li { display: flex; align-items: center; gap: 12px; padding: 10px 0; }
    .an-top li + li { border-top: 1px solid var(--fn-line); }
    .an-rank { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; font-weight: 700; font-size: 13px; background: var(--fn-paper); color: var(--fn-text); flex-shrink: 0; }
    .an-top li:first-child .an-rank { background: var(--fn-gold-soft); color: var(--fn-gold); }
    .an-top .main { flex: 1; min-width: 0; }
    .an-top .d { font-weight: 600; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .an-top .m { font-size: 13px; color: var(--fn-muted); }
    .an-top .a { text-align: right; font-weight: 700; color: var(--fn-text); white-space: nowrap; }
    .an-top .a small { display: block; font-weight: 400; font-size: 12px; color: var(--fn-muted); }

    .an-muted { color: var(--fn-muted); font-size: 14px; margin: 0; }
    .an-empty { padding: 56px 24px; text-align: center; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; }
    .an-empty h2 { margin: 0 0 6px; font-size: 20px; font-weight: 700; color: var(--fn-text); }
    .an-empty p { margin: 0 auto 20px; max-width: 46ch; color: var(--fn-muted); font-size: 15px; }

    @media (max-width: 960px) { .an-grid { grid-template-columns: 1fr; } }
    @media (max-width: 560px) {
      .an-head h1 { font-size: 26px; }
      .an-insights { grid-template-columns: 1fr; }
      .an-chart { height: 240px; }
      .main-content .an-controls .form-select { min-width: 0; flex: 1; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='analytics'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="an">

  <div class="an-head">
    <h1>Análise</h1>
    <form class="an-controls" method="get">
      <nav class="an-year" aria-label="Escolher ano">
        <a href="<?= htmlspecialchars(an_url(['year' => $year - 1, 'compare_year' => $year - 2])) ?>" aria-label="Ano anterior"<?= $year <= $minYear ? ' aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-left"></i></a>
        <span><?= (int) $year ?></span>
        <a href="<?= htmlspecialchars(an_url(['year' => $year + 1, 'compare_year' => $year])) ?>" aria-label="Ano seguinte"<?= $year >= $maxYear ? ' aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-right"></i></a>
      </nav>
      <input type="hidden" name="year" value="<?= (int) $year ?>">
      <input type="hidden" name="compare_year" value="<?= (int) $compareYear ?>">
      <label class="visually-hidden" for="anCard">Cartão</label>
      <select id="anCard" name="card_id" class="form-select" onchange="this.form.submit()">
        <option value="">Todos os cartões</option>
        <?php foreach ($cards as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $card_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($categoryTotals)): ?>
    <div class="an-empty">
      <h2>Sem gastos em <?= (int) $year ?></h2>
      <p>Quando registares transações neste ano, vês aqui a evolução mês a mês, as categorias e a comparação com anos anteriores.</p>
      <a href="create_transaction.php" class="btn-s btn-s-primary"><i class="bi bi-plus-lg"></i> Registar uma transação</a>
    </div>
  <?php else: ?>

  <section class="an-hero" aria-labelledby="anHeadline">
    <h2 id="anHeadline">Em <?= (int) $year ?> gastaste <?= fmt_money($totalYear) ?></h2>
    <p>
      <?php if ($diffPct === null): ?>
        Sem gastos em <?= (int) $compareYear ?> para comparar.
      <?php else: ?>
        <strong><?= round(abs($diffPct)) ?>% <?= $diffPct >= 0 ? 'mais' : 'menos' ?></strong>
        do que em <?= (int) $compareYear ?> <?= $periodLabel ?> (<?= fmt_money($curToDate, 0) ?> contra <?= fmt_money($prevToDate, 0) ?>).
      <?php endif; ?>
    </p>
    <div class="an-facts">
      <div><strong><?= (int) $stats['total_transactions'] ?></strong>transações</div>
      <div><strong><?= fmt_money($stats['avg_amount']) ?></strong>média por transação</div>
      <div><strong><?= ucfirst(month_name_pt((int) $maxMonth)) ?></strong>mês com mais gastos (<?= fmt_money($maxMonthVal, 0) ?>)</div>
      <div><strong><?= htmlspecialchars($categoryTotals[0]['category']) ?></strong>categoria principal</div>
    </div>
  </section>

  <!-- Gráfico principal: mês a mês, este ano vs ano de comparação -->
  <section class="an-panel" aria-labelledby="hMonth">
    <div class="an-panel-head">
      <div>
        <h2 id="hMonth">Mês a mês</h2>
        <p><?= (int) $year ?> comparado com <?= (int) $compareYear ?></p>
      </div>
      <form method="get">
        <input type="hidden" name="year" value="<?= (int) $year ?>">
        <?php if ($card_id): ?><input type="hidden" name="card_id" value="<?= (int) $card_id ?>"><?php endif; ?>
        <label class="visually-hidden" for="anCompare">Comparar com</label>
        <select id="anCompare" name="compare_year" class="form-select" onchange="this.form.submit()">
          <?php foreach ($allYears as $y): if ($y == $year) continue; ?>
            <option value="<?= $y ?>" <?= $compareYear == $y ? 'selected' : '' ?>>Comparar com <?= $y ?></option>
          <?php endforeach; ?>
          <?php if (!in_array($compareYear, $allYears)): ?><option value="<?= (int) $compareYear ?>" selected>Comparar com <?= (int) $compareYear ?></option><?php endif; ?>
        </select>
      </form>
    </div>
    <div class="an-chart"><canvas id="monthChart" role="img" aria-label="Gastos por mês em <?= (int) $year ?> e <?= (int) $compareYear ?>"></canvas></div>

    <?php if ($hasPrevData && ($biggestRise || $biggestDrop)): ?>
    <div class="an-insights">
      <?php if ($biggestRise && $biggestRise['pct'] > 0): ?>
        <div class="an-insight"><i class="bi bi-arrow-up-right"></i>Maior subida
          <strong><?= ucfirst(month_name_pt($biggestRise['month'])) ?></strong>
          <?= !empty($biggestRise['new']) ? 'sem gastos no mesmo mês de ' . (int) $compareYear : '+' . round($biggestRise['pct']) . '% face a ' . (int) $compareYear ?>
        </div>
      <?php endif; ?>
      <?php if ($biggestDrop && $biggestDrop['pct'] < 0): ?>
        <div class="an-insight"><i class="bi bi-arrow-down-right"></i>Maior descida
          <strong><?= ucfirst(month_name_pt($biggestDrop['month'])) ?></strong>
          <?= round($biggestDrop['pct']) ?>% face a <?= (int) $compareYear ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>

  <div class="an-grid">
    <!-- Categorias por mês (barras empilhadas) -->
    <section class="an-panel" aria-labelledby="hCatMonth">
      <div class="an-panel-head"><div><h2 id="hCatMonth">Categorias por mês</h2><p>Como se dividiu o gasto de cada mês</p></div></div>
      <div class="an-chart"><canvas id="catChart" role="img" aria-label="Gastos por categoria em cada mês de <?= (int) $year ?>"></canvas></div>
    </section>

    <!-- Categorias no ano -->
    <section class="an-panel" aria-labelledby="hCat">
      <div class="an-panel-head"><div><h2 id="hCat">Por categoria</h2><p>Total de <?= (int) $year ?></p></div></div>
      <ul class="an-bars">
        <?php foreach ($categoryTotals as $cat):
          $share = $totalYear > 0 ? $cat['total'] / $totalYear * 100 : 0; ?>
          <li>
            <div class="row1">
              <span class="nm"><i style="background:<?= category_color($cat['category']) ?>"></i><?= htmlspecialchars($cat['category']) ?></span>
              <span class="val"><?= fmt_money($cat['total']) ?><small><?= round($share) ?>%</small></span>
            </div>
            <div class="an-track"><span style="width:<?= round($cat['total'] / $maxCatTotal * 100, 1) ?>%;background:<?= category_color($cat['category']) ?>"></span></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <div class="an-grid">
    <!-- Top 5 -->
    <section class="an-panel" aria-labelledby="hTop">
      <div class="an-panel-head">
        <div><h2 id="hTop">Maiores gastos</h2><p><?= $top5Month ? ucfirst(month_name_pt($top5Month)) . ' de ' : '' ?><?= (int) $top5Year ?></p></div>
        <form method="get" class="an-controls">
          <input type="hidden" name="year" value="<?= (int) $year ?>">
          <input type="hidden" name="compare_year" value="<?= (int) $compareYear ?>">
          <?php if ($card_id): ?><input type="hidden" name="card_id" value="<?= (int) $card_id ?>"><?php endif; ?>
          <label class="visually-hidden" for="anTopMonth">Mês</label>
          <select id="anTopMonth" name="top5_month" class="form-select" onchange="this.form.submit()">
            <option value="0" <?= $top5Month == 0 ? 'selected' : '' ?>>Ano inteiro</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $top5Month == $m ? 'selected' : '' ?>><?= ucfirst(month_name_pt($m)) ?></option>
            <?php endfor; ?>
          </select>
        </form>
      </div>
      <?php if (empty($topTransactions)): ?>
        <p class="an-muted">Sem transações neste período.</p>
      <?php else: ?>
        <ol class="an-top">
          <?php foreach ($topTransactions as $i => $t):
            $pct = $totalForTop5 > 0 ? $t['amount'] / $totalForTop5 * 100 : 0;
            $ts  = strtotime($t['transaction_date']); ?>
            <li>
              <span class="an-rank"><?= $i + 1 ?></span>
              <div class="main">
                <div class="d"><?= htmlspecialchars($t['description'] ?: 'Sem descrição') ?></div>
                <div class="m"><?= htmlspecialchars($t['category'] ?: 'Outros') ?> · <?= date('j', $ts) ?> <?= mb_substr(month_name_pt((int) date('n', $ts)), 0, 3) ?><?= $t['card_name'] ? ' · ' . htmlspecialchars($t['card_name']) : '' ?></div>
              </div>
              <div class="a"><?= fmt_money($t['amount']) ?><small><?= number_format($pct, 1, ',', '') ?>% do período</small></div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <!-- Cartões -->
    <section class="an-panel" aria-labelledby="hCards">
      <div class="an-panel-head"><div><h2 id="hCards">Por cartão</h2><p>Total de <?= (int) $year ?></p></div></div>
      <?php if (empty($cardSpending)): ?>
        <p class="an-muted">Nenhum gasto associado a cartões neste ano.</p>
      <?php else: ?>
        <ul class="an-bars">
          <?php $maxCard = max(array_column($cardSpending, 'total'));
          foreach ($cardSpending as $c): ?>
            <li>
              <div class="row1">
                <span class="nm"><?= htmlspecialchars($c['name']) ?></span>
                <span class="val"><?= fmt_money($c['total']) ?><small><?= $cardTotal > 0 ? round($c['total'] / $cardTotal * 100) : 0 ?>%</small></span>
              </div>
              <div class="an-track"><span style="width:<?= round($c['total'] / $maxCard * 100, 1) ?>%;background:var(--fn-accent)"></span></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <?php endif; ?>

</div><!-- /an -->
</div><!-- /page-content -->
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($categoryTotals)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
  // Cores lidas das variáveis CSS, para o gráfico seguir o tema (claro/escuro) e a paleta validada
  const css  = getComputedStyle(document.documentElement);
  const v    = name => css.getPropertyValue(name).trim();
  const ink  = v('--fn-text'), muted = v('--fn-muted'), line = v('--fn-line'), surface = v('--fn-surface');
  const eur  = <?= money_js(0) ?>;
  const eur2 = <?= money_js() ?>;

  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 13;
  Chart.defaults.color = muted;

  const months = <?= json_encode(array_slice($monthNamesShort, 1)) ?>;
  const tooltip = { backgroundColor: v('--fn-ink') === '#e8ecf5' ? '#0e1420' : '#14213d', padding: 10, cornerRadius: 8, titleColor: '#fff', bodyColor: '#fff' };
  const axes = {
    x: { grid: { display: false }, border: { color: line }, ticks: { color: muted } },
    y: { beginAtZero: true, grid: { color: line }, border: { display: false }, ticks: { color: muted, callback: n => eur(n) } }
  };

  // 1. Mês a mês: este ano vs ano de comparação
  new Chart(document.getElementById('monthChart'), {
    type: 'bar',
    data: {
      labels: months,
      datasets: [
        { label: '<?= (int) $year ?>', data: <?= json_encode(array_values($monthlyTotals)) ?>,
          backgroundColor: v('--fn-accent'), borderRadius: 4, borderSkipped: 'start', maxBarThickness: 26 },
        { label: '<?= (int) $compareYear ?>', data: <?= json_encode(array_values($prevMonthlyTotals)) ?>,
          backgroundColor: 'color-mix(in srgb, ' + muted + ' 45%, transparent)', borderRadius: 4, borderSkipped: 'start', maxBarThickness: 26 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top', align: 'end', labels: { color: ink, boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 3 } },
        tooltip: Object.assign({}, tooltip, { callbacks: { label: c => ' ' + c.dataset.label + ': ' + eur2(c.parsed.y) } })
      },
      scales: axes
    }
  });

  // 2. Categorias por mês: barras empilhadas, cor fixa por categoria (slot --cat-N)
  const catSlots = <?= json_encode(array_map(fn($c) => category_slot($c), array_combine(array_keys($catMonthlyOrdered), array_keys($catMonthlyOrdered)))) ?>;
  const catData  = <?= json_encode($catMonthlyOrdered) ?>;
  const lastMonth = <?= $isCurrentYear ? (int) date('n') : 12 ?>;
  new Chart(document.getElementById('catChart'), {
    type: 'bar',
    data: {
      labels: months.slice(0, lastMonth),
      datasets: Object.keys(catData).map(cat => ({
        label: cat,
        data: catData[cat].slice(0, lastMonth),
        backgroundColor: v('--cat-' + catSlots[cat]),
        borderColor: surface, borderWidth: { top: 2 },   // 2px de separação entre segmentos
        borderRadius: 3, maxBarThickness: 26
      }))
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { color: ink, boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 3, padding: 14 } },
        tooltip: Object.assign({}, tooltip, { filter: i => i.parsed.y > 0, callbacks: { label: c => ' ' + c.dataset.label + ': ' + eur2(c.parsed.y) } })
      },
      scales: { x: Object.assign({}, axes.x, { stacked: true }), y: Object.assign({}, axes.y, { stacked: true }) }
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
