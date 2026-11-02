<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$reminder_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM payment_reminders WHERE id=:id AND user_id=:uid");
$stmt->execute([':id'=>$reminder_id, ':uid'=>$uid]);
$reminder = $stmt->fetch();

if (!$reminder) {
    header('Location: reminders.php');
    exit;
}

$categories = ['Compras','Alimentação','Transporte','Saúde','Entretenimento','Educação','Casa','Outros'];
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id=:uid AND active=1 ORDER BY name");
$stmt->execute([':uid'=>$uid]);
$cards = $stmt->fetchAll();

$errors = [];
$name          = $reminder['name'];
$amount        = $reminder['amount'];
$due_date      = $reminder['due_date'];
$recurrence    = $reminder['recurrence'];
$category      = $reminder['category'] ?? '';
$card_id       = $reminder['card_id'] ?? '';
$notify_days   = $reminder['notify_days_before'] ?? 3;
$notify_method = $reminder['notify_method'] ?? 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $amount        = floatval($_POST['amount'] ?? 0);
    $category      = trim($_POST['category'] ?? '');
    $card_id       = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
    $due_date      = $_POST['due_date'] ?? '';
    $recurrence    = $_POST['recurrence'] ?? 'once';
    $notify_days   = intval($_POST['notify_days_before'] ?? 3);
    $notify_method = $_POST['notify_method'] ?? 'email';

    if (strlen($name) < 3) $errors[] = 'O nome deve ter pelo menos 3 caracteres.';
    if ($amount <= 0)       $errors[] = 'O valor deve ser maior que zero.';
    if (!$due_date)         $errors[] = 'A data de vencimento é obrigatória.';

    if (empty($errors)) {
        try {
            $next_due = $due_date;
            if ($recurrence !== 'once') {
                $dateObj = new DateTime($due_date);
                match($recurrence) {
                    'weekly'  => $dateObj->modify('+1 week'),
                    'monthly' => $dateObj->modify('+1 month'),
                    'yearly'  => $dateObj->modify('+1 year'),
                    default   => null
                };
                $next_due = $dateObj->format('Y-m-d');
            }
            $stmt = $pdo->prepare("UPDATE payment_reminders SET name=:name, amount=:amount, category=:category, card_id=:card_id, due_date=:due_date, recurrence=:recurrence, notify_days_before=:notify, notify_method=:notify_method, next_due_date=:next_due WHERE id=:id AND user_id=:uid");
            $stmt->execute([':id'=>$reminder_id,':uid'=>$uid,':name'=>$name,':amount'=>$amount,':category'=>$category?:null,':card_id'=>$card_id,':due_date'=>$due_date,':recurrence'=>$recurrence,':notify'=>$notify_days,':notify_method'=>$notify_method,':next_due'=>$next_due]);
            header('Location: reminders.php?success=updated');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao atualizar lembrete. Tenta novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Lembrete - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
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
    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db !important; color: #fff !important; border-color: #3498db !important; }
    .btn-s-primary:hover { background: #2980b9 !important; color: #fff !important; border-color: #2980b9 !important; }
    .s-alert-danger { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.1); color: #7f1d1d; }
    [data-theme="dark"] .s-alert-danger { color: #fca5a5; }
    .form-section { padding: 18px 20px; }
    .form-section:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .m-form .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; display: block; }
    .m-form .form-label-sub { font-size: 11px; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--text-secondary); }
    .m-form .form-control, .m-form .form-select { font-size: 14px; border: 1px solid var(--border-color) !important; border-radius: 9px; padding: 9px 12px; background: var(--bg-primary) !important; color: var(--text-primary) !important; width: 100%; }
    .m-form .form-control:focus, .m-form .form-select:focus { border-color: #3498db !important; box-shadow: 0 0 0 2px rgba(52,152,219,.25) !important; outline: none; }
    input[type="number"]::-webkit-inner-spin-button, input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
    input[type="number"] { -moz-appearance: textfield; }
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
    .amount-input { font-size: 28px !important; font-weight: 700 !important; text-align: center !important; padding: 12px !important; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width:480px) { .two-col { grid-template-columns: 1fr; } }
    .recur-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    @media (max-width:480px) { .recur-grid { grid-template-columns: repeat(2, 1fr); } }
    .recur-option { position: relative; }
    .recur-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .recur-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; padding: 12px 6px; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; transition: all .15s; background: var(--bg-primary); text-align: center; }
    .recur-label:hover { border-color: #3498db; background: rgba(52,152,219,.05); }
    .recur-option input:checked + .recur-label { border-color: #3498db; background: rgba(52,152,219,.08); color: #3498db; }
    .recur-label i { font-size: 18px; }
    .recur-label span { font-size: 11px; font-weight: 600; }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='reminders'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-pencil-square"></i></div>
    <div style="flex:1">
      <h2>Editar Lembrete</h2>
      <p><?=htmlspecialchars($reminder['name'])?></p>
    </div>
    <a href="reminders.php" class="btn-s"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="s-alert-danger">
      <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
      <div><?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?></div>
    </div>
  <?php endif; ?>

  <div class="settings-group">
    <p class="group-label">Detalhes do lembrete</p>
    <div class="settings-card">
      <form method="post" class="m-form"><?= csrf_field() ?>

        <!-- Nome -->
        <div class="form-section">
          <label class="form-label">Nome do Pagamento *</label>
          <input type="text" name="name" class="form-control" placeholder="Ex: Renda, Netflix, Seguro..." value="<?=htmlspecialchars($name)?>" required>
        </div>

        <!-- Valor + Data -->
        <div class="form-section">
          <div class="two-col">
            <div>
              <label class="form-label">Valor (€) *</label>
              <input type="number" name="amount" step="0.01" min="0.01" class="form-control amount-input" placeholder="0.00" value="<?=htmlspecialchars($amount)?>" required>
            </div>
            <div>
              <label class="form-label">Data de Vencimento *</label>
              <input type="date" name="due_date" class="form-control" value="<?=htmlspecialchars($due_date)?>" required>
            </div>
          </div>
        </div>

        <!-- Recorrência -->
        <div class="form-section">
          <label class="form-label">Recorrência</label>
          <div class="recur-grid">
            <div class="recur-option">
              <input type="radio" name="recurrence" id="recur_once" value="once" <?=$recurrence==='once'?'checked':''?>>
              <label class="recur-label" for="recur_once"><i class="bi bi-1-circle"></i><span>Única vez</span></label>
            </div>
            <div class="recur-option">
              <input type="radio" name="recurrence" id="recur_weekly" value="weekly" <?=$recurrence==='weekly'?'checked':''?>>
              <label class="recur-label" for="recur_weekly"><i class="bi bi-calendar-week"></i><span>Semanal</span></label>
            </div>
            <div class="recur-option">
              <input type="radio" name="recurrence" id="recur_monthly" value="monthly" <?=$recurrence==='monthly'?'checked':''?>>
              <label class="recur-label" for="recur_monthly"><i class="bi bi-calendar-month"></i><span>Mensal</span></label>
            </div>
            <div class="recur-option">
              <input type="radio" name="recurrence" id="recur_yearly" value="yearly" <?=$recurrence==='yearly'?'checked':''?>>
              <label class="recur-label" for="recur_yearly"><i class="bi bi-calendar3"></i><span>Anual</span></label>
            </div>
          </div>
        </div>

        <!-- Categoria + Cartão -->
        <div class="form-section">
          <div class="two-col">
            <div>
              <label class="form-label">Categoria <span class="form-label-sub">(opcional)</span></label>
              <select name="category" class="form-select">
                <option value="">Sem categoria</option>
                <?php foreach($categories as $cat): ?>
                  <option value="<?=htmlspecialchars($cat)?>" <?=$category===$cat?'selected':''?>><?=htmlspecialchars($cat)?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label">Cartão <span class="form-label-sub">(opcional)</span></label>
              <select name="card_id" class="form-select">
                <option value="">Dinheiro / A definir</option>
                <?php foreach($cards as $c): ?>
                  <option value="<?=$c['id']?>" <?=$card_id==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Notificação -->
        <div class="form-section">
          <label class="form-label">Notificação</label>
          <div class="two-col">
            <div>
              <label class="form-label" style="font-size:11px">Notificar</label>
              <select name="notify_days_before" class="form-select">
                <option value="0" <?=$notify_days==0?'selected':''?>>No dia</option>
                <option value="1" <?=$notify_days==1?'selected':''?>>1 dia antes</option>
                <option value="3" <?=$notify_days==3?'selected':''?>>3 dias antes</option>
                <option value="7" <?=$notify_days==7?'selected':''?>>7 dias antes</option>
              </select>
            </div>
            <div>
              <label class="form-label" style="font-size:11px">Método</label>
              <select name="notify_method" class="form-select">
                <option value="email" <?=$notify_method==='email'?'selected':''?>>Email</option>
                <option value="site" <?=$notify_method==='site'?'selected':''?>>Notificação</option>
                <option value="both" <?=$notify_method==='both'?'selected':''?>>Ambos</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Botões -->
        <div class="form-section" style="display:flex;gap:8px;justify-content:flex-end">
          <a href="reminders.php" class="btn-s">Cancelar</a>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> Guardar Alterações</button>
        </div>

      </form>
    </div>
  </div>

</div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
