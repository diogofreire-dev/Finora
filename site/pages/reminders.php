<?php
// site/reminders.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
        $due_date = $_POST['due_date'] ?? '';
        $recurrence = $_POST['recurrence'] ?? 'once';
        $notify_days = intval($_POST['notify_days_before'] ?? 3);
        $notify_method = $_POST['notify_method'] ?? 'email';
        
        if ($amount > 0 && strlen($name) >= 3 && $due_date) {
            try {
                // Calcular next_due_date baseado na recorrência
                $next_due = $due_date;
                if ($recurrence !== 'once') {
                    $dateObj = new DateTime($due_date);
                    switch($recurrence) {
                        case 'weekly':
                            $dateObj->modify('+1 week');
                            break;
                        case 'monthly':
                            $dateObj->modify('+1 month');
                            break;
                        case 'yearly':
                            $dateObj->modify('+1 year');
                            break;
                    }
                    $next_due = $dateObj->format('Y-m-d');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO payment_reminders 
                    (user_id, name, amount, category, card_id, due_date, recurrence, notify_days_before, notify_method, next_due_date)
                    VALUES (:uid, :name, :amount, :category, :card_id, :due_date, :recurrence, :notify, :notify_method, :next_due)
                ");
                $stmt->execute([
                    ':uid' => $uid,
                    ':name' => $name,
                    ':amount' => $amount,
                    ':category' => $category ?: null,
                    ':card_id' => $card_id,
                    ':due_date' => $due_date,
                    ':recurrence' => $recurrence,
                    ':notify' => $notify_days,
                    ':notify_method' => $notify_method,
                    ':next_due' => $next_due
                ]);
                
                $message = 'Lembrete criado com sucesso!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao criar lembrete.';
                $messageType = 'danger';
            }
        }
    }
    
    elseif ($action === 'mark_paid') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        $paid_date = $_POST['paid_date'] ?? date('Y-m-d');
        $create_transaction = isset($_POST['create_transaction']);
        
        try {
            $pdo->beginTransaction();
            
            // Buscar o lembrete
            $stmt = $pdo->prepare("SELECT * FROM payment_reminders WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $reminder_id, ':uid' => $uid]);
            $reminder = $stmt->fetch();
            
            if ($reminder) {
                $transaction_id = null;
                
                // Criar transação se solicitado
                if ($create_transaction) {
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions 
                        (user_id, card_id, amount, description, category, transaction_date)
                        VALUES (:uid, :card_id, :amount, :desc, :category, :tdate)
                    ");
                    $stmt->execute([
                        ':uid' => $uid,
                        ':card_id' => $reminder['card_id'] ?: null,
                        ':amount' => $reminder['amount'],
                        ':desc' => $reminder['name'],
                        ':category' => $reminder['category'] ?: null,
                        ':tdate' => $paid_date
                    ]);
                    $transaction_id = $pdo->lastInsertId();
                    
                    // Atualizar saldo do cartão se houver
                    if ($reminder['card_id']) {
                        $stmt = $pdo->prepare("
                            UPDATE cards 
                            SET balance = balance + :amount 
                            WHERE id = :card_id AND user_id = :uid
                        ");
                        $stmt->execute([
                            ':amount' => $reminder['amount'],
                            ':card_id' => $reminder['card_id'],
                            ':uid' => $uid
                        ]);
                    }
                }
                
                // Registar no histórico
                $stmt = $pdo->prepare("
                    INSERT INTO reminder_history (reminder_id, transaction_id, paid_date, amount)
                    VALUES (:rid, :tid, :pdate, :amount)
                ");
                $stmt->execute([
                    ':rid' => $reminder_id,
                    ':tid' => $transaction_id,
                    ':pdate' => $paid_date,
                    ':amount' => $reminder['amount']
                ]);
                
                // Atualizar lembrete
                if ($reminder['recurrence'] === 'once') {
                    // Se é único, desativar
                    $stmt = $pdo->prepare("
                        UPDATE payment_reminders 
                        SET active = 0, last_paid_date = :pdate 
                        WHERE id = :id
                    ");
                    $stmt->execute([':pdate' => $paid_date, ':id' => $reminder_id]);
                } else {
                    // Se é recorrente, calcular próxima data
                    $nextDate = new DateTime($reminder['due_date']);
                    switch($reminder['recurrence']) {
                        case 'weekly':
                            $nextDate->modify('+1 week');
                            break;
                        case 'monthly':
                            $nextDate->modify('+1 month');
                            break;
                        case 'yearly':
                            $nextDate->modify('+1 year');
                            break;
                    }

                    $stmt = $pdo->prepare("
                        UPDATE payment_reminders
                        SET last_paid_date = :pdate,
                            due_date = :next_due,
                            next_due_date = :next_due_date
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':pdate' => $paid_date,
                        ':next_due' => $nextDate->format('Y-m-d'),
                        ':next_due_date' => $nextDate->format('Y-m-d'),
                        ':id' => $reminder_id
                    ]);
                }
                
                $pdo->commit();
                $message = 'Pagamento registado com sucesso!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Erro ao registar pagamento: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    elseif ($action === 'edit') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
        $due_date = $_POST['due_date'] ?? '';
        $recurrence = $_POST['recurrence'] ?? 'once';
        $notify_days = intval($_POST['notify_days_before'] ?? 3);
        
        if ($amount > 0 && strlen($name) >= 3 && $due_date) {
            try {
                // Calcular next_due_date baseado na recorrência
                $next_due = $due_date;
                if ($recurrence !== 'once') {
                    $dateObj = new DateTime($due_date);
                    switch($recurrence) {
                        case 'weekly':
                            $dateObj->modify('+1 week');
                            break;
                        case 'monthly':
                            $dateObj->modify('+1 month');
                            break;
                        case 'yearly':
                            $dateObj->modify('+1 year');
                            break;
                    }
                    $next_due = $dateObj->format('Y-m-d');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE payment_reminders 
                    SET name = :name, amount = :amount, category = :category, 
                        card_id = :card_id, due_date = :due_date, recurrence = :recurrence,
                        notify_days_before = :notify, notify_method = :notify_method, next_due_date = :next_due
                    WHERE id = :id AND user_id = :uid
                ");
                $stmt->execute([
                    ':id' => $reminder_id,
                    ':uid' => $uid,
                    ':name' => $name,
                    ':amount' => $amount,
                    ':category' => $category ?: null,
                    ':card_id' => $card_id,
                    ':due_date' => $due_date,
                    ':recurrence' => $recurrence,
                    ':notify' => $notify_days,
                    ':notify_method' => $_POST['notify_method'] ?? 'email',
                    ':next_due' => $next_due
                ]);
                
                $message = 'Lembrete atualizado com sucesso!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao atualizar lembrete.';
                $messageType = 'danger';
            }
        }
    }
    
    elseif ($action === 'toggle') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE payment_reminders SET active = NOT active WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $reminder_id, ':uid' => $uid]);
            $message = 'Estado do lembrete alterado!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao alterar lembrete.';
            $messageType = 'danger';
        }
    }
    
    elseif ($action === 'delete') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM payment_reminders WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $reminder_id, ':uid' => $uid]);
            $message = 'Lembrete removido!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao remover lembrete.';
            $messageType = 'danger';
        }
    }
}

