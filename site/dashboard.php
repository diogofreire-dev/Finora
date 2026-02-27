<?php
// site/dashboard.php
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/budget_alert_helper.php';
require_once __DIR__ . '/components/notifications_panel.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

// Buscar configurações do usuário
$stmt = $pdo->prepare("SELECT notifications FROM user_settings WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$userSettings = $stmt->fetch();
$notificationsEnabled = $userSettings['notifications'] ?? 1; // padrão ativado se não definido

if (!$uid) {
    header('Location: auth/login.php');
    exit;
}

// Verificar e enviar alertas de orçamento
if ($notificationsEnabled) {
    $userStmt = $pdo->prepare("SELECT email, username FROM users WHERE id = :uid");
    $userStmt->execute([':uid' => $uid]);
    $user = $userStmt->fetch();
    
    if ($user) {
        checkAndSendBudgetAlerts($pdo, $uid, $user['email'], $user['username']);
    }
}

// Total gasto no mês
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) AS total_month 
    FROM transactions 
    WHERE user_id = :uid 
    AND transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$stmt->execute([':uid' => $uid]);
$totalMonth = $stmt->fetchColumn();

// Total mês anterior
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) AS total_last_month 
    FROM transactions 
    WHERE user_id = :uid 
    AND transaction_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
    AND transaction_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$stmt->execute([':uid' => $uid]);
$totalLastMonth = $stmt->fetchColumn();

// Transacções últimos 30 dias
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM transactions 
    WHERE user_id = :uid 
    AND transaction_date >= NOW() - INTERVAL 30 DAY
");
$stmt->execute([':uid' => $uid]);
$count30 = $stmt->fetchColumn();

// Gastos por categoria (mês atual)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(category, 'Sem Categoria') as category,
        SUM(amount) as total,
        COUNT(*) as count
    FROM transactions 
    WHERE user_id = :uid 
    AND transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    GROUP BY category
    ORDER BY total DESC
");
$stmt->execute([':uid' => $uid]);
$categoryData = $stmt->fetchAll();

