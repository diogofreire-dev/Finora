<?php
// site/pages/investments.php — carteira de investimentos registada à mão
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = (int) ($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

// Tipos: rótulo, slot de cor (--cat-N, paleta validada) e ícone
const INVESTMENT_TYPES = [
    'acoes'      => ['Ações',            1, 'graph-up'],
    'etf'        => ['ETF',              3, 'collection'],
    'cripto'     => ['Cripto',           4, 'currency-bitcoin'],
    'ppr'        => ['PPR',              6, 'umbrella'],
    'deposito'   => ['Depósito a prazo', 2, 'bank'],
    'obrigacoes' => ['Obrigações',       7, 'file-earmark-text'],
    'fundo'      => ['Fundo',            5, 'pie-chart'],
    'outro'      => ['Outro',            8, 'three-dots'],
];

// Aceita "1 234,56" ou "1234.56"; devolve null se não for um número válido
function parse_amount($raw): ?float {
    $s = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) $raw));
    if (substr_count($s, ',') === 1 && substr_count($s, '.') >= 1) $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float) $s : null;
}

// Valida os dados do formulário; devolve [dados, erro]
function investment_input(array $in): array {
    $name   = trim($in['name'] ?? '');
    $type   = $in['type'] ?? 'outro';
    $ticker = strtoupper(trim($in['ticker'] ?? ''));
    $qty    = parse_amount($in['quantity'] ?? '');
    $buy    = parse_amount($in['buy_price'] ?? '');
    $cur    = parse_amount($in['current_price'] ?? '');
    if ($cur === null && $buy !== null) $cur = $buy;     // sem preço atual: usa o de compra

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100)          return [null, 'O nome tem de ter entre 2 e 100 caracteres.'];
    if (!isset(INVESTMENT_TYPES[$type]))                          return [null, 'Escolhe um tipo válido.'];
    if ($ticker !== '' && !preg_match('/^[A-Z0-9.\-:]{1,20}$/', $ticker)) return [null, 'O símbolo só pode ter letras, números, ponto, hífen e dois pontos (máx. 20).'];
    if ($qty === null || $qty <= 0 || $qty > 1e10)                 return [null, 'A quantidade tem de ser maior que zero e até 10 mil milhões.'];
    if ($buy === null || $buy < 0 || $buy > 1e9)                   return [null, 'O preço de compra tem de ser um número igual ou maior que zero.'];
    if ($cur === null || $cur < 0 || $cur > 1e9)                   return [null, 'O preço atual tem de ser um número igual ou maior que zero.'];

    return [['name' => $name, 'type' => $type, 'ticker' => $ticker ?: null,
             'quantity' => $qty, 'buy_price' => $buy, 'current_price' => $cur], null];
}

