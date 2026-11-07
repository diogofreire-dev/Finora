<?php
// site/pages/bolsa.php — Bolsa: pesquisar títulos e acompanhar cotações (lista de acompanhamento)
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = (int) ($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/market.php';
$currentTheme = getUserTheme($pdo, $uid);

// ── Seguir / deixar de seguir (PRG) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sym    = market_symbol($_POST['symbol'] ?? '');
    $action = $_POST['action'] ?? '';
    $flash  = ['danger', t('Símbolo inválido.')];
    if ($sym && $action === 'follow') {
        $n = $pdo->prepare('SELECT COUNT(*) FROM watchlist WHERE user_id = :u');
        $n->execute([':u' => $uid]);
        if ((int) $n->fetchColumn() >= MARKET_WATCH_MAX) {
            $flash = ['danger', t('Já segues {n} títulos. Deixa de seguir algum primeiro.', ['n' => MARKET_WATCH_MAX])];
        } elseif (!market_quote($pdo, $uid, $sym)) {          // só se segue o que existe mesmo na fonte
            $flash = ['danger', t('Não encontrámos cotação para {symbol}.', ['symbol' => $sym])];
        } else {
            $pdo->prepare('INSERT IGNORE INTO watchlist (user_id, symbol) VALUES (:u, :s)')->execute([':u' => $uid, ':s' => $sym]);
            $flash = ['success', t('A seguir {symbol}.', ['symbol' => $sym])];
        }
    } elseif ($sym && $action === 'unfollow') {
        $pdo->prepare('DELETE FROM watchlist WHERE user_id = :u AND symbol = :s')->execute([':u' => $uid, ':s' => $sym]);
        $flash = ['success', t('Deixaste de seguir {symbol}.', ['symbol' => $sym])];
    }
    $_SESSION['flash_bolsa'] = $flash;
    $q = trim((string) ($_POST['q'] ?? ''));
    header('Location: bolsa.php' . ($q !== '' ? '?q=' . rawurlencode(mb_substr($q, 0, 40)) : ''));
    exit;
}
[$flashType, $flashText] = $_SESSION['flash_bolsa'] ?? ['', ''];
unset($_SESSION['flash_bolsa']);

// ── Pesquisa ────────────────────────────────────────────────────────────────
$q       = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 40);
$results = $q !== '' ? market_search($pdo, $uid, $q) : [];

// ── Lista de acompanhamento ────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT symbol FROM watchlist WHERE user_id = :u ORDER BY created_at');
$stmt->execute([':u' => $uid]);
$following = array_column($stmt->fetchAll(), 'symbol');
$watch = [];
foreach ($following as $sym) $watch[$sym] = market_quote($pdo, $uid, $sym);