// Maior despesa do mês
$stmt = $pdo->prepare("
    SELECT description, amount, category
    FROM transactions 
    WHERE user_id = :uid 
    AND transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ORDER BY amount DESC
    LIMIT 1
");
$stmt->execute([':uid' => $uid]);
$biggestExpense = $stmt->fetch();

// Últimos registos
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS card_name
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid
    ORDER BY t.created_at DESC
    LIMIT 8
");
$stmt->execute([':uid' => $uid]);
$recent = $stmt->fetchAll();

// Cartões com estatísticas
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color, c.limit_amount, c.balance, c.active, c.created_at,
           COALESCE(SUM(t.amount), 0) as total_spent,
           COUNT(t.id) as transaction_count
    FROM cards c
    LEFT JOIN transactions t ON t.card_id = c.id
    WHERE c.user_id = :uid
    GROUP BY c.id
    ORDER BY c.active DESC, c.created_at DESC
");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Mapeamento de cores
$cardColors = [
    'purple' => 'linear-gradient(135deg, #667eea 0%, #667eea 100%)',
    'blue' => 'linear-gradient(135deg, #2196F3 0%, #2196F3 100%)',
    'green' => 'linear-gradient(135deg, #13d168ff 0%, #13d168ff 100%)',
    'orange' => 'linear-gradient(135deg, #FF9800 0%, #FF9800 100%)',
    'red' => 'linear-gradient(135deg, #f44336 0%, #f44336 100%)',
    'pink' => 'linear-gradient(135deg, #E91E63 0%, #E91E63 100%)',
    'teal' => 'linear-gradient(135deg, #00BCD4 0%, #00BCD4 100%)',
    'indigo' => 'linear-gradient(135deg, #3F51B5 0%, #3F51B5 100%)'
];

// Alertas: cartão com >80% do limite
$alerts = [];
foreach ($cards as $card) {
    if ($card['limit_amount'] > 0) {
        $pct = ($card['balance'] / $card['limit_amount']) * 100;
        if ($pct >= 80) {
            $alerts[] = "Cartão {$card['name']} atingiu " . round($pct) . "% do limite.";
        }
    }
}

// ORÇAMENTOS
$stmt = $pdo->prepare("
    SELECT
        b.*,
        COALESCE(SUM(CASE
            WHEN b.period = 'monthly' AND t.transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND t.transaction_date <= LAST_DAY(CURDATE()) THEN t.amount
            WHEN b.period = 'weekly' AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                AND t.transaction_date <= CURDATE() THEN t.amount
            WHEN b.period = 'yearly' AND YEAR(t.transaction_date) = YEAR(CURDATE()) THEN t.amount
            ELSE 0
        END), 0) as current_spent
    FROM budgets b
    LEFT JOIN transactions t ON t.user_id = b.user_id
        AND (b.category IS NULL OR t.category = b.category)
        AND (b.card_id IS NULL OR t.card_id = b.card_id)
    WHERE b.user_id = :uid
    AND b.active = 1
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 1
");
$stmt->execute([':uid' => $uid]);
$mainBudget = $stmt->fetch();

$budgetPercentage = 0;
$budgetRemaining = 0;
$budgetAlert = null;

if ($mainBudget) {
    $budgetPercentage = ($mainBudget['current_spent'] / $mainBudget['amount']) * 100;
    $budgetRemaining = $mainBudget['amount'] - $mainBudget['current_spent'];
    
    if ($budgetPercentage >= 100) {
        $budgetAlert = [
            'type' => 'danger',
            'icon' => 'exclamation-triangle-fill',
            'message' => 'Orçamento excedido! Já gastaste €' . number_format($mainBudget['current_spent'] - $mainBudget['amount'], 2) . ' acima do limite.'
        ];
    } elseif ($budgetPercentage >= 80) {
        $budgetAlert = [
            'type' => 'warning',
            'icon' => 'exclamation-circle-fill',
            'message' => 'Atenção! Já usaste ' . round($budgetPercentage) . '% do teu orçamento mensal.'
        ];
    }
}

$categoryColors = [
    'Compras' => '#3498db',
    'Alimentação' => '#e74c3c',
    'Transporte' => '#f39c12',
    'Saúde' => '#1abc9c',
    'Entretenimento' => '#9b59b6',
    'Educação' => '#34495e',
    'Casa' => '#e67e22',
    'Outros' => '#95a5a6',
    'Sem Categoria' => '#bdc3c7'
];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="css/theme.css">
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
    /* ========== FIM BACKGROUND ========== */

    :root {
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--text-primary);
    }
    .card { 
      transition: transform 0.2s; 
      border: none;
      box-shadow: 0 2px 10px var(--shadow);
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    .card:hover { transform: translateY(-5px); }
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
    .btn-outline-primary {
      color: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .btn-outline-primary:hover {
      background: var(--primary-blue);
      border-color: var(--primary-blue);
      color: white;
    }
    .text-primary { color: var(--primary-blue) !important; }
    
    .summary-card {
      background: var(--bg-secondary);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px var(--shadow);
    }
    .summary-stat {
      margin-bottom: 20px;
    }
    .summary-stat-value {
      font-size: 32px;
      font-weight: 800;
      color: var(--primary-blue);
      margin-bottom: 4px;
    }
    .summary-stat-label {
      font-size: 13px;
      color: var(--text-secondary);
      margin: 0;
    }
    
    .card-visual {
      border-radius: 12px;
      padding: 16px;
      color: white;
      min-height: 120px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
      margin-bottom: 12px;
    }
    .card-visual::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -30%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    }
    .card-visual-inactive {
      background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%) !important;
      opacity: 0.7;
    }
    .card-number {
      font-size: 16px;
      letter-spacing: 2px;
      font-weight: 600;
      position: relative;
    }
    .card-name {
      font-size: 12px;
      text-transform: uppercase;
      font-weight: 700;
      position: relative;
    }
    
    .category-bar-container {
      margin-bottom: 20px;
    }
    .category-bar-label {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
      color: var(--text-primary);
    }
    .category-bar-wrapper {
      background: var(--bg-hover);
      border-radius: 10px;
      height: 32px;
      overflow: hidden;
      position: relative;
    }
    .category-bar {
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
      background: linear-gradient(90deg, var(--bar-color), var(--bar-color-light));
    }
    
    .stat-mini-card {
      background: var(--bg-secondary);
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 20px var(--shadow);
      height: 100%;
      transition: transform 0.2s;
      color: var(--text-primary);
    }
    .stat-mini-card:hover {
      transform: translateY(-5px);
    }
    .stat-mini-card .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      flex-shrink: 0;
      border: 1px solid currentColor;
    }
    .stat-mini-card .stat-label {
      font-size: 13px;
      color: var(--text-secondary);
      margin-bottom: 8px;
    }
    .stat-mini-card .stat-value {
      font-size: 32px;
      font-weight: 800;
      margin-bottom: 4px;
      line-height: 1;
      color: var(--text-primary);
    }
    .stat-mini-card .stat-description {
      font-size: 14px;
      color: var(--text-secondary);
      margin: 0;
    }
    
    .transaction-item {
      background: var(--bg-primary);
      border: 1px solid var(--border-color);
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 10px;
      transition: all 0.2s;
    }
    .transaction-item:hover {
      background: var(--bg-hover);
      border-color: var(--primary-blue);
    }
    .transaction-item:last-child {
      margin-bottom: 0;
    }
    
    [data-theme="dark"] .text-muted {
      color: var(--text-secondary) !important;
    }
    [data-theme="dark"] .border {
      border-color: var(--border-color) !important;
    }
    [data-theme="dark"] .bg-light {
      background: var(--bg-hover) !important;
      color: var(--text-primary);
    }
    [data-theme="dark"] .badge {
      background: var(--bg-hover) !important;
      color: var(--text-primary) !important;
    }
    [data-theme="dark"] .badge.bg-info {
      background: #3498db !important;
      color: white !important;
    }
    [data-theme="dark"] .badge.bg-success {
      background: #27ae60 !important;
      color: white !important;
    }
    [data-theme="dark"] .badge.bg-secondary {
      background: #7f8c8d !important;
      color: white !important;
    }
    [data-theme="dark"] .progress {
      background: var(--bg-hover);
    }
    [data-theme="dark"] .alert-link {
      color: #0dcaf0 !important;
    }
  </style>
