<?php
// site/analytics.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);


// Filtros
$year = !empty($_GET['year']) ? intval($_GET['year']) : date('Y');
$card_id = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;

// Buscar anos disponíveis
$stmt = $pdo->prepare("
    SELECT DISTINCT YEAR(created_at) as year 
    FROM transactions 
    WHERE user_id = :uid 
    ORDER BY year DESC
");
$stmt->execute([':uid' => $uid]);
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar cartões
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Dados por mês (para o gráfico de linhas)
$monthlyData = [];
$categories = ['Compras', 'Alimentação', 'Transporte', 'Saúde', 'Entretenimento', 'Educação', 'Casa', 'Outros'];

// Inicializar array para cada categoria com 12 meses (índice 0-11)
foreach ($categories as $cat) {
    $monthlyData[$cat] = array_fill(0, 12, 0);
}

$sql = "
    SELECT 
        MONTH(transaction_date) as month,
        COALESCE(category, 'Outros') as category,
        SUM(amount) as total
    FROM transactions 
    WHERE user_id = :uid 
    AND YEAR(transaction_date) = :year
";
$params = [':uid' => $uid, ':year' => $year];

if ($card_id) {
    $sql .= " AND card_id = :cid";
    $params[':cid'] = $card_id;
}

$sql .= " GROUP BY MONTH(transaction_date), category";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

foreach ($results as $row) {
    $month = intval($row['month']) - 1;
    $category = $row['category'];
    $total = floatval($row['total']);
    
    if (isset($monthlyData[$category])) {
        $monthlyData[$category][$month] = $total;
    } else {
        $monthlyData['Outros'][$month] += $total;
    }
}

// Total por categoria
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(category, 'Outros') as category,
        SUM(amount) as total,
        COUNT(*) as count
    FROM transactions 
    WHERE user_id = :uid 
    AND YEAR(transaction_date) = :year
    " . ($card_id ? "AND card_id = :cid" : "") . "
    GROUP BY category
    ORDER BY total DESC
");
$stmt->execute($params);
$categoryTotals = $stmt->fetchAll();

// Total do ano
$totalYear = array_sum(array_column($categoryTotals, 'total'));

// Estatísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        AVG(amount) as avg_amount,
        MAX(amount) as max_amount,
        MIN(amount) as min_amount
    FROM transactions 
    WHERE user_id = :uid 
    AND YEAR(transaction_date) = :year
    " . ($card_id ? "AND card_id = :cid" : "")
);
$stmt->execute($params);
$stats = $stmt->fetch();

// Cores para categorias
$categoryColors = [
    'Compras' => '#3498db',
    'Alimentação' => '#e74c3c',
    'Transporte' => '#f39c12',
    'Saúde' => '#1abc9c',
    'Entretenimento' => '#9b59b6',
    'Educação' => '#34495e',
    'Casa' => '#e67e22',
    'Outros' => '#95a5a6'
];