$suggestions = ['VWCE.DE' => 'Vanguard FTSE All-World', 'EDP.LS' => 'EDP', 'GALP.LS' => 'Galp', 'AAPL' => 'Apple', 'BTC-EUR' => 'Bitcoin'];
function pct_txt(?float $v): string { return $v === null ? '—' : ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . fmt_num(abs($v), 2) . '%'; }
function price_txt(float $v, string $cur = 'EUR'): string { return fmt_price_in($v, $cur); }
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Bolsa - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=9">
  <style>
    .bk { max-width: 1120px; margin: 0 auto; padding-bottom: 56px; }
    .bk h1 { margin: 0 0 4px; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .bk-intro { margin: 0 0 20px; color: var(--fn-muted); font-size: 15px; }

    .bk-search { display: flex; gap: 10px; padding: 16px 20px; }
    .bk-search .form-control { min-height: 46px; border-radius: 12px; font-size: 15px; }
    .main-content .bk-search .btn-s { min-height: 46px; padding: 0 18px; }
    .bk-results { list-style: none; margin: 0; padding: 0 8px 10px; }
    .bk-results li { display: flex; align-items: center; gap: 14px; padding: 10px 12px; border-radius: 12px; }
    .bk-results li:hover { background: var(--fn-paper); }
    .bk-sym { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; font-weight: 700; padding: 3px 8px; border-radius: 7px; background: var(--fn-paper); color: var(--fn-text); white-space: nowrap; }
    .bk-what { flex: 1; min-width: 0; }
    .bk-what b { display: block; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bk-what small { color: var(--fn-muted); }
    .bk-results form, .bk-card form { margin: 0; }
    .bk-empty { padding: 26px 20px; color: var(--fn-muted); text-align: center; }

    .bk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; padding: 18px 20px 20px; }
    .bk-card { position: relative; border: 1px solid var(--fn-line); border-radius: 16px; padding: 16px 16px 12px; background: var(--fn-surface); display: flex; flex-direction: column; gap: 6px; }
    .bk-card .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .bk-card .nm { font-weight: 700; color: var(--fn-text); font-size: 15px; line-height: 1.25; }
    .bk-card .px { font-family: var(--fn-font-brand); font-weight: 700; font-size: 26px; letter-spacing: -0.5px; color: var(--fn-ink); line-height: 1.1; }
    [data-theme="dark"] .bk-card .px { color: #fff; }
    .bk-card .orig { font-size: 12.5px; color: var(--fn-muted); }
    .bk-card .bk-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .bk-card .when { font-size: 12px; color: var(--fn-muted); }
    .bk-card .acts { display: flex; gap: 6px; margin-top: 6px; }
    .main-content .bk-card .acts .btn-s { min-height: 34px; padding: 4px 10px; font-size: 13px; }
    .bk-x { width: 30px; height: 30px; border: 0; border-radius: 8px; background: transparent; color: var(--fn-muted); cursor: pointer; }
    .bk-x:hover { background: #fef3f2; color: #b42318; }
    .mk-chip { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 12.5px; font-weight: 700; background: var(--fn-paper); color: var(--fn-muted); }
    .mk-chip.up { background: #ecfdf3; color: #067647; } .mk-chip.down { background: #fef3f2; color: #b42318; }
    [data-theme="dark"] .mk-chip.up { background: rgba(117,224,167,.12); color: #75e0a7; } [data-theme="dark"] .mk-chip.down { background: rgba(253,162,155,.12); color: #fda29b; }
    .mk-spark { display: block; width: 100%; height: 44px; }
    .mk-spark polyline { stroke-width: 2; stroke: #12b76a; } .mk-spark.down polyline { stroke: #f04438; }
    .bk-sugg { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 12px; }
    .bk-sugg form { margin: 0; }
    .bk-note { margin: 4px 4px 0; font-size: 13px; color: var(--fn-muted); }
    @media (max-width: 520px) { .bk h1 { font-size: 26px; } .bk-search { flex-direction: column; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage = 'bolsa'; $sidebarPrefix = '../'; include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="bk">
  <h1><?= te('Bolsa') ?></h1>
  <p class="bk-intro"><?= te('Pesquisa ações, ETFs e cripto e segue as cotações. Para registar o que tens, usa os') ?> <a href="investments.php"><?= te('Investimentos') ?></a>.</p>

  <?php if ($flashText): ?>
    <div class="s-alert s-alert-<?= $flashType ?>" role="status"><i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i><span><?= htmlspecialchars($flashText) ?></span></div>
  <?php endif; ?>

  <section class="pg-card" style="--tone: var(--cat-1)" aria-labelledby="bkSearchT">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-search"></i></span><div><h2 id="bkSearchT"><?= te('Pesquisar') ?></h2><p><?= te('Pelo nome ou pelo símbolo: "galp", "vanguard", "AAPL", "bitcoin".') ?></p></div></header>
    <form class="bk-search" method="get" role="search">
      <label class="visually-hidden" for="bkQ"><?= te('Pesquisar título') ?></label>
      <input id="bkQ" type="search" name="q" class="form-control" maxlength="40" value="<?= htmlspecialchars($q) ?>" placeholder="<?= te('Ex: EDP') ?>" autocomplete="off">
      <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-search"></i> <?= te('Pesquisar') ?></button>
    </form>
    <?php if ($q !== ''): ?>
      <?php if (!$results): ?>
        <div class="bk-empty"><?= te('Sem resultados para "{q}". Experimenta outro nome ou o símbolo.', ['q' => $q]) ?></div>
      <?php else: ?>
        <ul class="bk-results">
          <?php foreach ($results as $r): $isF = in_array($r['symbol'], $following, true); ?>
            <li>
              <span class="bk-sym"><?= htmlspecialchars($r['symbol']) ?></span>
              <div class="bk-what"><b><?= htmlspecialchars($r['name']) ?></b><small><?= htmlspecialchars($r['type']) ?><?= $r['exchange'] ? ' · ' . htmlspecialchars($r['exchange']) : '' ?></small></div>
              <?php if ($isF): ?>
                <span class="mk-chip up"><i class="bi bi-check2"></i> <?= te('A seguir') ?></span>
              <?php else: ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="follow">
                  <input type="hidden" name="symbol" value="<?= htmlspecialchars($r['symbol']) ?>">
                  <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                  <button type="submit" class="btn-s"><i class="bi bi-plus-lg"></i> <?= te('Seguir') ?></button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="pg-card" style="--tone: var(--cat-3)" aria-labelledby="bkWatchT">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-eye"></i></span><div><h2 id="bkWatchT"><?= te('A seguir') ?></h2><p><?= te('{n} de {max} títulos · preços em euros', ['n' => count($following), 'max' => MARKET_WATCH_MAX]) ?></p></div></header>
    <?php if (!$following): ?>
      <div class="bk-empty">
        <?= te('Ainda não segues nenhum título. Experimenta um destes:') ?>
        <div class="bk-sugg">
          <?php foreach ($suggestions as $sym => $label): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="follow">
              <input type="hidden" name="symbol" value="<?= $sym ?>">
              <button type="submit" class="btn-s"><i class="bi bi-plus-lg"></i> <?= $label ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="bk-grid">
        <?php foreach ($watch as $sym => $w): ?>
          <article class="bk-card" aria-label="<?= htmlspecialchars($w['name'] ?? $sym) ?>">
            <div class="top">
              <div><span class="bk-sym"><?= htmlspecialchars($sym) ?></span><div class="nm" style="margin-top:6px"><?= htmlspecialchars($w['name'] ?? $sym) ?></div></div>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unfollow">
                <input type="hidden" name="symbol" value="<?= htmlspecialchars($sym) ?>">
                <button type="submit" class="bk-x" aria-label="<?= te('Deixar de seguir {symbol}', ['symbol' => $sym]) ?>" title="<?= te('Deixar de seguir') ?>"><i class="bi bi-x-lg"></i></button>
              </form>
            </div>
            <?php if ($w): ?>
              <div class="bk-row">
                <span class="px"><?= price_txt($w['price_eur']) ?></span>
                <?php $cp = $w['change_pct']; ?>
                <span class="mk-chip <?= $cp === null ? '' : ($cp > 0.005 ? 'up' : ($cp < -0.005 ? 'down' : '')) ?>"><?= pct_txt($cp) ?></span>
              </div>
              <?php if ($w['currency'] !== 'EUR'): ?><span class="orig"><?= price_txt($w['price'], $w['currency']) ?> <?= te('na moeda original') ?></span><?php endif; ?>
              <?= market_spark_svg($w['spark'], t('Evolução do último mês de {name}', ['name' => $w['name'] ?? $sym])) ?>
              <div class="bk-row">
                <span class="when"><?= $w['stale'] ? te('Sem ligação · último preço conhecido') : ($w['minutes'] < 1 ? te('Atualizado agora') : te('Há {n} min', ['n' => $w['minutes']])) ?></span>
                <a class="btn-s" href="investments.php?add=<?= rawurlencode($sym) ?>" style="min-height:32px;padding:3px 10px;font-size:12.5px"><i class="bi bi-plus-lg"></i> <?= te('À carteira') ?></a>
              </div>
            <?php else: ?>
              <span class="when"><?= te('Cotação indisponível de momento.') ?></span>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <p class="bk-note"><?= te('Cotações do Yahoo Finance (podem ter atraso) convertidas para euros com o câmbio do BCE. Informação, não aconselhamento financeiro.') ?></p>
</div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
