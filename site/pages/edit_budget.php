<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$budget_id = intval($_GET['id'] ?? 0);

// Load budget
$stmt = $pdo->prepare("SELECT * FROM budgets WHERE id=:id AND user_id=:uid");
$stmt->execute([':id'=>$budget_id, ':uid'=>$uid]);
$budget = $stmt->fetch();

if (!$budget) {
    header('Location: budgets.php');
    exit;
}

$categories = ['Compras','Alimentação','Transporte','Saúde','Entretenimento','Educação','Casa','Outros'];
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id=:uid ORDER BY name");
$stmt->execute([':uid'=>$uid]);
$cards = $stmt->fetchAll();

$errors = [];
$name     = $budget['name'];
$amount   = $budget['amount'];
$period   = $budget['period'];
$category = $budget['category'] ?? '';
$card_id  = $budget['card_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $amount   = floatval($_POST['amount'] ?? 0);
    $period   = $_POST['period'] ?? 'monthly';
    $category = !empty($_POST['category']) ? trim($_POST['category']) : null;
    $card_id  = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);

    if (strlen($name) < 3) $errors[] = 'O nome deve ter pelo menos 3 caracteres.';
    if ($amount <= 0)       $errors[] = 'O valor deve ser maior que zero.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE budgets SET name=:name,amount=:amount,period=:period,category=:category,card_id=:card_id WHERE id=:id AND user_id=:uid");
            $stmt->execute([':id'=>$budget_id,':uid'=>$uid,':name'=>$name,':amount'=>$amount,':period'=>$period,':category'=>$category,':card_id'=>$card_id]);
            header('Location: budgets.php?success=updated');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao atualizar orçamento. Tenta novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Orçamento - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=3">
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
    .btn-s-primary { background: var(--fn-accent) !important; color: #fff !important; border-color: var(--fn-accent) !important; }
    .btn-s-primary:hover { background: var(--fn-accent-hover) !important; color: #fff !important; border-color: var(--fn-accent-hover) !important; }
    .s-alert-danger { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.1); color: #7f1d1d; }
    [data-theme="dark"] .s-alert-danger { color: #fca5a5; }
    .form-section { padding: 18px 20px; }
    .form-section:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .m-form .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; display: block; }
    .m-form .form-label-sub { font-size: 11px; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--text-secondary); }
    .m-form .form-control, .m-form .form-select { font-size: 14px; border: 1px solid var(--border-color) !important; border-radius: 9px; padding: 9px 12px; background: var(--bg-primary) !important; color: var(--text-primary) !important; width: 100%; }
    .m-form .form-control:focus, .m-form .form-select:focus { border-color: var(--fn-accent) !important; box-shadow: 0 0 0 2px rgba(var(--fn-accent-rgb),.25) !important; outline: none; }
    .m-form .form-hint { font-size: 12px; color: var(--text-secondary); margin-top: 5px; }
    input[type="number"]::-webkit-inner-spin-button, input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
    input[type="number"] { -moz-appearance: textfield; }
    .amount-input { font-size: 28px !important; font-weight: 700 !important; text-align: center !important; padding: 12px !important; }
    .period-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .period-option { position: relative; }
    .period-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .period-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 14px 8px; border: 1px solid var(--border-color); border-radius: 10px; cursor: pointer; transition: all .15s; background: var(--bg-primary); text-align: center; }
    .period-label:hover { border-color: var(--fn-accent); background: rgba(var(--fn-accent-rgb),.05); }
    .period-option input:checked + .period-label { border-color: var(--fn-accent); background: rgba(var(--fn-accent-rgb),.08); color: var(--fn-accent); }
    .period-label i { font-size: 22px; }
    .period-label span { font-size: 12px; font-weight: 600; }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=4">
</head>
<body>
<div class="app-layout">
<?php $activePage='budgets'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper is-form">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-pencil-square"></i></div>
    <div style="flex:1">
      <h2>Editar Orçamento</h2>
      <p><?=htmlspecialchars($budget['name'])?></p>
    </div>
    <a href="budgets.php" class="btn-s"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="s-alert-danger">
      <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
      <div><?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?></div>
    </div>
  <?php endif; ?>

  <div class="settings-group">
    <p class="group-label">Detalhes do orçamento</p>
    <div class="settings-card">
      <form method="post" class="m-form"><?= csrf_field() ?>

        <!-- Nome -->
        <div class="form-section">
          <label class="form-label">Nome *</label>
          <input type="text" name="name" class="form-control" placeholder="Ex: Alimentação mensal, Lazer..." value="<?=htmlspecialchars($name)?>" required>
        </div>

        <!-- Valor -->
        <div class="form-section">
          <label class="form-label" style="text-align:center;width:100%">Valor limite (€) *</label>
          <input type="number" name="amount" step="0.01" min="0.01" class="form-control amount-input" placeholder="0.00" value="<?=htmlspecialchars($amount)?>" required>
        </div>

        <!-- Período -->
        <div class="form-section">
          <label class="form-label">Período</label>
          <div class="period-grid">
            <div class="period-option">
              <input type="radio" name="period" id="period_weekly" value="weekly" <?=$period==='weekly'?'checked':''?>>
              <label class="period-label" for="period_weekly"><i class="bi bi-calendar-week"></i><span>Semanal</span></label>
            </div>
            <div class="period-option">
              <input type="radio" name="period" id="period_monthly" value="monthly" <?=$period==='monthly'?'checked':''?>>
              <label class="period-label" for="period_monthly"><i class="bi bi-calendar-month"></i><span>Mensal</span></label>
            </div>
            <div class="period-option">
              <input type="radio" name="period" id="period_yearly" value="yearly" <?=$period==='yearly'?'checked':''?>>
              <label class="period-label" for="period_yearly"><i class="bi bi-calendar3"></i><span>Anual</span></label>
            </div>
          </div>
        </div>

        <!-- Categoria -->
        <div class="form-section">
          <label class="form-label">Categoria <span class="form-label-sub">(opcional)</span></label>
          <select name="category" class="form-select">
            <option value="">Todas as categorias</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?=htmlspecialchars($cat)?>" <?=$category===$cat?'selected':''?>><?=htmlspecialchars($cat)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Cartão -->
        <div class="form-section">
          <label class="form-label">Cartão <span class="form-label-sub">(opcional)</span></label>
          <select name="card_id" class="form-select">
            <option value="">Todos os cartões</option>
            <?php foreach($cards as $c): ?>
              <option value="<?=$c['id']?>" <?=$card_id==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Botões -->
        <div class="form-section" style="display:flex;gap:8px;justify-content:flex-end">
          <a href="budgets.php" class="btn-s">Cancelar</a>
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
