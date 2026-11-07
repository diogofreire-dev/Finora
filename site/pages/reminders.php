<?php
// site/reminders.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);
require_once __DIR__ . '/../helpers/reminder_dates.php';

$categories = ['Compras','Alimentação','Transporte','Saúde','Entretenimento','Educação','Casa','Outros'];
const NOTIFY_METHODS = ['email', 'site', 'both'];

// Data AAAA-MM-DD válida (2026-02-30 não passa) ou null
function valid_ymd(string $s): ?string {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
// Campos comuns de criar/editar, já validados contra listas fixas
function reminder_fields(array $post, array $categories): array {
    $rec = in_array($post['recurrence'] ?? '', REMINDER_RECURRENCES, true) ? $post['recurrence'] : 'once';
    $cat = in_array($post['category'] ?? '', $categories, true) ? $post['category'] : null;
    $nm  = in_array($post['notify_method'] ?? '', NOTIFY_METHODS, true) ? $post['notify_method'] : 'email';
    $due = valid_ymd((string) ($post['due_date'] ?? ''));
    return [
        'name'   => mb_substr(trim((string) ($post['name'] ?? '')), 0, 255),
        'amount' => round((float) ($post['amount'] ?? 0), 2),
        'rec' => $rec, 'cat' => $cat, 'nm' => $nm, 'due' => $due,
        'notify' => in_array((int) ($post['notify_days_before'] ?? 3), [0, 1, 3, 7], true) ? (int) $post['notify_days_before'] : 3,
    ];
}

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $f = reminder_fields($_POST, $categories);
        $card_id = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
        if ($f['amount'] > 0 && $f['amount'] <= 1000000 && mb_strlen($f['name']) >= 3 && $f['due']) {
            try {
                $next_due = $f['rec'] === 'once' ? $f['due'] : reminder_next_date($f['due'], $f['rec']);
                $stmt = $pdo->prepare("INSERT INTO payment_reminders (user_id, name, amount, category, card_id, due_date, anchor_day, recurrence, notify_days_before, notify_method, next_due_date) VALUES (:uid, :name, :amount, :category, :card_id, :due_date, :anchor, :recurrence, :notify, :notify_method, :next_due)");
                $stmt->execute([':uid'=>$uid,':name'=>$f['name'],':amount'=>$f['amount'],':category'=>$f['cat'],':card_id'=>$card_id,':due_date'=>$f['due'],':anchor'=>(int) substr($f['due'], 8, 2),':recurrence'=>$f['rec'],':notify'=>$f['notify'],':notify_method'=>$f['nm'],':next_due'=>$next_due]);
                $message = t('Lembrete criado com sucesso!'); $messageType = 'success';
            } catch (PDOException $e) { $message = t('Erro ao criar lembrete.'); $messageType = 'danger'; }
        } else { $message = t('Confirma o nome (3+ letras), o valor e a data.'); $messageType = 'danger'; }
    } elseif ($action === 'mark_paid') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        // A data do pagamento tem de ser real e não pode ser no futuro
        $paid_date = valid_ymd((string) ($_POST['paid_date'] ?? '')) ?? date('Y-m-d');
        if ($paid_date > date('Y-m-d')) $paid_date = date('Y-m-d');
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
                    // Recorrente: avança uma vez, mantendo o dia original (anchor_day)
                    $next = reminder_next_date($reminder['due_date'], $reminder['recurrence'], $reminder['anchor_day'] !== null ? (int) $reminder['anchor_day'] : null);
                    $stmt = $pdo->prepare("UPDATE payment_reminders SET last_paid_date=:pdate, due_date=:next_due, next_due_date=:next_due_date, paid=0 WHERE id=:id");
                    $stmt->execute([':pdate'=>$paid_date,':next_due'=>$next,':next_due_date'=>$next,':id'=>$reminder_id]);
                }
                $pdo->commit(); $message = t('Pagamento registado com sucesso!'); $messageType = 'success';
            }
        } catch (PDOException $e) { $pdo->rollBack(); $message = t('Erro ao registar pagamento.'); $messageType = 'danger'; }
    } elseif ($action === 'edit') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        $f = reminder_fields($_POST, $categories);
        $card_id = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
        if ($f['amount'] > 0 && $f['amount'] <= 1000000 && mb_strlen($f['name']) >= 3 && $f['due']) {
            try {
                $next_due = $f['rec'] === 'once' ? $f['due'] : reminder_next_date($f['due'], $f['rec']);
                $stmt = $pdo->prepare("UPDATE payment_reminders SET name=:name, amount=:amount, category=:category, card_id=:card_id, due_date=:due_date, anchor_day=:anchor, recurrence=:recurrence, notify_days_before=:notify, notify_method=:notify_method, next_due_date=:next_due WHERE id=:id AND user_id=:uid");
                $stmt->execute([':id'=>$reminder_id,':uid'=>$uid,':name'=>$f['name'],':amount'=>$f['amount'],':category'=>$f['cat'],':card_id'=>$card_id,':due_date'=>$f['due'],':anchor'=>(int) substr($f['due'], 8, 2),':recurrence'=>$f['rec'],':notify'=>$f['notify'],':notify_method'=>$f['nm'],':next_due'=>$next_due]);
                $message = t('Lembrete atualizado com sucesso!'); $messageType = 'success';
            } catch (PDOException $e) { $message = t('Erro ao atualizar lembrete.'); $messageType = 'danger'; }
        } else { $message = t('Confirma o nome (3+ letras), o valor e a data.'); $messageType = 'danger'; }
    } elseif ($action === 'toggle') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try { $stmt = $pdo->prepare("UPDATE payment_reminders SET active = NOT active WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$reminder_id,':uid'=>$uid]); $message = t('Estado alterado!'); $messageType = 'success'; } catch (PDOException $e) { $message = t('Erro.'); $messageType = 'danger'; }
    } elseif ($action === 'delete') {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try { $stmt = $pdo->prepare("DELETE FROM payment_reminders WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$reminder_id,':uid'=>$uid]); $message = t('Lembrete removido!'); $messageType = 'success'; } catch (PDOException $e) { $message = t('Erro.'); $messageType = 'danger'; }
    } elseif ($action === 'unmark_paid') {
        // Permite reverter pagamento de um lembrete "once" para ativo novamente
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        try { $stmt = $pdo->prepare("UPDATE payment_reminders SET paid=0, active=1 WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$reminder_id,':uid'=>$uid]); $message = t('Pagamento revertido!'); $messageType = 'success'; } catch (PDOException $e) { $message = t('Erro.'); $messageType = 'danger'; }
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

// ── Calendário do mês (?m=AAAA-MM) ───────────────────────────────────────────
// "Hoje" vem do MySQL: o PHP está noutro fuso e as datas guardadas usam o relógio da BD
$todayYmd = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
$calParam = (string) ($_GET['m'] ?? '');
$calFrom  = preg_match('/^(20[0-9]{2})-(0[1-9]|1[0-2])$/', $calParam)
          ? new DateTimeImmutable($calParam . '-01')
          : new DateTimeImmutable(substr($todayYmd, 0, 7) . '-01');
$calTo    = $calFrom->modify('last day of this month');
$calDays  = [];                                   // 'AAAA-MM-DD' => lista de pagamentos
$calDue   = 0.0; $calPaid = 0.0;

foreach ($reminders as $r) {
    if ($r['status'] === 'paid' || $r['status'] === 'inactive') continue;
    $anchor = $r['anchor_day'] !== null ? (int) $r['anchor_day'] : null;
    foreach (reminder_occurrences($r['due_date'], $r['recurrence'], $calFrom, $calTo, $anchor) as $d) {
        $calDays[$d][] = [
            'id' => (int) $r['id'], 'name' => $r['name'], 'amount' => (float) $r['amount'],
            'category' => $r['category'], 'card' => $r['card_name'],
            'state'  => $d < $todayYmd ? 'overdue' : ($d === $todayYmd ? 'today' : 'due'),
            'canPay' => $d === $r['due_date'],    // só a data em curso; as seguintes ainda não existem
        ];
        $calDue += (float) $r['amount'];
    }
}
$stmt = $pdo->prepare("SELECT rh.paid_date, rh.amount, r.name, r.category
                       FROM reminder_history rh JOIN payment_reminders r ON r.id = rh.reminder_id
                       WHERE r.user_id = :uid AND rh.paid_date BETWEEN :f AND :t ORDER BY rh.paid_date");
$stmt->execute([':uid' => $uid, ':f' => $calFrom->format('Y-m-d'), ':t' => $calTo->format('Y-m-d')]);
foreach ($stmt as $p) {
    $calDays[$p['paid_date']][] = ['id' => 0, 'name' => $p['name'], 'amount' => (float) $p['amount'],
        'category' => $p['category'], 'card' => null, 'state' => 'paid', 'canPay' => false];
    $calPaid += (float) $p['amount'];
}
// Peso de cada dia (0–3) para os dias com mais para pagar se destacarem
$dayDue = array_map(fn($items) => array_sum(array_map(fn($i) => $i['state'] === 'paid' ? 0 : $i['amount'], $items)), $calDays);
$maxDue = $dayDue ? max($dayDue) : 0;
$heat   = fn(string $d) => ($maxDue > 0 && ($dayDue[$d] ?? 0) > 0) ? (int) ceil(($dayDue[$d] / $maxDue) * 3) : 0;
ksort($calDays);
// Dia aberto ao início: hoje (se for este mês), senão o primeiro dia com pagamentos
$calSelected = ($todayYmd >= $calFrom->format('Y-m-d') && $todayYmd <= $calTo->format('Y-m-d'))
             ? $todayYmd : (array_key_first(array_filter($calDays)) ?? $calFrom->format('Y-m-d'));
$calPrev = $calFrom->modify('-1 month')->format('Y-m');
$calNext = $calFrom->modify('+1 month')->format('Y-m');
$weekdays = ['segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'];

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

$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid'=>$uid]);
$cards = $stmt->fetchAll();

$overdueReminders   = array_filter($reminders, fn($r) => $r['status'] === 'overdue');
$upcomingReminders  = array_filter($reminders, fn($r) => $r['status'] === 'upcoming');
$futureReminders    = array_filter($reminders, fn($r) => $r['status'] === 'future');
$paidReminders      = array_filter($reminders, fn($r) => $r['status'] === 'paid');
$inactiveReminders  = array_filter($reminders, fn($r) => $r['status'] === 'inactive');

$recurrenceLabels = ['once' => t('Única vez'), 'weekly' => t('Semanal'), 'monthly' => t('Mensal'), 'yearly' => t('Anual')];
// Linha de um lembrete por pagar (atrasado, próximo ou futuro)
function reminder_due_text(array $r): string {
    $days = (int) floor((strtotime($r['due_date']) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0)  return abs($days) > 1 ? t('Em atraso há {n} dias', ['n' => abs($days)]) : t('Em atraso há 1 dia');
    if ($days === 0) return t('Vence hoje');
    if ($days === 1) return t('Vence amanhã');
    return t('Vence em {n} dias', ['n' => $days]);
}
$groups = [
    ['key' => 'overdue',  'title' => t('Em atraso'),         'items' => $overdueReminders],
    ['key' => 'upcoming', 'title' => t('Próximos 7 dias'),   'items' => $upcomingReminders],
    ['key' => 'future',   'title' => 'Mais tarde',        'items' => $futureReminders],
];
$hasPending = $overdueReminders || $upcomingReminders || $futureReminders;
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Lembretes - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=9">
  <style>
    .lm { max-width: 1200px; margin: 0 auto; padding-bottom: 56px; }
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

    /* ── Calendário ── */
    .main-content .lc { margin-bottom: 28px; }
    .main-content .lc .pg-card-head p strong { color: var(--fn-text); }
    .lc-nav { display: flex; align-items: center; gap: 4px; }
    .lc-nav a { display: inline-grid; place-items: center; height: 36px; min-width: 36px; padding: 0 10px; border-radius: 10px;
                border: 1px solid var(--fn-line); background: var(--fn-surface); color: var(--fn-text); text-decoration: none; font-size: 14px; font-weight: 600; }
    .lc-nav a:hover { border-color: var(--fn-gold); }
    .lc-body { display: grid; grid-template-columns: minmax(0, 1fr) 290px; gap: 18px; padding: 16px 18px 18px; }
    .lc-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; align-content: start; }
    .lc-wd { font-size: 12px; font-weight: 600; color: var(--fn-muted); text-align: center; padding-bottom: 2px; }
    .lc-day {
      position: relative; min-height: 88px; padding: 6px 6px 6px; text-align: left; cursor: pointer;
      display: flex; flex-direction: column; gap: 4px; min-width: 0;
      border: 1px solid var(--fn-line); border-radius: 12px; background: var(--fn-surface); color: var(--fn-text);
      font: inherit; transition: border-color .15s, box-shadow .15s;
    }
    .lc-day:hover { border-color: color-mix(in srgb, var(--fn-gold) 60%, var(--fn-line)); }
    .lc-day:focus-visible { outline: 3px solid var(--fn-gold); outline-offset: 1px; }
    .lc-day[aria-pressed="true"] { border-color: var(--fn-gold); box-shadow: 0 0 0 2px var(--fn-gold); }
    /* Quanto mais há para pagar nesse dia, mais quente o fundo */
    .lc-day.heat-1 { background: color-mix(in srgb, var(--fn-gold) 7%, var(--fn-surface)); }
    .lc-day.heat-2 { background: color-mix(in srgb, var(--fn-gold) 14%, var(--fn-surface)); }
    .lc-day.heat-3 { background: color-mix(in srgb, var(--fn-gold) 22%, var(--fn-surface)); }
    .lc-day.is-past { color: var(--fn-muted); }
    .lc-day.has-late { border-color: #f4a49d; }
    [data-theme="dark"] .lc-day.has-late { border-color: rgba(253,162,155,.45); }
    .lc-num { font-size: 13px; font-weight: 700; line-height: 22px; width: 22px; text-align: center; border-radius: 50%; }
    .lc-day.is-today .lc-num { background: var(--fn-ink); color: #f3e7c8; }
    [data-theme="dark"] .lc-day.is-today .lc-num { background: var(--fn-gold); color: #14213d; }
    .lc-chips { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
    .lc-chip {
      display: flex; gap: 4px; justify-content: space-between; min-width: 0;
      font-size: 11.5px; line-height: 1.35; padding: 2px 5px; border-radius: 6px;
      border-left: 3px solid var(--c); background: color-mix(in srgb, var(--c) 12%, var(--fn-surface)); color: var(--fn-text);
    }
    .lc-chip .nm { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .lc-chip .am { font-weight: 700; white-space: nowrap; }
    .lc-chip.is-paid { opacity: .55; text-decoration: line-through; }
    .lc-chip.is-overdue { background: color-mix(in srgb, #d92d20 12%, var(--fn-surface)); border-left-color: #d92d20; }
    .lc-more { font-size: 11.5px; font-weight: 600; color: var(--fn-muted); padding-left: 4px; }
    .lc-dots { display: none; gap: 3px; }
    .lc-dots i { width: 7px; height: 7px; border-radius: 50%; background: var(--c); }
    .lc-dots i.is-paid { opacity: .4; }
    .lc-dots i.is-overdue { background: #d92d20; }

    .lc-side { border-left: 1px solid var(--fn-line); padding-left: 18px; }
    .lc-panel h3 { margin: 2px 0 12px; font-family: var(--fn-font-brand); font-size: 17px; font-weight: 700; color: var(--fn-text); }
    .lc-list { list-style: none; margin: 0 0 14px; padding: 0; display: grid; gap: 8px; }
    .lc-list li { display: flex; align-items: center; gap: 10px; padding: 10px 10px 10px 0; border-bottom: 1px solid var(--fn-line); }
    .lc-bar { align-self: stretch; width: 4px; border-radius: 2px; background: var(--c); flex-shrink: 0; }
    .lc-list li.is-overdue .lc-bar { background: #d92d20; }
    .lc-name { font-weight: 600; font-size: 14.5px; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lc-meta { font-size: 12.5px; color: var(--fn-muted); }
    .lc-list li.is-overdue .lc-meta { color: #b42318; font-weight: 600; }
    [data-theme="dark"] .lc-list li.is-overdue .lc-meta { color: #fda29b; }
    .lc-list li.is-paid .lc-name { color: var(--fn-muted); text-decoration: line-through; }
    .lc-amt { font-weight: 700; white-space: nowrap; color: var(--fn-text); }
    .main-content .lc-pay { min-height: 32px; padding: 4px 10px; font-size: 12.5px; }
    .lc-free { text-align: center; color: var(--fn-muted); font-size: 14px; padding: 4px 0 10px; }
    .lc-free img { display: block; margin: 0 auto 4px; width: 120px; height: auto; }
    .lc-free p { margin: 0; }
    .main-content .lc-add { width: 100%; justify-content: center; }
    .lm-empty-3d { display: block; margin: 0 auto 10px; width: 160px; height: auto; }

    @media (max-width: 980px) {
      .lc-body { grid-template-columns: minmax(0, 1fr); }
      .lc-side { border-left: 0; padding-left: 0; border-top: 1px solid var(--fn-line); padding-top: 16px; }
      .lc-chip .am { display: none; }
    }
    @media (max-width: 600px) {
      .lc-body { padding: 12px; }
      .lc-grid { gap: 4px; }
      .lc-day { min-height: 54px; padding: 5px 3px; align-items: center; border-radius: 10px; }
      .lc-chips { display: none; }
      .lc-dots { display: flex; }
      .lc-nav .lc-today { display: none; }
    }

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
    <h1><?= te('Lembretes') ?></h1>
    <button type="button" class="btn-s btn-s-primary" data-bs-toggle="modal" data-bs-target="#newReminderModal"><i class="bi bi-plus-lg"></i> <?= te('Novo lembrete') ?></button>
  </div>

  <?php if (!empty($reminders)): ?>
    <p class="lm-summary">
      <?= te('A pagar até ao fim do mês:') ?> <strong><?= fmt_money($total_month) ?></strong> ·
      <?= te('já pago este mês:') ?> <strong><?= fmt_money($paid_month_total) ?></strong>
      <?php if ($overdue_count): ?> · <span class="late"><?= $overdue_count ?> <?= te('em atraso') ?></span><?php endif; ?>
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
      <img class="lm-empty-3d" src="../assets/3d/calendar.webp" width="160" height="160" alt="">
      <h2><?= te('Ainda não tens lembretes') ?></h2>
      <p><?= te('Regista a renda, a luz, as subscrições e outros pagamentos. A Finora avisa-te antes de cada um vencer.') ?></p>
      <button type="button" class="btn-s btn-s-primary" data-bs-toggle="modal" data-bs-target="#newReminderModal"><i class="bi bi-plus-lg"></i> <?= te('Criar o primeiro lembrete') ?></button>
    </div>
  <?php else: ?>

    <?php
      $monthLabel = ucfirst(fmt_month_year($calFrom->getTimestamp()));
      $dayLabel = function (string $d): string {
          $ts = strtotime($d);
          return ucfirst(weekday_name((int) date('w', $ts))) . ', ' . fmt_date_long($ts);
      };
      $stateLabel = ['overdue' => t('Em atraso'), 'today' => t('Vence hoje'), 'due' => t('Por pagar'), 'paid' => t('Pago')];
    ?>
    <section class="pg-card lc" id="calendario" style="--tone: var(--cat-5)" aria-labelledby="lcTitle">
      <header class="pg-card-head">
        <span class="pg-ico" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
        <div class="pg-grow">
          <h2 id="lcTitle"><?= $monthLabel ?></h2>
          <p><?= te('Por pagar') ?> <strong><?= fmt_money($calDue) ?></strong><?php if ($calPaid > 0): ?> · <?= te('já pago') ?> <strong><?= fmt_money($calPaid) ?></strong><?php endif; ?></p>
        </div>
        <nav class="lc-nav" aria-label="<?= te('Mudar de mês') ?>">
          <a class="lc-arrow" href="?m=<?= $calPrev ?>#calendario" aria-label="<?= te('Mês anterior') ?>"><i class="bi bi-chevron-left"></i></a>
          <a class="lc-today" href="reminders.php#calendario"><?= te('Hoje') ?></a>
          <a class="lc-arrow" href="?m=<?= $calNext ?>#calendario" aria-label="<?= te('Mês seguinte') ?>"><i class="bi bi-chevron-right"></i></a>
        </nav>
      </header>

      <div class="lc-body">
        <div class="lc-grid">
          <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): ?><span class="lc-wd" aria-hidden="true"><?= htmlspecialchars(mb_substr(ucfirst(weekday_name($w)), 0, 3)) ?></span><?php endforeach; ?>
          <?php for ($i = 1; $i < (int) $calFrom->format('N'); $i++): ?><span class="lc-blank"></span><?php endfor; ?>
          <?php for ($d = $calFrom; $d <= $calTo; $d = $d->modify('+1 day')):
            $ymd   = $d->format('Y-m-d');
            $items = $calDays[$ymd] ?? [];
            $late  = array_filter($items, fn($i) => $i['state'] === 'overdue');
            $cls   = 'lc-day heat-' . $heat($ymd) . ($ymd === $todayYmd ? ' is-today' : '') . ($ymd < $todayYmd ? ' is-past' : '') . ($late ? ' has-late' : '');
            $aria  = $dayLabel($ymd) . ($items ? ': ' . count($items) . ' pagamento' . (count($items) > 1 ? 's' : '') . ', ' . fmt_money(array_sum(array_column($items, 'amount'))) : ': sem pagamentos');
          ?>
            <button type="button" class="<?= $cls ?>" data-day="<?= $ymd ?>" aria-pressed="<?= $ymd === $calSelected ? 'true' : 'false' ?>" aria-label="<?= htmlspecialchars($aria) ?>">
              <span class="lc-num"><?= $d->format('j') ?></span>
              <?php if ($items): ?>
                <span class="lc-chips" aria-hidden="true">
                  <?php foreach (array_slice($items, 0, 2) as $it): ?>
                    <span class="lc-chip is-<?= $it['state'] ?>" style="--c: <?= category_color($it['category'] ?? 'Outros') ?>"><span class="nm"><?= htmlspecialchars($it['name']) ?></span><span class="am"><?= fmt_money($it['amount']) ?></span></span>
                  <?php endforeach; ?>
                  <?php if (count($items) > 2): ?><span class="lc-more">+<?= count($items) - 2 ?></span><?php endif; ?>
                </span>
                <span class="lc-dots" aria-hidden="true">
                  <?php foreach (array_slice($items, 0, 3) as $it): ?><i class="is-<?= $it['state'] ?>" style="--c: <?= category_color($it['category'] ?? 'Outros') ?>"></i><?php endforeach; ?>
                </span>
              <?php endif; ?>
            </button>
          <?php endfor; ?>
        </div>

        <div class="lc-side" aria-live="polite">
          <?php for ($d = $calFrom; $d <= $calTo; $d = $d->modify('+1 day')):
            $ymd = $d->format('Y-m-d'); $items = $calDays[$ymd] ?? []; ?>
            <section class="lc-panel" data-panel="<?= $ymd ?>" <?= $ymd === $calSelected ? '' : 'hidden' ?>>
              <h3><?= $dayLabel($ymd) ?></h3>
              <?php if ($items): ?>
                <ul class="lc-list">
                  <?php foreach ($items as $it): ?>
                    <li class="is-<?= $it['state'] ?>">
                      <span class="lc-bar" style="--c: <?= category_color($it['category'] ?? 'Outros') ?>"></span>
                      <div class="pg-grow">
                        <div class="lc-name"><?= htmlspecialchars($it['name']) ?></div>
                        <div class="lc-meta"><?= $stateLabel[$it['state']] ?><?= $it['category'] ? ' · ' . te($it['category']) : '' ?><?= $it['card'] ? ' · ' . htmlspecialchars($it['card']) : '' ?></div>
                      </div>
                      <span class="lc-amt"><?= fmt_money($it['amount']) ?></span>
                      <?php if ($it['canPay']): ?>
                        <button type="button" class="btn-s btn-s-success lc-pay" onclick="openMarkPaidModal(<?= $it['id'] ?>)"><i class="bi bi-check2"></i> <?= te('Pago') ?></button>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="lc-free">
                  <img src="../assets/3d/calendar.webp" width="120" height="120" alt="" loading="lazy">
                  <p><?= te('Nada para pagar neste dia.') ?></p>
                </div>
              <?php endif; ?>
              <?php if ($ymd >= $todayYmd): ?>
                <button type="button" class="btn-s lc-add" data-new-on="<?= $ymd ?>"><i class="bi bi-plus-lg"></i> <?= te('Novo lembrete neste dia') ?></button>
              <?php endif; ?>
            </section>
          <?php endfor; ?>
        </div>
      </div>
    </section>

    <?php if (!$hasPending): ?>
      <div class="lm-caught-up"><?= te('Não tens pagamentos pendentes. Está tudo em dia.') ?></div>
    <?php endif; ?>

    <?php foreach ($groups as $g): if (!$g['items']) continue; ?>
      <section class="lm-group <?= $g['key'] ?>" aria-labelledby="g-<?= $g['key'] ?>">
        <div class="lm-group-head">
          <h2 id="g-<?= $g['key'] ?>"><?= htmlspecialchars($g['title']) ?></h2>
          <span><?= fmt_money(array_sum(array_column($g['items'], 'amount'))) ?></span>
        </div>
        <ul class="lm-list">
          <?php foreach ($g['items'] as $r): $ts = strtotime($r['due_date']); ?>
            <li class="lm-item">
              <span class="lm-date"><b><?= date('j', $ts) ?></b><small><?= mb_substr(month_name((int) date('n', $ts)), 0, 3) ?></small></span>
              <div class="lm-main">
                <div class="lm-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="lm-meta">
                  <span class="due"><?= reminder_due_text($r) ?></span>·
                  <span><?= $recurrenceLabels[$r['recurrence']] ?? '' ?></span>
                  <?php if ($r['category']): ?>·<span><span class="dot" style="background:<?= category_color($r['category']) ?>"></span> <?= te($r['category']) ?></span><?php endif; ?>
                  <?php if ($r['card_name']): ?>·<span><?= htmlspecialchars($r['card_name']) ?></span><?php endif; ?>
                </div>
              </div>
              <span class="lm-amt"><?= fmt_money($r['amount']) ?></span>
              <div class="lm-actions">
                <button type="button" class="btn-s btn-s-success lm-pay" onclick="openMarkPaidModal(<?= (int) $r['id'] ?>)"><i class="bi bi-check2"></i> <?= te('Marcar como pago') ?></button>
                <button type="button" class="ico" onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)" aria-label="<?= te('Editar {name}', ['name' => $r['name']]) ?>" title="<?= te('Editar') ?>"><i class="bi bi-pencil"></i></button>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico" aria-label="<?= te('Pausar {name}', ['name' => $r['name']]) ?>" title="<?= te('Pausar') ?>"><i class="bi bi-pause-circle"></i></button>
                </form>
                <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Apagar este lembrete? Não é possível desfazer.')) ?>)">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico del" aria-label="<?= te('Apagar {name}', ['name' => $r['name']]) ?>" title="<?= te('Apagar') ?>"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>

    <?php if ($paidReminders): ?>
      <details class="lm-more">
        <summary><?= te('Pagos') ?> <span><?= count($paidReminders) ?> <i class="bi bi-chevron-down"></i></span></summary>
        <ul class="lm-list">
          <?php foreach ($paidReminders as $r): $ts = strtotime($r['last_paid_date'] ?? $r['due_date']); ?>
            <li class="lm-item">
              <span class="lm-date"><b><?= date('j', $ts) ?></b><small><?= mb_substr(month_name((int) date('n', $ts)), 0, 3) ?></small></span>
              <div class="lm-main">
                <div class="lm-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="lm-meta"><span><?= te('Pago a {date}', ['date' => fmt_date_long($ts)]) ?></span></div>
              </div>
              <span class="lm-amt"><?= fmt_money($r['amount']) ?></span>
              <div class="lm-actions">
                <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Voltar a marcar este pagamento como pendente?')) ?>)">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unmark_paid">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico" aria-label="<?= te('Voltar a pendente') ?>" title="<?= te('Voltar a pendente') ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                </form>
                <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Apagar este lembrete? Não é possível desfazer.')) ?>)">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico del" aria-label="<?= te('Apagar {name}', ['name' => $r['name']]) ?>" title="<?= te('Apagar') ?>"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>

    <?php if ($inactiveReminders): ?>
      <details class="lm-more">
        <summary><?= te('Em pausa') ?> <span><?= count($inactiveReminders) ?> <i class="bi bi-chevron-down"></i></span></summary>
        <ul class="lm-list">
          <?php foreach ($inactiveReminders as $r): $ts = strtotime($r['due_date']); ?>
            <li class="lm-item">
              <span class="lm-date"><b><?= date('j', $ts) ?></b><small><?= mb_substr(month_name((int) date('n', $ts)), 0, 3) ?></small></span>
              <div class="lm-main">
                <div class="lm-name"><?= htmlspecialchars($r['name']) ?></div>
                <div class="lm-meta"><span><?= $recurrenceLabels[$r['recurrence']] ?? '' ?> <?= te('· sem avisos enquanto estiver em pausa') ?></span></div>
              </div>
              <span class="lm-amt"><?= fmt_money($r['amount']) ?></span>
              <div class="lm-actions">
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn-s lm-pay"><i class="bi bi-play-circle"></i> <?= te('Retomar') ?></button>
                </form>
                <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Apagar este lembrete? Não é possível desfazer.')) ?>)">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="ico del" aria-label="<?= te('Apagar {name}', ['name' => $r['name']]) ?>" title="<?= te('Apagar') ?>"><i class="bi bi-trash3"></i></button>
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
        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i><?= te('Novo Lembrete') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" class="m-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;padding:20px">
          <div><label class="form-label"><?= te('Nome do Pagamento *') ?></label><input type="text" name="name" class="form-control" placeholder="<?= te('ex: Netflix, Renda, Conta da Luz') ?>" required></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label"><?= te('Valor ({symbol}) *', ['symbol' => currency_symbol()]) ?></label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="15.99" required></div>
            <div><label class="form-label"><?= te('Data de Vencimento *') ?></label><input type="date" name="due_date" class="form-control" min="<?=date('Y-m-d')?>" required></div>
          </div>
          <div><label class="form-label"><?= te('Recorrência') ?></label><select name="recurrence" class="form-select"><option value="once"><?= te('Única vez') ?></option><option value="weekly"><?= te('Semanal') ?></option><option value="monthly" selected><?= te('Mensal') ?></option><option value="yearly"><?= te('Anual') ?></option></select></div>
          <div><label class="form-label"><?= te('Categoria') ?></label><select name="category" class="form-select"><option value=""><?= te('Sem categoria') ?></option><?php foreach($categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= te($cat) ?></option><?php endforeach; ?></select></div>
          <div><label class="form-label"><?= te('Cartão') ?></label><select name="card_id" class="form-select"><option value=""><?= te('Dinheiro / A definir') ?></option><?php foreach($cards as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label"><?= te('Notificar') ?></label><select name="notify_days_before" class="form-select"><option value="0"><?= te('No dia') ?></option><option value="1"><?= te('1 dia antes') ?></option><option value="3" selected><?= te('3 dias antes') ?></option><option value="7"><?= te('7 dias antes') ?></option></select></div>
            <div><label class="form-label"><?= te('Método') ?></label><select name="notify_method" class="form-select"><option value="email" selected><?= te('Email') ?></option><option value="site"><?= te('Notificação') ?></option><option value="both"><?= te('Ambos') ?></option></select></div>
          </div>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn-s" data-bs-dismiss="modal"><?= te('Cancelar') ?></button>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> <?= te('Criar Lembrete') ?></button>
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
        <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i><?= te('Marcar Como Pago') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="markPaidForm" class="m-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_paid">
        <input type="hidden" name="reminder_id" id="mark_paid_reminder_id">
        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;padding:20px">
          <div class="s-alert s-alert-info"><i class="bi bi-info-circle" style="flex-shrink:0;margin-top:1px"></i><span><?= te('Ao marcar como pago, podes criar automaticamente uma transação no sistema.') ?></span></div>
          <div><label class="form-label"><?= te('Data do Pagamento') ?></label><input type="date" name="paid_date" class="form-control" value="<?=date('Y-m-d')?>" max="<?=date('Y-m-d')?>" required></div>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
            <input class="form-check-input" type="checkbox" name="create_transaction" checked style="margin:0">
            <?= te('Criar transação e atualizar saldo do cartão') ?>
          </label>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn-s" data-bs-dismiss="modal"><?= te('Cancelar') ?></button>
          <button type="submit" class="btn-s btn-s-success"><i class="bi bi-check-circle"></i> <?= te('Confirmar Pagamento') ?></button>
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
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i><?= te('Editar Lembrete') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" class="m-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="reminder_id" id="edit_reminder_id">
        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;padding:20px">
          <div><label class="form-label"><?= te('Nome *') ?></label><input type="text" name="name" id="edit_name" class="form-control" required></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label"><?= te('Valor ({symbol}) *', ['symbol' => currency_symbol()]) ?></label><input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0.01" required></div>
            <div><label class="form-label"><?= te('Data *') ?></label><input type="date" name="due_date" id="edit_due_date" class="form-control" required></div>
          </div>
          <div><label class="form-label"><?= te('Recorrência') ?></label><select name="recurrence" id="edit_recurrence" class="form-select"><option value="once"><?= te('Única vez') ?></option><option value="weekly"><?= te('Semanal') ?></option><option value="monthly"><?= te('Mensal') ?></option><option value="yearly"><?= te('Anual') ?></option></select></div>
          <div><label class="form-label"><?= te('Categoria') ?></label><select name="category" id="edit_category" class="form-select"><option value=""><?= te('Sem categoria') ?></option><?php foreach($categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= te($cat) ?></option><?php endforeach; ?></select></div>
          <div><label class="form-label"><?= te('Cartão') ?></label><select name="card_id" id="edit_card_id" class="form-select"><option value=""><?= te('Dinheiro / A definir') ?></option><?php foreach($cards as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="form-label"><?= te('Notificar') ?></label><select name="notify_days_before" id="edit_notify_days" class="form-select"><option value="0"><?= te('No dia') ?></option><option value="1"><?= te('1 dia antes') ?></option><option value="3"><?= te('3 dias antes') ?></option><option value="7"><?= te('7 dias antes') ?></option></select></div>
            <div><label class="form-label"><?= te('Método') ?></label><select name="notify_method" id="edit_notify_method" class="form-select"><option value="email"><?= te('Email') ?></option><option value="site"><?= te('Notificação') ?></option><option value="both"><?= te('Ambos') ?></option></select></div>
          </div>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn-s" data-bs-dismiss="modal"><?= te('Cancelar') ?></button>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> <?= te('Guardar') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Calendário: escolher um dia mostra o painel desse dia ──
(function () {
  const days = [...document.querySelectorAll('.lc-day')];
  if (!days.length) return;
  const narrow = matchMedia('(max-width: 980px)');
  function select(btn, focus) {
    days.forEach((d) => { const on = d === btn; d.setAttribute('aria-pressed', on); d.tabIndex = on ? 0 : -1; });
    document.querySelectorAll('.lc-panel').forEach((p) => { p.hidden = p.dataset.panel !== btn.dataset.day; });
    if (focus) btn.focus();
  }
  days.forEach((btn, i) => {
    btn.tabIndex = btn.getAttribute('aria-pressed') === 'true' ? 0 : -1;
    btn.addEventListener('click', () => {
      select(btn, false);
      // No telemóvel o painel fica por baixo: desce até ele
      if (narrow.matches) document.querySelector('.lc-side').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    // Setas: um único "Tab" entra na grelha e as setas mudam de dia (como num calendário)
    btn.addEventListener('keydown', (e) => {
      const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7, Home: -i, End: days.length - 1 - i }[e.key];
      if (step === undefined) return;
      e.preventDefault();
      const next = days[Math.max(0, Math.min(days.length - 1, i + step))];
      select(next, true);
    });
  });
  if (!days.some((d) => d.tabIndex === 0)) days[0].tabIndex = 0;
  // "Novo lembrete neste dia": abre o formulário com a data já escolhida
  document.querySelectorAll('[data-new-on]').forEach((b) => b.addEventListener('click', () => {
    document.querySelector('#newReminderModal input[name="due_date"]').value = b.dataset.newOn;
    new bootstrap.Modal(document.getElementById('newReminderModal')).show();
  }));
})();

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