$months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Análise de Gastos - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <style>
    /* ========== BACKGROUND ANIMADO ========== */
    body {
      position: relative;
      min-height: 100vh;
    }
    .bg-animation {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
    }
    [data-theme="light"] body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #e8eef5ff 0%, #e9eef8ff 50%, #e8edf5ff 100%);
      z-index: -1;
    }
    [data-theme="dark"] body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #2b3035 0%, #323844 50%, #2b3035 100%);
      z-index: -1;
    }
    .floating-shape {
      position: absolute;
      border-radius: 50%;
      animation: float 20s infinite ease-in-out;
    }
    [data-theme="light"] .floating-shape {
      background: rgba(46, 88, 204);
    }
    [data-theme="dark"] .floating-shape {
      background: rgba(46, 88, 204);
    }
    .shape1 { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
    .shape2 { width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: 5s; }
    .shape3 { width: 150px; height: 150px; top: 50%; right: 10%; animation-delay: 2s; }
    .shape4 { width: 100px; height: 100px; bottom: 20%; left: 15%; animation-delay: 7s; }
    .shape5 { width: 250px; height: 250px; top: 30%; left: 50%; animation-delay: 3s; }
    .shape6 { width: 250px; height: 300px; top: -100px; right: -100px; animation-delay: 6s; }
    .shape7 { width: 180px; height: 180px; top: 10%; left: 70%; animation-delay: 1s; }
    .shape8 { width: 120px; height: 120px; bottom: 10%; right: 30%; animation-delay: 4s; }
    .shape9 { width: 220px; height: 220px; top: 60%; left: -80px; animation-delay: 8s; }
    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.3; }
      50% { transform: translateY(-30px) rotate(180deg); opacity: 0.6; }
    }
    .particle {
      position: absolute;
      width: 4px;
      height: 4px;
      border-radius: 50%;
      animation: rise 15s infinite ease-in;
    }
    [data-theme="light"] .particle { background: rgba(46, 88, 204); }
    [data-theme="dark"] .particle { background: rgba(46, 88, 204); }
    @keyframes rise {
      0% { transform: translateY(0) translateX(0); opacity: 0; }
      10% { opacity: 1; }
      90% { opacity: 1; }
      100% { transform: translateY(-100vh) translateX(50px); opacity: 0; }
    }
    .navbar, .container { position: relative; z-index: 1; }
    @media (max-width: 768px) {
      .floating-shape { opacity: 0.4; animation-duration: 25s; }
      .particle { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
      .floating-shape, .particle { animation: none; opacity: 0.2; }
    }
    :root {
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-primary);
      color: var(--text-primary);
    }
    .navbar { 
      box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
      background: var(--navbar-bg);
    }
    .navbar-brand img { height: 35px; margin-right: 8px; }
    .btn-primary {
      background: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .btn-primary:hover {
      background: var(--dark-blue);
      border-color: var(--dark-blue);
    }
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px var(--shadow);
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    
    .chart-container {
      position: relative;
      height: 400px;
      padding: 20px;
      background: var(--bg-secondary);
      border-radius: 16px;
    }
    
    .legend {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 20px;
      justify-content: center;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      cursor: pointer;
      transition: opacity 0.2s;
    }
    
    .legend-item:hover {
      opacity: 0.7;
    }
    
    .legend-color {
      width: 20px;
      height: 3px;
      border-radius: 2px;
    }
    
    .bar-chart {
      padding: 20px 0;
    }
    
    .bar-item {
      margin-bottom: 20px;
    }
    
    .bar-label {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }
    
    .bar-container {
      background: var(--bg-hover);
      border-radius: 10px;
      height: 32px;
      position: relative;
      overflow: hidden;
    }
    
    .bar-fill {
      height: 100%;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding-right: 12px;
      color: white;
      font-weight: 600;
      font-size: 13px;
      transition: width 1s ease-out;
    }
    
    .summary-card {
      background: var(--bg-secondary);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px var(--shadow);
    }
    .stat-item {
      text-align: center;
      padding: 16px;
    }
    .stat-item h3 {
      font-size: 28px;
      font-weight: 800;
      margin-bottom: 4px;
      color: var(--text-primary);
    }
    .stat-item p {
      font-size: 13px;
      margin: 0;
      color: var(--text-secondary);
    }
    .stat-item:not(:last-child) {
      border-right: 1px solid var(--border-color);
    }
    
    .card-usage-item {
      padding: 12px;
      background: var(--bg-primary);
      border-radius: 8px;
    }
    
    .card-usage-item .progress-bar {
      display: flex;
      align-items: center;
      justify-content: center;
      transition: width 1s ease-out;
    }
    
    /* Tema escuro */
    [data-theme="dark"] .text-muted {
      color: var(--text-secondary) !important;
    }
    
    .form-select {
      background-color: var(--bg-primary);
      color: var(--text-primary);
      border-color: var(--border-color);
      transition: all 0.3s;
    }
    
    .form-select:focus {
      background-color: var(--bg-primary);
      color: var(--text-primary);
      border-color: var(--primary-blue);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    
    /* Tema escuro - Selects */
    [data-theme="dark"] .form-select {
      background-color: #2b3035;
      color: #ecf0f1;
      border-color: #34495e;
      /* Seta do dropdown em branco */
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ecf0f1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    }
    
    [data-theme="dark"] .form-select option {
      background-color: #323844;
      color: #ecf0f1;
    }
    
    [data-theme="dark"] .form-select:focus {
      background-color: #2b3035;
      color: #ecf0f1;
      border-color: #2ecc71;
      box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
    }
    
    /* Labels dos filtros */
    [data-theme="dark"] .form-label {
      color: #ecf0f1;
    }
    
    [data-theme="dark"] .small.fw-semibold {
      color: #ecf0f1;
    }
    
    /* Card dos filtros */
    [data-theme="dark"] .card .card-body {
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    
    /* Garantir que os ícones também seguem a cor */
    [data-theme="dark"] .form-label .bi {
      color: #ecf0f1;
    }

    /* Estilização da scrollbar para listas roláveis */
    .scrollable-list::-webkit-scrollbar {
      width: 8px;
    }

    .scrollable-list::-webkit-scrollbar-track {
      background: var(--bg-primary);
    }

    .scrollable-list::-webkit-scrollbar-thumb {
      background: var(--border-color);
      border-radius: 4px;
    }

    .scrollable-list::-webkit-scrollbar-thumb:hover {
      background: var(--text-secondary);
    }

    /* Firefox */
    .scrollable-list {
      scrollbar-color: var(--border-color) var(--bg-primary);
      padding-right: 5px;
    }
  </style>
</head>
<body>
<!-- Background animado -->
<div class="bg-animation">
  <div class="floating-shape shape1"></div>
  <div class="floating-shape shape2"></div>
  <div class="floating-shape shape3"></div>
  <div class="floating-shape shape4"></div>
  <div class="floating-shape shape5"></div>
  <div class="floating-shape shape6"></div>
  <div class="floating-shape shape7"></div>
  <div class="floating-shape shape8"></div>  
  <div class="floating-shape shape9"></div>
</div>
<nav class="navbar navbar-expand-lg navbar-light">
  <div class="container">
    <a class="navbar-brand fw-bold" href="../dashboard.php">
      <img src="../assets/logo2.png" alt="Freecard">
      FreeCard
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="cards.php"><i class="bi bi-wallet2"></i> Cartões</a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="bi bi-receipt"></i> Transações</a></li>
        <li class="nav-item"><a class="nav-link" href="budgets.php"><i class="bi bi-piggy-bank"></i> Orçamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="reminders.php"><i class="bi bi-calendar-check"></i> Lembretes</a></li>
        <li class="nav-item"><a class="nav-link active" href="analytics.php"><i class="bi bi-graph-up"></i> Análise</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?=htmlspecialchars($_SESSION['username'])?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Configurações</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 mb-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2><i class="bi bi-graph-up"></i> Análise de Gastos</h2>
      <p class="text-muted mb-0">Visualiza os teus padrões de gastos ao longo do tempo</p>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label small fw-semibold">
            <i class="bi bi-calendar"></i> Ano
          </label>
          <select name="year" class="form-select">
            <?php if (empty($availableYears)): ?>
              <option value="<?=date('Y')?>"><?=date('Y')?></option>
            <?php else: ?>
              <?php foreach($availableYears as $y): ?>
                <option value="<?=$y?>" <?=$year == $y ? 'selected' : ''?>><?=$y?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">
            <i class="bi bi-credit-card"></i> Cartão
          </label>
          <select name="card_id" class="form-select">
            <option value="">Todos os cartões</option>
            <?php foreach($cards as $c): ?>
            <option value="<?=$c['id']?>" <?=$card_id == $c['id'] ? 'selected' : ''?>>
                <?=htmlspecialchars($c['name'])?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Filtrar
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php if (empty($categoryTotals)): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <div class="mb-4">
          <i class="bi bi-graph-up" style="font-size: 80px; color: #e0e0e0;"></i>
        </div>
        <h4 class="text-muted mb-3">Sem dados para análise</h4>
        <p class="text-muted mb-4">Ainda não tens transações registadas para este período</p>
        <a href="create_transaction.php" class="btn btn-primary">
          <i class="bi bi-plus-circle"></i> Criar Transação
        </a>
      </div>
    </div>
  <?php else: ?>

  <!-- Estatísticas -->
  <div class="summary-card mb-4">
    <div class="row">
      <div class="col-md-3">
        <div class="stat-item">
          <h3 class="text-success">€<?=number_format($totalYear, 2)?></h3>
          <p>Total Gasto</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item">
          <h3 style="color: #3498db;"><?=$stats['total_transactions']?></h3>
          <p>Transações</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item">
          <h3 style="color: #f39c12;">€<?=number_format($stats['avg_amount'], 2)?></h3>
          <p>Média por Transação</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item">
          <h3 class="text-danger">€<?=number_format($stats['max_amount'], 2)?></h3>
          <p>Maior Gasto</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráficos de Cartões e Insights -->
  <div class="row mb-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-4">
            <i class="bi bi-pie-chart"></i> Distribuição de Gastos por Cartão
          </h5>
          <?php
          $stmtCardSpending = $pdo->prepare("
            SELECT c.name, COALESCE(SUM(t.amount), 0) as total
            FROM cards c
            LEFT JOIN transactions t ON t.card_id = c.id
              AND YEAR(t.transaction_date) = :year
              " . ($card_id ? "AND c.id = :cid" : "") . "
            WHERE c.user_id = :uid
            GROUP BY c.id
            HAVING total > 0
            ORDER BY total DESC
          ");
          $cardSpendParams = [':uid' => $uid, ':year' => $year];
          if ($card_id) {
            $cardSpendParams[':cid'] = $card_id;
          }
          $stmtCardSpending->execute($cardSpendParams);
          $cardSpending = $stmtCardSpending->fetchAll();
          
          if (empty($cardSpending)): ?>
            <div class="text-center py-5">
              <i class="bi bi-credit-card-2-front" style="font-size: 64px; color: #e0e0e0;"></i>
              <h6 class="text-muted mt-3 mb-2">Sem transações por cartão</h6>
              <p class="text-muted small mb-0">
                Nenhum dos teus cartões tem transações registadas neste período.
              </p>
            </div>
          <?php else: ?>
            <div class="chart-container" style="height: 300px;">
              <canvas id="cardPieChart"></canvas>
            </div>
            <div class="mt-3 text-center">
              <small class="text-muted">Mostra a distribuição percentual de gastos entre os teus cartões</small>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-4">
            <i class="bi bi-trophy"></i> Top 5 Maiores Gastos
          </h5>
          <div class="scrollable-list" style="max-height: 350px; overflow-y: auto;">
            <?php
            $stmtTopTransactions = $pdo->prepare("
              SELECT t.*, c.name as card_name
              FROM transactions t
              LEFT JOIN cards c ON c.id = t.card_id
              WHERE t.user_id = :uid
              AND YEAR(t.transaction_date) = :year
              " . ($card_id ? "AND t.card_id = :cid" : "") . "
              ORDER BY t.amount DESC
              LIMIT 5
            ");
            $topParams = [':uid' => $uid, ':year' => $year];
            if ($card_id) {
              $topParams[':cid'] = $card_id;
            }
            $stmtTopTransactions->execute($topParams);
            $topTransactions = $stmtTopTransactions->fetchAll();
            
            if (empty($topTransactions)): ?>
              <div class="text-center py-4">
                <i class="bi bi-receipt" style="font-size: 48px; color: #e0e0e0;"></i>
                <p class="text-muted mt-3 mb-0">Sem transações registadas</p>
              </div>
            <?php else:
              $position = 1;
              foreach($topTransactions as $topT):
                $medalColor = match($position) {
                  1 => '#FFD700',
                  2 => '#C0C0C0',
                  3 => '#CD7F32',
                  default => '#95a5a6'
                };
            ?>
              <div class="card-usage-item mb-3" <?=$medalColor?>;">
                <div class="d-flex align-items-center gap-3">
                  <div class="flex-shrink-0 text-center" style="width: 40px;">
                    <div style="font-size: 24px; font-weight: 800; color: <?=$medalColor?>;">
                      #<?=$position?>
                    </div>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold mb-1"><?=htmlspecialchars($topT['description'])?></div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                      <?php if($topT['category']): ?>
                        <small class="badge bg-secondary"><?=htmlspecialchars($topT['category'])?></small>
                      <?php endif; ?>
                      <?php if($topT['card_name']): ?>
                        <small class="text-muted">
                          <i class="bi bi-credit-card"></i> <?=htmlspecialchars($topT['card_name'])?>
                        </small>
                      <?php endif; ?>
                      <small class="text-muted">
                        <i class="bi bi-calendar"></i> <?=date('d/m/Y', strtotime($topT['created_at']))?>
                      </small>
                    </div>
                  </div>
                  <div class="flex-shrink-0 text-end">
                    <div class="fw-bold text-danger" style="font-size: 18px;">
                      €<?=number_format($topT['amount'], 2)?>
                    </div>
                    <?php if ($totalYear > 0): ?>
                      <small class="text-muted">
                        <?=round(($topT['amount'] / $totalYear) * 100, 1)?>% do total
                      </small>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php 
                $position++;
              endforeach; 
            endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráfico de Linhas -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-4">
        <i class="bi bi-graph-up"></i> Evolução Mensal por Categoria (<?=$year?>)
      </h5>
      
      <div class="chart-container">
        <canvas id="lineChart" width="800" height="400"></canvas>
      </div>
      
      <div class="legend" id="legend">
        <?php foreach($categories as $cat): ?>
          <div class="legend-item" data-category="<?=$cat?>">
            <div class="legend-color" style="background: <?=$categoryColors[$cat]?>;"></div>
            <span><?=$cat?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Gráfico de Barras -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title mb-4">
        <i class="bi bi-bar-chart"></i> Total por Categoria (<?=$year?>)
      </h5>
      
      <div class="bar-chart">
        <?php 
        $maxTotal = !empty($categoryTotals) ? max(array_column($categoryTotals, 'total')) : 1;
        foreach($categoryTotals as $cat): 
          $percentage = ($cat['total'] / $maxTotal) * 100;
          $color = $categoryColors[$cat['category']] ?? '#95a5a6';
        ?>
        <div class="bar-item">
          <div class="bar-label">
            <span><strong><?=htmlspecialchars($cat['category'])?></strong> <small class="text-muted">(<?=$cat['count']?> transações)</small></span>
            <span class="text-danger fw-bold">€<?=number_format($cat['total'], 2)?></span>
          </div>
          <div class="bar-container">
            <div class="bar-fill" 
                 style="background: <?=$color?>; width: 0%;"
                 data-width="<?=$percentage?>%">
              <?=round(($cat['total'] / $totalYear) * 100)?>%
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const monthlyData = <?=json_encode($monthlyData)?>;
const categoryColors = <?=json_encode($categoryColors)?>;
const months = <?=json_encode($months)?>;

const ctx = document.getElementById('lineChart').getContext('2d');

const datasets = Object.keys(monthlyData).map(category => ({
  label: category,
  data: Object.values(monthlyData[category]),
  borderColor: categoryColors[category],
  backgroundColor: categoryColors[category] + '20',
  borderWidth: 3,
  tension: 0.4,
  pointRadius: 5,
  pointHoverRadius: 7,
  pointBackgroundColor: categoryColors[category],
  pointBorderColor: '#fff',
  pointBorderWidth: 2
}));

const lineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: months,
    datasets: datasets
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        padding: 12,
        titleFont: {
          size: 14,
          weight: 'bold'
        },
        bodyFont: {
          size: 13
        },
        filter: function(tooltipItem) {
          return tooltipItem.parsed.y > 0;
        },
        callbacks: {
          label: function(context) {
            return context.dataset.label + ': €' + context.parsed.y.toFixed(2);
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: 'rgba(128, 128, 128, 0.1)'
        },
        ticks: {
          callback: function(value) {
            return '€' + value;
          },
          color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
        }
      },
      x: {
        grid: {
          display: false
        },
        ticks: {
          color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
        }
      }
    }
  }
});

<?php
$cardLabels = array_map(function($c) {
  return $c['name'];
}, $cardSpending);
$cardData = array_map(function($c) {
  return floatval($c['total']);
}, $cardSpending);
?>

<?php if (!empty($cardSpending)): ?>
const cardPieCtx = document.getElementById('cardPieChart').getContext('2d');

const cardPieData = {
  labels: <?=json_encode($cardLabels)?>,
  datasets: [{
    data: <?=json_encode($cardData)?>,
    backgroundColor: [
      '#3498db',
      '#e74c3c', 
      '#f39c12',
      '#1abc9c',
      '#9b59b6',
      '#34495e',
      '#e67e22',
      '#95a5a6'
    ],
    borderWidth: 2,
    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--bg-secondary')
  }]
};

const cardPieChart = new Chart(cardPieCtx, {
  type: 'doughnut',
  data: cardPieData,
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          padding: 15,
          font: {
            size: 12
          },
          color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
        }
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        padding: 12,
        callbacks: {
          label: function(context) {
            const label = context.label || '';
            const value = context.parsed || 0;
            const total = context.dataset.data.reduce((a, b) => a + b, 0);
            const percentage = ((value / total) * 100).toFixed(1);
            return label + ': €' + value.toFixed(2) + ' (' + percentage + '%)';
          }
        }
      }
    }
  }
});
<?php endif; ?>

document.querySelectorAll('.legend-item').forEach((item, index) => {
  item.addEventListener('click', function() {
    const meta = lineChart.getDatasetMeta(index);
    meta.hidden = !meta.hidden;
    lineChart.update();
    this.style.opacity = meta.hidden ? '0.3' : '1';
  });
});

document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    document.querySelectorAll('.bar-fill').forEach(bar => {
      bar.style.width = bar.dataset.width;
    });
  }, 100);
});

<!-- Gerar partículas (apenas desktop) -->

(function() {
  if (window.innerWidth <= 768) return;
  const container = document.querySelector('.bg-animation');
  if (!container) return;
  for (let i = 0; i < 15; i++) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    particle.style.left = Math.random() * 100 + '%';
    particle.style.animationDelay = Math.random() * 15 + 's';
    particle.style.animationDuration = (15 + Math.random() * 10) + 's';
    container.appendChild(particle);
  }
})();

</script>
</body>
</html>