</head>
<body>

<!-- Painel de Notificações -->
<?php require_once __DIR__ . '/components/notifications_panel.php'; ?>

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
    <a class="navbar-brand fw-bold" href="dashboard.php">
      <img src="assets/logo2.png" alt="Freecard">
      FreeCard
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="pages/cards.php"><i class="bi bi-wallet2"></i> Cartões</a></li>
        <li class="nav-item"><a class="nav-link" href="pages/transactions.php"><i class="bi bi-receipt"></i> Transações</a></li>
        <li class="nav-item"><a class="nav-link" href="pages/budgets.php"><i class="bi bi-piggy-bank"></i> Orçamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="pages/reminders.php"><i class="bi bi-calendar-check"></i> Lembretes</a></li>
        <li class="nav-item"><a class="nav-link" href="pages/analytics.php"><i class="bi bi-graph-up"></i> Análise</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?=htmlspecialchars($_SESSION['username'])?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="pages/settings.php"><i class="bi bi-gear"></i> Configurações</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="auth/logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 pb-5">
  <?php if ($notificationsEnabled && !empty($alerts)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <strong><i class="bi bi-exclamation-triangle"></i> Alertas:</strong>
      <ul class="mb-0 mt-2">
        <?php foreach($alerts as $a): ?>
          <li><?=htmlspecialchars($a)?></li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php
    // Buscar lembretes vencidos e próximos
    $stmt = $pdo->prepare("
        SELECT r.*, c.name as card_name,
              CASE 
                  WHEN r.due_date < CURDATE() THEN 'overdue'
                  WHEN r.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'upcoming'
                  ELSE 'future'
              END as status,
              DATEDIFF(r.due_date, CURDATE()) as days_until
        FROM payment_reminders r
        LEFT JOIN cards c ON c.id = r.card_id
        WHERE r.user_id = :uid 
        AND r.active = 1
        AND r.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY r.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([':uid' => $uid]);
    $upcomingReminders = $stmt->fetchAll();

    $overdueReminders = array_filter($upcomingReminders, fn($r) => $r['status'] === 'overdue');
    $soonReminders = array_filter($upcomingReminders, fn($r) => $r['status'] === 'upcoming');
    ?>

    <?php if ($notificationsEnabled && !empty($overdueReminders)): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <strong><i class="bi bi-exclamation-triangle"></i> Pagamentos Atrasados:</strong>
        <ul class="mb-0 mt-2">
          <?php foreach($overdueReminders as $r): ?>
            <li>
              <strong><?=htmlspecialchars($r['name'])?></strong> -
              €<?=number_format($r['amount'], 2)?>
              (<?=abs($r['days_until'])?> dia(s) atrasado)
              <a href="pages/reminders.php" class="alert-link">Ver detalhes</a>
            </li>
          <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($notificationsEnabled && !empty($soonReminders)): ?>
      <div class="alert alert-warning alert-dismissible fade show">
        <strong><i class="bi bi-clock-history"></i> Vencimentos Próximos:</strong>
        <ul class="mb-0 mt-2">
          <?php foreach($soonReminders as $r): ?>
            <li>
              <strong><?=htmlspecialchars($r['name'])?></strong> -
              €<?=number_format($r['amount'], 2)?>
              (vence em <?=$r['days_until']?> dia(s))
              <a href="pages/reminders.php" class="alert-link">Gerir</a>
            </li>
          <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body py-3 px-4">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-file-earmark-pdf" style="font-size: 24px; color: #e74c3c;"></i>
          <div>
            <small class="text-muted d-block" style="font-size: 12px;">Relatórios</small>
            <strong>Exporta os teus dados em PDF</strong>
          </div>
        </div>
        <a href="pages/export_pdf.php" class="btn btn-sm btn-danger">
          <i class="bi bi-download"></i> Exportar PDF
        </a>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-4">
      <div class="summary-card">
        <h5 class="mb-4"><i class="bi bi-graph-up"></i> Resumo Rápido</h5>
        
        <div class="summary-stat">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="summary-stat-label">Gasto este mês</span>
          </div>
          <div class="summary-stat-value">€<?=number_format($totalMonth,2)?></div>
        </div>

        <hr class="my-3">
        
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Transações (30d)</span>
          <strong><?=intval($count30)?></strong>
        </div>
        <div class="d-flex justify-content-between mb-3">
          <span class="text-muted">Cartões ativos</span>
          <strong><?=count(array_filter($cards, fn($c) => $c['active']))?></strong>
        </div>
        
        <div class="d-grid gap-2">
          <a href="pages/create_transaction.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nova Transação
          </a>
          <a href="pages/add_card.php" class="btn btn-outline-primary">
            <i class="bi bi-credit-card-2-front"></i> Adicionar Cartão
          </a>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-body">
          <h6 class="card-title mb-3"><i class="bi bi-wallet2"></i> Os Teus Cartões</h6>
          <?php if (empty($cards)): ?>
            <div class="text-center py-3">
              <p class="text-muted mb-2">Ainda não tens cartões</p>
              <a href="pages/add_card.php" class="btn btn-sm btn-primary">Adicionar primeiro cartão</a>
            </div>
          <?php else: ?>
            <?php 
            $displayCards = array_slice($cards, 0, 2);
            $remainingCount = count($cards) - 2;
            foreach($displayCards as $c): 
              $percentage = $c['limit_amount'] > 0 ? ($c['balance'] / $c['limit_amount']) * 100 : 0;
              $progressColor = $percentage >= 80 ? 'danger' : ($percentage >= 60 ? 'warning' : 'success');
              $available = $c['limit_amount'] - $c['balance'];
              $cardColor = $c['color'] ?? 'purple';
              $gradient = $cardColors[$cardColor] ?? $cardColors['purple'];
            ?>
              <div class="card-visual <?=!$c['active'] ? 'card-visual-inactive' : ''?>" style="background: <?=$gradient?>;">
                <div>
                  <div class="mb-2">
                    <i class="bi bi-credit-card" style="font-size: 20px;"></i>
                  </div>
                  <div class="card-number">•••• •••• •••• ••••</div>
                </div>
                <div>
                  <div class="card-name"><?=htmlspecialchars($c['name'])?></div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <small>FreeCard</small>
                    <span class="badge bg-<?=$c['active'] ? 'success' : 'secondary'?> text-white" style="font-size: 10px;">
                      <?=$c['active'] ? 'ATIVO' : 'INATIVO'?>
                    </span>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted small">Utilização</span>
                  <span class="fw-bold small"><?=round($percentage)?>%</span>
                </div>
                <div class="progress" style="height: 8px; border-radius: 10px;">
                  <div class="progress-bar bg-<?=$progressColor?>" style="width: <?=min($percentage, 100)?>%"></div>
                </div>
                <div class="d-flex justify-content-between mt-2 small">
                  <span class="text-muted">€<?=number_format($c['balance'],2)?> / €<?=number_format($c['limit_amount'],2)?></span>
                  <span class="text-success fw-bold">€<?=number_format($available, 2)?> livre</span>
                </div>
              </div>
            <?php endforeach; ?>
            
            <?php if ($remainingCount > 0): ?>
              <div class="text-center p-2 border rounded bg-light">
                <i class="bi bi-plus-circle text-muted" style="font-size: 14px;"></i>
                <small class="text-muted ms-1">+<?=$remainingCount?> cart<?=$remainingCount > 1 ? 'ões' : 'ão'?></small>
              </div>
            <?php endif; ?>
            
            <a href="pages/cards.php" class="btn btn-sm btn-outline-secondary w-100 mt-2">Gerir todos os cartões</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      
      <?php if (!empty($categoryData) || $biggestExpense): ?>
      <div class="row g-3 mb-4">
        <?php if ($biggestExpense): ?>
        <div class="col-md-6">
          <div class="stat-mini-card">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="stat-label">Maior Despesa</div>
                <div class="stat-value text-danger">€<?=number_format($biggestExpense['amount'],2)?></div>
                <div class="stat-description"><?=htmlspecialchars($biggestExpense['description'])?></div>
              </div>
              <div class="stat-icon" style="background:  var(--bg-primary); color: #e74c3c;">
                <i class="bi bi-exclamation-circle"></i>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($categoryData)): ?>
        <div class="col-md-6">
          <div class="stat-mini-card">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="stat-label">Categoria Top</div>
                <div class="stat-value" style="color: var(--primary-blue);"><?=htmlspecialchars($categoryData[0]['category'])?></div>
                <div class="stat-description">€<?=number_format($categoryData[0]['total'],2)?> em <?=$categoryData[0]['count']?> transações</div>
              </div>
              <div class="stat-icon" style="background:  var(--bg-primary); color: var(--primary-blue);">
                <i class="bi bi-star-fill"></i>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($categoryData)): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title mb-4"><i class="bi bi-bar-chart"></i> Gastos por Categoria</h5>
          <?php 
          $maxAmount = max(array_column($categoryData, 'total'));
          $displayCategories = array_slice($categoryData, 0, 2);
          $remainingCategories = count($categoryData) - 2;
          
          foreach ($displayCategories as $cat): 
            $percentage = ($cat['total'] / $maxAmount) * 100;
            $color = $categoryColors[$cat['category']] ?? '#95a5a6';
          ?>
          <div class="category-bar-container">
            <div class="category-bar-label">
              <span><strong><?=htmlspecialchars($cat['category'])?></strong> <small class="text-muted">(<?=$cat['count']?> transações)</small></span>
              <span class="text-danger fw-bold">€<?=number_format($cat['total'],2)?></span>
            </div>
            <div class="category-bar-wrapper">
              <div class="category-bar" 
                   style="--bar-color: <?=$color?>; --bar-color-light: <?=$color?>aa; width: 0%;"
                   data-width="<?=$percentage?>%">
                <?=round(($cat['total'] / $totalMonth) * 100)?>%
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          
          <?php if ($remainingCategories > 0): ?>
            <div class="text-center mt-3 p-2 border rounded bg-light">
              <i class="bi bi-plus-circle text-muted" style="font-size: 14px;"></i>
              <small class="text-muted ms-1"><?=$remainingCategories?> categoria<?=$remainingCategories > 1 ? 's' : ''?></small>
            </div>
          <?php endif; ?>
          
          <div class="text-center mt-3">
            <a href="pages/analytics.php" class="btn btn-sm btn-outline-primary">Ver análise completa</a>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-header" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
          <h5 class="mb-0"><i class="bi bi-receipt"></i> Últimas Transações</h5>
        </div>
        <div class="card-body">
          <?php if (empty($recent)): ?>
            <div class="text-center py-5">
              <p class="text-muted mb-3">Ainda não tens transações registadas</p>
              <a href="pages/create_transaction.php" class="btn btn-primary">Criar primeira transação</a>
            </div>
          <?php else: ?>
            <?php 
            $displayTransactions = array_slice($recent, 0, 3);
            $remainingTransactions = count($recent) - 3;
            
            foreach($displayTransactions as $r): ?>
              <div class="transaction-item">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="flex-grow-1">
                    <div class="fw-semibold mb-1"><?=htmlspecialchars($r['description'] ?: '-')?></div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                      <?php if($r['category']): ?>
                        <span class="badge bg-info"><?=htmlspecialchars($r['category'])?></span>
                      <?php endif; ?>
                      <?php if($r['card_name']): ?>
                        <small class="text-muted">
                          <i class="bi bi-credit-card"></i>
                          <?=htmlspecialchars($r['card_name'])?>
                        </small>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-end ms-3">
                    <strong class="text-danger">-€<?=number_format($r['amount'],2)?></strong>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            
            <?php if ($remainingTransactions > 0): ?>
              <div class="text-center mt-3 p-2 border rounded bg-light">
                <i class="bi bi-plus-circle text-muted" style="font-size: 14px;"></i>
                <small class="text-muted ms-1">+<?=$remainingTransactions?> transaç<?=$remainingTransactions > 1 ? 'ões' : 'ão'?></small>
              </div>
            <?php endif; ?>
            
            <div class="text-center mt-3">
              <a href="pages/transactions.php" class="btn btn-outline-primary">Ver todas as transações</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-12">
          <?php
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM budgets WHERE user_id = :uid AND active = 1");
          $stmt->execute([':uid' => $uid]);
          $activeBudgetsCount = $stmt->fetchColumn();
          
          if ($activeBudgetsCount > 0): 
          ?>
            <div class="card shadow-sm">
              <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-piggy-bank" style="font-size: 24px; color: var(--primary-blue);"></i>
                    <div>
                      <small class="text-muted d-block" style="font-size: 12px;">Orçamentos Ativos</small>
                      <strong>
                        <?php if ($activeBudgetsCount == 1): ?>
                          Tens 1 orçamento definido
                        <?php else: ?>
                          Tens <?=$activeBudgetsCount?> orçamentos definidos
                        <?php endif; ?>
                      </strong>
                    </div>
                  </div>
                  <a href="pages/budgets.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-right"></i> Ver Detalhes
                  </a>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="card shadow-sm">
              <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-piggy-bank" style="font-size: 24px; color: #95a5a6;"></i>
                    <div>
                      <small class="text-muted d-block" style="font-size: 12px;">Orçamentos</small>
                      <strong>Nenhum orçamento definido</strong>
                    </div>
                  </div>
                  <a href="pages/budgets.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle"></i> Criar Orçamento
                  </a>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Gerar partículas (apenas desktop)
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

// Animar barras de categoria
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    document.querySelectorAll('.category-bar').forEach(bar => {
      bar.style.width = bar.dataset.width;
    });
  }, 100);
});
</script>
</body>
</html>