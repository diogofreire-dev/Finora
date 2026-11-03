<?php
// site/edit_transaction.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$errors = [];

$transaction_id = !empty($_GET['id']) ? intval($_GET['id']) : null;
if (!$transaction_id) { header('Location: transactions.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = :id AND user_id = :uid");
$stmt->execute([':id' => $transaction_id, ':uid' => $uid]);
$transaction = $stmt->fetch();
if (!$transaction) { header('Location: transactions.php'); exit; }

$stmt = $pdo->prepare("SELECT id, name, limit_amount, balance FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

$categories = [
    ['icon' => 'cart',        'name' => 'Compras'],
    ['icon' => 'cup-straw',   'name' => 'Alimentação'],
    ['icon' => 'bus-front',   'name' => 'Transporte'],
    ['icon' => 'heart-pulse', 'name' => 'Saúde'],
    ['icon' => 'controller',  'name' => 'Entretenimento'],
    ['icon' => 'book',        'name' => 'Educação'],
    ['icon' => 'house-door',  'name' => 'Casa'],
    ['icon' => 'three-dots',  'name' => 'Outros'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount           = floatval($_POST['amount'] ?? 0);
    $description      = trim($_POST['description'] ?? '');
    $category         = trim($_POST['category'] ?? '');
    $card_id          = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $old_amount       = $transaction['amount'];
    $old_card_id      = $transaction['card_id'];

    if ($amount <= 0)               { $errors[] = 'O valor deve ser maior que zero.'; }
    if (strlen($description) < 3)   { $errors[] = 'A descrição deve ter pelo menos 3 caracteres.'; }
    if (empty($transaction_date)) {
        $errors[] = 'A data da transação é obrigatória.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $transaction_date);
        if (!$date || $date->format('Y-m-d') !== $transaction_date) { $errors[] = 'Data inválida.'; }
        elseif ($date > new DateTime()) { $errors[] = 'A data da transação não pode ser no futuro.'; }
    }

    if ($card_id && ($card_id != $old_card_id || $amount != $old_amount)) {
        $stmt = $pdo->prepare("SELECT limit_amount, balance FROM cards WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $card_id, ':uid' => $uid]);
        $card = $stmt->fetch();
        if ($card && $card['limit_amount'] > 0) {
            $cur = $card['balance'];
            if ($card_id == $old_card_id) { $cur -= $old_amount; }
            if (($cur + $amount) > $card['limit_amount']) { $errors[] = 'Esta transação excede o limite disponível do cartão.'; }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE transactions SET amount=:amt,description=:desc,category=:cat,card_id=:cid,transaction_date=:tdate WHERE id=:id AND user_id=:uid");
            $stmt->execute([':amt'=>$amount,':desc'=>$description,':cat'=>$category?:null,':cid'=>$card_id,':tdate'=>$transaction_date,':id'=>$transaction_id,':uid'=>$uid]);
            if ($old_card_id) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance - :amt WHERE id = :cid AND user_id = :uid");
                $stmt->execute([':amt'=>$old_amount,':cid'=>$old_card_id,':uid'=>$uid]);
            }
            if ($card_id) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance + :amt WHERE id = :cid AND user_id = :uid");
                $stmt->execute([':amt'=>$amount,':cid'=>$card_id,':uid'=>$uid]);
            }
            $pdo->commit();
            header('Location: transactions.php?success=transaction_updated');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao atualizar transação. Tenta novamente.';
        }
    }
} else {
    $amount           = $transaction['amount'];
    $description      = $transaction['description'];
    $category         = $transaction['category'] ?? '';
    $card_id          = $transaction['card_id'];
    $transaction_date = $transaction['transaction_date'];
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Transação - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=3">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary) !important; }
    .settings-wrapper { max-width: 700px; margin: 0 auto; padding: 0 16px 60px; }

    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--bg-secondary) !important; border: 1px solid var(--border-color) !important; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary) !important; }
    .page-header p { font-size: 13px; color: var(--text-secondary) !important; margin: 0; }

    .settings-group { margin-bottom: 24px; }
    .group-label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-secondary) !important; margin: 0 0 8px 4px; }
    .settings-card { background: var(--bg-secondary) !important; border: 1px solid var(--border-color) !important; border-radius: 14px; overflow: hidden; }

    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color) !important; background: var(--bg-primary) !important; color: var(--text-primary) !important; cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary) !important; }
    .btn-s-primary { background: var(--fn-accent); color: #fff; border-color: var(--fn-accent); }
    .btn-s-primary:hover { background: var(--fn-accent-hover); color: #fff; border-color: var(--fn-accent-hover); }

    .s-alert-danger { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.1); color: #7f1d1d; }
    [data-theme="dark"] .s-alert-danger { color: #fca5a5; }

    /* Form */
    .form-section { padding: 18px 20px; }
    .form-section:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .m-form .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary) !important; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; display: block; }
    .m-form .form-label-sub { font-size: 11px; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--text-secondary) !important; }
    .m-form .form-control, .m-form .form-select { font-size: 14px; border: 1px solid var(--border-color) !important; border-radius: 9px; padding: 9px 12px; background: var(--bg-primary) !important; color: var(--text-primary) !important; width: 100%; }
    .m-form .form-control:focus, .m-form .form-select:focus { border-color: var(--fn-accent); box-shadow: none; outline: none; }
    [data-theme="dark"] .m-form .form-control,
    [data-theme="dark"] .m-form .form-select { background: var(--bg-primary) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
    .m-form .form-hint { font-size: 12px; color: var(--text-secondary) !important; margin-top: 5px; }

    /* Amount input */
    .amount-input { font-size: 28px !important; font-weight: 700 !important; text-align: center !important; padding: 12px !important; }
    .amount-input::-webkit-outer-spin-button, .amount-input::-webkit-inner-spin-button { -webkit-appearance: none; }
    .amount-input[type=number] { -moz-appearance: textfield; }

    /* Category picker */
    .cat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .cat-option { position: relative; }
    .cat-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .cat-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 14px 8px; border: 1px solid var(--border-color) !important; border-radius: 10px; cursor: pointer; transition: all .15s; background: var(--bg-primary) !important; text-align: center; }
    .cat-label:hover { border-color: var(--fn-accent); background: rgba(var(--fn-accent-rgb),.05); }
    .cat-option input:checked + .cat-label { border-color: var(--fn-accent); background: rgba(var(--fn-accent-rgb),.08); color: var(--fn-accent); }
    .cat-label i { font-size: 22px; }
    .cat-label span { font-size: 11px; font-weight: 600; line-height: 1.2; }

    /* Original data block */
    .orig-row { display: flex; flex-direction: column; gap: 2px; padding: 11px 20px; }
    .orig-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .orig-label { font-size: 11px; font-weight: 600; color: var(--text-secondary) !important; text-transform: uppercase; letter-spacing: .04em; }
    .orig-value { font-size: 14px; color: var(--text-primary) !important; }

    /* Info notice */
    .info-notice { display: flex; gap: 10px; align-items: flex-start; padding: 14px 20px; background: rgba(var(--fn-accent-rgb),.06); border-bottom: 1px solid var(--border-color); }

    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0); }
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=1">
</head>
<body>
<div class="app-layout">
<?php $activePage='transactions'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-pencil-square"></i></div>
    <div style="flex:1">
      <h2>Editar Transação</h2>
      <p>Atualiza os dados desta despesa</p>
    </div>
    <a href="transactions.php" class="btn-s"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="s-alert-danger">
      <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
      <div><?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?></div>
    </div>
  <?php endif; ?>

  <div class="settings-group">
    <p class="group-label">Editar detalhes</p>
    <div class="settings-card">

      <!-- Notice -->
      <div class="info-notice">
        <i class="bi bi-info-circle" style="color:var(--fn-accent);flex-shrink:0;margin-top:2px"></i>
        <div style="font-size:13px;color:var(--text-secondary)">
          Registo criado em <strong style="color:var(--text-primary)"><?=date('d/m/Y \à\s H:i', strtotime($transaction['created_at']))?></strong> — esta data não será alterada.
        </div>
      </div>

      <form method="post" class="m-form"><?= csrf_field() ?>

        <!-- Data -->
        <div class="form-section">
          <label class="form-label">Data da transação *</label>
          <input type="date" name="transaction_date" class="form-control"
                 value="<?=htmlspecialchars($transaction['transaction_date'])?>"
                 max="<?=date('Y-m-d')?>" required autofocus>
          <p class="form-hint">Podes alterar a data em que a despesa realmente ocorreu.</p>
        </div>

        <!-- Valor -->
        <div class="form-section">
          <label class="form-label" style="text-align:center;width:100%">Valor (€) *</label>
          <input type="number" name="amount" step="0.01" min="0" class="form-control amount-input"
                 placeholder="0.00" value="<?=htmlspecialchars($transaction['amount'])?>" required>
        </div>

        <!-- Descrição -->
        <div class="form-section">
          <label class="form-label">Descrição *</label>
          <input type="text" name="description" class="form-control"
                 placeholder="Ex: Supermercado, Netflix, Gasolina..."
                 value="<?=htmlspecialchars($transaction['description'])?>" required>
        </div>

        <!-- Categoria -->
        <div class="form-section">
          <label class="form-label">Categoria</label>
          <div class="cat-grid">
            <?php foreach($categories as $cat): ?>
            <div class="cat-option">
              <input type="radio" name="category" id="cat_<?=$cat['name']?>" value="<?=$cat['name']?>"
                     <?=$category===$cat['name']?'checked':''?>>
              <label class="cat-label" for="cat_<?=$cat['name']?>">
                <i class="bi bi-<?=$cat['icon']?>"></i>
                <span><?=$cat['name']?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Cartão -->
        <div class="form-section">
          <label class="form-label">Cartão associado <span class="form-label-sub">(opcional)</span></label>
          <select name="card_id" class="form-select" style="background-color:var(--bg-primary);color:var(--text-primary);border-color:var(--border-color);">
            <option value="" <?=!$card_id?'selected':''?>>Nenhum / Dinheiro</option>
            <?php foreach($cards as $c): ?>
              <option value="<?=$c['id']?>" <?=$card_id==$c['id']?'selected':''?>>
                <?=htmlspecialchars($c['name'])?> — Disponível: <?=fmt_eur($c['limit_amount']-$c['balance'])?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="form-hint">Se mudares o cartão ou o valor, os saldos serão ajustados automaticamente.</p>
        </div>

        <!-- Botões -->
        <div class="form-section" style="display:flex;gap:8px;justify-content:flex-end">
          <a href="transactions.php" class="btn-s">Cancelar</a>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> Guardar Alterações</button>
        </div>

      </form>
    </div>
  </div>

  <div class="settings-group">
    <p class="group-label">Dados originais</p>
    <div class="settings-card">
      <div class="orig-row">
        <span class="orig-label">Data da transação</span>
        <span class="orig-value"><?=date('d/m/Y', strtotime($transaction['transaction_date']))?></span>
      </div>
      <div class="orig-row">
        <span class="orig-label">Valor</span>
        <span class="orig-value" style="font-weight:700;color:#e74c3c"><?=fmt_eur($transaction['amount'])?></span>
      </div>
      <div class="orig-row">
        <span class="orig-label">Descrição</span>
        <span class="orig-value"><?=htmlspecialchars($transaction['description'])?></span>
      </div>
      <?php if($transaction['category']): ?>
      <div class="orig-row">
        <span class="orig-label">Categoria</span>
        <span class="orig-value"><?=htmlspecialchars($transaction['category'])?></span>
      </div>
      <?php endif; ?>
      <div class="orig-row">
        <span class="orig-label">Registo criado em</span>
        <span class="orig-value"><?=date('d/m/Y \à\s H:i', strtotime($transaction['created_at']))?></span>
      </div>
    </div>
  </div>

</div><!-- /settings-wrapper -->
</div><!-- /page-content -->
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>