// ── Ações (POST → guarda → redireciona: recarregar a página não repete a ação) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['investment_id'] ?? 0);
    $flash  = ['danger', 'Não foi possível guardar. Tenta novamente.'];
    try {
        if ($action === 'create' || $action === 'update') {
            [$d, $err] = investment_input($_POST);
            if ($err) {
                $flash = ['danger', $err];
            } elseif ($action === 'create') {
                $pdo->prepare("INSERT INTO investments (user_id, name, type, ticker, quantity, buy_price, current_price, price_updated_at)
                               VALUES (:uid, :name, :type, :ticker, :qty, :buy, :cur, NOW())")
                    ->execute([':uid' => $uid, ':name' => $d['name'], ':type' => $d['type'], ':ticker' => $d['ticker'],
                               ':qty' => $d['quantity'], ':buy' => $d['buy_price'], ':cur' => $d['current_price']]);
                $flash = ['success', 'Investimento adicionado.'];
            } else {
                $st = $pdo->prepare("UPDATE investments SET name=:name, type=:type, ticker=:ticker, quantity=:qty, buy_price=:buy,
                                            current_price=:cur, price_updated_at=NOW() WHERE id=:id AND user_id=:uid");
                $st->execute([':id' => $id, ':uid' => $uid, ':name' => $d['name'], ':type' => $d['type'], ':ticker' => $d['ticker'],
                              ':qty' => $d['quantity'], ':buy' => $d['buy_price'], ':cur' => $d['current_price']]);
                $flash = $st->rowCount() ? ['success', 'Investimento atualizado.'] : ['info', 'Nada para atualizar.'];
            }
        } elseif ($action === 'price') {
            $cur = parse_amount($_POST['current_price'] ?? '');
            if ($cur === null || $cur < 0 || $cur > 1e9) {
                $flash = ['danger', 'O preço atual tem de ser um número igual ou maior que zero.'];
            } else {
                $st = $pdo->prepare("UPDATE investments SET current_price=:cur, price_updated_at=NOW() WHERE id=:id AND user_id=:uid");
                $st->execute([':cur' => $cur, ':id' => $id, ':uid' => $uid]);
                $flash = $st->rowCount() ? ['success', 'Preço atualizado.'] : ['info', 'O preço já era esse.'];
            }
        } elseif ($action === 'delete') {
            $st = $pdo->prepare("DELETE FROM investments WHERE id=:id AND user_id=:uid");
            $st->execute([':id' => $id, ':uid' => $uid]);
            $flash = $st->rowCount() ? ['success', 'Investimento apagado.'] : ['danger', 'Investimento não encontrado.'];
        }
    } catch (PDOException $e) {
        error_log('[investments] ' . $e->getMessage());
    }
    $_SESSION['flash_investments'] = $flash;
    header('Location: investments.php');
    exit;
}

[$messageType, $message] = $_SESSION['flash_investments'] ?? ['', ''];
unset($_SESSION['flash_investments']);

// ── Dados ────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT *, quantity * current_price AS value, quantity * buy_price AS cost,
                              DATEDIFF(NOW(), price_updated_at) AS price_age
                       FROM investments WHERE user_id = :uid ORDER BY quantity * current_price DESC");
$stmt->execute([':uid' => $uid]);
$investments = $stmt->fetchAll();

$totalValue = array_sum(array_column($investments, 'value'));
$totalCost  = array_sum(array_column($investments, 'cost'));
$totalGain  = $totalValue - $totalCost;
$totalPct   = $totalCost > 0 ? $totalGain / $totalCost * 100 : 0;

$byType = [];
foreach ($investments as $i) $byType[$i['type']] = ($byType[$i['type']] ?? 0) + $i['value'];
arsort($byType);

function signed_eur(float $v): string { return ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . fmt_eur(abs($v)); }
function signed_pct(float $v): string { return ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . number_format(abs($v), 1, ',', '') . '%'; }
function fmt_price(float $v): string { return number_format($v, $v < 1 ? 4 : 2, ',', "\u{202F}") . "\u{00A0}€"; }
function fmt_qty(float $v): string { return rtrim(rtrim(number_format($v, 8, ',', "\u{202F}"), '0'), ','); }

$activePage = 'investments'; $sidebarPrefix = '../';
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Investimentos - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
  <link rel="stylesheet" href="../css/finora.css?v=6">
  <style>
    .iv { max-width: 1200px; margin: 0 auto; padding-bottom: 56px; }
    .iv-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
    .iv-head h1 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }

    .iv-hero { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 32px; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; padding: 26px 28px; margin-bottom: 24px; }
    .iv-label { font-size: 14px; color: var(--fn-muted); margin: 0; }
    .iv-total { margin: 2px 0 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: clamp(30px, 4vw, 44px); letter-spacing: -1px; color: var(--fn-ink); line-height: 1.1; }
    .iv-gain { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 15px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
    .iv-gain.up   { background: #ecfdf3; color: #067647; }
    .iv-gain.down { background: #fef3f2; color: #b42318; }
    .iv-gain.flat { background: var(--fn-paper); color: var(--fn-muted); }
    [data-theme="dark"] .iv-gain.up   { background: rgba(117,224,167,.12); color: #75e0a7; }
    [data-theme="dark"] .iv-gain.down { background: rgba(253,162,155,.12); color: #fda29b; }
    .iv-sub { margin: 10px 0 0; font-size: 14px; color: var(--fn-muted); }

    .iv-alloc-bar { display: flex; height: 14px; border-radius: 7px; overflow: hidden; gap: 2px; background: var(--fn-paper); margin: 10px 0 14px; }
    .iv-alloc-bar span { display: block; height: 100%; }
    .iv-alloc { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; }
    .iv-alloc li { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--fn-text); }
    .iv-alloc i { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .iv-alloc .pc { margin-left: auto; color: var(--fn-muted); }

    .iv-list { list-style: none; margin: 0; padding: 0; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; }
    .iv-item { display: grid; grid-template-columns: 44px minmax(0, 2fr) minmax(0, 1.2fr) minmax(0, 1.1fr) auto; gap: 16px; align-items: center; padding: 16px 18px; }
    .iv-item + .iv-item { border-top: 1px solid var(--fn-line); }
    .iv-ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 19px; }
    .iv-name { font-size: 15px; font-weight: 700; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .iv-meta { font-size: 13px; color: var(--fn-muted); }
    .iv-meta .tk { font-weight: 700; color: var(--fn-text); letter-spacing: .3px; }
    .iv-val { text-align: right; }
    .iv-val b { display: block; font-size: 16px; color: var(--fn-text); }
    .iv-delta { text-align: right; font-size: 14px; font-weight: 700; }
    .iv-delta small { display: block; font-weight: 500; font-size: 13px; }
    .iv-delta.up { color: #067647; } .iv-delta.down { color: #b42318; } .iv-delta.flat { color: var(--fn-muted); }
    [data-theme="dark"] .iv-delta.up { color: #75e0a7; } [data-theme="dark"] .iv-delta.down { color: #fda29b; }
    .iv-old { color: #b54708; }
    [data-theme="dark"] .iv-old { color: #fedf89; }

    .iv-actions { display: flex; gap: 2px; justify-content: flex-end; }
    .iv-actions form { margin: 0; }
    .iv-actions .ico { width: 34px; height: 34px; display: inline-grid; place-items: center; border-radius: 9px; border: 0; background: transparent; color: var(--fn-muted); cursor: pointer; }
    .iv-actions .ico:hover { background: var(--fn-paper); color: var(--fn-text); }
    .iv-actions .del:hover { background: #fef3f2; color: #b42318; }
    .main-content .iv-actions .btn-s { min-height: 34px; padding: 4px 10px; font-size: 13px; margin-right: 4px; }

    .iv-note { margin: 18px 4px 0; font-size: 13px; color: var(--fn-muted); }
    .iv-empty { padding: 56px 24px; text-align: center; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; }
    .iv-empty-art { display: inline-grid; place-items: center; width: 72px; height: 72px; margin-bottom: 18px; border-radius: 20px; background: var(--fn-gold-soft); color: var(--fn-gold); font-size: 32px; }
    .iv-empty h2 { margin: 0 0 6px; font-size: 20px; font-weight: 700; color: var(--fn-text); }
    .iv-empty p { margin: 0 auto 20px; max-width: 48ch; color: var(--fn-muted); font-size: 15px; }

    .iv-dialog { border: 1px solid var(--fn-line); border-radius: 18px; padding: 24px; width: min(520px, calc(100% - 32px)); background: var(--fn-surface); color: var(--fn-text); }
    .iv-dialog::backdrop { background: rgba(14, 20, 32, .45); }
    .iv-dialog h2 { margin: 0 0 16px; font-family: var(--fn-font-brand); font-size: 20px; font-weight: 700; color: var(--fn-ink); }
    .iv-dialog .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .iv-dialog .mb { margin-bottom: 14px; }
    .iv-dialog .form-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .iv-dialog .form-control, .iv-dialog .form-select { min-height: 44px; border-radius: 10px; border: 1px solid var(--fn-line); background-color: var(--fn-surface); color: var(--fn-text); }
    .iv-dialog .form-select { padding-right: 38px; }
    .iv-dialog .form-control::placeholder { color: var(--fn-muted); opacity: .75; }
    .iv-dialog .hint { font-size: 13px; color: var(--fn-muted); margin-top: 5px; }
    .iv-dialog .acts { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; }

    @media (max-width: 900px) {
      .iv-hero { grid-template-columns: 1fr; }
      .iv-item { grid-template-columns: 44px minmax(0, 1fr) auto; }
      .iv-item .iv-val, .iv-item .iv-delta { grid-column: 2 / 3; text-align: left; }
      .iv-item .iv-actions { grid-column: 3; grid-row: 1 / span 3; flex-direction: column; align-items: flex-end; }
    }
    @media (max-width: 560px) {
      .iv-head h1 { font-size: 26px; }
      .iv-alloc { grid-template-columns: 1fr; }
      .iv-dialog .row2 { grid-template-columns: 1fr; }
      /* Valor e ganho lado a lado, ações numa linha por baixo */
      .iv-item { grid-template-columns: 44px minmax(0, 1fr) minmax(0, 1fr); gap: 10px 12px; align-items: start; }
      .iv-item > div:nth-child(2) { grid-column: 2 / 4; }
      .iv-item .iv-val { grid-column: 2; }
      .iv-item .iv-delta { grid-column: 3; text-align: right; }
      .iv-item .iv-actions { grid-column: 2 / 4; grid-row: auto; flex-direction: row; align-items: center; justify-content: flex-start; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="iv">

  <div class="iv-head">
    <h1>Investimentos</h1>
    <button type="button" class="btn-s btn-s-primary js-new"><i class="bi bi-plus-lg"></i> Adicionar investimento</button>
  </div>

  <?php if ($message): ?>
    <div class="s-alert s-alert-<?= htmlspecialchars($messageType) ?>" role="status">
      <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($investments)): ?>
    <div class="iv-empty">
      <span class="iv-empty-art" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
      <h2>Ainda não registaste investimentos</h2>
      <p>Adiciona as tuas ações, ETFs, cripto, PPR ou depósitos com a quantidade e o preço de compra. A Finora mostra quanto valem hoje e quanto ganhaste ou perdeste.</p>
      <button type="button" class="btn-s btn-s-primary js-new"><i class="bi bi-plus-lg"></i> Adicionar o primeiro</button>
    </div>
  <?php else:
    $state = $totalGain > 0.005 ? 'up' : ($totalGain < -0.005 ? 'down' : 'flat'); ?>

    <section class="iv-hero" aria-label="Resumo da carteira">
      <div>
        <p class="iv-label">Valor atual da carteira</p>
        <p class="iv-total"><?= fmt_eur($totalValue) ?></p>
        <span class="iv-gain <?= $state ?>">
          <i class="bi bi-<?= $state === 'up' ? 'arrow-up-right' : ($state === 'down' ? 'arrow-down-right' : 'dash') ?>"></i>
          <?= signed_eur($totalGain) ?> (<?= signed_pct($totalPct) ?>)
        </span>
        <p class="iv-sub">Investiste <?= fmt_eur($totalCost) ?> em <?= count($investments) ?> posiç<?= count($investments) === 1 ? 'ão' : 'ões' ?>.</p>
      </div>
      <div>
        <p class="iv-label">Distribuição por tipo</p>
        <div class="iv-alloc-bar" role="img" aria-label="Distribuição da carteira por tipo de investimento">
          <?php foreach ($byType as $t => $v): if ($totalValue <= 0) break; ?>
            <span style="width:<?= round($v / $totalValue * 100, 2) ?>%;background:var(--cat-<?= INVESTMENT_TYPES[$t][1] ?>)"></span>
          <?php endforeach; ?>
        </div>
        <ul class="iv-alloc">
          <?php foreach ($byType as $t => $v): ?>
            <li><i style="background:var(--cat-<?= INVESTMENT_TYPES[$t][1] ?>)"></i><?= INVESTMENT_TYPES[$t][0] ?><span class="pc"><?= $totalValue > 0 ? round($v / $totalValue * 100) : 0 ?>%</span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <ul class="iv-list" aria-label="Posições">
      <?php foreach ($investments as $i):
        [$tLabel, $slot, $icon] = INVESTMENT_TYPES[$i['type']] ?? INVESTMENT_TYPES['outro'];
        $gain = $i['value'] - $i['cost'];
        $pct  = $i['cost'] > 0 ? $gain / $i['cost'] * 100 : 0;
        $st   = $gain > 0.005 ? 'up' : ($gain < -0.005 ? 'down' : 'flat');
        $age  = $i['price_age'] === null ? null : (int) $i['price_age'];
      ?>
        <li class="iv-item">
          <span class="iv-ico" style="background:color-mix(in srgb, var(--cat-<?= $slot ?>) 15%, transparent);color:var(--cat-<?= $slot ?>)"><i class="bi bi-<?= $icon ?>"></i></span>
          <div style="min-width:0">
            <div class="iv-name"><?= htmlspecialchars($i['name']) ?></div>
            <div class="iv-meta">
              <?php if ($i['ticker']): ?><span class="tk"><?= htmlspecialchars($i['ticker']) ?></span> · <?php endif; ?><?= $tLabel ?>
              · <?= fmt_qty((float) $i['quantity']) ?> × <?= fmt_price((float) $i['current_price']) ?>
            </div>
          </div>
          <div class="iv-val">
            <b><?= fmt_eur($i['value']) ?></b>
            <span class="iv-meta <?= $age !== null && $age > 30 ? 'iv-old' : '' ?>">
              <?= $age === null ? 'Preço por atualizar' : ($age === 0 ? 'Preço de hoje' : 'Preço de há ' . $age . ' dia' . ($age > 1 ? 's' : '')) ?>
            </span>
          </div>
          <div class="iv-delta <?= $st ?>">
            <?= signed_eur($gain) ?><small><?= signed_pct($pct) ?> · compra a <?= fmt_price((float) $i['buy_price']) ?></small>
          </div>
          <div class="iv-actions">
            <button type="button" class="btn-s js-price" aria-label="Atualizar preço de <?= htmlspecialchars($i['name']) ?>" data-id="<?= (int) $i['id'] ?>" data-name="<?= htmlspecialchars($i['name']) ?>" data-price="<?= htmlspecialchars((string) (float) $i['current_price']) ?>"><i class="bi bi-arrow-repeat"></i> Preço</button>
            <button type="button" class="ico js-edit" aria-label="Editar <?= htmlspecialchars($i['name']) ?>" title="Editar"
                    data-id="<?= (int) $i['id'] ?>" data-name="<?= htmlspecialchars($i['name']) ?>" data-type="<?= htmlspecialchars($i['type']) ?>"
                    data-ticker="<?= htmlspecialchars($i['ticker'] ?? '') ?>" data-quantity="<?= htmlspecialchars((string) (float) $i['quantity']) ?>"
                    data-buy="<?= htmlspecialchars((string) (float) $i['buy_price']) ?>" data-current="<?= htmlspecialchars((string) (float) $i['current_price']) ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" onsubmit="return confirm('Apagar este investimento? Não é possível desfazer.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="investment_id" value="<?= (int) $i['id'] ?>">
              <button type="submit" class="ico del" aria-label="Apagar <?= htmlspecialchars($i['name']) ?>" title="Apagar"><i class="bi bi-trash3"></i></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="iv-note">Os preços são introduzidos por ti; atualiza-os de vez em quando para os valores estarem certos. A Finora regista e calcula, mas não dá aconselhamento financeiro.</p>
  <?php endif; ?>

</div><!-- /iv -->
</div><!-- /page-content -->
</main>
</div>

<!-- Adicionar / editar -->
<dialog class="iv-dialog" id="ivForm" aria-labelledby="ivFormTitle">
  <h2 id="ivFormTitle">Adicionar investimento</h2>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="investment_id" value="">
    <div class="mb">
      <label class="form-label" for="ivName">Nome</label>
      <input id="ivName" type="text" name="name" class="form-control" required minlength="2" maxlength="100" placeholder="Ex: Vanguard FTSE All-World">
    </div>
    <div class="row2 mb">
      <div>
        <label class="form-label" for="ivType">Tipo</label>
        <select id="ivType" name="type" class="form-select">
          <?php foreach (INVESTMENT_TYPES as $k => [$label]): ?><option value="<?= $k ?>"><?= $label ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label" for="ivTicker">Símbolo <span style="font-weight:400;color:var(--fn-muted)">(opcional)</span></label>
        <input id="ivTicker" type="text" name="ticker" class="form-control" maxlength="20" placeholder="Ex: VWCE" autocapitalize="characters">
      </div>
    </div>
    <div class="mb">
      <label class="form-label" for="ivQty">Quantidade</label>
      <input id="ivQty" type="text" name="quantity" class="form-control" inputmode="decimal" required placeholder="Ex: 12 ou 0,05">
      <div class="hint">Unidades, ações ou moedas. Para um depósito, indica 1.</div>
    </div>
    <div class="row2 mb">
      <div>
        <label class="form-label" for="ivBuy">Preço médio de compra (€)</label>
        <input id="ivBuy" type="text" name="buy_price" class="form-control" inputmode="decimal" required placeholder="Ex: 105,40">
      </div>
      <div>
        <label class="form-label" for="ivCur">Preço atual (€)</label>
        <input id="ivCur" type="text" name="current_price" class="form-control" inputmode="decimal" placeholder="Igual ao de compra">
      </div>
    </div>
    <div class="acts">
      <button type="button" class="btn-s" onclick="this.closest('dialog').close()">Cancelar</button>
      <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check2"></i> <span>Adicionar</span></button>
    </div>
  </form>
</dialog>

<!-- Atualizar só o preço -->
<dialog class="iv-dialog" id="ivPrice" aria-labelledby="ivPriceTitle">
  <h2 id="ivPriceTitle">Atualizar preço</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="price">
    <input type="hidden" name="investment_id" value="">
    <div class="mb">
      <label class="form-label" for="ivNewPrice">Preço atual por unidade (€) de <strong class="js-pname"></strong></label>
      <input id="ivNewPrice" type="text" name="current_price" class="form-control" inputmode="decimal" required>
    </div>
    <div class="acts">
      <button type="button" class="btn-s" onclick="this.closest('dialog').close()">Cancelar</button>
      <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check2"></i> Guardar preço</button>
    </div>
  </form>
</dialog>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const formDlg = document.getElementById('ivForm'), f = formDlg.querySelector('form');
  const priceDlg = document.getElementById('ivPrice'), pf = priceDlg.querySelector('form');
  const toPt = n => String(n).replace('.', ',');

  document.querySelectorAll('.js-new').forEach(b => b.addEventListener('click', () => {
    f.reset(); f.elements.action.value = 'create'; f.elements.investment_id.value = '';
    document.getElementById('ivFormTitle').textContent = 'Adicionar investimento';
    f.querySelector('[type=submit] span').textContent = 'Adicionar';
    formDlg.showModal(); f.elements.name.focus();
  }));
  document.querySelectorAll('.js-edit').forEach(b => b.addEventListener('click', () => {
    const d = b.dataset;
    f.elements.action.value = 'update'; f.elements.investment_id.value = d.id;
    f.elements.name.value = d.name; f.elements.type.value = d.type; f.elements.ticker.value = d.ticker;
    f.elements.quantity.value = toPt(d.quantity); f.elements.buy_price.value = toPt(d.buy); f.elements.current_price.value = toPt(d.current);
    document.getElementById('ivFormTitle').textContent = 'Editar investimento';
    f.querySelector('[type=submit] span').textContent = 'Guardar';
    formDlg.showModal(); f.elements.name.focus();
  }));
  document.querySelectorAll('.js-price').forEach(b => b.addEventListener('click', () => {
    pf.elements.investment_id.value = b.dataset.id;
    pf.elements.current_price.value = toPt(b.dataset.price);
    priceDlg.querySelector('.js-pname').textContent = b.dataset.name;
    priceDlg.showModal(); pf.elements.current_price.select();
  }));
  [formDlg, priceDlg].forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));
})();
</script>
</body>
</html>
