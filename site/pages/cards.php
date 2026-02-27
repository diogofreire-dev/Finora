<?php
// site/cards.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

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

// Ação: ativar/desativar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'toggle') {
    $cardId = intval($_POST['card_id'] ?? 0);

    try {
        $stmt = $pdo->prepare("UPDATE cards SET active = NOT active WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $cardId, ':uid' => $uid]);
        $message = 'Estado do cartão alterado com sucesso!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Erro ao executar a ação.';
        $messageType = 'danger';
    }
}

// Buscar todos os cartões com estatísticas
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
$allCards = $stmt->fetchAll();

// Separar cartões ativos e inativos
$activeCards = array_filter($allCards, fn($c) => $c['active']);
$inactiveCards = array_filter($allCards, fn($c) => !$c['active']);

// Estatísticas gerais
$totalLimit = array_sum(array_column($allCards, 'limit_amount'));
$totalBalance = array_sum(array_column($allCards, 'balance'));

// Estatísticas para cartões ativos
$activeTotalLimit = array_sum(array_column($activeCards, 'limit_amount'));
$activeTotalBalance = array_sum(array_column($activeCards, 'balance'));
$activeAvailable = $activeTotalLimit - $activeTotalBalance;

// Estatísticas para cartões inativos
$inactiveTotalLimit = array_sum(array_column($inactiveCards, 'limit_amount'));
$inactiveTotalBalance = array_sum(array_column($inactiveCards, 'balance'));
$inactiveAvailable = $inactiveTotalLimit - $inactiveTotalBalance;
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gerir Cartões - FreeCard</title>
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
    .btn-outline-primary {
      color: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .btn-outline-primary:hover {
      background: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .text-primary { color: var(--primary-blue) !important; }
    
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px var(--shadow);
      transition: all 0.3s;
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px var(--shadow);
    }
    .card-visual {
      border-radius: 12px;
      padding: 20px;
      color: white;
      min-height: 140px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
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
      font-size: 20px;
      letter-spacing: 3px;
      font-weight: 600;
      position: relative;
    }
    .card-name {
      font-size: 14px;
      text-transform: uppercase;
      font-weight: 700;
      position: relative;
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
    
    .section-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--border-color);
    }
    
    .section-header h3 {
      font-size: 24px;
      font-weight: 700;
      margin: 0;
      color: var(--text-primary);
    }
    
    /* Tema escuro */
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
    
    /* ========== MOBILE RESPONSIVE FIXES ========== */
    @media (max-width: 768px) {
      /* Remover border-right e adicionar border-bottom em mobile */
      .stat-item:not(:last-child) {
        border-right: none !important;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 16px;
        margin-bottom: 16px;
      }
      
      .stat-item:last-child {
        padding-bottom: 0;
        margin-bottom: 0;
      }
      
      /* Ajustar tamanho dos títulos em mobile */
      .stat-item h3 {
        font-size: 24px;
      }
      
      .stat-item p {
        font-size: 12px;
      }
      
      /* Corrigir card de cartão em mobile */
      .card-body .bg-light .row .col-6 {
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      .card-body .bg-light .row .col-6:first-child {
        border-right: none !important;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 12px;
      }
    }

    /* Corrigir botões de ação em mobile */
    @media (max-width: 576px) {
      .d-flex.gap-2.mb-2 {
        flex-direction: column !important;
      }

      .d-flex.gap-2.mb-2 > * {
        width: 100% !important;
      }

      .d-flex.gap-2.mb-2 form {
        width: 100% !important;
      }

      .d-flex.gap-2.mb-2 .btn {
        width: 100% !important;
      }
    }

    /* Header da página responsivo */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      gap: 1rem;
    }

    .page-header-buttons {
      display: flex;
      gap: 0.5rem;
    }

    @media (max-width: 576px) {
      .page-header {
        flex-direction: column;
        align-items: stretch;
      }

      .page-header-buttons {
        width: 100%;
      }

      .page-header-buttons .btn {
        flex: 1;
        white-space: nowrap;
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
      }
    }
  </style>
