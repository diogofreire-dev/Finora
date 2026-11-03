<?php
// site/pages/savings.php — Poupanças: o mealheiro e o registo do que pões de lado (ou tiras)
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = (int) ($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/savings.php';
$currentTheme = getUserTheme($pdo, $uid);

$todayYmd = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();   // relógio da BD

// Metas do utilizador (para o select e para validar que a meta é dele)
$stmt = $pdo->prepare('SELECT id, name, target_amount, target_date, created_at FROM future_goals WHERE user_id = :u ORDER BY target_date');
$stmt->execute([':u' => $uid]);
$goals = $stmt->fetchAll();
$goalIds = array_map('intval', array_column($goals, 'id'));

// ── Ações (PRG: depois de gravar, redireciona; recarregar não repete o movimento) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $flash  = ['danger', 'Não foi possível guardar. Tenta outra vez.'];

    if ($action === 'add') {
        $kind   = ($_POST['kind'] ?? 'in') === 'out' ? 'out' : 'in';
        $amount = round((float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0')), 2);
        $date   = (string) ($_POST['saved_on'] ?? '');
        $d      = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $goalId = (int) ($_POST['goal_id'] ?? 0);
        $goalId = in_array($goalId, $goalIds, true) ? $goalId : null;   // só metas do próprio utilizador
        $note   = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($_POST['note'] ?? ''))), 0, 120);

        $byGoal    = savings_by_goal($pdo, $uid);
        $available = $goalId ? ($byGoal[$goalId] ?? 0) : array_sum($byGoal);

        if ($amount <= 0 || $amount > SAVINGS_MAX) {
            $flash = ['danger', 'Escreve um valor entre 0,01 € e 1 000 000 €.'];
        } elseif (!$d || $d->format('Y-m-d') !== $date || $date > $todayYmd || $date < '2000-01-01') {
            $flash = ['danger', 'Escolhe uma data válida, até hoje.'];
        } elseif ($kind === 'out' && $amount > $available + 0.001) {
            $flash = ['danger', 'Só tens ' . fmt_eur(max($available, 0)) . ($goalId ? ' nessa meta.' : ' poupados.')];
        } else {
            $pdo->prepare('INSERT INTO savings (user_id, goal_id, amount, note, saved_on) VALUES (:u, :g, :a, :n, :d)')
                ->execute([':u' => $uid, ':g' => $goalId, ':a' => $kind === 'out' ? -$amount : $amount, ':n' => $note !== '' ? $note : null, ':d' => $date]);
            $flash = ['success', $kind === 'out' ? 'Registado: tiraste ' . fmt_eur($amount) . '.' : 'Boa! Puseste ' . fmt_eur($amount) . ' de lado.'];
        }
    } elseif ($action === 'delete') {
        $del = $pdo->prepare('DELETE FROM savings WHERE id = :id AND user_id = :u');
        $del->execute([':id' => (int) ($_POST['saving_id'] ?? 0), ':u' => $uid]);
        $flash = $del->rowCount() ? ['success', 'Movimento apagado.'] : ['danger', 'Esse movimento já não existe.'];
    }
    $_SESSION['flash_savings'] = $flash;
    header('Location: savings.php');
    exit;
}
[$flashType, $flashText] = $_SESSION['flash_savings'] ?? ['', ''];
unset($_SESSION['flash_savings']);

// ── Números ─────────────────────────────────────────────────────────────────
$byGoal = savings_by_goal($pdo, $uid);
$total  = array_sum($byGoal);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(amount, 0)), 0) AS put, COALESCE(SUM(LEAST(amount, 0)), 0) AS took
                       FROM savings WHERE user_id = :u AND saved_on >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$stmt->execute([':u' => $uid]);
$month = $stmt->fetch();

