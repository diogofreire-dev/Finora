<?php
// site/create_transaction.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$errors = [];

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

$amount = '';
$description = '';
$category = '';
$card_id = null;
$transaction_date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount           = floatval($_POST['amount'] ?? 0);
    $description      = trim($_POST['description'] ?? '');
    $category         = trim($_POST['category'] ?? '');
    $card_id          = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
    $transaction_date = trim($_POST['transaction_date'] ?? '');

    if ($amount <= 0)               { $errors[] = 'O valor deve ser maior que zero.'; }
    if (strlen($description) < 3)   { $errors[] = 'A descrição deve ter pelo menos 3 caracteres.'; }
    if (empty($transaction_date)) {
        $errors[] = 'A data da transação é obrigatória.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $transaction_date);
        if (!$date || $date->format('Y-m-d') !== $transaction_date) { $errors[] = 'Data inválida.'; }
        elseif ($date > new DateTime()) { $errors[] = 'A data da transação não pode ser no futuro.'; }
    }

    if ($card_id) {
        $stmt = $pdo->prepare("SELECT limit_amount, balance FROM cards WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $card_id, ':uid' => $uid]);
        $card = $stmt->fetch();
        if ($card && $card['limit_amount'] > 0 && ($card['balance'] + $amount) > $card['limit_amount']) {
            $errors[] = 'Esta transação excede o limite disponível do cartão.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at) VALUES (:uid,:cid,:amt,:desc,:cat,:tdate,NOW())");
            $stmt->execute([':uid'=>$uid,':cid'=>$card_id,':amt'=>$amount,':desc'=>$description,':cat'=>$category?:null,':tdate'=>$transaction_date]);
            if ($card_id) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance + :amt WHERE id = :cid AND user_id = :uid");
                $stmt->execute([':amt'=>$amount,':cid'=>$card_id,':uid'=>$uid]);
            }
            $pdo->commit();
            header('Location: transactions.php?success=transaction_added');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao criar transação. Tenta novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nova Transação - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
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
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; }
    .btn-s-primary:hover { background: #2980b9; color: #fff; border-color: #2980b9; }

    .s-alert-danger { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.1); color: #7f1d1d; }
    [data-theme="dark"] .s-alert-danger { color: #fca5a5; }

    /* Form */
    .form-section { padding: 18px 20px; }
    .form-section:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .m-form .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary) !important; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; display: block; }
    .m-form .form-label-sub { font-size: 11px; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--text-secondary) !important; }
    .m-form .form-control, .m-form .form-select { font-size: 14px; border: 1px solid var(--border-color) !important; border-radius: 9px; padding: 9px 12px; background: var(--bg-primary) !important; color: var(--text-primary) !important; width: 100%; }
    .m-form .form-control:focus, .m-form .form-select:focus { border-color: #3498db; box-shadow: none; outline: none; }
    [data-theme="dark"] .m-form .form-control,
    [data-theme="dark"] .m-form .form-select { background: var(--bg-primary) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
    .m-form .form-hint { font-size: 12px; color: var(--text-secondary) !important; margin-top: 5px; }

    /* Amount input */
    .amount-input { font-size: 28px !important; font-weight: 700 !important; text-align: center !important; padding: 12px !important; }
    .amount-input::-webkit-outer-spin-button, .amount-input::-webkit-inner-spin-button { -webkit-appearance: none; }
    .amount-input[type=number] { -moz-appearance: textfield; }

    /* Category picker */
    .cat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    @media (max-width: 480px) { .cat-grid { grid-template-columns: repeat(4, 1fr); } }
    .cat-option { position: relative; }
    .cat-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .cat-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 14px 8px; border: 1px solid var(--border-color) !important; border-radius: 10px; cursor: pointer; transition: all .15s; background: var(--bg-primary) !important; text-align: center; }
    .cat-label:hover { border-color: #3498db; background: rgba(52,152,219,.05); }
    .cat-option input:checked + .cat-label { border-color: #3498db; background: rgba(52,152,219,.08); color: #3498db; }
    .cat-label i { font-size: 22px; }
    .cat-label span { font-size: 11px; font-weight: 600; line-height: 1.2; }

    /* Card progress */
    .card-mini { padding: 12px 16px; border-radius: 10px; background: var(--bg-primary) !important; border: 1px solid var(--border-color) !important; margin-bottom: 8px; }
    .card-mini:last-child { margin-bottom: 0; }
    .card-bar { height: 5px; border-radius: 3px; background: var(--border-color); overflow: hidden; margin: 6px 0 4px; }
    .card-bar-fill { height: 100%; border-radius: 3px; }

    /* Info tip */
    .tip-row { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; }
    .tip-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .tip-icon { width: 28px; height: 28px; border-radius: 8px; background: var(--bg-primary) !important; border: 1px solid var(--border-color) !important; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; margin-top: 1px; }

    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0); }
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='transactions'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-plus-circle"></i></div>
    <div style="flex:1">
      <h2>Nova Transação</h2>
      <p>Regista uma nova despesa</p>
    </div>
    <a href="transactions.php" class="btn-s"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="s-alert-danger" style="border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px">
      <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
      <div>
        <?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($cards)): ?>
    <div style="border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;border:1px solid rgba(52,152,219,.3);background:rgba(52,152,219,.08);color:var(--text-secondary)">
      <i class="bi bi-info-circle" style="flex-shrink:0;margin-top:1px"></i>
      <span>Ainda não tens cartões registados. <a href="add_card.php" style="color:#3498db;font-weight:600">Adiciona um cartão</a> ou continua sem cartão associado.</span>
    </div>
  <?php endif; ?>

  <div class="settings-group">
    <p class="group-label">Detalhes da transação</p>
    <div class="settings-card">
      <form method="post" class="m-form"><?= csrf_field() ?>

        <!-- Data -->
        <div class="form-section">
          <label class="form-label">Data da transação *</label>
          <input type="date" name="transaction_date" class="form-control"
                 value="<?=htmlspecialchars($transaction_date)?>"
                 max="<?=date('Y-m-d')?>" required>
          <p class="form-hint">Quando é que esta despesa ocorreu realmente?</p>
        </div>

        <!-- Valor -->
        <div class="form-section">
          <label class="form-label" style="text-align:center;width:100%">Valor (€) *</label>
          <input type="number" name="amount" step="0.01" min="0" class="form-control amount-input"
                 placeholder="0.00" value="<?=htmlspecialchars($amount)?>" required>
        </div>

        <!-- Descrição -->
        <div class="form-section">
          <label class="form-label">Descrição *</label>
          <input type="text" name="description" class="form-control"
                 placeholder="Ex: Supermercado, Netflix, Gasolina..."
                 value="<?=htmlspecialchars($description)?>" required>
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
            <option value="">Nenhum / Dinheiro</option>
            <?php foreach($cards as $c): ?>
              <option value="<?=$c['id']?>" <?=($card_id==$c['id'])?'selected':''?>>
                <?=htmlspecialchars($c['name'])?> — Disponível: €<?=number_format($c['limit_amount']-$c['balance'],2)?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="form-hint">Deixa vazio se foi pago em dinheiro.</p>
        </div>

        <!-- Botões -->
        <div class="form-section" style="display:flex;gap:8px;justify-content:flex-end">
          <a href="transactions.php" class="btn-s">Cancelar</a>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> Registar Transação</button>
        </div>

      </form>
    </div>
  </div>

  <?php if (!empty($cards)): ?>
  <div class="settings-group">
    <p class="group-label">Os teus cartões</p>
    <div class="settings-card" style="padding:16px 20px">
      <?php foreach($cards as $c):
        $available = $c['limit_amount'] - $c['balance'];
        $pct = $c['limit_amount'] > 0 ? ($c['balance'] / $c['limit_amount']) * 100 : 0;
        $barColor = $pct >= 80 ? '#ef4444' : ($pct >= 60 ? '#f59e0b' : '#22c55e');
      ?>
      <div class="card-mini">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:13px;font-weight:600;color:var(--text-primary)"><?=htmlspecialchars($c['name'])?></span>
          <span style="font-size:12px;color:var(--text-secondary)"><?=round($pct)?>%</span>
        </div>
        <div class="card-bar"><div class="card-bar-fill" style="width:<?=min($pct,100)?>%;background:<?=$barColor?>"></div></div>
        <div style="font-size:12px;color:var(--text-secondary)">Disponível: <strong style="color:var(--text-primary)">€<?=number_format($available,2)?></strong></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="settings-group">
    <p class="group-label">Dicas</p>
    <div class="settings-card" style="padding:4px 20px">
      <div class="tip-row">
        <div class="tip-icon"><i class="bi bi-calendar-event" style="color:#3498db"></i></div>
        <div><div style="font-size:13px;font-weight:500;color:var(--text-primary)">Data da transação</div><div style="font-size:12px;color:var(--text-secondary)">Podes adicionar transações passadas escolhendo a data correta.</div></div>
      </div>
      <div class="tip-row">
        <div class="tip-icon"><i class="bi bi-lightbulb" style="color:#f59e0b"></i></div>
        <div><div style="font-size:13px;font-weight:500;color:var(--text-primary)">Organiza melhor</div><div style="font-size:12px;color:var(--text-secondary)">Usa categorias para analisar onde gastas mais dinheiro.</div></div>
      </div>
      <div class="tip-row">
        <div class="tip-icon"><i class="bi bi-credit-card" style="color:#22c55e"></i></div>
        <div><div style="font-size:13px;font-weight:500;color:var(--text-primary)">Associa ao cartão</div><div style="font-size:12px;color:var(--text-secondary)">O saldo do cartão é atualizado automaticamente.</div></div>
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