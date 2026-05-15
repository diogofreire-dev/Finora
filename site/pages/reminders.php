<?php
// site/reminders.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

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
                $next_due = $due_date;
                if ($recurrence !== 'once') {
                    $dateObj = new DateTime($due_date);
                    match($recurrence) { 'weekly' => $dateObj->modify('+1 week'), 'monthly' => $dateObj->modify('+1 month'), 'yearly' => $dateObj->modify('+1 year'), default => null };
                    $next_due = $dateObj->format('Y-m-d');
                }
                $stmt = $pdo->prepare("INSERT INTO payment_reminders (user_id, name, amount, category, card_id, due_date, recurrence, notify_days_before, notify_method, next_due_date) VALUES (:uid, :name, :amount, :category, :card_id, :due_date, :recurrence, :notify, :notify_method, :next_due)");
                $stmt->execute([':uid'=>$uid,':name'=>$name,':amount'=>$amount,':category'=>$category?:null,':card_id'=>$card_id,':due_date'=>$due_date,':recurrence'=>$recurrence,':notify'=>$notify_days,':notify_method'=>$notify_method,':next_due'=>$next_due]);
                $message = 'Lembrete criado com sucesso!'; $messageType = 'success';
            } catch (PDOException $e) { $message = 'Erro ao criar lembrete.'; $messageType = 'danger'; }
        }
    } elseif ($action === 'mark_paid') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        $paid_date = $_POST['paid_date'] ?? date('Y-m-d');
        $create_transaction = isset($_POST['create_transaction']);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM payment_reminders WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id'=>$reminder_id,':uid'=>$uid]);
            $reminder = $stmt->fetch();
            if ($reminder) {
                $transaction_id = null;
                if ($create_transaction) {
                    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date) VALUES (:uid, :card_id, :amount, :desc, :category, :tdate)");
                    $stmt->execute([':uid'=>$uid,':card_id'=>$reminder['card_id']?:null,':amount'=>$reminder['amount'],':desc'=>$reminder['name'],':category'=>$reminder['category']?:null,':tdate'=>$paid_date]);
                    $transaction_id = $pdo->lastInsertId();
                    if ($reminder['card_id']) { $stmt = $pdo->prepare("UPDATE cards SET balance = balance + :amount WHERE id = :card_id AND user_id = :uid"); $stmt->execute([':amount'=>$reminder['amount'],':card_id'=>$reminder['card_id'],':uid'=>$uid]); }
                }
                $stmt = $pdo->prepare("INSERT INTO reminder_history (reminder_id, transaction_id, paid_date, amount) VALUES (:rid, :tid, :pdate, :amount)");
                $stmt->execute([':rid'=>$reminder_id,':tid'=>$transaction_id,':pdate'=>$paid_date,':amount'=>$reminder['amount']]);

                if ($reminder['recurrence'] === 'once') {
                    // Marcar como PAGO (paid=1), NÃO como inativo
                    $stmt = $pdo->prepare("UPDATE payment_reminders SET paid = 1, last_paid_date = :pdate WHERE id = :id");
                    $stmt->execute([':pdate'=>$paid_date,':id'=>$reminder_id]);
                } else {
                    // Recorrente: avançar para próxima data, marcar o ciclo atual como pago
                    $nextDate = new DateTime($reminder['due_date']);
                    match($reminder['recurrence']) { 'weekly' => $nextDate->modify('+1 week'), 'monthly' => $nextDate->modify('+1 month'), 'yearly' => $nextDate->modify('+1 year'), default => null };
                    $stmt = $pdo->prepare("UPDATE payment_reminders SET last_paid_date=:pdate, due_date=:next_due, next_due_date=:next_due_date, paid=0 WHERE id=:id");
                    $stmt->execute([':pdate'=>$paid_date,':next_due'=>$nextDate->format('Y-m-d'),':next_due_date'=>$nextDate->format('Y-m-d'),':id'=>$reminder_id]);
                }
                $pdo->commit(); $message = 'Pagamento registado com sucesso!'; $messageType = 'success';
            }
        } catch (PDOException $e) { $pdo->rollBack(); $message = 'Erro ao registar pagamento.'; $messageType = 'danger'; }
    } elseif ($action === 'edit') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        $name = trim($_POST['name'] ?? ''); $amount = floatval($_POST['amount'] ?? 0);
        $category = trim($_POST['category'] ?? ''); $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
        $due_date = $_POST['due_date'] ?? ''; $recurrence = $_POST['recurrence'] ?? 'once';
        $notify_days = intval($_POST['notify_days_before'] ?? 3);
        if ($amount > 0 && strlen($name) >= 3 && $due_date) {
            try {
                $next_due = $due_date;
                if ($recurrence !== 'once') { $dateObj = new DateTime($due_date); match($recurrence) { 'weekly' => $dateObj->modify('+1 week'), 'monthly' => $dateObj->modify('+1 month'), 'yearly' => $dateObj->modify('+1 year'), default => null }; $next_due = $dateObj->format('Y-m-d'); }
                $stmt = $pdo->prepare("UPDATE payment_reminders SET name=:name, amount=:amount, category=:category, card_id=:card_id, due_date=:due_date, recurrence=:recurrence, notify_days_before=:notify, notify_method=:notify_method, next_due_date=:next_due WHERE id=:id AND user_id=:uid");
                $stmt->execute([':id'=>$reminder_id,':uid'=>$uid,':name'=>$name,':amount'=>$amount,':category'=>$category?:null,':card_id'=>$card_id,':due_date'=>$due_date,':recurrence'=>$recurrence,':notify'=>$notify_days,':notify_method'=>$_POST['notify_method']??'email',':next_due'=>$next_due]);
                $message = 'Lembrete atualizado com sucesso!'; $messageType = 'success';
            } catch (PDOException $e) { $message = 'Erro ao atualizar lembrete.'; $messageType = 'danger'; }
        }
    } elseif ($action === 'toggle') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try { $stmt = $pdo->prepare("UPDATE payment_reminders SET active = NOT active WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$reminder_id,':uid'=>$uid]); $message = 'Estado alterado!'; $messageType = 'success'; } catch (PDOException $e) { $message = 'Erro.'; $messageType = 'danger'; }
    } elseif ($action === 'delete') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try { $stmt = $pdo->prepare("DELETE FROM payment_reminders WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$reminder_id,':uid'=>$uid]); $message = 'Lembrete removido!'; $messageType = 'success'; } catch (PDOException $e) { $message = 'Erro.'; $messageType = 'danger'; }
    } elseif ($action === 'unmark_paid') {
        // Permite reverter pagamento de um lembrete "once" para ativo novamente
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try { $stmt = $pdo->prepare("UPDATE payment_reminders SET paid=0, active=1 WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$reminder_id,':uid'=>$uid]); $message = 'Pagamento revertido!'; $messageType = 'success'; } catch (PDOException $e) { $message = 'Erro.'; $messageType = 'danger'; }
    }
}

// Fetch all reminders — inclui paid=1 separadamente
$stmt = $pdo->prepare("
    SELECT r.*, c.name as card_name,
           CASE
             WHEN r.paid = 1 THEN 'paid'
             WHEN r.active = 0 THEN 'inactive'
             WHEN r.due_date < CURDATE() THEN 'overdue'
             WHEN r.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'upcoming'
             ELSE 'future'
           END as status
    FROM payment_reminders r
    LEFT JOIN cards c ON c.id = r.card_id
    WHERE r.user_id = :uid
    ORDER BY
      CASE WHEN r.paid=1 THEN 2 WHEN r.active=0 THEN 3 ELSE 1 END,
      r.due_date ASC
");
$stmt->execute([':uid'=>$uid]);
$reminders = $stmt->fetchAll();

// ── Resumo do mês ────────────────────────────────────────────────────────────
$total_month = 0; $overdue_count = 0; $upcoming_count = 0; $paid_month_total = 0;
$today = new DateTime(); $endOfMonth = new DateTime('last day of this month');
$startOfMonth = new DateTime('first day of this month');

foreach($reminders as $r) {
    if ($r['status'] === 'inactive') continue;

    $dueDate = new DateTime($r['due_date']);

    // Pagos este mês
    if ($r['status'] === 'paid' && $r['last_paid_date']) {
        $paidDate = new DateTime($r['last_paid_date']);
        if ($paidDate >= $startOfMonth && $paidDate <= $today) {
            $paid_month_total += $r['amount'];
        }
    }

    // A pagar este mês (ainda não pagos)
    if ($r['status'] !== 'paid' && $dueDate <= $endOfMonth && $dueDate >= $today) {
        $total_month += $r['amount'];
    }

    if ($r['status'] === 'overdue')  $overdue_count++;
    if ($r['status'] === 'upcoming') $upcoming_count++;
}

// Pago este mês via histórico também
$stmtPaid = $pdo->prepare("
    SELECT COALESCE(SUM(rh.amount),0) as total
    FROM reminder_history rh
    INNER JOIN payment_reminders r ON r.id = rh.reminder_id
    WHERE r.user_id = :uid
      AND rh.paid_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
      AND rh.paid_date <= CURDATE()
");
$stmtPaid->execute([':uid'=>$uid]);
$paid_month_total = floatval($stmtPaid->fetch()['total']);

$categories = ['Compras','Alimentação','Transporte','Saúde','Entretenimento','Educação','Casa','Outros'];
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid'=>$uid]);
$cards = $stmt->fetchAll();

$overdueReminders   = array_filter($reminders, fn($r) => $r['status'] === 'overdue');
$upcomingReminders  = array_filter($reminders, fn($r) => $r['status'] === 'upcoming');
$futureReminders    = array_filter($reminders, fn($r) => $r['status'] === 'future');
$paidReminders      = array_filter($reminders, fn($r) => $r['status'] === 'paid');
$inactiveReminders  = array_filter($reminders, fn($r) => $r['status'] === 'inactive');

$recurrenceLabels = ['once'=>'Única vez','weekly'=>'Semanal','monthly'=>'Mensal','yearly'=>'Anual'];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lembretes de Pagamento - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary); }
    .settings-wrapper { max-width: 700px; margin: 0 auto; padding: 0 16px 60px; }
    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary); }
    .page-header p { font-size: 13px; color: var(--text-secondary); margin: 0; }
    .settings-group { margin-bottom: 24px; }
    .group-label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-secondary); margin: 0 0 8px 4px; }
    .settings-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; }
    .s-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; transition: background .15s; }
    .s-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .s-row-label { font-size: 14px; font-weight: 500; color: var(--text-primary); margin: 0 0 2px; }
    .s-row-sub { font-size: 12px; color: var(--text-secondary); margin: 0; }
    .btn-s { font-size: 13px; font-weight: 500; padding: 6px 14px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; }
    .btn-s-primary:hover { background: #2980b9; color: #fff; border-color: #2980b9; }

    /* Green paid button — softer tone */
    .btn-s-success {
      background: rgba(34,197,94,.09);
      color: #16a34a;
      border-color: rgba(34,197,94,.28);
    }
    .btn-s-success:hover {
      background: rgba(34,197,94,.16);
      border-color: rgba(34,197,94,.45);
      color: #15803d;
    }
    [data-theme="dark"] .btn-s-success {
      background: rgba(34,197,94,.10);
      color: #4ade80;
      border-color: rgba(34,197,94,.22);
    }
    [data-theme="dark"] .btn-s-success:hover {
      background: rgba(34,197,94,.18);
    }

    .btn-s-danger { background: rgba(220,38,38,.07); border-color: rgba(220,38,38,.2); color: #dc2626; }
    .btn-s-danger:hover { background: rgba(220,38,38,.14); border-color: rgba(220,38,38,.4); }
    [data-theme="dark"] .btn-s-danger { background: rgba(220,38,38,.10); border-color: rgba(220,38,38,.25); color: #f87171; }
    [data-theme="dark"] .btn-s-danger:hover { background: rgba(220,38,38,.18); }

    .s-alert { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
    .s-alert-success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    .s-alert-info    { background: rgba(59,130,246,.1); border-color: rgba(59,130,246,.3); color: #1e3a8a; }
    [data-theme="dark"] .s-alert-success { color: #6ee7b7; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }
    [data-theme="dark"] .s-alert-info    { color: #93c5fd; }

    /* ── Stats row — now 4 cells ── */
    .stats-row { display: flex; }
    .stat-cell { flex: 1; padding: 14px 16px; text-align: center; }
    .stat-cell:not(:last-child) { border-right: 1px solid var(--border-color); }
    .stat-num { font-size: 20px; font-weight: 700; line-height: 1; margin-bottom: 3px; }
    .stat-label { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; }

    .s-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; }
    .s-badge-overdue  { background: rgba(239,68,68,.12);  color: #dc2626; }
    .s-badge-upcoming { background: rgba(245,158,11,.12); color: #d97706; }
    .s-badge-future   { background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); }
    .s-badge-inactive { background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); }
    .s-badge-recur    { background: rgba(59,130,246,.1); color: #2563eb; }

    /* ── PAID badge — soft green ── */
    .s-badge-paid {
      background: rgba(34,197,94,.10);
      color: #16a34a;
      border: 1px solid rgba(34,197,94,.25);
    }
    [data-theme="dark"] .s-badge-paid { color: #4ade80; border-color: rgba(34,197,94,.2); }
    [data-theme="dark"] .s-badge-overdue  { color: #fca5a5; }
    [data-theme="dark"] .s-badge-upcoming { color: #fcd34d; }
    [data-theme="dark"] .s-badge-recur    { color: #93c5fd; }

    /* ── Paid section row styling ── */
    .paid-row { background: rgba(34,197,94,.035); }
    [data-theme="dark"] .paid-row { background: rgba(34,197,94,.045); }

    .empty-state { padding: 40px 20px; text-align: center; }
    .empty-state i { font-size: 48px; color: var(--border-color); display: block; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; color: var(--text-secondary); margin-bottom: 16px; }
    /* Modal */
    .modal-content { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; }
    .modal-header { border-bottom: 1px solid var(--border-color); padding: 18px 20px; }
    .modal-footer { border-top: 1px solid var(--border-color); padding: 14px 20px; }
    .modal-title { font-size: 15px; font-weight: 600; color: var(--text-primary); }
    .m-form .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    .m-form .form-control, .m-form .form-select { font-size: 14px; border: 1px solid var(--border-color); border-radius: 9px; padding: 9px 12px; background: var(--bg-primary); color: var(--text-primary); }
    .m-form .form-control:focus, .m-form .form-select:focus { border-color: #3498db; box-shadow: none; background: var(--bg-primary); color: var(--text-primary); }
    [data-theme="dark"] .m-form .form-control, [data-theme="dark"] .m-form .form-select { background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color); }
    input[type="number"]::-webkit-inner-spin-button, input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
    input[type="number"] { -moz-appearance: textfield; }
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
    @media (max-width:600px) { .stats-row { flex-wrap: wrap; } .stat-cell { min-width: 50%; } .stat-cell:nth-child(2) { border-right: none; } .stat-cell:nth-child(n+3) { border-top: 1px solid var(--border-color); } .s-row { flex-wrap: wrap; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='reminders'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-calendar-check"></i></div>
    <div style="flex:1">
      <h2>Lembretes de Pagamento</h2>
      <p>Gere os teus pagamentos futuros e despesas recorrentes</p>
    </div>
    <button class="btn-s btn-s-primary" data-bs-toggle="modal" data-bs-target="#newReminderModal">
      <i class="bi bi-plus-circle"></i> Novo
    </button>
  </div>

  <?php if ($message): ?>
    <?php $icon = $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle'); ?>
    <div class="s-alert s-alert-<?=$messageType?>">
      <i class="bi bi-<?=$icon?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?=htmlspecialchars($message)?></span>
    </div>
  <?php endif; ?>

  <!-- RESUMO — agora com 4 células -->
  <div class="settings-group">
    <p class="group-label">Resumo do mês</p>
    <div class="settings-card">
      <div class="stats-row">
        <div class="stat-cell">
          <div class="stat-num" style="color:#3498db">€<?=number_format($paid_month_total,2)?></div>
          <div class="stat-label">Pagos este mês</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num" style="color:#e74c3c">€<?=number_format($total_month,2)?></div>
          <div class="stat-label">A pagar este mês</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num" style="color:#d97706"><?=$upcoming_count?></div>
          <div class="stat-label">Próximos 7 dias</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num" style="color:#e74c3c"><?=$overdue_count?></div>
          <div class="stat-label">Atrasados</div>
        </div>
      </div>
    </div>
  </div>

  <?php if (empty($reminders)): ?>
    <div class="settings-card">
      <div class="empty-state">
        <i class="bi bi-calendar-check"></i>
        <p>Ainda não tens lembretes de pagamento.<br>Cria o primeiro para nunca mais esquecer um pagamento.</p>
        <button class="btn-s btn-s-primary" data-bs-toggle="modal" data-bs-target="#newReminderModal">
          <i class="bi bi-plus-circle"></i> Criar Primeiro Lembrete
        </button>
      </div>
    </div>
  <?php else: ?>

    <?php if (!empty($overdueReminders)): ?>
    <div class="settings-group">
      <p class="group-label" style="color:#dc2626"><i class="bi bi-exclamation-triangle me-1"></i>Atrasados</p>
      <div class="settings-card">
        <?php foreach($overdueReminders as $r): ?>
        <div class="s-row" style="align-items:flex-start">
          <div style="flex:1;min-width:0">
            <p class="s-row-label"><?=htmlspecialchars($r['name'])?></p>
            <p class="s-row-sub">
              Venceu a <?=date('d/m/Y', strtotime($r['due_date']))?>
              &nbsp;<span class="s-badge s-badge-overdue"><i class="bi bi-exclamation-circle" style="font-size:10px"></i> Atrasado</span>
              <?php if($r['recurrence']!=='once'): ?>&nbsp;<span class="s-badge s-badge-recur"><?=$recurrenceLabels[$r['recurrence']]?></span><?php endif; ?>
              <?php if($r['category']): ?>&nbsp;<span class="s-badge s-badge-future"><i class="bi bi-tag" style="font-size:10px"></i> <?=htmlspecialchars($r['category'])?></span><?php endif; ?>
            </p>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <span style="font-size:16px;font-weight:700;color:var(--text-primary)">€<?=number_format($r['amount'],2)?></span>
            <button class="btn-s btn-s-success" onclick="openMarkPaidModal(<?=$r['id']?>)"><i class="bi bi-check-circle"></i> Pago</button>
            <button class="btn-s" onclick="openEditModal(<?=htmlspecialchars(json_encode($r))?>)"><i class="bi bi-pencil"></i></button>
            <form method="post" style="margin:0" onsubmit="return confirm('Eliminar lembrete?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="reminder_id" value="<?=$r['id']?>">
              <button class="btn-s btn-s-danger" type="submit"><i class="bi bi-trash3"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($upcomingReminders)): ?>
    <div class="settings-group">
      <p class="group-label" style="color:#d97706"><i class="bi bi-clock-history me-1"></i>Próximos 7 dias</p>
      <div class="settings-card">
        <?php foreach($upcomingReminders as $r): ?>
        <div class="s-row" style="align-items:flex-start">
          <div style="flex:1;min-width:0">
            <p class="s-row-label"><?=htmlspecialchars($r['name'])?></p>
            <p class="s-row-sub">
              Vence a <?=date('d/m/Y', strtotime($r['due_date']))?>
              &nbsp;<span class="s-badge s-badge-upcoming"><i class="bi bi-clock" style="font-size:10px"></i> Em breve</span>
              <?php if($r['recurrence']!=='once'): ?>&nbsp;<span class="s-badge s-badge-recur"><?=$recurrenceLabels[$r['recurrence']]?></span><?php endif; ?>
              <?php if($r['category']): ?>&nbsp;<span class="s-badge s-badge-future"><i class="bi bi-tag" style="font-size:10px"></i> <?=htmlspecialchars($r['category'])?></span><?php endif; ?>
            </p>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <span style="font-size:16px;font-weight:700;color:var(--text-primary)">€<?=number_format($r['amount'],2)?></span>
            <button class="btn-s btn-s-success" onclick="openMarkPaidModal(<?=$r['id']?>)"><i class="bi bi-check-circle"></i> Pago</button>
            <button class="btn-s" onclick="openEditModal(<?=htmlspecialchars(json_encode($r))?>)"><i class="bi bi-pencil"></i></button>
            <form method="post" style="margin:0" onsubmit="return confirm('Eliminar lembrete?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="reminder_id" value="<?=$r['id']?>">
              <button class="btn-s btn-s-danger" type="submit"><i class="bi bi-trash3"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($futureReminders)): ?>
    <div class="settings-group">
      <p class="group-label"><i class="bi bi-calendar3 me-1"></i>Futuros</p>
      <div class="settings-card">
        <?php foreach($futureReminders as $r): ?>
        <div class="s-row" style="align-items:flex-start">
          <div style="flex:1;min-width:0">
            <p class="s-row-label"><?=htmlspecialchars($r['name'])?></p>
            <p class="s-row-sub">
              Vence a <?=date('d/m/Y', strtotime($r['due_date']))?>
              <?php if($r['recurrence']!=='once'): ?>&nbsp;<span class="s-badge s-badge-recur"><?=$recurrenceLabels[$r['recurrence']]?></span><?php endif; ?>
              <?php if($r['category']): ?>&nbsp;<span class="s-badge s-badge-future"><i class="bi bi-tag" style="font-size:10px"></i> <?=htmlspecialchars($r['category'])?></span><?php endif; ?>
            </p>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <span style="font-size:16px;font-weight:700;color:var(--text-primary)">€<?=number_format($r['amount'],2)?></span>
            <button class="btn-s btn-s-success" onclick="openMarkPaidModal(<?=$r['id']?>)"><i class="bi bi-check-circle"></i> Pago</button>
            <button class="btn-s" onclick="openEditModal(<?=htmlspecialchars(json_encode($r))?>)"><i class="bi bi-pencil"></i></button>
            <form method="post" style="margin:0" onsubmit="return confirm('Eliminar lembrete?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="reminder_id" value="<?=$r['id']?>">
              <button class="btn-s btn-s-danger" type="submit"><i class="bi bi-trash3"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── PAGOS (antes "inativos" para lembretes únicos confirmados) ── -->
    <?php if (!empty($paidReminders)): ?>
    <div class="settings-group">
      <p class="group-label" style="color:#16a34a"><i class="bi bi-check-circle-fill me-1"></i>Pagos</p>
      <div class="settings-card">
        <?php foreach($paidReminders as $r): ?>
        <div class="s-row paid-row" style="align-items:flex-start">
          <div style="flex:1;min-width:0">
            <p class="s-row-label" style="color:var(--text-secondary)"><?=htmlspecialchars($r['name'])?></p>
            <p class="s-row-sub">
              Pago a <?=date('d/m/Y', strtotime($r['last_paid_date'] ?? $r['due_date']))?>
              &nbsp;<span class="s-badge s-badge-paid"><i class="bi bi-check-circle" style="font-size:10px"></i> Pago</span>
              <?php if($r['recurrence']!=='once'): ?>&nbsp;<span class="s-badge s-badge-recur"><?=$recurrenceLabels[$r['recurrence']]?></span><?php endif; ?>
              <?php if($r['category']): ?>&nbsp;<span class="s-badge s-badge-future"><i class="bi bi-tag" style="font-size:10px"></i> <?=htmlspecialchars($r['category'])?></span><?php endif; ?>
            </p>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <span style="font-size:15px;font-weight:600;color:var(--text-secondary)">€<?=number_format($r['amount'],2)?></span>
            <!-- Reverter pagamento -->
            <form method="post" style="margin:0" onsubmit="return confirm('Reverter pagamento?')">
              <input type="hidden" name="action" value="unmark_paid">
              <input type="hidden" name="reminder_id" value="<?=$r['id']?>">
              <button class="btn-s" style="padding:5px 10px;font-size:12px" type="submit" title="Reverter para ativo"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
            <form method="post" style="margin:0" onsubmit="return confirm('Eliminar?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="reminder_id" value="<?=$r['id']?>">
              <button class="btn-s btn-s-danger" style="padding:5px 10px" type="submit"><i class="bi bi-trash3"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Inativos manuais (toggle) -->
    <?php if (!empty($inactiveReminders)): ?>
    <div class="settings-group">
      <p class="group-label"><i class="bi bi-archive me-1"></i>Inativos</p>
      <div class="settings-card">
        <?php foreach($inactiveReminders as $r): ?>
        <div class="s-row" style="align-items:flex-start;opacity:.55">
          <div style="flex:1;min-width:0">
            <p class="s-row-label"><?=htmlspecialchars($r['name'])?></p>
            <p class="s-row-sub"><?=date('d/m/Y', strtotime($r['due_date']))?> &nbsp;<span class="s-badge s-badge-inactive">Inativo</span></p>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <span style="font-size:15px;font-weight:600;color:var(--text-secondary)">€<?=number_format($r['amount'],2)?></span>
            <form method="post" style="margin:0"><input type="hidden" name="action" value="toggle"><input type="hidden" name="reminder_id" value="<?=$r['id']?>"><button class="btn-s" style="padding:5px 12px" type="submit"><i class="bi bi-play-circle"></i> Ativar</button></form>
            <form method="post" style="margin:0" onsubmit="return confirm('Eliminar?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="reminder_id" value="<?=$r['id']?>"><button class="btn-s btn-s-danger" style="padding:5px 10px" type="submit"><i class="bi bi-trash3"></i></button></form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <?php endif; ?>

</div>
</div>

<!-- Modal Novo Lembrete -->
<div class="modal fade" id="newReminderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Novo Lembrete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" class="m-form">
        <input type="hidden" name="action" value="create">
        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;padding:20px">
          <div><label class="form-label">Nome do Pagamento *</label><input type="text" name="name" class="form-control" placeholder="ex: Netflix, Renda, Conta da Luz" required></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label">Valor (€) *</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="15.99" required></div>
            <div><label class="form-label">Data de Vencimento *</label><input type="date" name="due_date" class="form-control" min="<?=date('Y-m-d')?>" required></div>
          </div>
          <div><label class="form-label">Recorrência</label><select name="recurrence" class="form-select"><option value="once">Única vez</option><option value="weekly">Semanal</option><option value="monthly" selected>Mensal</option><option value="yearly">Anual</option></select></div>
          <div><label class="form-label">Categoria</label><select name="category" class="form-select"><option value="">Sem categoria</option><?php foreach($categories as $cat): ?><option><?=htmlspecialchars($cat)?></option><?php endforeach; ?></select></div>
          <div><label class="form-label">Cartão</label><select name="card_id" class="form-select"><option value="">Dinheiro / A definir</option><?php foreach($cards as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label">Notificar</label><select name="notify_days_before" class="form-select"><option value="0">No dia</option><option value="1">1 dia antes</option><option value="3" selected>3 dias antes</option><option value="7">7 dias antes</option></select></div>
            <div><label class="form-label">Método</label><select name="notify_method" class="form-select"><option value="email" selected>Email</option><option value="site">Notificação</option><option value="both">Ambos</option></select></div>
          </div>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn-s" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> Criar Lembrete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Marcar Como Pago -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Marcar Como Pago</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="markPaidForm" class="m-form">
        <input type="hidden" name="action" value="mark_paid">
        <input type="hidden" name="reminder_id" id="mark_paid_reminder_id">
        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;padding:20px">
          <div class="s-alert s-alert-info"><i class="bi bi-info-circle" style="flex-shrink:0;margin-top:1px"></i><span>Ao marcar como pago, podes criar automaticamente uma transação no sistema.</span></div>
          <div><label class="form-label">Data do Pagamento</label><input type="date" name="paid_date" class="form-control" value="<?=date('Y-m-d')?>" max="<?=date('Y-m-d')?>" required></div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
            <input class="form-check-input" type="checkbox" name="create_transaction" checked style="margin:0">
            Criar transação e atualizar saldo do cartão
          </label>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn-s" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn-s btn-s-success"><i class="bi bi-check-circle"></i> Confirmar Pagamento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="editReminderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Lembrete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" class="m-form">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="reminder_id" id="edit_reminder_id">
        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;padding:20px">
          <div><label class="form-label">Nome *</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label">Valor (€) *</label><input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0.01" required></div>
            <div><label class="form-label">Data *</label><input type="date" name="due_date" id="edit_due_date" class="form-control" required></div>
          </div>
          <div><label class="form-label">Recorrência</label><select name="recurrence" id="edit_recurrence" class="form-select"><option value="once">Única vez</option><option value="weekly">Semanal</option><option value="monthly">Mensal</option><option value="yearly">Anual</option></select></div>
          <div><label class="form-label">Categoria</label><select name="category" id="edit_category" class="form-select"><option value="">Sem categoria</option><?php foreach($categories as $cat): ?><option><?=htmlspecialchars($cat)?></option><?php endforeach; ?></select></div>
          <div><label class="form-label">Cartão</label><select name="card_id" id="edit_card_id" class="form-select"><option value="">Dinheiro / A definir</option><?php foreach($cards as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label">Notificar</label><select name="notify_days_before" id="edit_notify_days" class="form-select"><option value="0">No dia</option><option value="1">1 dia antes</option><option value="3">3 dias antes</option><option value="7">7 dias antes</option></select></div>
            <div><label class="form-label">Método</label><select name="notify_method" id="edit_notify_method" class="form-select"><option value="email">Email</option><option value="site">Notificação</option><option value="both">Ambos</option></select></div>
          </div>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn-s" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openMarkPaidModal(reminderId) {
  document.getElementById('mark_paid_reminder_id').value = reminderId;
  new bootstrap.Modal(document.getElementById('markPaidModal')).show();
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
  new bootstrap.Modal(document.getElementById('editReminderModal')).show();
}
</script>
</body>
</html>