<?php
// site/add_card.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

require_once __DIR__ . '/../helpers/banks.php';

$errors = [];
$name    = '';
$limit   = 0;
$balance = 0;
$color   = 'purple';
$bank    = '';

$cardColors = [
    'purple' => ['name' => 'Roxo',     'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
    'blue'   => ['name' => 'Azul',     'gradient' => 'linear-gradient(135deg, #2196F3 0%, #1565C0 100%)'],
    'green'  => ['name' => 'Verde',    'gradient' => 'linear-gradient(135deg, #13d168 0%, #0f9c4e 100%)'],
    'orange' => ['name' => 'Laranja',  'gradient' => 'linear-gradient(135deg, #FF9800 0%, #E65100 100%)'],
    'red'    => ['name' => 'Vermelho', 'gradient' => 'linear-gradient(135deg, #f44336 0%, #c62828 100%)'],
    'pink'   => ['name' => 'Rosa',     'gradient' => 'linear-gradient(135deg, #E91E63 0%, #880E4F 100%)'],
    'teal'   => ['name' => 'Turquesa','gradient' => 'linear-gradient(135deg, #00BCD4 0%, #006064 100%)'],
    'indigo' => ['name' => 'Indigo',   'gradient' => 'linear-gradient(135deg, #3F51B5 0%, #1A237E 100%)'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $limit   = floatval($_POST['limit_amount'] ?? 0);
    $balance = floatval($_POST['balance'] ?? 0);
    $color   = $_POST['color'] ?? 'purple';
    $bank    = $_POST['bank'] ?? '';

    // Se banco conhecido, sobrepor cor
    if (!empty($bank) && isset($BANKS[$bank]) && $BANKS[$bank]['color']) {
        $color = $BANKS[$bank]['color'];
    }

    if (strlen($name) < 3)        { $errors[] = 'O nome do cartao deve ter pelo menos 3 caracteres.'; }
    if ($limit < 0)                { $errors[] = 'O limite nao pode ser negativo.'; }
    if ($balance < 0)              { $errors[] = 'O saldo nao pode ser negativo.'; }
    if ($balance > $limit)         { $errors[] = 'O saldo nao pode ser superior ao limite.'; }
    if (!array_key_exists($color, $cardColors)) { $color = 'purple'; }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO cards (user_id, name, limit_amount, balance, active, color, bank) VALUES (:uid, :name, :limit, :balance, 1, :color, :bank)");
            $stmt->execute([':uid' => $uid, ':name' => $name, ':limit' => $limit, ':balance' => $balance, ':color' => $color, ':bank' => $bank]);
            header('Location: cards.php?success=card_added');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao adicionar cartao. Tenta novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Adicionar Cartao - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary) !important; }
    .settings-wrapper { max-width: 860px; margin: 0 auto; padding: 0 16px 60px; }
    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--bg-secondary) !important; border: 1px solid var(--border-color) !important; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary) !important; }
    .page-header p  { font-size: 13px; color: var(--text-secondary) !important; margin: 0; }
    .settings-group { margin-bottom: 24px; }
    .group-label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-secondary) !important; margin: 0 0 8px 4px; }
    .settings-card { background: var(--bg-secondary) !important; border: 1px solid var(--border-color) !important; border-radius: 14px; overflow: hidden; }
    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color) !important; background: var(--bg-primary) !important; color: var(--text-primary) !important; cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary) !important; }
    .btn-s-primary { background: var(--fn-accent); color: #fff; border-color: var(--fn-accent); }
    .btn-s-primary:hover { background: var(--fn-accent-hover); color: #fff; border-color: var(--fn-accent-hover); }
    .s-alert { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
    .s-alert-danger { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3); color: #7f1d1d; }
    [data-theme="dark"] .s-alert-danger { color: #fca5a5; }
    .two-col { display: grid; grid-template-columns: 1fr 1.4fr; gap: 16px; align-items: start; }
    @media (max-width: 680px) { .two-col { grid-template-columns: 1fr; } }
    .card-preview-wrap { background: var(--bg-secondary) !important; border: 1px solid var(--border-color) !important; border-radius: 14px; padding: 20px; }
    .card-visual-lg { border-radius: 14px; padding: 20px 22px; color: #fff; min-height: 160px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; transition: background .3s; }
    .card-visual-lg::before { content: ''; position: absolute; top: -60%; right: -40%; width: 220%; height: 220%; background: radial-gradient(circle, rgba(255,255,255,.15) 0%, transparent 65%); pointer-events: none; }
    .cv-number { font-size: 14px; letter-spacing: 4px; font-weight: 600; opacity: .9; position: relative; margin-top: 10px; }
    .cv-name   { font-size: 13px; text-transform: uppercase; font-weight: 700; position: relative; }
    .cv-meta   { font-size: 11px; opacity: .85; position: relative; margin-top: 6px; display: flex; justify-content: space-between; }
    .usage-track { height: 5px; border-radius: 3px; background: var(--border-color); overflow: hidden; margin: 6px 0 4px; }
    .usage-bar   { height: 100%; border-radius: 3px; background: #22c55e; transition: width .4s, background .3s; }
    .usage-labels { display: flex; justify-content: space-between; font-size: 11px; color: var(--text-secondary) !important; }
    .form-section { padding: 16px 20px; }
    .form-section:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .m-form .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary) !important; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; display: block; }
    .m-form .form-control { font-size: 14px; border: 1px solid var(--border-color) !important; border-radius: 9px; padding: 9px 12px; background: var(--bg-primary) !important; color: var(--text-primary) !important; width: 100%; }
    .m-form .form-control:focus { border-color: var(--fn-accent); box-shadow: none; outline: none; }
    [data-theme="dark"] .m-form .form-control { background: var(--bg-primary) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
    .m-form .form-hint { font-size: 12px; color: var(--text-secondary) !important; margin-top: 5px; }
    input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; }
    input[type="number"] { -moz-appearance: textfield; }
    .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 400px) { .input-row { grid-template-columns: 1fr; } }
    .color-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 4px; }
    .color-opt { position: relative; aspect-ratio: 1; }
    .color-opt input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .color-swatch { width: 100%; height: 100%; border-radius: 10px; border: 2px solid transparent; cursor: pointer; transition: all .15s; display: block; outline: 2px solid transparent; outline-offset: 2px; }
    .color-opt input:checked + .color-swatch { outline-color: var(--fn-accent); }
    .color-swatch:hover { transform: scale(1.05); }
    .color-row-hidden { opacity: .4; pointer-events: none; }
    .bank-logo { position: absolute; top: 14px; right: 16px; font-size: 11px; font-weight: 800; letter-spacing: .5px; color: rgba(255,255,255,.9); background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3); padding: 3px 8px; border-radius: 5px; }
    .form-actions { display: flex; gap: 8px; padding: 16px 20px; }
    .form-actions .btn-s { flex: 1; justify-content: center; }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=6">
</head>
<body>
<div class="app-layout">
<?php $activePage = 'cards'; $sidebarPrefix = '../'; include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper is-form">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-plus-circle"></i></div>
    <div style="flex:1">
      <h2>Adicionar Cartao</h2>
      <p>Regista um novo cartao de credito ou debito</p>
    </div>
    <a href="cards.php" class="btn-s"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="s-alert s-alert-danger">
      <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
      <div><?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?></div>
    </div>
  <?php endif; ?>

  <div class="two-col">

    <div class="settings-group">
      <p class="group-label">Pre-visualizacao</p>
      <div class="card-preview-wrap">
        <div class="card-visual-lg" id="cardPreview" style="background:<?=$cardColors[$color]['gradient']?>">
            <!-- Watermark do banco -->
            <div id="bankWatermarkEl" style="position:absolute;right:10px;bottom:8px;width:40%;height:75%;opacity:0.13;display:none;align-items:flex-end;justify-content:flex-end;pointer-events:none;overflow:hidden;">
              <img id="bankWatermarkImg" src="" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;object-position:right bottom;" alt="">
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <i class="bi bi-credit-card" style="font-size:22px;position:relative"></i>
                <div id="bankLogoEl" class="bank-logo" style="display:none"></div>
              </div>
            <div class="cv-number">•••• •••• •••• ••••</div>
          </div>
          <div>
            <div class="cv-name" id="preview-name"><?=strtoupper(htmlspecialchars($name ?: 'NOME DO CARTAO'))?></div>
          </div>
          <div class="cv-meta">
            <span>Limite: <strong id="preview-limit"><?=fmt_eur($limit)?></strong></span>
            <span>Gasto: <strong id="preview-balance"><?=fmt_eur($balance)?></strong></span>
          </div>
        </div>
        <div style="margin-top:16px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-bottom:4px">
            <span>Utilizacao</span>
            <strong id="usage-pct" style="color:var(--text-primary)">0%</strong>
          </div>
          <div class="usage-track"><div class="usage-bar" id="usage-bar" style="width:0%"></div></div>
          <div class="usage-labels">
            <span id="usage-spent">€0.00 gasto</span>
            <span id="usage-limit-lbl">€0.00 limite</span>
          </div>
        </div>
      </div>
    </div>

    <div class="settings-group">
      <p class="group-label">Dados do cartao</p>
      <div class="settings-card">
        <form method="post" class="m-form"><?= csrf_field() ?>

          <div class="form-section">
            <label class="form-label">Banco</label>
            <select name="bank" id="bankSelect" class="form-control" style="background-color:var(--bg-primary);color:var(--text-primary);border-color:var(--border-color);">
              <?php foreach($BANKS as $bk => $bd): ?>
                <option value="<?=$bk?>"
                  data-gradient="<?=htmlspecialchars($bd['gradient'] ?? '')?>"
                  data-color="<?=htmlspecialchars($bd['color'] ?? '')?>"
                  data-label="<?=htmlspecialchars($bd['label'])?>"
                  <?=$bank===$bk?'selected':''?>>
                  <?=htmlspecialchars($bd['name'])?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint">A cor do cartao é aplicada automaticamente</p>
          </div>

          <div class="form-section">
            <label class="form-label">Nome do Cartao *</label>
            <input type="text" name="name" id="cardName" class="form-control"
                   placeholder="Ex: Cartão Principal, Poupança, Viagens..."
                   value="<?=htmlspecialchars($name)?>" required>
            <p class="form-hint">Da um nome descritivo ao teu cartao</p>
          </div>

          <div class="form-section">
            <label class="form-label">Cor</label>
            <div class="color-grid">
              <?php foreach($cardColors as $ck => $cd): ?>
                <div class="color-opt">
                  <input type="radio" name="color" id="color_<?=$ck?>" value="<?=$ck?>"
                         <?=$color===$ck?'checked':''?> data-gradient="<?=$cd['gradient']?>">
                  <label class="color-swatch" for="color_<?=$ck?>"
                         style="background:<?=$cd['gradient']?>" title="<?=$cd['name']?>"></label>
                </div>
              <?php endforeach; ?>
            </div>
            <p class="form-hint" style="margin-top:8px">Escolhe uma cor para identificar facilmente o cartao</p>
          </div>

          <div class="form-section">
            <div class="input-row">
              <div>
                <label class="form-label">Limite (€) *</label>
                <input type="number" name="limit_amount" id="cardLimit" class="form-control"
                       placeholder="0.00" step="0.01" min="0"
                       value="<?=htmlspecialchars($limit)?>" required>
                <p class="form-hint">Limite maximo de gastos</p>
              </div>
              <div>
                <label class="form-label">Gasto Inicial (€)</label>
                <input type="number" name="balance" id="cardBalance" class="form-control"
                       placeholder="0.00" step="0.01" min="0"
                       value="<?=htmlspecialchars($balance)?>">
                <p class="form-hint">Se ja tens gastos acumulados</p>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-s btn-s-primary">
              <i class="bi bi-plus-circle"></i> Adicionar Cartao
            </button>
            <a href="cards.php" class="btn-s">Cancelar</a>
          </div>

        </form>
      </div>
    </div>

  </div>

</div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const cardColors = <?=json_encode(array_map(fn($c) => $c['gradient'], $cardColors))?>;

// Logos base64 para o preview
const bankLogos = <?php
  $logos = [];
  $logoFiles = ['millennium'=>'millennium.svg','cgd'=>'cgd.svg','revolut'=>'revolut.svg','n26'=>'n26.svg','santander'=>'santander.svg','bpi'=>'bpi.svg','novobanco'=>'novobanco.svg','montepio'=>'montepio.svg','wise'=>'wise.svg','activobank'=>'activobank.svg','mbway'=>'mbway.svg','visa'=>'visa.svg','mastercard'=>'mastercard.svg'];
  foreach ($logoFiles as $key => $file) {
    $path = __DIR__ . '/../assets/banks/' . $file;
    if (file_exists($path)) {
      $svg = file_get_contents($path);
      $svg = preg_replace('/fill="(?!none)[^"]*"/', 'fill="white"', $svg);
      $svg = preg_replace('/stroke="(?!none|white)[^"]*"/', 'stroke="white"', $svg);
      $logos[$key] = 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
  }
  echo json_encode($logos);
?>;

document.getElementById('cardName').addEventListener('input', function() {
  document.getElementById('preview-name').textContent = (this.value || 'NOME DO CARTAO').toUpperCase();
});

const bankSelect      = document.getElementById('bankSelect');
const colorSection    = document.querySelector('.color-grid').closest('.form-section');
const bankLogoEl      = document.getElementById('bankLogoEl');
const bankWatermarkEl = document.getElementById('bankWatermarkEl');
const bankWatermarkImg= document.getElementById('bankWatermarkImg');

bankSelect.addEventListener('change', function() {
  const opt      = this.options[this.selectedIndex];
  const gradient = opt.dataset.gradient;
  const label    = opt.dataset.label;
  const colorKey = opt.dataset.color;
  const bankKey  = this.value;

  if (gradient) {
    document.getElementById('cardPreview').style.background = gradient;
    colorSection.classList.add('color-row-hidden');
    if (colorKey) {
      const radio = document.querySelector(`input[name="color"][value="${colorKey}"]`);
      if (radio) radio.checked = true;
    }
  } else {
    colorSection.classList.remove('color-row-hidden');
    const checked = document.querySelector('input[name="color"]:checked');
    if (checked) document.getElementById('cardPreview').style.background = cardColors[checked.value];
  }

  // Badge pequeno
  if (label) {
    bankLogoEl.textContent = label;
    bankLogoEl.style.display = 'block';
  } else {
    bankLogoEl.style.display = 'none';
  }

  // Watermark
  if (bankLogos[bankKey]) {
    bankWatermarkImg.src = bankLogos[bankKey];
    bankWatermarkEl.style.display = 'flex';
  } else {
    bankWatermarkEl.style.display = 'none';
    bankWatermarkImg.src = '';
  }
});

bankSelect.dispatchEvent(new Event('change'));

document.querySelectorAll('input[name="color"]').forEach(r => {
  r.addEventListener('change', function() {
    const opt = bankSelect.options[bankSelect.selectedIndex];
    if (!opt.dataset.gradient) {
      document.getElementById('cardPreview').style.background = cardColors[this.value];
    }
  });
});

document.getElementById('cardLimit').addEventListener('input', updatePreview);
document.getElementById('cardBalance').addEventListener('input', updatePreview);
function updatePreview() {
  const lim = parseFloat(document.getElementById('cardLimit').value) || 0;
  const bal = parseFloat(document.getElementById('cardBalance').value) || 0;
  document.getElementById('preview-limit').textContent = '€' + lim.toFixed(2);
  document.getElementById('preview-balance').textContent = '€' + bal.toFixed(2);
  document.getElementById('usage-spent').textContent = '€' + bal.toFixed(2) + ' gasto';
  document.getElementById('usage-limit-lbl').textContent = '€' + lim.toFixed(2) + ' limite';
  const pct = lim > 0 ? Math.min((bal / lim) * 100, 100) : 0;
  const bar = document.getElementById('usage-bar');
  bar.style.width = pct + '%';
  bar.style.background = pct >= 80 ? '#ef4444' : pct >= 60 ? '#f59e0b' : '#22c55e';
  document.getElementById('usage-pct').textContent = Math.round(pct) + '%';
}
updatePreview();
</script>
</body>
</html>