// Buscar lembretes ativos
$stmt = $pdo->prepare("
    SELECT r.*, c.name as card_name,
           CASE 
               WHEN r.due_date < CURDATE() THEN 'overdue'
               WHEN r.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'upcoming'
               ELSE 'future'
           END as status
    FROM payment_reminders r
    LEFT JOIN cards c ON c.id = r.card_id
    WHERE r.user_id = :uid
    ORDER BY r.active DESC, r.due_date ASC
");
$stmt->execute([':uid' => $uid]);
$reminders = $stmt->fetchAll();

// Estatísticas
$total_month = 0;
$overdue_count = 0;
$upcoming_count = 0;

$today = new DateTime();
$endOfMonth = new DateTime('last day of this month');

foreach($reminders as $r) {
    if (!$r['active']) continue;
    
    $dueDate = new DateTime($r['due_date']);
    
    if ($dueDate <= $endOfMonth && $dueDate >= $today) {
        $total_month += $r['amount'];
    }
    
    if ($r['status'] === 'overdue') $overdue_count++;
    if ($r['status'] === 'upcoming') $upcoming_count++;
}

// Categorias e cartões para os formulários
$categories = ['Compras', 'Alimentação', 'Transporte', 'Saúde', 'Entretenimento', 'Educação', 'Casa', 'Outros'];

$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Agrupar por status
$overdueReminders = array_filter($reminders, fn($r) => $r['status'] === 'overdue' && $r['active']);
$upcomingReminders = array_filter($reminders, fn($r) => $r['status'] === 'upcoming' && $r['active']);
$futureReminders = array_filter($reminders, fn($r) => $r['status'] === 'future' && $r['active']);
$inactiveReminders = array_filter($reminders, fn($r) => !$r['active']);
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lembretes de Pagamento - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <style>
    body { position: relative; min-height: 100vh; }
    .bg-animation {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      z-index: 0; pointer-events: none; overflow: hidden;
    }
    [data-theme="light"] body::before {
      content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: linear-gradient(135deg, #e8eef5ff 0%, #e9eef8ff 50%, #e8edf5ff 100%);
      z-index: -1;
    }
    [data-theme="dark"] body::before {
      content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: linear-gradient(135deg, #2b3035 0%, #323844 50%, #2b3035 100%);
      z-index: -1;
    }
    .floating-shape {
      position: absolute; border-radius: 50%;
      animation: float 20s infinite ease-in-out;
    }
    [data-theme="light"] .floating-shape { background: rgba(46, 88, 204); }
    [data-theme="dark"] .floating-shape { background: rgba(46, 88, 204); }
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
    .navbar, .container { position: relative; z-index: 1; }
    
    :root { --primary-blue: #3498db; --dark-blue: #2980b9; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-primary); color: var(--text-primary);
    }
    .navbar { box-shadow: 0 2px 10px rgba(0,0,0,0.05); background: var(--navbar-bg); }
    .navbar-brand img { height: 35px; margin-right: 8px; }
    .btn-primary { background: var(--primary-blue); border-color: var(--primary-blue); }
    .btn-primary:hover { background: var(--dark-blue); border-color: var(--dark-blue); }
    
    .card {
      border: none; border-radius: 16px; box-shadow: 0 4px 20px var(--shadow);
      background: var(--bg-secondary); color: var(--text-primary);
    }
    
    .summary-card {
      background: var(--bg-secondary); border-radius: 16px; padding: 24px;
      box-shadow: 0 4px 20px var(--shadow);
    }
    
    .stat-item {
      text-align: center; padding: 16px;
    }
    .stat-item h3 {
      font-size: 28px; font-weight: 800; margin-bottom: 4px;
      color: var(--text-primary);
    }
    .stat-item p {
      font-size: 13px; margin: 0; color: var(--text-secondary);
    }
    .stat-item:not(:last-child) {
      border-right: 1px solid var(--border-color);
    }
    
    .reminder-card {
      padding: 20px; border-radius: 12px; margin-bottom: 16px;
      background: var(--bg-secondary); border: 2px solid var(--border-color);
      transition: all 0.3s;
    }
    .reminder-card:hover {
      transform: translateY(-2px); box-shadow: 0 8px 20px var(--shadow);
    }
    
    .reminder-card.overdue {
      border-color: #e74c3c; background: var(--bg-secondary);
    }
    .reminder-card.upcoming {
      border-color: #f39c12; background: var(--bg-secondary);
    }
    
    .status-badge {
      padding: 6px 12px; border-radius: 8px; font-size: 12px;
      font-weight: 600; display: inline-flex; align-items: center; gap: 4px;
    }
    .status-badge.overdue { background: #e74c3c; color: white; }
    .status-badge.upcoming { background: #f39c12; color: white; }
    .status-badge.future { background: var(--bg-hover); color: var(--text-primary); }
    
    .recurrence-badge {
      padding: 4px 10px; border-radius: 6px; font-size: 11px;
      font-weight: 600; background: var(--bg-hover); color: var(--text-primary);
    }
    
    .form-control, .form-select {
      background: var(--bg-primary); color: var(--text-primary);
      border: 2px solid var(--border-color);
    }
    .form-control:focus, .form-select:focus {
      background: var(--bg-primary); color: var(--text-primary);
      border-color: var(--primary-blue);
    }
    
    [data-theme="dark"] .form-control::placeholder {
      color: var(--text-secondary); opacity: 0.6;
    }
    [data-theme="dark"] .text-muted {
      color: var(--text-secondary) !important;
    }

    /* Corrigir ícones dos inputs no tema escuro */
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
    [data-theme="dark"] input[type="number"]::-webkit-inner-spin-button,
    [data-theme="dark"] input[type="number"]::-webkit-outer-spin-button {
      filter: invert(1);
    }

    /* Remover setas do input number */
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    input[type="number"] {
      -moz-appearance: textfield;
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

      .stat-item:not(:last-child) {
        border-right: none !important;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 16px;
        margin-bottom: 16px;
      }

      /* Reminder cards em mobile */
      .reminder-card .d-flex.justify-content-between.align-items-start {
        flex-direction: column !important;
        gap: 12px;
      }

      .reminder-card .text-end.ms-3 {
        margin-left: 0 !important;
        text-align: left !important;
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
      }

      .reminder-card .d-flex.justify-content-between.flex-wrap {
        flex-direction: column;
        gap: 8px;
      }

      .reminder-card .reminder-actions {
        width: 100%;
      }

      .reminder-card .reminder-actions .btn,
      .reminder-card .reminder-actions form {
        flex: 1;
      }

      .reminder-card .reminder-actions form .btn {
        width: 100%;
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
      <img src="../assets/logo2.png" alt="Freecard"> FreeCard
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
        <li class="nav-item"><a class="nav-link active" href="reminders.php"><i class="bi bi-calendar-check"></i> Lembretes</a></li>
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
      <h2><i class="bi bi-calendar-check"></i> Lembretes de Pagamento</h2>
      <p class="text-muted mb-0">Gere os teus pagamentos futuros e despesas recorrentes</p>
    </div>
    <div class="page-header-buttons">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReminderModal">
        <i class="bi bi-plus-circle"></i> Novo Lembrete
      </button>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?=$messageType?> alert-dismissible fade show">
      <?=htmlspecialchars($message)?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Estatísticas -->
  <div class="summary-card mb-4">
    <div class="row">
      <div class="col-md-4">
        <div class="stat-item">
          <h3 class="text-danger">€<?=number_format($total_month, 2)?></h3>
          <p>A Pagar Este Mês</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-item">
          <h3 style="color: #f39c12;"><?=$upcoming_count?></h3>
          <p>Vencimentos Próximos (7 dias)</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-item">
          <h3 class="text-danger"><?=$overdue_count?></h3>
          <p>Pagamentos Atrasados</p>
        </div>
      </div>
    </div>
  </div>

  <?php if (empty($reminders)): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="bi bi-calendar-check" style="font-size: 80px; color: #e0e0e0;"></i>
        <h4 class="text-muted mt-4 mb-3">Ainda não tens lembretes de pagamento</h4>
        <p class="text-muted mb-4">Cria o teu primeiro lembrete para nunca mais esquecer um pagamento</p>
        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#newReminderModal">
          <i class="bi bi-plus-circle"></i> Criar Primeiro Lembrete
        </button>
      </div>
    </div>
  <?php else: ?>
    
    <?php if (!empty($overdueReminders)): ?>
      <h5 class="text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> Pagamentos Atrasados</h5>
      <?php foreach($overdueReminders as $r): ?>
        <?php include '../components/reminder_card.php'; ?>
      <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($upcomingReminders)): ?>
      <h5 class="mb-3" style="color: #f39c12;"><i class="bi bi-clock-history"></i> Vencimentos Próximos</h5>
      <?php foreach($upcomingReminders as $r): ?>
        <?php include '../components/reminder_card.php'; ?>
      <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($futureReminders)): ?>
      <h5 class="mb-3"><i class="bi bi-calendar3"></i> Pagamentos Futuros</h5>
      <?php foreach($futureReminders as $r): ?>
        <?php include '../components/reminder_card.php'; ?>
      <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($inactiveReminders)): ?>
      <h5 class="text-muted mb-3"><i class="bi bi-archive"></i> Inativos</h5>
      <?php foreach($inactiveReminders as $r): ?>
        <?php include '../components/reminder_card.php'; ?>
      <?php endforeach; ?>
    <?php endif; ?>
    
  <?php endif; ?>
</div>

<!-- Modal Novo Lembrete -->
<div class="modal fade" id="newReminderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background: var(--bg-secondary); border: none; border-radius: 20px;">
      <div class="modal-header" style="border: none;">
        <h5 class="modal-title" style="color: var(--text-primary);">
          <i class="bi bi-calendar-plus"></i> Novo Lembrete de Pagamento
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome do Pagamento *</label>
            <input type="text" name="name" class="form-control" placeholder="ex: Netflix, Renda, Conta da Luz" required>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Valor (€) *</label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="15.99" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Data de Vencimento *</label>
              <input type="date" name="due_date" class="form-control" min="<?=date('Y-m-d')?>" required>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Recorrência</label>
            <select name="recurrence" class="form-select">
              <option value="once">Única vez</option>
              <option value="weekly">Semanal</option>
              <option value="monthly" selected>Mensal</option>
              <option value="yearly">Anual</option>
            </select>
            <small class="text-muted">Para pagamentos recorrentes, a próxima data será calculada automaticamente</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Categoria (opcional)</label>
            <select name="category" class="form-select">
              <option value="">Sem categoria</option>
              <?php foreach($categories as $cat): ?>
                <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Cartão (opcional)</label>
            <select name="card_id" class="form-select">
              <option value="">Dinheiro / A definir</option>
              <?php foreach($cards as $c): ?>
                <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Podes alterar na hora do pagamento</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Notificar com quantos dias de antecedência?</label>
            <select name="notify_days_before" class="form-select">
              <option value="0">No dia</option>
              <option value="1">1 dia antes</option>
              <option value="3" selected>3 dias antes</option>
              <option value="7">7 dias antes</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Como deseja receber as notificações?</label>
            <select name="notify_method" class="form-select">
              <option value="email" selected>Email</option>
              <option value="site">Notificação no Site</option>
              <option value="both">Email + Notificação</option>
            </select>
            <small class="text-muted">Escolhe como queres ser notificado dos lembretes</small>
          </div>
        </div>
        <div class="modal-footer" style="border: none;">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar Lembrete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Marcar Como Pago -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background: var(--bg-secondary); border: none; border-radius: 20px;">
      <div class="modal-header" style="border: none;">
        <h5 class="modal-title" style="color: var(--text-primary);">
          <i class="bi bi-check-circle"></i> Marcar Como Pago
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="markPaidForm">
        <input type="hidden" name="action" value="mark_paid">
        <input type="hidden" name="reminder_id" id="mark_paid_reminder_id">
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Ao marcar como pago, podes criar automaticamente uma transação no sistema.
          </div>
          
          <div class="mb-3">
            <label class="form-label">Data do Pagamento</label>
            <input type="date" name="paid_date" class="form-control" value="<?=date('Y-m-d')?>" max="<?=date('Y-m-d')?>" required>
          </div>
          
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="create_transaction" id="create_transaction" checked>
            <label class="form-check-label" for="create_transaction">
              Criar transação e atualizar saldo do cartão
            </label>
          </div>
        </div>
        <div class="modal-footer" style="border: none;">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Confirmar Pagamento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Lembrete -->
<div class="modal fade" id="editReminderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background: var(--bg-secondary); border: none; border-radius: 20px;">
      <div class="modal-header" style="border: none;">
        <h5 class="modal-title" style="color: var(--text-primary);">
          <i class="bi bi-pencil"></i> Editar Lembrete
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="reminder_id" id="edit_reminder_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome do Pagamento *</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Valor (€) *</label>
              <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Data de Vencimento *</label>
              <input type="date" name="due_date" id="edit_due_date" class="form-control" required>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Recorrência</label>
            <select name="recurrence" id="edit_recurrence" class="form-select">
              <option value="once">Única vez</option>
              <option value="weekly">Semanal</option>
              <option value="monthly">Mensal</option>
              <option value="yearly">Anual</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Categoria (opcional)</label>
            <select name="category" id="edit_category" class="form-select">
              <option value="">Sem categoria</option>
              <?php foreach($categories as $cat): ?>
                <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Cartão (opcional)</label>
            <select name="card_id" id="edit_card_id" class="form-select">
              <option value="">Dinheiro / A definir</option>
              <?php foreach($cards as $c): ?>
                <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Notificar com quantos dias de antecedência?</label>
            <select name="notify_days_before" id="edit_notify_days" class="form-select">
              <option value="0">No dia</option>
              <option value="1">1 dia antes</option>
              <option value="3">3 dias antes</option>
              <option value="7">7 dias antes</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Como deseja receber as notificações?</label>
            <select name="notify_method" id="edit_notify_method" class="form-select">
              <option value="email">Email</option>
              <option value="site">Notificação no Site</option>
              <option value="both">Email + Notificação</option>
            </select>
            <small class="text-muted">Escolhe como queres ser notificado dos lembretes</small>
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
function openMarkPaidModal(reminderId) {
  document.getElementById('mark_paid_reminder_id').value = reminderId;
  var modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
  modal.show();
}

function openEditModal(reminder) {
  document.getElementById('edit_reminder_id').value = reminder.id;
  document.getElementById('edit_name').value = reminder.name;
  document.getElementById('edit_amount').value = reminder.amount;
  document.getElementById('edit_due_date').value = reminder.due_date;
  document.getElementById('edit_recurrence').value = reminder.recurrence;
  document.getElementById('edit_category').value = reminder.category || '';
  document.getElementById('edit_card_id').value = reminder.card_id || '';
  document.getElementById('edit_notify_days').value = reminder.notify_days_before;
  document.getElementById('edit_notify_method').value = reminder.notify_method || 'email';
  
  var modal = new bootstrap.Modal(document.getElementById('editReminderModal'));
  modal.show();
}
</script>
</body>
</html>