// Últimos 12 meses (líquido por mês) para as barras
$stmt = $pdo->prepare("SELECT DATE_FORMAT(saved_on, '%Y-%m') AS ym, SUM(amount) AS net FROM savings
                       WHERE user_id = :u AND saved_on >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                       GROUP BY ym");
$stmt->execute([':u' => $uid]);
$netByMonth = array_column($stmt->fetchAll(), 'net', 'ym');
$bars = [];
$cursor = new DateTimeImmutable(substr($todayYmd, 0, 7) . '-01');
for ($i = 11; $i >= 0; $i--) {
    $ym = $cursor->modify("-$i months")->format('Y-m');
    $bars[$ym] = (float) ($netByMonth[$ym] ?? 0);
}
$maxBar  = max(1, max(array_map('abs', $bars)));
$lastSix = array_slice($bars, -7, 6);          // 6 meses completos antes do atual
$avgSix  = $lastSix ? array_sum($lastSix) / count($lastSix) : 0;

// Movimentos (os 80 mais recentes), agrupados por mês
$stmt = $pdo->prepare('SELECT s.id, s.amount, s.note, s.saved_on, g.name AS goal_name FROM savings s
                       LEFT JOIN future_goals g ON g.id = s.goal_id
                       WHERE s.user_id = :u ORDER BY s.saved_on DESC, s.id DESC LIMIT 80');
$stmt->execute([':u' => $uid]);
$moves = [];
foreach ($stmt as $m) $moves[substr($m['saved_on'], 0, 7)][] = $m;

$milestone = savings_next_milestone(max($total, 0));
$fillPct   = $milestone > 0 ? max($total, 0) / $milestone * 100 : 0;
$monthName = fn(string $ym) => ucfirst(month_name_pt((int) substr($ym, 5, 2))) . ' ' . substr($ym, 0, 4);
$preGoal   = (int) ($_GET['goal'] ?? 0);
$preGoal   = in_array($preGoal, $goalIds, true) ? $preGoal : 0;
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Poupanças - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=7">
  <link rel="stylesheet" href="../css/piggy.css?v=1">
  <style>
    .sv { max-width: 1120px; margin: 0 auto; padding-bottom: 56px; }
    .sv h1 { margin: 0 0 18px; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }

    /* Mealheiro grande + números */
    .sv-hero {
      display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 28px; align-items: center;
      padding: 26px 30px; margin-bottom: 22px; border-radius: 22px; border: 1px solid var(--fn-line);
      background: radial-gradient(120% 140% at 0% 100%, color-mix(in srgb, var(--fn-gold) 14%, var(--fn-surface)), var(--fn-surface) 60%);
    }
    .sv-hero .piggy { margin: 0 auto; }
    .sv-label { font-size: 15px; color: var(--fn-muted); margin: 0; }
    .sv-total { margin: 2px 0 10px; font-family: var(--fn-font-brand); font-weight: 800; font-size: clamp(40px, 6vw, 60px); letter-spacing: -1.5px; line-height: 1; color: var(--fn-ink); }
    [data-theme="dark"] .sv-total { color: #fff; }
    .sv-milestone { margin: 0 0 18px; font-size: 15px; color: var(--fn-muted); }
    .sv-milestone strong { color: var(--fn-text); }
    .sv-stats { display: flex; gap: 26px; flex-wrap: wrap; margin-bottom: 22px; }
    .sv-stats div { font-size: 13px; color: var(--fn-muted); }
    .sv-stats strong { display: block; font-size: 19px; color: var(--fn-text); }
    .sv-stats .pos { color: #067647; } .sv-stats .neg { color: #b42318; }
    [data-theme="dark"] .sv-stats .pos { color: #75e0a7; } [data-theme="dark"] .sv-stats .neg { color: #fda29b; }
    .sv-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .main-content .sv-actions .btn-s { min-height: 46px; padding: 0 20px; font-size: 15px; }

    .sv-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 22px; align-items: start; }

    /* Movimentos */
    .sv-month { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 20px 6px; font-size: 14px; font-weight: 700; color: var(--fn-text); }
    .sv-month span { font-weight: 600; color: var(--fn-muted); }
    .sv-list { list-style: none; margin: 0; padding: 0 8px 8px; }
    .sv-list li { display: flex; align-items: center; gap: 14px; padding: 10px 12px; border-radius: 12px; }
    .sv-list li:hover { background: var(--fn-paper); }
    .sv-ico { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; flex-shrink: 0; font-size: 16px; color: #fff; background: #12b76a; }
    .sv-ico.out { background: #d92d20; }
    .sv-what { flex: 1; min-width: 0; }
    .sv-what b { display: block; font-size: 15px; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sv-what small { font-size: 13px; color: var(--fn-muted); }
    .sv-amt { font-weight: 700; white-space: nowrap; color: #067647; }
    .sv-amt.out { color: #b42318; }
    [data-theme="dark"] .sv-amt { color: #75e0a7; } [data-theme="dark"] .sv-amt.out { color: #fda29b; }
    .sv-list form { margin: 0; }
    .sv-del { width: 32px; height: 32px; border: 0; border-radius: 9px; background: transparent; color: var(--fn-muted); cursor: pointer; }
    .sv-del:hover { background: #fef3f2; color: #b42318; }
    .sv-empty { padding: 36px 24px; text-align: center; color: var(--fn-muted); }

    /* Lado: metas e últimos 12 meses */
    .sv-goal { display: block; padding: 12px 20px; text-decoration: none; color: inherit; }
    .sv-goal + .sv-goal { border-top: 1px solid var(--fn-line); }
    .sv-goal:hover { background: var(--fn-paper); }
    .sv-goal .row1 { display: flex; justify-content: space-between; gap: 10px; font-size: 14.5px; }
    .sv-goal .row1 b { color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sv-goal .row1 span { color: var(--fn-muted); white-space: nowrap; }
    .sv-goal .bar { height: 8px; border-radius: 4px; background: var(--fn-paper); margin-top: 8px; overflow: hidden; }
    .sv-goal .bar i { display: block; height: 100%; border-radius: 4px; background: var(--fn-gold); }
    .sv-bars { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 5px; align-items: end; height: 110px; padding: 16px 20px 6px; }
    .sv-bars .col { display: flex; flex-direction: column; justify-content: flex-end; height: 100%; }
    .sv-bars .b { border-radius: 4px 4px 2px 2px; background: var(--fn-gold); min-height: 2px; }
    .sv-bars .b.neg { background: #f04438; }
    .sv-bars .b.now { background: var(--fn-ink); }
    [data-theme="dark"] .sv-bars .b.now { background: #f3e7c8; }
    .sv-bars-lbl { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 5px; padding: 0 20px 16px; font-size: 11px; color: var(--fn-muted); text-align: center; }

    /* Formulário (<dialog> nativo) */
    .sv-dialog { border: 1px solid var(--fn-line); border-radius: 18px; padding: 24px; width: min(440px, calc(100% - 32px)); background: var(--fn-surface); color: var(--fn-text); }
    .sv-dialog::backdrop { background: rgba(14, 20, 32, .45); }
    .sv-dialog h2 { margin: 0 0 16px; font-family: var(--fn-font-brand); font-size: 21px; font-weight: 700; color: var(--fn-ink); }
    [data-theme="dark"] .sv-dialog h2 { color: #fff; }
    .sv-kind { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 4px; border-radius: 12px; background: var(--fn-paper); margin-bottom: 16px; }
    .sv-kind label { display: flex; justify-content: center; align-items: center; gap: 6px; min-height: 40px; border-radius: 9px; cursor: pointer; font-weight: 600; font-size: 14px; color: var(--fn-muted); }
    .sv-kind input { position: absolute; opacity: 0; pointer-events: none; }
    .sv-kind input:checked + label { background: var(--fn-surface); color: var(--fn-text); box-shadow: 0 1px 3px rgba(20, 33, 61, .15); }
    .sv-kind input:focus-visible + label { outline: 3px solid var(--fn-gold); }
    .sv-dialog .sv-field { margin-bottom: 14px; }
    .sv-dialog .row2 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; }
    .sv-dialog .form-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .sv-dialog .form-control, .sv-dialog .form-select { min-height: 44px; border-radius: 10px; }
    .sv-amount-input { font-family: var(--fn-font-brand); font-size: 24px !important; font-weight: 700; }
    .sv-dialog .acts { display: flex; gap: 8px; justify-content: flex-end; margin-top: 6px; }

    @media (max-width: 900px) {
      .sv-hero { grid-template-columns: minmax(0, 1fr); text-align: center; padding: 22px 18px; }
      .sv-hero .piggy-lg { width: min(70%, 240px); }
      .sv-stats, .sv-actions { justify-content: center; }
      .sv-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 520px) { .sv h1 { font-size: 26px; } .main-content .sv-actions .btn-s { flex: 1; justify-content: center; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage = 'savings'; $sidebarPrefix = '../'; include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="sv">
  <h1>Poupanças</h1>

  <?php if ($flashText): ?>
    <div class="s-alert s-alert-<?= $flashType ?>" role="status"><i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i><span><?= htmlspecialchars($flashText) ?></span></div>
  <?php endif; ?>

  <section class="sv-hero" aria-labelledby="svTotalLabel">
    <?= piggy_html($fillPct, 'Mealheiro ' . round($fillPct) . '% cheio até ao próximo marco de ' . fmt_eur($milestone, 0)) ?>
    <div>
      <p class="sv-label" id="svTotalLabel">Tens poupado</p>
      <p class="sv-total"><?= fmt_eur(max($total, 0)) ?></p>
      <p class="sv-milestone">
        <?php if ($total <= 0): ?>
          O mealheiro está vazio. O primeiro marco é <strong><?= fmt_eur($milestone, 0) ?></strong>.
        <?php else: ?>
          Faltam <strong><?= fmt_eur($milestone - $total) ?></strong> para o próximo marco: <?= fmt_eur($milestone, 0) ?>.
        <?php endif; ?>
      </p>
      <div class="sv-stats">
        <?php $net = (float) $month['put'] + (float) $month['took']; ?>
        <div><strong class="<?= $net >= 0 ? 'pos' : 'neg' ?>"><?= ($net >= 0 ? '+' : '−') . fmt_eur(abs($net)) ?></strong>este mês</div>
        <div><strong><?= fmt_eur($avgSix) ?></strong>média por mês (6 meses)</div>
        <div><strong><?= fmt_eur(max($byGoal[0] ?? 0, 0)) ?></strong>sem meta</div>
      </div>
      <div class="sv-actions">
        <button type="button" class="btn-s btn-s-primary" data-open="in"><i class="bi bi-plus-lg"></i> Pôr de lado</button>
        <button type="button" class="btn-s" data-open="out" <?= $total <= 0 ? 'disabled' : '' ?>><i class="bi bi-dash-lg"></i> Tirar</button>
      </div>
    </div>
  </section>

  <div class="sv-grid">
    <section class="pg-card" style="--tone: var(--cat-3)" aria-labelledby="svMoves">
      <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-list-ul"></i></span><div><h2 id="svMoves">Movimentos</h2><p>Tudo o que puseste de lado ou tiraste.</p></div></header>
      <?php if (!$moves): ?>
        <div class="sv-empty">Ainda não há movimentos. Começa com "Pôr de lado", mesmo que sejam 5 €.</div>
      <?php endif; ?>
      <?php foreach ($moves as $ym => $items): $sum = array_sum(array_column($items, 'amount')); ?>
        <div class="sv-month"><?= $monthName($ym) ?><span><?= ($sum >= 0 ? '+' : '−') . fmt_eur(abs($sum)) ?></span></div>
        <ul class="sv-list">
          <?php foreach ($items as $m): $out = $m['amount'] < 0; $ts = strtotime($m['saved_on']); ?>
            <li>
              <span class="sv-ico <?= $out ? 'out' : '' ?>" aria-hidden="true"><i class="bi bi-<?= $out ? 'arrow-up-right' : 'arrow-down-left' ?>"></i></span>
              <div class="sv-what">
                <b><?= htmlspecialchars($m['note'] ?? ($out ? 'Tirei das poupanças' : 'Pus de lado')) ?></b>
                <small><?= date('j', $ts) ?> <?= mb_substr(month_name_pt((int) date('n', $ts)), 0, 3) ?> · <?= $m['goal_name'] ? htmlspecialchars($m['goal_name']) : 'Sem meta' ?></small>
              </div>
              <span class="sv-amt <?= $out ? 'out' : '' ?>"><?= ($out ? '−' : '+') . fmt_eur(abs($m['amount'])) ?></span>
              <form method="post" onsubmit="return confirm('Apagar este movimento?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="saving_id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="sv-del" aria-label="Apagar movimento de <?= fmt_eur(abs($m['amount'])) ?>"><i class="bi bi-trash3"></i></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </section>

    <div>
      <section class="pg-card" style="--tone: var(--cat-4)" aria-labelledby="svGoals">
        <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-flag"></i></span><div class="pg-grow"><h2 id="svGoals">Por meta</h2></div><a href="futuro.php" class="btn-s">Metas</a></header>
        <?php if (!$goals): ?>
          <div class="sv-empty">Cria uma meta para separares as poupanças por objetivo.</div>
        <?php endif; ?>
        <?php foreach ($goals as $g): $saved = max($byGoal[(int) $g['id']] ?? 0, 0); $p = $g['target_amount'] > 0 ? min(100, $saved / $g['target_amount'] * 100) : 0; ?>
          <a class="sv-goal" href="futuro.php#g<?= (int) $g['id'] ?>">
            <div class="row1"><b><?= htmlspecialchars($g['name']) ?></b><span><?= fmt_eur($saved, 0) ?> de <?= fmt_eur($g['target_amount'], 0) ?></span></div>
            <div class="bar" role="img" aria-label="<?= round($p) ?>% da meta"><i style="width: <?= round($p, 1) ?>%"></i></div>
          </a>
        <?php endforeach; ?>
      </section>

      <section class="pg-card" style="--tone: var(--cat-1)" aria-labelledby="svYear">
        <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-bar-chart"></i></span><div><h2 id="svYear">Últimos 12 meses</h2><p>Quanto ficou no mealheiro em cada mês.</p></div></header>
        <div class="sv-bars" role="img" aria-label="Poupança líquida por mês nos últimos 12 meses">
          <?php $last = array_key_last($bars); foreach ($bars as $ym => $v): ?>
            <div class="col" title="<?= $monthName($ym) ?>: <?= fmt_eur($v) ?>"><span class="b <?= $v < 0 ? 'neg' : '' ?> <?= $ym === $last ? 'now' : '' ?>" style="height: <?= round(abs($v) / $maxBar * 100, 1) ?>%"></span></div>
          <?php endforeach; ?>
        </div>
        <div class="sv-bars-lbl" aria-hidden="true"><?php foreach (array_keys($bars) as $ym): ?><span><?= mb_substr(month_name_pt((int) substr($ym, 5, 2)), 0, 1) ?></span><?php endforeach; ?></div>
      </section>
    </div>
  </div>
</div>
</div>
</main>
</div>

<dialog class="sv-dialog" id="svDialog" aria-labelledby="svDialogTitle">
  <h2 id="svDialogTitle">Pôr de lado</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="sv-kind" role="radiogroup" aria-label="Tipo de movimento">
      <input type="radio" name="kind" id="kindIn" value="in" checked><label for="kindIn"><i class="bi bi-arrow-down-left"></i> Pôr de lado</label>
      <input type="radio" name="kind" id="kindOut" value="out"><label for="kindOut"><i class="bi bi-arrow-up-right"></i> Tirar</label>
    </div>
    <div class="sv-field">
      <label class="form-label" for="svAmount">Valor (€)</label>
      <input type="number" class="form-control sv-amount-input" id="svAmount" name="amount" step="0.01" min="0.01" max="1000000" inputmode="decimal" placeholder="0,00" required>
    </div>
    <div class="row2">
      <div class="sv-field">
        <label class="form-label" for="svDate">Data</label>
        <input type="date" class="form-control" id="svDate" name="saved_on" value="<?= $todayYmd ?>" max="<?= $todayYmd ?>" required>
      </div>
      <div class="sv-field">
        <label class="form-label" for="svGoal">Meta</label>
        <select class="form-select" id="svGoal" name="goal_id">
          <option value="0">Sem meta</option>
          <?php foreach ($goals as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $preGoal === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="sv-field">
      <label class="form-label" for="svNote">Nota <span class="text-muted fw-normal">(opcional)</span></label>
      <input type="text" class="form-control" id="svNote" name="note" maxlength="120" placeholder="Ex: sobrou do ordenado">
    </div>
    <div class="acts">
      <button type="button" class="btn-s" data-close>Cancelar</button>
      <button type="submit" class="btn-s btn-s-primary" id="svSubmit"><i class="bi bi-check2"></i> Guardar</button>
    </div>
  </form>
</dialog>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const dlg = document.getElementById('svDialog'), title = document.getElementById('svDialogTitle');
  const setKind = (k) => {
    document.getElementById(k === 'out' ? 'kindOut' : 'kindIn').checked = true;
    title.textContent = k === 'out' ? 'Tirar das poupanças' : 'Pôr de lado';
  };
  document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => {
    setKind(b.dataset.open); dlg.showModal(); document.getElementById('svAmount').focus();
  }));
  document.querySelectorAll('input[name="kind"]').forEach((r) => r.addEventListener('change', () => setKind(r.value)));
  dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
  // Vindo das Metas com "Juntar" (savings.php?goal=ID#juntar): abre logo o formulário
  if (location.hash === '#juntar') { setKind('in'); dlg.showModal(); document.getElementById('svAmount').focus(); }
})();
</script>
</body>
</html>
