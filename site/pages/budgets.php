<?php
// site/budgets.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $amount = floatval($_POST['amount'] ?? 0);
                $period = $_POST['period'] ?? 'monthly';
                $category = !empty($_POST['category']) ? trim($_POST['category']) : null;
                $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
                
                if ($amount > 0 && strlen($name) >= 3) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO budgets (user_id, name, amount, period, category, card_id, start_date)
                            VALUES (:uid, :name, :amount, :period, :category, :card_id, CURDATE())
                        ");
                        $stmt->execute([
                            ':uid' => $uid,
                            ':name' => $name,
                            ':amount' => $amount,
                            ':period' => $period,
                            ':category' => $category,
                            ':card_id' => $card_id
                        ]);
                        $message = 'Orçamento criado com sucesso!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Erro ao criar orçamento.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Dados inválidos.';
                    $messageType = 'danger';
                }
                break;
            
            case 'edit':
                $budget_id = intval($_POST['budget_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $amount = floatval($_POST['amount'] ?? 0);
                $period = $_POST['period'] ?? 'monthly';
                $category = !empty($_POST['category']) ? trim($_POST['category']) : null;
                $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
                
                if ($amount > 0 && strlen($name) >= 3) {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE budgets 
                            SET name = :name, amount = :amount, period = :period, 
                                category = :category, card_id = :card_id
                            WHERE id = :id AND user_id = :uid
                        ");
                        $stmt->execute([
                            ':id' => $budget_id,
                            ':uid' => $uid,
                            ':name' => $name,
                            ':amount' => $amount,
                            ':period' => $period,
                            ':category' => $category,
                            ':card_id' => $card_id
                        ]);
                        $message = 'Orçamento atualizado com sucesso!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Erro ao atualizar orçamento.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Dados inválidos.';
                    $messageType = 'danger';
                }
                break;
                
            case 'toggle':
                $budget_id = intval($_POST['budget_id'] ?? 0);
                try {
                    $stmt = $pdo->prepare("UPDATE budgets SET active = NOT active WHERE id = :id AND user_id = :uid");
                    $stmt->execute([':id' => $budget_id, ':uid' => $uid]);
                    $message = 'Estado do orçamento alterado!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Erro ao alterar orçamento.';
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $budget_id = intval($_POST['budget_id'] ?? 0);
                try {
                    $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = :id AND user_id = :uid");
                    $stmt->execute([':id' => $budget_id, ':uid' => $uid]);
                    $message = 'Orçamento removido!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Erro ao remover orçamento.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Buscar orçamentos com gastos atuais
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        c.name as card_name,
        COALESCE(SUM(CASE 
            WHEN b.period = 'monthly' AND t.transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
                AND t.transaction_date <= LAST_DAY(CURDATE()) THEN t.amount
            WHEN b.period = 'weekly' AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
                AND t.transaction_date <= CURDATE() THEN t.amount
            WHEN b.period = 'yearly' AND YEAR(t.transaction_date) = YEAR(CURDATE()) THEN t.amount
            ELSE 0
        END), 0) as current_spent
    FROM budgets b
    LEFT JOIN cards c ON c.id = b.card_id
    LEFT JOIN transactions t ON t.user_id = b.user_id
        AND (b.category IS NULL OR t.category = b.category)
        AND (b.card_id IS NULL OR t.card_id = b.card_id)
    WHERE b.user_id = :uid
    GROUP BY b.id
    ORDER BY b.active DESC, b.created_at DESC
");
$stmt->execute([':uid' => $uid]);
$budgets = $stmt->fetchAll();

// Array fixo de todas as categorias disponíveis
$categories = [
    'Compras',
    'Alimentação',
    'Transporte',
    'Saúde',
    'Entretenimento',
    'Educação',
    'Casa',
    'Outros'
];

// Buscar cartões
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orçamentos - FreeCard</title>
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
      transition: all 0.3s;
    }
    .budget-card {
      padding: 24px;
      border-radius: 16px;
      background: var(--bg-secondary);
      box-shadow: 0 4px 20px var(--shadow);
      margin-bottom: 20px;
      border: 2px solid transparent;
      transition: all 0.3s;
    }
    .budget-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px var(--shadow);
    }
    .budget-card.warning {
      border-color: #f39c12;
    }
    .budget-card.danger {
      border-color: #e74c3c;
    }
    .budget-progress {
      height: 12px;
      border-radius: 10px;
      background: var(--bg-hover);
      overflow: hidden;
      margin: 16px 0;
    }
    .budget-progress-bar {
      height: 100%;
      transition: width 1s ease-out;
      border-radius: 10px;
    }
    .badge-period {
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
    }
    .form-label {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 8px;
    }
    .form-control, .form-select {
      background: var(--bg-primary);
      color: var(--text-primary);
      border: 2px solid var(--border-color);
      padding: 10px 14px;
    }
    .form-control:focus, .form-select:focus {
      background: var(--bg-primary);
      color: var(--text-primary);
      border-color: var(--primary-blue);
      box-shadow: 0 0 0 0.2rem rgba(46, 101, 204, 0.25);
    }
    
    /* Corrigir visibilidade do texto nos inputs no tema escuro */
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select {
      background: var(--bg-secondary);
      color: var(--text-primary);
      border-color: var(--border-color);
    }
    
    [data-theme="dark"] .form-control:focus,
    [data-theme="dark"] .form-select:focus {
      background: var(--bg-secondary);
      color: var(--text-primary);
      border-color: var(--primary-blue);
    }
    
    [data-theme="dark"] .form-control::placeholder {
      color: var(--text-secondary);
      opacity: 0.6;
    }
    
    /* Garantir que o texto digitado seja visível */
    [data-theme="dark"] input.form-control,
    [data-theme="dark"] select.form-select {
      color: #ecf0f1 !important;
    }
    
    [data-theme="dark"] .form-select option {
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    
    [data-theme="dark"] .text-muted {
      color: var(--text-secondary) !important;
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

      /* Budget cards em mobile */
      .budget-card .d-flex.justify-content-between.flex-wrap {
        flex-direction: column;
        gap: 8px;
      }

      .budget-card .budget-actions {
        width: 100%;
      }

      .budget-card .budget-actions .btn,
      .budget-card .budget-actions form {
        flex: 1;
      }

      .budget-card .budget-actions form .btn {
        width: 100%;
      }
    }

    /* Remove number input spinners */
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    input[type="number"] {
      -moz-appearance: textfield;
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
        <li class="nav-item"><a class="nav-link active" href="budgets.php"><i class="bi bi-piggy-bank"></i> Orçamentos</a></li>
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
      <h2><i class="bi bi-piggy-bank"></i> Os Meus Orçamentos</h2>
      <p class="text-muted mb-0">Controla os teus gastos mensais e mantém-te no orçamento</p>
    </div>
    <div class="page-header-buttons">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newBudgetModal">
        <i class="bi bi-plus-circle"></i> Novo Orçamento
      </button>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?=$messageType?> alert-dismissible fade show">
      <?=htmlspecialchars($message)?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (empty($budgets)): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="bi bi-piggy-bank" style="font-size: 80px; color: #e0e0e0;"></i>
        <h4 class="text-muted mt-4 mb-3">Ainda não tens orçamentos definidos</h4>
        <p class="text-muted mb-4">Cria o teu primeiro orçamento para começares a controlar os teus gastos</p>
        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#newBudgetModal">
          <i class="bi bi-plus-circle"></i> Criar Primeiro Orçamento
        </button>
      </div>
    </div>
  <?php else: ?>
    <div class="row">
      <?php foreach($budgets as $b): ?>
        <?php 
          $percentage = $b['amount'] > 0 ? ($b['current_spent'] / $b['amount']) * 100 : 0;
          $remaining = $b['amount'] - $b['current_spent'];
          $statusClass = $percentage >= 100 ? 'danger' : ($percentage >= 80 ? 'warning' : '');
          $barColor = $percentage >= 100 ? '#e74c3c' : ($percentage >= 80 ? '#f39c12' : '#2ecc71');
          
          $periodLabels = [
            'monthly' => 'Mensal',
            'weekly' => 'Semanal',
            'yearly' => 'Anual'
          ];
        ?>
        <div class="col-md-6 col-xl-4">
          <div class="budget-card <?=$statusClass?> <?=!$b['active'] ? 'opacity-50' : ''?>">
            <div class="mb-3">
              <h5 class="mb-1"><?=htmlspecialchars($b['name'])?></h5>
              <div class="d-flex gap-2 flex-wrap">
                <span class="badge-period" style="background: var(--bg-hover); color: var(--text-primary);">
                  <i class="bi bi-calendar-event"></i> <?=$periodLabels[$b['period']]?>
                </span>
                <?php if($b['category']): ?>
                  <span class="badge-period" style="background: var(--bg-hover); color: var(--text-primary);">
                    <i class="bi bi-tag"></i> <?=htmlspecialchars($b['category'])?>
                  </span>
                <?php endif; ?>
                <?php if($b['card_name']): ?>
                  <span class="badge-period" style="background: var(--bg-hover); color: var(--text-primary);">
                    <i class="bi bi-credit-card"></i> <?=htmlspecialchars($b['card_name'])?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="budget-progress">
              <div class="budget-progress-bar" style="width: 0%; background: <?=$barColor?>;" data-width="<?=min($percentage, 100)?>%"></div>
            </div>

            <div class="d-flex justify-content-between align-items-end">
              <div>
                <small class="text-muted d-block">Gasto</small>
                <h4 class="mb-0 <?=$percentage >= 100 ? 'text-danger' : ''?>">
                  €<?=number_format($b['current_spent'], 2)?>
                </h4>
              </div>
              <div class="text-end">
                <small class="text-muted d-block">Orçamento</small>
                <h4 class="mb-0">€<?=number_format($b['amount'], 2)?></h4>
              </div>
            </div>

            <div class="mt-3 pt-3" style="border-top: 1px solid var(--border-color);">
              <div class="d-flex justify-content-between">
                <span class="text-muted small">Restante</span>
                <strong class="<?=$remaining < 0 ? 'text-danger' : 'text-success'?>">
                  €<?=number_format(abs($remaining), 2)?> <?=$remaining < 0 ? 'excedido' : ''?>
                </strong>
              </div>
              <div class="d-flex justify-content-between mt-1">
                <span class="text-muted small">Percentagem</span>
                <strong class="<?=$percentage >= 100 ? 'text-danger' : ($percentage >= 80 ? 'text-warning' : '')?>">
                  <?=round($percentage)?>%
                </strong>
              </div>
            </div>

            <!-- Botões de ação -->
            <div class="d-flex gap-2 justify-content-between flex-wrap mt-3 pt-3" style="border-top: 1px solid var(--border-color);">
              <div class="d-flex gap-2 flex-wrap budget-actions">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBudget(<?=htmlspecialchars(json_encode($b))?>)">
                  <i class="bi bi-pencil"></i> Editar
                </button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="budget_id" value="<?=$b['id']?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-<?=$b['active'] ? 'pause' : 'play'?>-circle"></i>
                    <?=$b['active'] ? 'Desativar' : 'Ativar'?>
                  </button>
                </form>
              </div>
              <form method="post" class="d-inline" onsubmit="return confirm('Tens a certeza?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="budget_id" value="<?=$b['id']?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Novo Orçamento -->
<div class="modal fade" id="newBudgetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background: var(--bg-secondary); border: none; border-radius: 20px;">
      <div class="modal-header" style="border: none;">
        <h5 class="modal-title" style="color: var(--text-primary);">
          <i class="bi bi-piggy-bank"></i> Novo Orçamento
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome do Orçamento</label>
            <input type="text" name="name" class="form-control" placeholder="ex: Orçamento Mensal" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Valor (€)</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="1500.00" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Período</label>
            <select name="period" class="form-select">
              <option value="monthly">Mensal</option>
              <option value="weekly">Semanal</option>
              <option value="yearly">Anual</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Categoria (opcional)</label>
            <select name="category" class="form-select">
              <option value="">Todas as categorias</option>
              <?php foreach($categories as $cat): ?>
                <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Deixa vazio para orçamento global</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Cartão (opcional)</label>
            <select name="card_id" class="form-select">
              <option value="">Todos os cartões</option>
              <?php foreach($cards as $c): ?>
                <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Deixa vazio para todos os cartões</small>
          </div>
        </div>
        <div class="modal-footer" style="border: none;">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar Orçamento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Orçamento -->
<div class="modal fade" id="editBudgetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background: var(--bg-secondary); border: none; border-radius: 20px;">
      <div class="modal-header" style="border: none;">
        <h5 class="modal-title" style="color: var(--text-primary);">
          <i class="bi bi-pencil"></i> Editar Orçamento
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="budget_id" id="edit_budget_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome do Orçamento</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Valor (€)</label>
            <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0.01" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Período</label>
            <select name="period" id="edit_period" class="form-select">
              <option value="monthly">Mensal</option>
              <option value="weekly">Semanal</option>
              <option value="yearly">Anual</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Categoria (opcional)</label>
            <select name="category" id="edit_category" class="form-select">
              <option value="">Todas as categorias</option>
              <?php foreach($categories as $cat): ?>
                <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Cartão (opcional)</label>
            <select name="card_id" id="edit_card_id" class="form-select">
              <option value="">Todos os cartões</option>
              <?php foreach($cards as $c): ?>
                <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer" style="border: none;">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animar barras de progresso
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    document.querySelectorAll('.budget-progress-bar').forEach(bar => {
      bar.style.width = bar.dataset.width;
    });
  }, 100);
});

// Função para editar orçamento
function editBudget(budget) {
  document.getElementById('edit_budget_id').value = budget.id;
  document.getElementById('edit_name').value = budget.name;
  document.getElementById('edit_amount').value = budget.amount;
  document.getElementById('edit_period').value = budget.period;
  document.getElementById('edit_category').value = budget.category || '';
  document.getElementById('edit_card_id').value = budget.card_id || '';
  
  var editModal = new bootstrap.Modal(document.getElementById('editBudgetModal'));
  editModal.show();
}

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