</head>
<body>
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
        <li class="nav-item"><a class="nav-link active" href="cards.php"><i class="bi bi-wallet2"></i> Cartões</a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="bi bi-receipt"></i> Transações</a></li>
        <li class="nav-item"><a class="nav-link" href="budgets.php"><i class="bi bi-piggy-bank"></i> Orçamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="reminders.php"><i class="bi bi-calendar-check"></i> Lembretes</a></li>
        <li class="nav-item"><a class="nav-link" href="analytics.php"><i class="bi bi-graph-up"></i> Análise</a></li>
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
  <div class="page-header">
    <div>
      <h2><i class="bi bi-wallet2"></i> Os Meus Cartões</h2>
      <p class="text-muted mb-0">Gere os teus cartões e acompanha os limites</p>
    </div>
    <div class="page-header-buttons">
      <a href="add_card.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Adicionar Cartão
      </a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?=$messageType?> alert-dismissible fade show">
      <i class="bi bi-<?=$messageType === 'success' ? 'check-circle' : 'info-circle'?>"></i>
      <?=htmlspecialchars($message)?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($allCards)): ?>
    <!-- Cartões Ativos -->
    <?php if (!empty($activeCards)): ?>
      <!-- Estatísticas Cartões Ativos -->
      <div class="summary-card mb-4">
        <div class="section-header mb-3">
          <h3><i class="text-success"></i> Cartões Ativos</h3>
        </div>
        <div class="row">
          <div class="col-12 col-md-4">
            <div class="stat-item">
              <h3 style="color: #3498db;">€<?=number_format($activeTotalLimit, 2)?></h3>
              <p>Limite Total</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="stat-item">
              <h3 class="text-danger">€<?=number_format($activeTotalBalance, 2)?></h3>
              <p>Gasto Atual</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="stat-item">
              <h3 class="text-success">€<?=number_format($activeAvailable, 2)?></h3>
              <p>Disponível</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista Cartões Ativos -->
      <div class="row g-4 mb-5">
        <?php foreach($activeCards as $c): ?>
          <?php 
            $percentage = $c['limit_amount'] > 0 ? ($c['balance'] / $c['limit_amount']) * 100 : 0;
            $progressColor = $percentage >= 80 ? 'danger' : ($percentage >= 60 ? 'warning' : 'success');
            $available = $c['limit_amount'] - $c['balance'];
            $cardColor = $c['color'] ?? 'purple';
            $gradient = $cardColors[$cardColor] ?? $cardColors['purple'];
          ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
              <div class="card-body p-4">
                <!-- Card Visual -->
                <div class="card-visual mb-3" style="background: <?=$gradient?>;">
                  <div>
                    <div class="mb-2">
                      <i class="bi bi-credit-card" style="font-size: 28px;"></i>
                    </div>
                    <div class="card-number">•••• •••• •••• ••••</div>
                  </div>
                  <div>
                    <div class="card-name"><?=htmlspecialchars($c['name'])?></div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <small>FreeCard</small>
                      <span class="badge bg-success text-white">ATIVO</span>
                    </div>
                  </div>
                </div>

                <!-- Informações -->
                <div class="mb-3">
                  <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Utilização do Limite</span>
                    <span class="fw-bold small"><?=round($percentage)?>%</span>
                  </div>
                  <div class="progress" style="height: 10px; border-radius: 10px;">
                    <div class="progress-bar bg-<?=$progressColor?>" style="width: <?=min($percentage, 100)?>%"></div>
                  </div>
                  <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted">€<?=number_format($c['balance'],2)?> usado</small>
                    <small class="text-muted">€<?=number_format($c['limit_amount'],2)?> limite</small>
                  </div>
                </div>

                <div class="p-3 bg-light rounded mb-3">
                  <div class="row text-center">
                    <div class="col-6 border-end">
                      <div class="fw-bold text-success">€<?=number_format($available, 2)?></div>
                      <small class="text-muted">Disponível</small>
                    </div>
                    <div class="col-6">
                      <div class="fw-bold"><?=$c['transaction_count']?></div>
                      <small class="text-muted">Transações</small>
                    </div>
                  </div>
                </div>

                <!-- Ações -->
                <div class="d-flex gap-2 mb-2">
                  <a href="edit_card.php?id=<?=$c['id']?>" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-pencil"></i> Editar
                  </a>
                  <form method="post" class="flex-fill">
                    <input type="hidden" name="card_id" value="<?=$c['id']?>">
                    <input type="hidden" name="action" value="toggle">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                      <i class="bi bi-pause-circle"></i> Desativar
                    </button>
                  </form>
                </div>

                <div class="text-center mt-3">
                  <small class="text-muted">
                    <i class="bi bi-calendar"></i> Criado em <?=date('d/m/Y', strtotime($c['created_at']))?>
                  </small>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Cartões Inativos -->
    <?php if (!empty($inactiveCards)): ?>
      <!-- Estatísticas Cartões Inativos -->
      <div class="summary-card mb-4">
        <div class="section-header mb-3">
          <h3><i class="text-secondary"></i> Cartões Inativos</h3>
        </div>
        <div class="row">
          <div class="col-12 col-md-4">
            <div class="stat-item">
              <h3 style="color: #3498db;">€<?=number_format($inactiveTotalLimit, 2)?></h3>
              <p>Limite Total</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="stat-item">
              <h3 class="text-danger">€<?=number_format($inactiveTotalBalance, 2)?></h3>
              <p>Gasto Atual</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="stat-item">
              <h3 class="text-success">€<?=number_format($inactiveAvailable, 2)?></h3>
              <p>Disponível</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista Cartões Inativos -->
      <div class="row g-4">
        <?php foreach($inactiveCards as $c): ?>
          <?php 
            $percentage = $c['limit_amount'] > 0 ? ($c['balance'] / $c['limit_amount']) * 100 : 0;
            $progressColor = $percentage >= 80 ? 'danger' : ($percentage >= 60 ? 'warning' : 'success');
            $available = $c['limit_amount'] - $c['balance'];
            $cardColor = $c['color'] ?? 'purple';
            $gradient = $cardColors[$cardColor] ?? $cardColors['purple'];
          ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
              <div class="card-body p-4">
                <!-- Card Visual -->
                <div class="card-visual card-visual-inactive mb-3" style="background: <?=$gradient?>;">
                  <div>
                    <div class="mb-2">
                      <i class="bi bi-credit-card" style="font-size: 28px;"></i>
                    </div>
                    <div class="card-number">•••• •••• •••• ••••</div>
                  </div>
                  <div>
                    <div class="card-name"><?=htmlspecialchars($c['name'])?></div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <small>FreeCard</small>
                      <span class="badge bg-secondary text-white">INATIVO</span>
                    </div>
                  </div>
                </div>

                <!-- Informações -->
                <div class="mb-3">
                  <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Utilização do Limite</span>
                    <span class="fw-bold small"><?=round($percentage)?>%</span>
                  </div>
                  <div class="progress" style="height: 10px; border-radius: 10px;">
                    <div class="progress-bar bg-<?=$progressColor?>" style="width: <?=min($percentage, 100)?>%"></div>
                  </div>
                  <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted">€<?=number_format($c['balance'],2)?> usado</small>
                    <small class="text-muted">€<?=number_format($c['limit_amount'],2)?> limite</small>
                  </div>
                </div>

                <div class="p-3 bg-light rounded mb-3">
                  <div class="row text-center">
                    <div class="col-6 border-end">
                      <div class="fw-bold text-success">€<?=number_format($available, 2)?></div>
                      <small class="text-muted">Disponível</small>
                    </div>
                    <div class="col-6">
                      <div class="fw-bold"><?=$c['transaction_count']?></div>
                      <small class="text-muted">Transações</small>
                    </div>
                  </div>
                </div>

                <!-- Ações -->
                <div class="d-flex gap-2 mb-2">
                  <a href="edit_card.php?id=<?=$c['id']?>" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-pencil"></i> Editar
                  </a>
                  <form method="post" class="flex-fill">
                    <input type="hidden" name="card_id" value="<?=$c['id']?>">
                    <input type="hidden" name="action" value="toggle">
                    <button type="submit" class="btn btn-sm btn-outline-success w-100">
                      <i class="bi bi-play-circle"></i> Ativar
                    </button>
                  </form>
                </div>

                <div class="text-center mt-3">
                  <small class="text-muted">
                    <i class="bi bi-calendar"></i> Criado em <?=date('d/m/Y', strtotime($c['created_at']))?>
                  </small>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <!-- Estado vazio -->
    <div class="card">
      <div class="card-body text-center py-5">
        <div class="mb-4">
          <i class="bi bi-credit-card-2-front" style="font-size: 80px; color: #e0e0e0;"></i>
        </div>
        <h4 class="text-muted mb-3">Ainda não tens cartões registados</h4>
        <p class="text-muted mb-4">Adiciona o teu primeiro cartão para começares a gerir as tuas finanças de forma inteligente</p>
        <a href="add_card.php" class="btn btn-primary btn-lg">
          <i class="bi bi-plus-circle"></i> Adicionar Primeiro Cartão
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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