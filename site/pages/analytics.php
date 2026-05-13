<?php
// site/analytics.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$year    = !empty($_GET['year'])    ? intval($_GET['year'])    : date('Y');
$card_id = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;

// Anos disponíveis
$stmt = $pdo->prepare("SELECT DISTINCT YEAR(transaction_date) as year FROM transactions WHERE user_id = :uid ORDER BY year DESC");
$stmt->execute([':uid' => $uid]);
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($availableYears)) $availableYears = [date('Y')];

// Cartões
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

$params = [':uid' => $uid, ':year' => $year];
$cardFilter = $card_id ? " AND card_id = :cid" : "";
if ($card_id) $params[':cid'] = $card_id;

// Total por categoria
$stmt = $pdo->prepare("
    SELECT COALESCE(category,'Outros') as category, SUM(amount) as total, COUNT(*) as count
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
    GROUP BY category ORDER BY total DESC
");
$stmt->execute($params);
$categoryTotals = $stmt->fetchAll();
$totalYear = array_sum(array_column($categoryTotals, 'total'));
// Estatísticas gerais
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_transactions, AVG(amount) as avg_amount,
           MAX(amount) as max_amount, MIN(amount) as min_amount
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
");
$stmt->execute($params);
$stats = $stmt->fetch();

// Gastos por mês (totais simples)
$stmt = $pdo->prepare("
    SELECT MONTH(transaction_date) as month, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
    GROUP BY MONTH(transaction_date) ORDER BY month
");
$stmt->execute($params);
$monthlyRows = $stmt->fetchAll();
$monthlyTotals = array_fill(1, 12, 0);
foreach ($monthlyRows as $r) $monthlyTotals[intval($r['month'])] = floatval($r['total']);

// Gastos mensais por categoria (gráfico de linhas)
$stmt = $pdo->prepare("
    SELECT MONTH(transaction_date) as month, COALESCE(category,'Outros') as category, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
    GROUP BY MONTH(transaction_date), category ORDER BY month
");
$stmt->execute($params);
$catMonthlyData = [];
foreach ($stmt->fetchAll() as $r) {
    $cat = $r['category'];
    if (!isset($catMonthlyData[$cat])) $catMonthlyData[$cat] = array_fill(1, 12, 0);
    $catMonthlyData[$cat][intval($r['month'])] = floatval($r['total']);
}
// Ordenar pelas categorias com mais gasto total
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
    WHERE c.user_id=:uid GROUP BY c.id HAVING total>0 ORDER BY total DESC
");
$stmt->execute($params);
$cardSpending = $stmt->fetchAll();

// Top 5 transações
$stmt = $pdo->prepare("
    SELECT t.*, c.name as card_name FROM transactions t
    LEFT JOIN cards c ON c.id=t.card_id
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:year $cardFilter
    ORDER BY t.amount DESC LIMIT 5
");
$stmt->execute($params);
$topTransactions = $stmt->fetchAll();

// Mês com mais gastos
$maxMonthVal = max($monthlyTotals);
$maxMonth = array_search($maxMonthVal, $monthlyTotals);

$categoryColors = [
    'Compras'=>'#3498db','Alimentação'=>'#e74c3c','Transporte'=>'#f39c12',
    'Saúde'=>'#1abc9c','Entretenimento'=>'#9b59b6','Educação'=>'#34495e',
    'Casa'=>'#e67e22','Outros'=>'#95a5a6'
];
$monthNames = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$monthNamesShort = ['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Análise - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary); }

    /* ===== LAYOUT ===== */
    .analytics-wrapper { max-width: 1000px; margin: 0 auto; padding: 0 16px 60px; }

    /* ===== CABEÇALHO ===== */
    .page-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; padding-top:8px; }
    .page-header-icon { width:42px; height:42px; border-radius:12px; background:var(--bg-secondary); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
    .page-header h2 { font-size:20px; font-weight:600; margin:0 0 2px; }
    .page-header p { font-size:13px; color:var(--text-secondary); margin:0; }

    /* ===== GRUPOS ===== */
    .a-group { margin-bottom:20px; }
    .group-label { font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--text-secondary); margin:0 0 8px 4px; }

    /* ===== CARDS ===== */
    .a-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; overflow:hidden; }
    .a-card-body { padding:20px; }
    .a-card-title { font-size:14px; font-weight:600; color:var(--text-primary); margin:0 0 16px; display:flex; align-items:center; gap:8px; }
    .a-card-title i { color:#3498db; font-size:16px; }

    /* ===== MÉTRICAS ===== */
    .metrics-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
    .metric { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:16px; }
    .metric-label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
    .metric-value { font-size:22px; font-weight:700; color:var(--text-primary); line-height:1; }
    .metric-value.blue { color:#3498db; }
    .metric-value.green { color:#1abc9c; }
    .metric-value.amber { color:#f39c12; }
    .metric-value.red { color:#e74c3c; }
    .metric-sub { font-size:11px; color:var(--text-secondary); margin-top:4px; }

    /* ===== FILTROS ===== */
    .filter-row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; padding:16px 20px; }
    .filter-group { display:flex; flex-direction:column; gap:5px; }
    .filter-label { font-size:11px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; }
    .filter-select { font-size:13px; padding:8px 12px; border-radius:9px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); cursor:pointer; min-width:140px; }
    .filter-select:focus { outline:none; border-color:#3498db; }
    .btn-filter { font-size:13px; font-weight:500; padding:8px 18px; border-radius:9px; border:none; background:#3498db; color:#fff; cursor:pointer; display:flex; align-items:center; gap:6px; white-space:nowrap; }
    .btn-filter:hover { background:#2980b9; }

    /* ===== GRÁFICOS ===== */
    .chart-wrap { position:relative; }

    /* ===== BARRAS CATEGORIA ===== */
    .cat-bar-item { margin-bottom:14px; }
    .cat-bar-header { display:flex; justify-content:space-between; margin-bottom:5px; font-size:13px; }
    .cat-bar-name { font-weight:500; color:var(--text-primary); }
    .cat-bar-val { color:var(--text-secondary); font-size:12px; }
    .cat-bar-track { height:8px; background:var(--border-color); border-radius:4px; overflow:hidden; }
    .cat-bar-fill { height:100%; border-radius:4px; transition:width 1s ease-out; }

    /* ===== TOP 5 ===== */
    .top-item { display:flex; align-items:center; gap:12px; padding:12px 0; }
    .top-item:not(:last-child) { border-bottom:1px solid var(--border-color); }
    .top-rank { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }
    .top-desc { flex:1; min-width:0; }
    .top-desc-name { font-size:13px; font-weight:500; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .top-desc-meta { font-size:11px; color:var(--text-secondary); margin-top:2px; }
    .top-amount { font-size:14px; font-weight:700; color:#e74c3c; white-space:nowrap; }
    .top-pct { font-size:11px; color:var(--text-secondary); text-align:right; margin-top:1px; }

    /* ===== ESTADO VAZIO ===== */
    .empty-state { text-align:center; padding:48px 20px; }
    .empty-state i { font-size:48px; color:var(--border-color); display:block; margin-bottom:12px; }
    .empty-state h5 { font-size:15px; color:var(--text-secondary); margin-bottom:6px; }
    .empty-state p { font-size:13px; color:var(--text-secondary); margin-bottom:20px; }
    .btn-primary-s { font-size:13px; font-weight:500; padding:9px 20px; border-radius:9px; border:none; background:#3498db; color:#fff; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .btn-primary-s:hover { background:#2980b9; color:#fff; }

    /* ===== DARK MODE SELECTS ===== */
    [data-theme="dark"] .filter-select { background:#1a1d29; color:#ecf0f1; border-color:#34495e; background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ecf0f1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); background-repeat:no-repeat; background-position:right 10px center; background-size:12px; padding-right:30px; appearance:none; -webkit-appearance:none; }
    [data-theme="dark"] .filter-select option { background:#252936; }

    @media(max-width:768px) {
      .metrics-grid { grid-template-columns:repeat(2,1fr); }
      .two-col { grid-template-columns:1fr !important; }
    }
    @media(max-width:480px) {
      .metrics-grid { grid-template-columns:1fr 1fr; }
    }

    /* ===== COMPARAÇÃO ANUAL ===== */
    .compare-summary {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1px;
      background: var(--border-color);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 20px;
    }
    .compare-summary-cell {
      background: var(--bg-primary);
      padding: 14px 10px;
      text-align: center;
      min-width: 0;
    }
    .compare-summary-cell-label {
      font-size: 11px;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-bottom: 5px;
    }
    .compare-summary-cell-value {
      font-size: 18px;
      font-weight: 700;
      word-break: break-all;
      line-height: 1.2;
    }
    .compare-summary-cell-sub {
      font-size: 11px;
      color: var(--text-secondary);
      margin-top: 2px;
    }
    .compare-highlights {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    @media(max-width: 520px) {
      .compare-summary { grid-template-columns: 1fr; gap: 0; }
      .compare-summary-cell { border-bottom: 1px solid var(--border-color); padding: 12px 16px; }
      .compare-summary-cell:last-child { border-bottom: none; }
      .compare-summary-cell-value { font-size: 22px; word-break: normal; }
      .compare-highlights { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='analytics'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="analytics-wrapper">

  <!-- CABEÇALHO -->
  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-graph-up"></i></div>
    <div>
      <h2>Análise de Gastos</h2>
      <p>Visualiza os teus padrões de gastos ao longo do tempo</p>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="a-group">
    <p class="group-label">Filtros</p>
    <div class="a-card">
      <form method="get">
        <div class="filter-row">
          <div class="filter-group">
            <span class="filter-label"><i class="bi bi-calendar3"></i> Ano</span>
            <select name="year" class="filter-select">
              <?php foreach($availableYears as $y): ?>
                <option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <span class="filter-label"><i class="bi bi-credit-card"></i> Cartão</span>
            <select name="card_id" class="filter-select">
              <option value="">Todos os cartões</option>
              <?php foreach($cards as $c): ?>
                <option value="<?=$c['id']?>" <?=$card_id==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i> Aplicar</button>
        </div>
      </form>
    </div>
  </div>

  <?php if (empty($categoryTotals)): ?>
    <div class="a-card">
      <div class="empty-state">
        <i class="bi bi-graph-up"></i>
        <h5>Sem dados para este período</h5>
        <p>Ainda não tens transações registadas em <?=$year?></p>
        <a href="create_transaction.php" class="btn-primary-s"><i class="bi bi-plus-circle"></i> Criar transação</a>
      </div>
    </div>
  <?php else: ?>

  <!-- MÉTRICAS -->
  <div class="a-group">
    <p class="group-label">Resumo <?=$year?></p>
    <div class="metrics-grid">
      <div class="metric">
        <p class="metric-label">Total gasto</p>
        <p class="metric-value blue">€<?=number_format($totalYear,2,'.','')?></p>
        <p class="metric-sub"><?=count($categoryTotals)?> categorias</p>
      </div>
      <div class="metric">
        <p class="metric-label">Transações</p>
        <p class="metric-value"><?=$stats['total_transactions']?></p>
        <p class="metric-sub">em <?=$year?></p>
      </div>
      <div class="metric">
        <p class="metric-label">Média / transação</p>
        <p class="metric-value amber">€<?=number_format($stats['avg_amount'],2,'.','')?></p>
        <p class="metric-sub">por despesa</p>
      </div>
      <div class="metric">
        <p class="metric-label">Maior gasto</p>
        <p class="metric-value red">€<?=number_format($stats['max_amount'],2,'.','')?></p>
        <p class="metric-sub">transação única</p>
      </div>
    </div>
  </div>

  <!-- GRÁFICO MENSAL + TOP 5 -->
  <div class="a-group">
    <p class="group-label">Mensal</p>
    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:12px" class="two-col">

      <!-- Gráfico de linhas por categoria -->
      <div class="a-card" style="display:flex;flex-direction:column">
        <div class="a-card-body" style="display:flex;flex-direction:column;flex:1;justify-content:center">
          <p class="a-card-title"><i class="bi bi-graph-up"></i> Gastos mensais por categoria</p>
          <div class="chart-wrap" style="height:260px">
            <canvas id="monthlyChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Top 5 -->
      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-trophy-fill"></i> Top 5 maiores gastos</p>
          <?php
          $medals = ['#FFD700','#C0C0C0','#CD7F32','#95a5a6','#95a5a6'];
          foreach($topTransactions as $i => $t):
            $pct = $totalYear > 0 ? round(($t['amount']/$totalYear)*100,1) : 0;
          ?>
          <div class="top-item">
            <div class="top-rank" style="background:<?=$medals[$i]?>22;color:<?=$medals[$i]?>"><?=$i+1?></div>
            <div class="top-desc">
              <p class="top-desc-name"><?=htmlspecialchars($t['description'])?></p>
              <p class="top-desc-meta">
                <?=htmlspecialchars($t['category']??'Outros')?>
                <?php if($t['card_name']): ?> · <?=htmlspecialchars($t['card_name'])?><?php endif; ?>
              </p>
            </div>
            <div>
              <p class="top-amount">€<?=number_format($t['amount'],2)?></p>
              <p class="top-pct"><?=$pct?>%</p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- COMPARAÇÃO MÊS A MÊS -->
  <?php
    // Buscar dados do mesmo período do ano anterior
    $prevYear = $year - 1;
    $paramsPrev = [':uid' => $uid, ':year' => $prevYear];
    if ($card_id) $paramsPrev[':cid'] = $card_id;

    $stmtPrev = $pdo->prepare("
        SELECT MONTH(transaction_date) as month, SUM(amount) as total
        FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
        GROUP BY MONTH(transaction_date) ORDER BY month
    ");
    $stmtPrev->execute($paramsPrev);
    $prevMonthlyRows = $stmtPrev->fetchAll();
    $prevMonthlyTotals = array_fill(1, 12, 0);
    foreach ($prevMonthlyRows as $r) $prevMonthlyTotals[intval($r['month'])] = floatval($r['total']);

    // Calcular variação total e meses com dados
    $currentMonthNum = date('n');
    $totalCurrent = array_sum($monthlyTotals);
    $totalPrev    = array_sum($prevMonthlyTotals);
    $hasPrevData  = $totalPrev > 0;

    // Mês atual com mais variação
    // Só comparar meses que já passaram (excluir meses futuros onde o ano atual tem €0 naturalmente)
    $biggestDrop = null; $biggestRise = null;
    for ($m = 1; $m <= $currentMonthNum; $m++) {
        $cur = $monthlyTotals[$m]; $prv = $prevMonthlyTotals[$m];
        if ($cur == 0 && $prv == 0) continue;
        $diff = $cur - $prv;

        if ($prv > 0 && $cur > 0) {
            $pctChange = (($cur - $prv) / $prv) * 100;
            $isNew = false;
        } elseif ($prv == 0 && $cur > 0) {
            $pctChange = PHP_FLOAT_MAX;
            $isNew = true;
        } elseif ($prv > 0 && $cur == 0) {
            $pctChange = -100.0;
            $isNew = false;
        } else {
            continue;
        }

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
  ?>
  <div class="a-group">
    <p class="group-label">Comparação com <?=$prevYear?></p>
    <div class="a-card">
      <div class="a-card-body">
        <p class="a-card-title"><i class="bi bi-arrow-left-right"></i> <?=$year?> vs <?=$prevYear?></p>

        <?php if (!$hasPrevData): ?>
          <div style="text-align:center;padding:24px 0;color:var(--text-secondary);font-size:13px">
            <i class="bi bi-calendar-x" style="font-size:32px;display:block;margin-bottom:8px;color:var(--border-color)"></i>
            Sem dados de <?=$prevYear?> para comparar
          </div>
        <?php else: ?>

        <!-- Resumo rápido -->
        <div class="compare-summary">
          <?php
            $totalDiff = $totalCurrent - $totalPrev;
            $totalPct  = round(($totalDiff / $totalPrev) * 100, 1);
            $isUp = $totalDiff >= 0;
          ?>
          <div class="compare-summary-cell">
            <div class="compare-summary-cell-label"><?=$year?></div>
            <div class="compare-summary-cell-value" style="color:#3498db">€<?=number_format($totalCurrent,2)?></div>
          </div>
          <div class="compare-summary-cell" style="border-left:1px solid var(--border-color);border-right:1px solid var(--border-color)">
            <div class="compare-summary-cell-label">Variação</div>
            <div class="compare-summary-cell-value" style="color:<?=$isUp?'#e74c3c':'#22c55e'?>">
              <?=$isUp?'+':''?><?=$totalPct?>%
            </div>
            <div class="compare-summary-cell-sub"><?=$isUp?'+':''?>€<?=number_format(abs($totalDiff),2)?></div>
          </div>
          <div class="compare-summary-cell">
            <div class="compare-summary-cell-label"><?=$prevYear?></div>
            <div class="compare-summary-cell-value" style="color:var(--text-secondary)">€<?=number_format($totalPrev,2)?></div>
          </div>
        </div>

        <!-- Gráfico comparativo mês a mês -->
        <div class="chart-wrap" style="height:200px;margin-bottom:20px">
          <canvas id="compareChart"></canvas>
        </div>

        <!-- Destaques -->
        <?php if ($biggestRise || $biggestDrop): ?>
        <div class="compare-highlights">
          <?php if ($biggestRise && $biggestRise['pct'] > 0): ?>
          <div style="background:rgba(231,76,60,.07);border:1px solid rgba(231,76,60,.2);border-radius:10px;padding:12px 14px">
            <div style="font-size:11px;font-weight:600;color:#e74c3c;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">
              <i class="bi bi-arrow-up-circle"></i> Maior subida
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?=$monthNames[$biggestRise['month']]?></div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">
              <?php if (!empty($biggestRise['new'])): ?>
                Sem gasto em <?=$prevYear?>
              <?php else: ?>
                +<?=round($biggestRise['pct'],1)?>% vs <?=$prevYear?>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($biggestDrop && $biggestDrop['pct'] < 0): ?>
          <div style="background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:12px 14px">
            <div style="font-size:11px;font-weight:600;color:#16a34a;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">
              <i class="bi bi-arrow-down-circle"></i> Maior poupança
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?=$monthNames[$biggestDrop['month']]?></div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px"><?=round($biggestDrop['pct'],1)?>% vs <?=$prevYear?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CATEGORIAS + CARTÕES -->
  <div class="a-group">
    <p class="group-label">Distribuição</p>
    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:12px" class="two-col">

      <!-- Barras por categoria -->
      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-tag-fill"></i> Por categoria</p>
          <?php
          $maxCat = max(array_column($categoryTotals,'total'));
          foreach($categoryTotals as $cat):
            $pct = ($cat['total']/$maxCat)*100;
            $color = $categoryColors[$cat['category']] ?? '#95a5a6';
            $share = $totalYear > 0 ? round(($cat['total']/$totalYear)*100,1) : 0;
          ?>
          <div class="cat-bar-item">
            <div class="cat-bar-header">
              <span class="cat-bar-name"><?=htmlspecialchars($cat['category'])?></span>
              <span class="cat-bar-val">€<?=number_format($cat['total'],2)?> · <?=$share?>%</span>
            </div>
            <div class="cat-bar-track">
              <div class="cat-bar-fill" style="width:0%;background:<?=$color?>" data-w="<?=$pct?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Doughnut por cartão -->
      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-pie-chart-fill"></i> Por cartão</p>
          <?php if(empty($cardSpending)): ?>
            <div class="empty-state" style="padding:24px 0">
              <i class="bi bi-credit-card-2-front" style="font-size:32px"></i>
              <p style="margin:8px 0 0">Sem dados por cartão</p>
            </div>
          <?php else: ?>
            <div class="chart-wrap" style="height:200px">
              <canvas id="cardChart"></canvas>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <?php endif; ?>

</div><!-- /analytics-wrapper -->
</div><!-- /page-content -->
</main>
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
const tickColor = isDark ? '#9ca3af' : '#6b7280';
const tooltipBg = isDark ? '#1f2937' : '#111827';

Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
Chart.defaults.font.size = 12;

// ── Gráfico de Linhas por Categoria ──
const allMonthLabels = <?=json_encode(array_slice($monthNamesShort, 1))?>;
const catColors = <?=json_encode($categoryColors)?>;
const catMonthly = <?=json_encode($catMonthlyOrdered)?>;

// Cortar até ao último mês com dados (evitar meses em branco no fim)
const allValues = Object.values(catMonthly);
let lastActiveMonth = 0;
allValues.forEach(vals => {
  vals.forEach((v, i) => { if (v > 0 && i > lastActiveMonth) lastActiveMonth = i; });
});
const cutoff = Math.min(lastActiveMonth, 11); // até ao último mês com dados
const monthLabels = allMonthLabels.slice(0, cutoff + 1);

const lineDatasets = Object.entries(catMonthly).map(([cat, values]) => {
  const sliced = values.slice(0, cutoff + 1);
  return {
    label: cat,
    data: sliced,
    borderColor: catColors[cat] || '#95a5a6',
    backgroundColor: (catColors[cat] || '#95a5a6') + '18',
    borderWidth: 2.5,
    pointRadius: sliced.map(v => v > 0 ? 4 : 0),
    pointHoverRadius: 6,
    pointBackgroundColor: catColors[cat] || '#95a5a6',
    tension: 0.35,
    fill: false,
  };
});

new Chart(document.getElementById('monthlyChart'), {
  type: 'line',
  data: { labels: monthLabels, datasets: lineDatasets },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        position: 'bottom',
        labels: { color: tickColor, padding: 14, boxWidth: 10, font: { size: 12 } }
      },
      tooltip: {
        backgroundColor: tooltipBg, padding: 10, cornerRadius: 8,
        callbacks: {
          label: ctx => ' ' + ctx.dataset.label + ': €' + ctx.parsed.y.toFixed(2),
          filter: item => item.parsed.y > 0
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: tickColor, callback: v => '€' + v }
      },
      x: { grid: { display: false }, ticks: { color: tickColor } }
    }
  }
});

<?php if (!empty($cardSpending)): ?>
// ── Doughnut Cartões ──
const cardLabels = <?=json_encode(array_column($cardSpending,'name'))?>;
const cardData   = <?=json_encode(array_map(fn($c)=>floatval($c['total']),$cardSpending))?>;

new Chart(document.getElementById('cardChart'), {
  type: 'doughnut',
  data: {
    labels: cardLabels,
    datasets: [{
      data: cardData,
      backgroundColor: ['#3498db','#1abc9c','#e74c3c','#f39c12','#9b59b6','#34495e'],
      borderWidth: 2,
      borderColor: isDark ? '#252936' : '#ffffff',
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '65%',
    plugins: {
      legend: { position: 'bottom', labels: { color: tickColor, padding: 12, boxWidth: 10, font: { size: 12 } } },
      tooltip: {
        backgroundColor: tooltipBg, padding: 10, cornerRadius: 8,
        callbacks: {
          label: ctx => {
            const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
            return ' €' + ctx.parsed.toFixed(2) + ' (' + ((ctx.parsed/total)*100).toFixed(1) + '%)';
          }
        }
      }
    }
  }
});
<?php endif; ?>

// ── Gráfico Comparação Anual ──
<?php if ($hasPrevData): ?>
const compareLabels = <?=json_encode(array_slice($monthNamesShort, 1))?>;
const compareCurrentData = <?=json_encode(array_values($monthlyTotals))?>;
const comparePrevData    = <?=json_encode(array_values($prevMonthlyTotals))?>;

new Chart(document.getElementById('compareChart'), {
  type: 'bar',
  data: {
    labels: compareLabels,
    datasets: [
      {
        label: '<?=$year?>',
        data: compareCurrentData,
        backgroundColor: '#3498db',
        borderRadius: 5,
        borderSkipped: false,
      },
      {
        label: '<?=$prevYear?>',
        data: comparePrevData,
        backgroundColor: isDark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.08)',
        borderRadius: 5,
        borderSkipped: false,
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { color: tickColor, padding: 14, boxWidth: 10, font: { size: 12 } } },
      tooltip: {
        backgroundColor: tooltipBg, padding: 10, cornerRadius: 8,
        callbacks: { label: ctx => ' ' + ctx.dataset.label + ': €' + ctx.parsed.y.toFixed(2) }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: tickColor, callback: v => '€' + v }
      },
      x: { grid: { display: false }, ticks: { color: tickColor } }
    }
  }
});
<?php endif; ?>


document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    document.querySelectorAll('.cat-bar-fill').forEach(b => {
      b.style.width = b.dataset.w;
    });
  }, 150);
});
</script>
</body>
</html><?php
// site/analytics.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$year    = !empty($_GET['year'])    ? intval($_GET['year'])    : date('Y');
$card_id = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;

// Anos disponíveis
$stmt = $pdo->prepare("SELECT DISTINCT YEAR(transaction_date) as year FROM transactions WHERE user_id = :uid ORDER BY year DESC");
$stmt->execute([':uid' => $uid]);
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($availableYears)) $availableYears = [date('Y')];

// Cartões
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

$params = [':uid' => $uid, ':year' => $year];
$cardFilter = $card_id ? " AND card_id = :cid" : "";
if ($card_id) $params[':cid'] = $card_id;

// Total por categoria
$stmt = $pdo->prepare("
    SELECT COALESCE(category,'Outros') as category, SUM(amount) as total, COUNT(*) as count
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
    GROUP BY category ORDER BY total DESC
");
$stmt->execute($params);
$categoryTotals = $stmt->fetchAll();
$totalYear = array_sum(array_column($categoryTotals, 'total'));
// Estatísticas gerais
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_transactions, AVG(amount) as avg_amount,
           MAX(amount) as max_amount, MIN(amount) as min_amount
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
");
$stmt->execute($params);
$stats = $stmt->fetch();

// Gastos por mês (totais simples)
$stmt = $pdo->prepare("
    SELECT MONTH(transaction_date) as month, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
    GROUP BY MONTH(transaction_date) ORDER BY month
");
$stmt->execute($params);
$monthlyRows = $stmt->fetchAll();
$monthlyTotals = array_fill(1, 12, 0);
foreach ($monthlyRows as $r) $monthlyTotals[intval($r['month'])] = floatval($r['total']);

// Gastos mensais por categoria (gráfico de linhas)
$stmt = $pdo->prepare("
    SELECT MONTH(transaction_date) as month, COALESCE(category,'Outros') as category, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
    GROUP BY MONTH(transaction_date), category ORDER BY month
");
$stmt->execute($params);
$catMonthlyData = [];
foreach ($stmt->fetchAll() as $r) {
    $cat = $r['category'];
    if (!isset($catMonthlyData[$cat])) $catMonthlyData[$cat] = array_fill(1, 12, 0);
    $catMonthlyData[$cat][intval($r['month'])] = floatval($r['total']);
}
// Ordenar pelas categorias com mais gasto total
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
    WHERE c.user_id=:uid GROUP BY c.id HAVING total>0 ORDER BY total DESC
");
$stmt->execute($params);
$cardSpending = $stmt->fetchAll();

// Top 5 transações
$stmt = $pdo->prepare("
    SELECT t.*, c.name as card_name FROM transactions t
    LEFT JOIN cards c ON c.id=t.card_id
    WHERE t.user_id=:uid AND YEAR(t.transaction_date)=:year $cardFilter
    ORDER BY t.amount DESC LIMIT 5
");
$stmt->execute($params);
$topTransactions = $stmt->fetchAll();

// Mês com mais gastos
$maxMonthVal = max($monthlyTotals);
$maxMonth = array_search($maxMonthVal, $monthlyTotals);

$categoryColors = [
    'Compras'=>'#3498db','Alimentação'=>'#e74c3c','Transporte'=>'#f39c12',
    'Saúde'=>'#1abc9c','Entretenimento'=>'#9b59b6','Educação'=>'#34495e',
    'Casa'=>'#e67e22','Outros'=>'#95a5a6'
];
$monthNames = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$monthNamesShort = ['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Análise - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary); }

    /* ===== LAYOUT ===== */
    .analytics-wrapper { max-width: 1000px; margin: 0 auto; padding: 0 16px 60px; }

    /* ===== CABEÇALHO ===== */
    .page-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; padding-top:8px; }
    .page-header-icon { width:42px; height:42px; border-radius:12px; background:var(--bg-secondary); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
    .page-header h2 { font-size:20px; font-weight:600; margin:0 0 2px; }
    .page-header p { font-size:13px; color:var(--text-secondary); margin:0; }

    /* ===== GRUPOS ===== */
    .a-group { margin-bottom:20px; }
    .group-label { font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--text-secondary); margin:0 0 8px 4px; }

    /* ===== CARDS ===== */
    .a-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; overflow:hidden; }
    .a-card-body { padding:20px; }
    .a-card-title { font-size:14px; font-weight:600; color:var(--text-primary); margin:0 0 16px; display:flex; align-items:center; gap:8px; }
    .a-card-title i { color:#3498db; font-size:16px; }

    /* ===== MÉTRICAS ===== */
    .metrics-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
    .metric { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:16px; }
    .metric-label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
    .metric-value { font-size:22px; font-weight:700; color:var(--text-primary); line-height:1; }
    .metric-value.blue { color:#3498db; }
    .metric-value.green { color:#1abc9c; }
    .metric-value.amber { color:#f39c12; }
    .metric-value.red { color:#e74c3c; }
    .metric-sub { font-size:11px; color:var(--text-secondary); margin-top:4px; }

    /* ===== FILTROS ===== */
    .filter-row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; padding:16px 20px; }
    .filter-group { display:flex; flex-direction:column; gap:5px; }
    .filter-label { font-size:11px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; }
    .filter-select { font-size:13px; padding:8px 12px; border-radius:9px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); cursor:pointer; min-width:140px; }
    .filter-select:focus { outline:none; border-color:#3498db; }
    .btn-filter { font-size:13px; font-weight:500; padding:8px 18px; border-radius:9px; border:none; background:#3498db; color:#fff; cursor:pointer; display:flex; align-items:center; gap:6px; white-space:nowrap; }
    .btn-filter:hover { background:#2980b9; }

    /* ===== GRÁFICOS ===== */
    .chart-wrap { position:relative; }

    /* ===== BARRAS CATEGORIA ===== */
    .cat-bar-item { margin-bottom:14px; }
    .cat-bar-header { display:flex; justify-content:space-between; margin-bottom:5px; font-size:13px; }
    .cat-bar-name { font-weight:500; color:var(--text-primary); }
    .cat-bar-val { color:var(--text-secondary); font-size:12px; }
    .cat-bar-track { height:8px; background:var(--border-color); border-radius:4px; overflow:hidden; }
    .cat-bar-fill { height:100%; border-radius:4px; transition:width 1s ease-out; }

    /* ===== TOP 5 ===== */
    .top-item { display:flex; align-items:center; gap:12px; padding:12px 0; }
    .top-item:not(:last-child) { border-bottom:1px solid var(--border-color); }
    .top-rank { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }
    .top-desc { flex:1; min-width:0; }
    .top-desc-name { font-size:13px; font-weight:500; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .top-desc-meta { font-size:11px; color:var(--text-secondary); margin-top:2px; }
    .top-amount { font-size:14px; font-weight:700; color:#e74c3c; white-space:nowrap; }
    .top-pct { font-size:11px; color:var(--text-secondary); text-align:right; margin-top:1px; }

    /* ===== ESTADO VAZIO ===== */
    .empty-state { text-align:center; padding:48px 20px; }
    .empty-state i { font-size:48px; color:var(--border-color); display:block; margin-bottom:12px; }
    .empty-state h5 { font-size:15px; color:var(--text-secondary); margin-bottom:6px; }
    .empty-state p { font-size:13px; color:var(--text-secondary); margin-bottom:20px; }
    .btn-primary-s { font-size:13px; font-weight:500; padding:9px 20px; border-radius:9px; border:none; background:#3498db; color:#fff; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .btn-primary-s:hover { background:#2980b9; color:#fff; }

    /* ===== DARK MODE SELECTS ===== */
    [data-theme="dark"] .filter-select { background:#1a1d29; color:#ecf0f1; border-color:#34495e; background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ecf0f1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); background-repeat:no-repeat; background-position:right 10px center; background-size:12px; padding-right:30px; appearance:none; -webkit-appearance:none; }
    [data-theme="dark"] .filter-select option { background:#252936; }

    @media(max-width:768px) {
      .metrics-grid { grid-template-columns:repeat(2,1fr); }
      .two-col { grid-template-columns:1fr !important; }
    }
    @media(max-width:480px) {
      .metrics-grid { grid-template-columns:1fr 1fr; }
    }

    /* ===== COMPARAÇÃO ANUAL ===== */
    .compare-summary {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1px;
      background: var(--border-color);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 20px;
    }
    .compare-summary-cell {
      background: var(--bg-primary);
      padding: 14px 10px;
      text-align: center;
      min-width: 0;
    }
    .compare-summary-cell-label {
      font-size: 11px;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-bottom: 5px;
    }
    .compare-summary-cell-value {
      font-size: 18px;
      font-weight: 700;
      word-break: break-all;
      line-height: 1.2;
    }
    .compare-summary-cell-sub {
      font-size: 11px;
      color: var(--text-secondary);
      margin-top: 2px;
    }
    .compare-highlights {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    @media(max-width: 520px) {
      .compare-summary { grid-template-columns: 1fr; gap: 0; }
      .compare-summary-cell { border-bottom: 1px solid var(--border-color); padding: 12px 16px; }
      .compare-summary-cell:last-child { border-bottom: none; }
      .compare-summary-cell-value { font-size: 22px; word-break: normal; }
      .compare-highlights { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='analytics'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="analytics-wrapper">

  <!-- CABEÇALHO -->
  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-graph-up"></i></div>
    <div>
      <h2>Análise de Gastos</h2>
      <p>Visualiza os teus padrões de gastos ao longo do tempo</p>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="a-group">
    <p class="group-label">Filtros</p>
    <div class="a-card">
      <form method="get">
        <div class="filter-row">
          <div class="filter-group">
            <span class="filter-label"><i class="bi bi-calendar3"></i> Ano</span>
            <select name="year" class="filter-select">
              <?php foreach($availableYears as $y): ?>
                <option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <span class="filter-label"><i class="bi bi-credit-card"></i> Cartão</span>
            <select name="card_id" class="filter-select">
              <option value="">Todos os cartões</option>
              <?php foreach($cards as $c): ?>
                <option value="<?=$c['id']?>" <?=$card_id==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i> Aplicar</button>
        </div>
      </form>
    </div>
  </div>

  <?php if (empty($categoryTotals)): ?>
    <div class="a-card">
      <div class="empty-state">
        <i class="bi bi-graph-up"></i>
        <h5>Sem dados para este período</h5>
        <p>Ainda não tens transações registadas em <?=$year?></p>
        <a href="create_transaction.php" class="btn-primary-s"><i class="bi bi-plus-circle"></i> Criar transação</a>
      </div>
    </div>
  <?php else: ?>

  <!-- MÉTRICAS -->
  <div class="a-group">
    <p class="group-label">Resumo <?=$year?></p>
    <div class="metrics-grid">
      <div class="metric">
        <p class="metric-label">Total gasto</p>
        <p class="metric-value blue">€<?=number_format($totalYear,2,'.','')?></p>
        <p class="metric-sub"><?=count($categoryTotals)?> categorias</p>
      </div>
      <div class="metric">
        <p class="metric-label">Transações</p>
        <p class="metric-value"><?=$stats['total_transactions']?></p>
        <p class="metric-sub">em <?=$year?></p>
      </div>
      <div class="metric">
        <p class="metric-label">Média / transação</p>
        <p class="metric-value amber">€<?=number_format($stats['avg_amount'],2,'.','')?></p>
        <p class="metric-sub">por despesa</p>
      </div>
      <div class="metric">
        <p class="metric-label">Maior gasto</p>
        <p class="metric-value red">€<?=number_format($stats['max_amount'],2,'.','')?></p>
        <p class="metric-sub">transação única</p>
      </div>
    </div>
  </div>

  <!-- GRÁFICO MENSAL + TOP 5 -->
  <div class="a-group">
    <p class="group-label">Mensal</p>
    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:12px" class="two-col">

      <!-- Gráfico de linhas por categoria -->
      <div class="a-card" style="display:flex;flex-direction:column">
        <div class="a-card-body" style="display:flex;flex-direction:column;flex:1;justify-content:center">
          <p class="a-card-title"><i class="bi bi-graph-up"></i> Gastos mensais por categoria</p>
          <div class="chart-wrap" style="height:260px">
            <canvas id="monthlyChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Top 5 -->
      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-trophy-fill"></i> Top 5 maiores gastos</p>
          <?php
          $medals = ['#FFD700','#C0C0C0','#CD7F32','#95a5a6','#95a5a6'];
          foreach($topTransactions as $i => $t):
            $pct = $totalYear > 0 ? round(($t['amount']/$totalYear)*100,1) : 0;
          ?>
          <div class="top-item">
            <div class="top-rank" style="background:<?=$medals[$i]?>22;color:<?=$medals[$i]?>"><?=$i+1?></div>
            <div class="top-desc">
              <p class="top-desc-name"><?=htmlspecialchars($t['description'])?></p>
              <p class="top-desc-meta">
                <?=htmlspecialchars($t['category']??'Outros')?>
                <?php if($t['card_name']): ?> · <?=htmlspecialchars($t['card_name'])?><?php endif; ?>
              </p>
            </div>
            <div>
              <p class="top-amount">€<?=number_format($t['amount'],2)?></p>
              <p class="top-pct"><?=$pct?>%</p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- COMPARAÇÃO MÊS A MÊS -->
  <?php
    // Buscar dados do mesmo período do ano anterior
    $prevYear = $year - 1;
    $paramsPrev = [':uid' => $uid, ':year' => $prevYear];
    if ($card_id) $paramsPrev[':cid'] = $card_id;

    $stmtPrev = $pdo->prepare("
        SELECT MONTH(transaction_date) as month, SUM(amount) as total
        FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=:year $cardFilter
        GROUP BY MONTH(transaction_date) ORDER BY month
    ");
    $stmtPrev->execute($paramsPrev);
    $prevMonthlyRows = $stmtPrev->fetchAll();
    $prevMonthlyTotals = array_fill(1, 12, 0);
    foreach ($prevMonthlyRows as $r) $prevMonthlyTotals[intval($r['month'])] = floatval($r['total']);

    // Calcular variação total e meses com dados
    $currentMonthNum = date('n');
    $totalCurrent = array_sum($monthlyTotals);
    $totalPrev    = array_sum($prevMonthlyTotals);
    $hasPrevData  = $totalPrev > 0;

    // Mês atual com mais variação
    // Só comparar meses que já passaram (excluir meses futuros onde o ano atual tem €0 naturalmente)
    $biggestDrop = null; $biggestRise = null;
    for ($m = 1; $m <= $currentMonthNum; $m++) {
        $cur = $monthlyTotals[$m]; $prv = $prevMonthlyTotals[$m];
        if ($cur == 0 && $prv == 0) continue;
        $diff = $cur - $prv;

        if ($prv > 0 && $cur > 0) {
            $pctChange = (($cur - $prv) / $prv) * 100;
            $isNew = false;
        } elseif ($prv == 0 && $cur > 0) {
            $pctChange = PHP_FLOAT_MAX;
            $isNew = true;
        } elseif ($prv > 0 && $cur == 0) {
            $pctChange = -100.0;
            $isNew = false;
        } else {
            continue;
        }

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
  ?>
  <div class="a-group">
    <p class="group-label">Comparação com <?=$prevYear?></p>
    <div class="a-card">
      <div class="a-card-body">
        <p class="a-card-title"><i class="bi bi-arrow-left-right"></i> <?=$year?> vs <?=$prevYear?></p>

        <?php if (!$hasPrevData): ?>
          <div style="text-align:center;padding:24px 0;color:var(--text-secondary);font-size:13px">
            <i class="bi bi-calendar-x" style="font-size:32px;display:block;margin-bottom:8px;color:var(--border-color)"></i>
            Sem dados de <?=$prevYear?> para comparar
          </div>
        <?php else: ?>

        <!-- Resumo rápido -->
        <div class="compare-summary">
          <?php
            $totalDiff = $totalCurrent - $totalPrev;
            $totalPct  = round(($totalDiff / $totalPrev) * 100, 1);
            $isUp = $totalDiff >= 0;
          ?>
          <div class="compare-summary-cell">
            <div class="compare-summary-cell-label"><?=$year?></div>
            <div class="compare-summary-cell-value" style="color:#3498db">€<?=number_format($totalCurrent,2)?></div>
          </div>
          <div class="compare-summary-cell" style="border-left:1px solid var(--border-color);border-right:1px solid var(--border-color)">
            <div class="compare-summary-cell-label">Variação</div>
            <div class="compare-summary-cell-value" style="color:<?=$isUp?'#e74c3c':'#22c55e'?>">
              <?=$isUp?'+':''?><?=$totalPct?>%
            </div>
            <div class="compare-summary-cell-sub"><?=$isUp?'+':''?>€<?=number_format(abs($totalDiff),2)?></div>
          </div>
          <div class="compare-summary-cell">
            <div class="compare-summary-cell-label"><?=$prevYear?></div>
            <div class="compare-summary-cell-value" style="color:var(--text-secondary)">€<?=number_format($totalPrev,2)?></div>
          </div>
        </div>

        <!-- Gráfico comparativo mês a mês -->
        <div class="chart-wrap" style="height:200px;margin-bottom:20px">
          <canvas id="compareChart"></canvas>
        </div>

        <!-- Destaques -->
        <?php if ($biggestRise || $biggestDrop): ?>
        <div class="compare-highlights">
          <?php if ($biggestRise && $biggestRise['pct'] > 0): ?>
          <div style="background:rgba(231,76,60,.07);border:1px solid rgba(231,76,60,.2);border-radius:10px;padding:12px 14px">
            <div style="font-size:11px;font-weight:600;color:#e74c3c;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">
              <i class="bi bi-arrow-up-circle"></i> Maior subida
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?=$monthNames[$biggestRise['month']]?></div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">
              <?php if (!empty($biggestRise['new'])): ?>
                Sem gasto em <?=$prevYear?>
              <?php else: ?>
                +<?=round($biggestRise['pct'],1)?>% vs <?=$prevYear?>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($biggestDrop && $biggestDrop['pct'] < 0): ?>
          <div style="background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:12px 14px">
            <div style="font-size:11px;font-weight:600;color:#16a34a;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">
              <i class="bi bi-arrow-down-circle"></i> Maior poupança
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?=$monthNames[$biggestDrop['month']]?></div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px"><?=round($biggestDrop['pct'],1)?>% vs <?=$prevYear?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CATEGORIAS + CARTÕES -->
  <div class="a-group">
    <p class="group-label">Distribuição</p>
    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:12px" class="two-col">

      <!-- Barras por categoria -->
      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-tag-fill"></i> Por categoria</p>
          <?php
          $maxCat = max(array_column($categoryTotals,'total'));
          foreach($categoryTotals as $cat):
            $pct = ($cat['total']/$maxCat)*100;
            $color = $categoryColors[$cat['category']] ?? '#95a5a6';
            $share = $totalYear > 0 ? round(($cat['total']/$totalYear)*100,1) : 0;
          ?>
          <div class="cat-bar-item">
            <div class="cat-bar-header">
              <span class="cat-bar-name"><?=htmlspecialchars($cat['category'])?></span>
              <span class="cat-bar-val">€<?=number_format($cat['total'],2)?> · <?=$share?>%</span>
            </div>
            <div class="cat-bar-track">
              <div class="cat-bar-fill" style="width:0%;background:<?=$color?>" data-w="<?=$pct?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Doughnut por cartão -->
      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-pie-chart-fill"></i> Por cartão</p>
          <?php if(empty($cardSpending)): ?>
            <div class="empty-state" style="padding:24px 0">
              <i class="bi bi-credit-card-2-front" style="font-size:32px"></i>
              <p style="margin:8px 0 0">Sem dados por cartão</p>
            </div>
          <?php else: ?>
            <div class="chart-wrap" style="height:200px">
              <canvas id="cardChart"></canvas>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <?php endif; ?>

</div><!-- /analytics-wrapper -->
</div><!-- /page-content -->
</main>
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
const tickColor = isDark ? '#9ca3af' : '#6b7280';
const tooltipBg = isDark ? '#1f2937' : '#111827';

Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
Chart.defaults.font.size = 12;

// ── Gráfico de Linhas por Categoria ──
const allMonthLabels = <?=json_encode(array_slice($monthNamesShort, 1))?>;
const catColors = <?=json_encode($categoryColors)?>;
const catMonthly = <?=json_encode($catMonthlyOrdered)?>;

// Cortar até ao último mês com dados (evitar meses em branco no fim)
const allValues = Object.values(catMonthly);
let lastActiveMonth = 0;
allValues.forEach(vals => {
  vals.forEach((v, i) => { if (v > 0 && i > lastActiveMonth) lastActiveMonth = i; });
});
const cutoff = Math.min(lastActiveMonth, 11); // até ao último mês com dados
const monthLabels = allMonthLabels.slice(0, cutoff + 1);

const lineDatasets = Object.entries(catMonthly).map(([cat, values]) => {
  const sliced = values.slice(0, cutoff + 1);
  return {
    label: cat,
    data: sliced,
    borderColor: catColors[cat] || '#95a5a6',
    backgroundColor: (catColors[cat] || '#95a5a6') + '18',
    borderWidth: 2.5,
    pointRadius: sliced.map(v => v > 0 ? 4 : 0),
    pointHoverRadius: 6,
    pointBackgroundColor: catColors[cat] || '#95a5a6',
    tension: 0.35,
    fill: false,
  };
});

new Chart(document.getElementById('monthlyChart'), {
  type: 'line',
  data: { labels: monthLabels, datasets: lineDatasets },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        position: 'bottom',
        labels: { color: tickColor, padding: 14, boxWidth: 10, font: { size: 12 } }
      },
      tooltip: {
        backgroundColor: tooltipBg, padding: 10, cornerRadius: 8,
        callbacks: {
          label: ctx => ' ' + ctx.dataset.label + ': €' + ctx.parsed.y.toFixed(2),
          filter: item => item.parsed.y > 0
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: tickColor, callback: v => '€' + v }
      },
      x: { grid: { display: false }, ticks: { color: tickColor } }
    }
  }
});

<?php if (!empty($cardSpending)): ?>
// ── Doughnut Cartões ──
const cardLabels = <?=json_encode(array_column($cardSpending,'name'))?>;
const cardData   = <?=json_encode(array_map(fn($c)=>floatval($c['total']),$cardSpending))?>;

new Chart(document.getElementById('cardChart'), {
  type: 'doughnut',
  data: {
    labels: cardLabels,
    datasets: [{
      data: cardData,
      backgroundColor: ['#3498db','#1abc9c','#e74c3c','#f39c12','#9b59b6','#34495e'],
      borderWidth: 2,
      borderColor: isDark ? '#252936' : '#ffffff',
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '65%',
    plugins: {
      legend: { position: 'bottom', labels: { color: tickColor, padding: 12, boxWidth: 10, font: { size: 12 } } },
      tooltip: {
        backgroundColor: tooltipBg, padding: 10, cornerRadius: 8,
        callbacks: {
          label: ctx => {
            const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
            return ' €' + ctx.parsed.toFixed(2) + ' (' + ((ctx.parsed/total)*100).toFixed(1) + '%)';
          }
        }
      }
    }
  }
});
<?php endif; ?>

// ── Gráfico Comparação Anual ──
<?php if ($hasPrevData): ?>
const compareLabels = <?=json_encode(array_slice($monthNamesShort, 1))?>;
const compareCurrentData = <?=json_encode(array_values($monthlyTotals))?>;
const comparePrevData    = <?=json_encode(array_values($prevMonthlyTotals))?>;

new Chart(document.getElementById('compareChart'), {
  type: 'bar',
  data: {
    labels: compareLabels,
    datasets: [
      {
        label: '<?=$year?>',
        data: compareCurrentData,
        backgroundColor: '#3498db',
        borderRadius: 5,
        borderSkipped: false,
      },
      {
        label: '<?=$prevYear?>',
        data: comparePrevData,
        backgroundColor: isDark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.08)',
        borderRadius: 5,
        borderSkipped: false,
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { color: tickColor, padding: 14, boxWidth: 10, font: { size: 12 } } },
      tooltip: {
        backgroundColor: tooltipBg, padding: 10, cornerRadius: 8,
        callbacks: { label: ctx => ' ' + ctx.dataset.label + ': €' + ctx.parsed.y.toFixed(2) }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: tickColor, callback: v => '€' + v }
      },
      x: { grid: { display: false }, ticks: { color: tickColor } }
    }
  }
});
<?php endif; ?>


document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    document.querySelectorAll('.cat-bar-fill').forEach(b => {
      b.style.width = b.dataset.w;
    });
  }, 150);
});
</script>
</body>
</html>