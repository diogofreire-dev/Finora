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
        $card_id = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
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
        $category = trim($_POST['category'] ?? ''); $card_id = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
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
// Linha de um lembrete por pagar (atrasado, próximo ou futuro)
function reminder_due_text(array $r): string {
    $days = (int) floor((strtotime($r['due_date']) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0)  return 'Em atraso há ' . abs($days) . ' dia' . (abs($days) > 1 ? 's' : '');
    if ($days === 0) return 'Vence hoje';
    if ($days === 1) return 'Vence amanhã';
    return 'Vence em ' . $days . ' dias';
}
$groups = [
    ['key' => 'overdue',  'title' => 'Em atraso',         'items' => $overdueReminders],
    ['key' => 'upcoming', 'title' => 'Próximos 7 dias',   'items' => $upcomingReminders],
    ['key' => 'future',   'title' => 'Mais tarde',        'items' => $futureReminders],
];
$hasPending = $overdueReminders || $upcomingReminders || $futureReminders;
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lembretes - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
  <link rel="stylesheet" href="../css/finora.css?v=6">
  <style>
    .lm { max-width: 1000px; margin: 0 auto; padding-bottom: 56px; }
    .lm-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 6px; }
    .lm-head h1 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .lm-summary { margin: 0 0 28px; font-size: 15px; color: var(--fn-muted); }
    .lm-summary strong { color: var(--fn-text); }
    .lm-summary .late { color: #b42318; font-weight: 600; }
    [data-theme="dark"] .lm-summary .late { color: #fda29b; }

    .lm-group + .lm-group { margin-top: 26px; }
    .lm-group-head { display: flex; justify-content: space-between; align-items: baseline; margin: 0 4px 8px; }
    .lm-group-head h2 { margin: 0; font-size: 16px; font-weight: 700; color: var(--fn-text); }
    .lm-group-head span { font-size: 14px; color: var(--fn-muted); }
    .lm-group.overdue .lm-group-head h2 { color: #b42318; }
    [data-theme="dark"] .lm-group.overdue .lm-group-head h2 { color: #fda29b; }

    .lm-list { list-style: none; margin: 0; padding: 0; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; }
    .lm-group.overdue .lm-list { border-color: #fecdca; border-left: 4px solid #d92d20; }
    [data-theme="dark"] .lm-group.overdue .lm-list { border-color: rgba(253,162,155,.3); border-left-color: #f97066; }
    .lm-item { display: flex; align-items: center; gap: 16px; padding: 14px 16px 14px 18px; }
    .lm-item + .lm-item { border-top: 1px solid var(--fn-line); }

    .lm-date { width: 52px; text-align: center; flex-shrink: 0; border-radius: 12px; padding: 6px 0; background: var(--fn-paper); }
    .lm-date b { display: block; font-size: 19px; line-height: 1.1; color: var(--fn-text); }
    .lm-date small { font-size: 12px; color: var(--fn-muted); }
    .lm-group.overdue .lm-date { background: #fef3f2; }
    .lm-group.overdue .lm-date b, .lm-group.overdue .lm-date small { color: #b42318; }
    [data-theme="dark"] .lm-group.overdue .lm-date { background: rgba(253,162,155,.12); }
    [data-theme="dark"] .lm-group.overdue .lm-date b, [data-theme="dark"] .lm-group.overdue .lm-date small { color: #fda29b; }

    .lm-main { flex: 1; min-width: 0; }
    .lm-name { font-size: 15px; font-weight: 600; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lm-meta { margin-top: 2px; font-size: 13px; color: var(--fn-muted); display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .lm-meta .due { font-weight: 600; color: var(--fn-text); }
    .lm-group.overdue .lm-meta .due { color: #b42318; }
    [data-theme="dark"] .lm-group.overdue .lm-meta .due { color: #fda29b; }
    .lm-meta .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .lm-amt { font-size: 16px; font-weight: 700; color: var(--fn-text); white-space: nowrap; }

    .lm-actions { display: flex; align-items: center; gap: 2px; }
    .lm-actions form { margin: 0; }
    .lm-actions .ico {
      width: 34px; height: 34px; display: inline-grid; place-items: center; border-radius: 9px;
      border: 0; background: transparent; color: var(--fn-muted); font-size: 15px; cursor: pointer;
    }
    .lm-actions .ico:hover { background: var(--fn-paper); color: var(--fn-text); }
    .lm-actions .ico.del:hover { background: #fef3f2; color: #b42318; }
    [data-theme="dark"] .lm-actions .ico.del:hover { background: rgba(253,162,155,.1); color: #fda29b; }
    .main-content .lm-pay { min-height: 36px; padding: 6px 12px; font-size: 13px; margin-right: 6px; }

    /* Pagos e em pausa: recolhidos */
    .lm-more { margin-top: 26px; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; }
    .lm-more summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; font-size: 15px; font-weight: 700; color: var(--fn-text); }
    .lm-more summary::-webkit-details-marker { display: none; }
    .lm-more summary span { font-weight: 400; color: var(--fn-muted); font-size: 14px; }
    .lm-more summary i { transition: transform .2s; color: var(--fn-muted); }
    .lm-more[open] summary i { transform: rotate(180deg); }
    .lm-more .lm-list { border: 0; border-top: 1px solid var(--fn-line); border-radius: 0 0 16px 16px; }
    .lm-more .lm-name { color: var(--fn-muted); }

    .lm-empty { padding: 56px 24px; text-align: center; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; }
    .lm-empty-art { display: inline-grid; place-items: center; width: 72px; height: 72px; margin-bottom: 18px; border-radius: 20px; background: var(--fn-gold-soft); color: var(--fn-gold); font-size: 32px; }
    .lm-empty h2 { margin: 0 0 6px; font-size: 20px; font-weight: 700; color: var(--fn-text); }
    .lm-empty p { margin: 0 auto 20px; max-width: 46ch; color: var(--fn-muted); font-size: 15px; }
    .lm-caught-up { padding: 22px; text-align: center; color: var(--fn-muted); background: var(--fn-surface); border: 1px dashed var(--fn-line); border-radius: 16px; }

    @media (max-width: 640px) {
      .lm-head h1 { font-size: 26px; }
      .lm-item { flex-wrap: wrap; gap: 12px; padding: 14px; }
      .lm-main { flex: 1 1 calc(100% - 70px); }
      .lm-amt { margin-left: 64px; }
      .lm-actions { margin-left: auto; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='reminders'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="lm">

  <div class="lm-head">
    <h1>Lembretes</h1>
    <button type="button" class="btn-s btn-s-primary" data-bs-toggle="modal" data-bs-target="#newReminderModal"><i class="bi bi-plus-lg"></i> Novo lembrete</button>
  </div>

  <?php if (!empty($reminders)): ?>
    <p class="lm-summary">
      A pagar até ao fim do mês: <strong><?= fmt_eur($total_month) ?></strong> ·
      já pago este mês: <strong><?= fmt_eur($paid_month_total) ?></strong>
      <?php if ($overdue_count): ?> · <span class="late"><?= $overdue_count ?> em atraso</span><?php endif; ?>
    </p>
  <?php else: ?>
    <p class="lm-summary">&nbsp;</p>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="s-alert s-alert-<?= $messageType ?>" role="status">
      <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($reminders)): ?>
    <div class="lm-empty">
      <span class="lm-empty-art" aria-hidden="true"><i class="bi bi-calendar-check"></i></span>
      <h2>Ainda não tens lembretes</h2>
      <p>Regista a renda, a luz, as subscrições e outros pagamentos. A Finora avisa-te antes de cada um vencer.</p>
      <button type="button" class="btn-s btn-s-primary" data-bs-toggle="modal" data-bs-target="#newReminderModal"><i class="bi bi-plus-lg"></i> Criar o primeiro lembrete</button>
    </div>
  <?php else: ?>

    <?php if (!$hasPending): ?>
      <div class="lm-caught-up">Não tens pagamentos pendentes. Está tudo em dia.</div>
    <?php endif; ?>

    <?php foreach ($groups as $g): if (!$g['items']) continue; ?>
      <section class="lm-group <?= $g['key'] ?>" aria-labelledby="g-<?= $g['key'] ?>">
        <div class="lm-group-head">
          <h2 id="g-<?= $g['key'] ?>"><?= $g['title'] ?></h2>
          <span><?= fmt_eur(array_sum(array_column($g['items'], 'amount'))) ?></span>
        </div>
        <ul class="lm-list">
          <?php foreach ($g['items'] as $r): $ts = strtotime($r['due_date']); ?>
            <li class="lm-item">
              <span class="lm-date"><b><?= date('j', $ts) ?></b><small><?= mb_substr(month_name_pt((int) date('n', $ts)), 0, 3) ?></small></span>
              <div class="lm-main">
                <div class="lm-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="lm-meta">
                  <span class="due"><?= reminder_due_text($r) ?></span>·
                  <span><?= $recurrenceLabels[$r['recurrence']] ?? '' ?></span>
                  <?php if ($r['category']): ?>·<span><span class="dot" style="background:<?= category_color($r['category']) ?>"></span> <?= htmlspecialchars($r['category']) ?></span><?php endif; ?>
                  <?php if ($r['card_name']): ?>·<span><?= htmlspecialchars($r['card_name']) ?></span><?php endif; ?>
                </div>
              </div>
              <span class="lm-amt"><?= fmt_eur($r['amount']) ?></span>
              <div class="lm-actions">
                <button type="button" class="btn-s btn-s-success lm-pay" onclick="openMarkPaidModal(<?= (int) $r['id'] ?>)"><i class="bi bi-check2"></i> Marcar como pago</button>
                <button type="button" class="ico" onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)" aria-label="Editar <?= htmlspecialchars($r['name']) ?>" title="Editar"><i class="bi bi-pencil"></i></button>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico" aria-label="Pausar <?= htmlspecialchars($r['name']) ?>" title="Pausar"><i class="bi bi-pause-circle"></i></button>
                </form>
                <form method="post" onsubmit="return confirm('Apagar este lembrete? Não é possível desfazer.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico del" aria-label="Apagar <?= htmlspecialchars($r['name']) ?>" title="Apagar"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>

    <?php if ($paidReminders): ?>
      <details class="lm-more">
        <summary>Pagos <span><?= count($paidReminders) ?> <i class="bi bi-chevron-down"></i></span></summary>
        <ul class="lm-list">
          <?php foreach ($paidReminders as $r): $ts = strtotime($r['last_paid_date'] ?? $r['due_date']); ?>
            <li class="lm-item">
              <span class="lm-date"><b><?= date('j', $ts) ?></b><small><?= mb_substr(month_name_pt((int) date('n', $ts)), 0, 3) ?></small></span>
              <div class="lm-main">
                <div class="lm-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="lm-meta"><span>Pago a <?= date('j', $ts) ?> de <?= month_name_pt((int) date('n', $ts)) ?></span></div>
              </div>
              <span class="lm-amt"><?= fmt_eur($r['amount']) ?></span>
              <div class="lm-actions">
                <form method="post" onsubmit="return confirm('Voltar a marcar este pagamento como pendente?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unmark_paid">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico" aria-label="Voltar a pendente" title="Voltar a pendente"><i class="bi bi-arrow-counterclockwise"></i></button>
                </form>
                <form method="post" onsubmit="return confirm('Apagar este lembrete? Não é possível desfazer.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico del" aria-label="Apagar <?= htmlspecialchars($r['name']) ?>" title="Apagar"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>

    <?php if ($inactiveReminders): ?>
      <details class="lm-more">
        <summary>Em pausa <span><?= count($inactiveReminders) ?> <i class="bi bi-chevron-down"></i></span></summary>
        <ul class="lm-list">
          <?php foreach ($inactiveReminders as $r): $ts = strtotime($r['due_date']); ?>
            <li class="lm-item">
              <span class="lm-date"><b><?= date('j', $ts) ?></b><small><?= mb_substr(month_name_pt((int) date('n', $ts)), 0, 3) ?></small></span>
              <div class="lm-main">
                <div class="lm-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="lm-meta"><span><?= $recurrenceLabels[$r['recurrence']] ?? '' ?> · sem avisos enquanto estiver em pausa</span></div>
              </div>
              <span class="lm-amt"><?= fmt_eur($r['amount']) ?></span>
              <div class="lm-actions">
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn-s lm-pay"><i class="bi bi-play-circle"></i> Retomar</button>
                </form>
                <form method="post" onsubmit="return confirm('Apagar este lembrete? Não é possível desfazer.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico del" aria-label="Apagar <?= htmlspecialchars($r['name']) ?>" title="Apagar"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>

  <?php endif; ?>

</div><!-- /lm -->
</div><!-- /page-content -->
<!-- Modal Novo Lembrete -->
<div class="modal fade" id="newReminderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Novo Lembrete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" class="m-form"><?= csrf_field() ?>
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
      <form method="post" id="markPaidForm" class="m-form"><?= csrf_field() ?>
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
      <form method="post" class="m-form"><?= csrf_field() ?>
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