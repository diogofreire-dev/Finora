<?php
// site/budgets.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = ''; $messageType = 'info';

if (!empty($_GET['success'])) {
    $message = $_GET['success'] === 'created' ? 'Orçamento criado com sucesso!' : 'Orçamento atualizado com sucesso!';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            $name = trim($_POST['name'] ?? ''); $amount = floatval($_POST['amount'] ?? 0);
            $period = $_POST['period'] ?? 'monthly';
            $category = !empty($_POST['category']) ? trim($_POST['category']) : null;
            $card_id = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
            if ($amount > 0 && strlen($name) >= 3) {
                try { $stmt = $pdo->prepare("INSERT INTO budgets (user_id, name, amount, period, category, card_id, start_date) VALUES (:uid,:name,:amount,:period,:category,:card_id,CURDATE())"); $stmt->execute([':uid'=>$uid,':name'=>$name,':amount'=>$amount,':period'=>$period,':category'=>$category,':card_id'=>$card_id]); $message = 'Orçamento criado com sucesso!'; $messageType = 'success'; } catch (PDOException $e) { $message = 'Erro ao criar orçamento.'; $messageType = 'danger'; }
            } else { $message = 'Dados inválidos.'; $messageType = 'danger'; }
            break;
        case 'edit':
            $budget_id = intval($_POST['budget_id'] ?? 0); $name = trim($_POST['name'] ?? ''); $amount = floatval($_POST['amount'] ?? 0);
            $period = $_POST['period'] ?? 'monthly'; $category = !empty($_POST['category']) ? trim($_POST['category']) : null; $card_id = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
            if ($amount > 0 && strlen($name) >= 3) {
                try { $stmt = $pdo->prepare("UPDATE budgets SET name=:name,amount=:amount,period=:period,category=:category,card_id=:card_id WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$budget_id,':uid'=>$uid,':name'=>$name,':amount'=>$amount,':period'=>$period,':category'=>$category,':card_id'=>$card_id]); $message = 'Orçamento atualizado!'; $messageType = 'success'; } catch (PDOException $e) { $message = 'Erro ao atualizar.'; $messageType = 'danger'; }
            } else { $message = 'Dados inválidos.'; $messageType = 'danger'; }
            break;
        case 'toggle':
            $budget_id = intval($_POST['budget_id'] ?? 0);
            try { $stmt = $pdo->prepare("UPDATE budgets SET active = NOT active WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$budget_id,':uid'=>$uid]); $message = 'Estado alterado!'; $messageType = 'success'; } catch (PDOException $e) { $message = 'Erro.'; $messageType = 'danger'; }
            break;
        case 'delete':
            $budget_id = intval($_POST['budget_id'] ?? 0);
            try { $stmt = $pdo->prepare("DELETE FROM budgets WHERE id=:id AND user_id=:uid"); $stmt->execute([':id'=>$budget_id,':uid'=>$uid]); $message = 'Orçamento removido!'; $messageType = 'success'; } catch (PDOException $e) { $message = 'Erro.'; $messageType = 'danger'; }
            break;
    }
}

$stmt = $pdo->prepare("SELECT b.*, c.name as card_name, COALESCE(SUM(CASE WHEN b.period='monthly' AND t.transaction_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND t.transaction_date<=LAST_DAY(CURDATE()) THEN t.amount WHEN b.period='weekly' AND t.transaction_date>=DATE_SUB(CURDATE(),INTERVAL WEEKDAY(CURDATE()) DAY) AND t.transaction_date<=CURDATE() THEN t.amount WHEN b.period='yearly' AND YEAR(t.transaction_date)=YEAR(CURDATE()) THEN t.amount ELSE 0 END),0) as current_spent FROM budgets b LEFT JOIN cards c ON c.id=b.card_id LEFT JOIN transactions t ON t.user_id=b.user_id AND (b.category IS NULL OR t.category=b.category) AND (b.card_id IS NULL OR t.card_id=b.card_id) WHERE b.user_id=:uid GROUP BY b.id ORDER BY b.active DESC, b.created_at DESC");
$stmt->execute([':uid'=>$uid]);
$budgets = $stmt->fetchAll();

$categories = ['Compras','Alimentação','Transporte','Saúde','Entretenimento','Educação','Casa','Outros'];
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id=:uid ORDER BY name");
$stmt->execute([':uid'=>$uid]);
$cards = $stmt->fetchAll();

$periodLabels = ['monthly'=>'Mensal','weekly'=>'Semanal','yearly'=>'Anual'];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orçamentos - Finora</title>
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
    .s-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; transition: background .15s; }
    .s-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .s-row-label { font-size: 14px; font-weight: 500; color: var(--text-primary); margin: 0 0 2px; }
    .s-row-sub { font-size: 12px; color: var(--text-secondary); margin: 0; }
    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; }
    .btn-s-primary:hover { background: #2980b9; color: #fff; border-color: #2980b9; }
    .btn-s-danger { border-color: #dc2626; color: #dc2626; }
    .btn-s-danger:hover { background: rgba(239,68,68,.07); }
    .s-alert { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
    .s-alert-success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    [data-theme="dark"] .s-alert-success { color: #6ee7b7; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }
    .s-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); }
    .empty-state { padding: 40px 20px; text-align: center; }
    .empty-state i { font-size: 48px; color: var(--border-color); display: block; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; color: var(--text-secondary); margin-bottom: 16px; }
    /* Budget progress block inside card */
    .budget-block { padding: 16px 20px; }
    .budget-block:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .budget-block.inactive { opacity: .5; }
    .budget-progress { height: 6px; border-radius: 3px; background: var(--bg-primary); overflow: hidden; margin: 10px 0 8px; }
    .budget-bar { height: 100%; border-radius: 3px; transition: width 1s ease-out; width: 0; }
    /* Modal */
    .modal-content { background: var(--bg-secondary) !important; border: 1px solid var(--border-color) !important; border-radius: 16px; color: var(--text-primary) !important; }
    .modal-header { border-bottom: 1px solid var(--border-color) !important; padding: 18px 20px; background: transparent !important; }
    .modal-body { background: transparent !important; }
    .modal-footer { border-top: 1px solid var(--border-color) !important; padding: 14px 20px; background: transparent !important; }
    .modal-title { font-size: 15px; font-weight: 600; color: var(--text-primary) !important; }
    .m-form .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary) !important; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    .m-form .form-control, .m-form .form-select { font-size: 14px; border: 1px solid var(--border-color) !important; border-radius: 9px; padding: 9px 12px; background: var(--bg-primary) !important; background-color: var(--bg-primary) !important; color: var(--text-primary) !important; --bs-body-bg: var(--bg-primary); --bs-body-color: var(--text-primary); --bs-border-color: var(--border-color); }
    .m-form .form-control:focus, .m-form .form-select:focus { border-color: #3498db !important; box-shadow: 0 0 0 2px rgba(52,152,219,.25) !important; background: var(--bg-primary) !important; background-color: var(--bg-primary) !important; color: var(--text-primary) !important; }
    .m-form .form-control::placeholder { color: var(--text-secondary) !important; opacity: 1; }
    input[type="number"]::-webkit-inner-spin-button, input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
    input[type="number"] { -moz-appearance: textfield; }
    @media (max-width:600px) { .s-row { flex-wrap: wrap; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='budgets'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-piggy-bank"></i></div>
    <div style="flex:1">
      <h2>Os Meus Orçamentos</h2>
      <p>Controla os teus gastos mensais e mantém-te no orçamento</p>
    </div>
    <a href="add_budget.php" class="btn-s btn-s-primary">
      <i class="bi bi-plus-circle"></i> Novo
    </a>
  </div>

  <?php if ($message): ?>
    <?php $icon = $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>
    <div class="s-alert s-alert-<?=$messageType?>">
      <i class="bi bi-<?=$icon?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?=htmlspecialchars($message)?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($budgets)): ?>
    <div class="settings-card">
      <div class="empty-state">
        <i class="bi bi-piggy-bank"></i>
        <p>Ainda não tens orçamentos definidos.<br>Cria o primeiro para começares a controlar os teus gastos.</p>
        <a href="add_budget.php" class="btn-s btn-s-primary">
          <i class="bi bi-plus-circle"></i> Criar Primeiro Orçamento
        </a>
      </div>
    </div>
  <?php else: ?>

    <?php
    $activeB   = array_filter($budgets, fn($b) => $b['active']);
    $inactiveB = array_filter($budgets, fn($b) => !$b['active']);
    ?>

    <?php if (!empty($activeB)): ?>
    <div class="settings-group">
      <p class="group-label">Orçamentos ativos</p>
      <div class="settings-card">
        <?php foreach($activeB as $b):
          $pct = $b['amount'] > 0 ? ($b['current_spent'] / $b['amount']) * 100 : 0;
          $remaining = $b['amount'] - $b['current_spent'];
          $barColor = $pct >= 100 ? '#ef4444' : ($pct >= 80 ? '#f59e0b' : '#22c55e');
          $pctLabel = $pct >= 100 ? 'text-danger' : ($pct >= 80 ? '' : '');
        ?>
        <div class="budget-block">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                <span style="font-size:14px;font-weight:600;color:var(--text-primary)"><?=htmlspecialchars($b['name'])?></span>
                <span class="s-badge"><i class="bi bi-calendar-event" style="font-size:10px"></i> <?=$periodLabels[$b['period']]?></span>
                <?php if($b['category']): ?><span class="s-badge"><i class="bi bi-tag" style="font-size:10px"></i> <?=htmlspecialchars($b['category'])?></span><?php endif; ?>
                <?php if($b['card_name']): ?><span class="s-badge"><i class="bi bi-credit-card" style="font-size:10px"></i> <?=htmlspecialchars($b['card_name'])?></span><?php endif; ?>
              </div>
              <div class="budget-progress">
                <div class="budget-bar" style="background:<?=$barColor?>" data-width="<?=min($pct,100)?>%"></div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:12px;color:var(--text-secondary)">
                  <strong style="color:var(--text-primary)">€<?=number_format($b['current_spent'],2)?></strong> de €<?=number_format($b['amount'],2)?>
                </span>
                <span style="font-size:12px;font-weight:600;color:<?=$pct>=100?'#ef4444':($pct>=80?'#d97706':'#16a34a')?>">
                  <?php if($remaining<0): ?>+€<?=number_format(abs($remaining),2)?> excedido<?php else: ?>€<?=number_format($remaining,2)?> restante<?php endif; ?>
                  &nbsp;(<?=round($pct)?>%)
                </span>
              </div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;margin-top:2px">
              <a href="edit_budget.php?id=<?=$b['id']?>" class="btn-s" style="padding:5px 10px" title="Editar"><i class="bi bi-pencil"></i></a>
              <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="budget_id" value="<?=$b['id']?>"><button class="btn-s" style="padding:5px 10px" type="submit" title="Desativar"><i class="bi bi-pause-circle"></i></button></form>
              <form method="post" style="margin:0" onsubmit="return confirm('Eliminar orçamento?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="budget_id" value="<?=$b['id']?>"><button class="btn-s btn-s-danger" style="padding:5px 10px" type="submit"><i class="bi bi-trash3"></i></button></form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($inactiveB)): ?>
    <div class="settings-group">
      <p class="group-label">Inativos</p>
      <div class="settings-card">
        <?php foreach($inactiveB as $b): ?>
        <div class="budget-block inactive">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <div>
              <span style="font-size:14px;font-weight:500;color:var(--text-primary)"><?=htmlspecialchars($b['name'])?></span>
              &nbsp;<span class="s-badge"><?=$periodLabels[$b['period']]?></span>
              &nbsp;<span style="font-size:13px;color:var(--text-secondary)">€<?=number_format($b['amount'],2)?></span>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
              <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="budget_id" value="<?=$b['id']?>"><button class="btn-s" style="padding:5px 12px;font-size:12px" type="submit"><i class="bi bi-play-circle"></i> Ativar</button></form>
              <form method="post" style="margin:0" onsubmit="return confirm('Eliminar?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="budget_id" value="<?=$b['id']?>"><button class="btn-s btn-s-danger" style="padding:5px 10px" type="submit"><i class="bi bi-trash3"></i></button></form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <?php endif; ?>

</div><!-- /settings-wrapper -->
</div><!-- /page-content -->

</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    document.querySelectorAll('.budget-bar').forEach(bar => { bar.style.width = bar.dataset.width; });
  }, 100);
});
</script>
</body>
</html>