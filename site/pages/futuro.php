<?php
// site/pages/futuro.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

require_once __DIR__ . '/../helpers/savings.php';
// A tabela future_goals está no schema.sql (antes era criada aqui em cada visita)

// Data AAAA-MM-DD válida e no futuro, ou null
function goal_date(string $s): ?string {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s && $s > date('Y-m-d') && $s < '2100-01-01') ? $s : null;
}

$message = ''; $messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name          = trim($_POST['name'] ?? '');
        $targetAmount  = floatval($_POST['target_amount'] ?? 0);
        $monthlyIncome = floatval($_POST['monthly_income'] ?? 0);
        $targetDate    = goal_date((string) ($_POST['target_date'] ?? ''));
        $name          = mb_substr($name, 0, 120);
        if (mb_strlen($name) >= 2 && $targetAmount > 0 && $targetAmount <= SAVINGS_MAX && $monthlyIncome > 0 && $monthlyIncome <= SAVINGS_MAX && $targetDate) {
            try {
                $stmt = $pdo->prepare("INSERT INTO future_goals (user_id, name, target_amount, monthly_income, target_date) VALUES (:uid,:name,:amount,:income,:date)");
                $stmt->execute([':uid'=>$uid,':name'=>$name,':amount'=>$targetAmount,':income'=>$monthlyIncome,':date'=>$targetDate]);
                $message = t('Meta criada.'); $messageType = 'success';
            } catch (PDOException $e) { $message = t('Não foi possível criar a meta. Tenta novamente.'); $messageType = 'danger'; }
        } else { $message = t('Preenche todos os campos: nome, valor, rendimento e uma data no futuro.'); $messageType = 'danger'; }
    }
    if ($action === 'delete') {
        $gid = intval($_POST['goal_id'] ?? 0);
        $pdo->prepare("DELETE FROM future_goals WHERE id=:id AND user_id=:uid")->execute([':id'=>$gid,':uid'=>$uid]);
        $message = t('Meta apagada. O dinheiro que lá estava passou para as poupanças sem meta.'); $messageType = 'info';
    }
    if ($action === 'update') {
        $gid           = intval($_POST['goal_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $targetAmount  = floatval($_POST['target_amount'] ?? 0);
        $monthlyIncome = floatval($_POST['monthly_income'] ?? 0);
        $targetDate    = goal_date((string) ($_POST['target_date'] ?? ''));
        $name          = mb_substr($name, 0, 120);
        if ($gid && mb_strlen($name) >= 2 && $targetAmount > 0 && $targetAmount <= SAVINGS_MAX && $monthlyIncome > 0 && $monthlyIncome <= SAVINGS_MAX && $targetDate) {
            try {
                $stmt = $pdo->prepare("UPDATE future_goals SET name=:name, target_amount=:amount, monthly_income=:income, target_date=:date WHERE id=:id AND user_id=:uid");
                $stmt->execute([':name'=>$name,':amount'=>$targetAmount,':income'=>$monthlyIncome,':date'=>$targetDate,':id'=>$gid,':uid'=>$uid]);
                $message = t('Meta atualizada.'); $messageType = 'success';
            } catch (PDOException $e) { $message = t('Não foi possível atualizar a meta. Tenta novamente.'); $messageType = 'danger'; }
        } else { $message = t('Preenche todos os campos: nome, valor, rendimento e uma data no futuro.'); $messageType = 'danger'; }
    }
}

$stmt = $pdo->prepare("SELECT * FROM future_goals WHERE user_id=:uid ORDER BY target_date ASC");
$stmt->execute([':uid'=>$uid]); $goals = $stmt->fetchAll();
$savedByGoal = savings_by_goal($pdo, (int) $uid);
$todayYmd    = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();

$stmt = $pdo->prepare("
    SELECT YEAR(transaction_date) as y, MONTH(transaction_date) as m, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY y, m ORDER BY y DESC, m DESC
");
$stmt->execute([':uid'=>$uid]); $monthlySpending = $stmt->fetchAll();
$avgMonthlySpend = count($monthlySpending) > 0 ? array_sum(array_column($monthlySpending,'total')) / count($monthlySpending) : 0;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=YEAR(CURDATE()) AND MONTH(transaction_date)=MONTH(CURDATE())");
$stmt->execute([':uid'=>$uid]); $thisMonthSpend = floatval($stmt->fetchColumn());

$activePage='futuro'; $sidebarPrefix='../';

// ── Projeção do "eu do futuro" ───────────────────────────────────────────────
// Poupança por mês = rendimento − média de gastos dos últimos 6 meses.
function goal_projection(array $goal, float $avgMonthlySpend): array {
    $target       = (float) $goal['target_amount'];
    $monthsLeft   = max(1, (strtotime($goal['target_date']) - time()) / (30 * 24 * 3600));
    $perMonth     = (float) $goal['monthly_income'] - $avgMonthlySpend;
    $projected    = $perMonth * $monthsLeft;
    $neededMonth  = $target / $monthsLeft;
    $ratio        = $target > 0 ? $projected / $target : 0;

    if ($perMonth <= 0) {
        $tone = 'danger'; $title = t('Estás a gastar mais do que ganhas');
        $msg  = t('Com os hábitos de agora não sobra nada ao fim do mês. Para chegares à meta, precisas de reduzir os gastos em {amount} por mês.', ['amount' => fmt_money(abs($perMonth) + $neededMonth, 0)]);
    } elseif ($ratio >= 1.1) {
        $tone = 'success'; $title = t('Vais conseguir, e ainda sobra');
        $msg  = t('Se mantiveres este ritmo, chegas ao prazo com {amount}: mais {extra} do que precisas.', ['amount' => fmt_money($projected, 0), 'extra' => fmt_money($projected - $target, 0)]);
    } elseif ($ratio >= 0.85) {
        $tone = 'warning'; $title = t('Estás quase lá, mas sem margem');
        $msg  = t('Chegas com cerca de {amount}. Um mês com mais gastos pode deixar-te abaixo da meta.', ['amount' => fmt_money($projected, 0)]);
    } else {
        $tone = 'warning'; $title = t('Assim não chegas lá');
        $msg  = t('Ao ritmo atual juntas {amount} de {target}. Precisas de poupar mais {more} por mês.', ['amount' => fmt_money($projected, 0), 'target' => fmt_money($target, 0), 'more' => fmt_money(($target - $projected) / $monthsLeft, 0)]);
    }
    return compact('tone', 'title', 'msg', 'projected', 'perMonth', 'neededMonth', 'monthsLeft');
}

function months_left_label(string $date): string {
    $m = (int) round((strtotime($date) - time()) / (30 * 24 * 3600));
    if ($m <= 0) return t('prazo a terminar');
    return $m === 1 ? t('falta 1 mês') : t('faltam {n} meses', ['n' => $m]);
}
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Metas - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=9">
  <link rel="stylesheet" href="../css/piggy.css?v=3">
  <style>
    .gl { max-width: 1200px; margin: 0 auto; padding-bottom: 56px; }
    .gl-head h1 { margin: 0 0 6px; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .gl-intro { margin: 0 0 6px; font-size: 15px; color: var(--fn-muted); max-width: 64ch; }
    .gl-context { margin: 0 0 28px; font-size: 15px; color: var(--fn-muted); }
    .gl-context strong { color: var(--fn-text); }

    .gl-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 28px; align-items: start; }
    .gl-list { display: flex; flex-direction: column; gap: 20px; }

    .gl-tile { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; padding: 22px 22px 16px; }
    .gl-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .gl-name { margin: 0; font-size: 18px; font-weight: 700; color: var(--fn-text); }
    .gl-due { margin: 2px 0 0; font-size: 14px; color: var(--fn-muted); }
    .gl-icons { display: flex; gap: 2px; }
    .gl-icons form { margin: 0; }
    .gl-icons button { width: 34px; height: 34px; display: inline-grid; place-items: center; border: 0; border-radius: 9px; background: transparent; color: var(--fn-muted); cursor: pointer; }
    .gl-icons button:hover { background: var(--fn-paper); color: var(--fn-text); }
    .gl-icons .del:hover { background: #fef3f2; color: #b42318; }

    /* Mealheiro da meta: o que já lá está e onde devias estar hoje */
    .gl-saved { display: flex; gap: 18px; align-items: center; margin-top: 14px; padding: 14px 16px; border-radius: 16px; background: color-mix(in srgb, var(--fn-gold) 7%, var(--fn-paper)); }
    .gl-saved .info { flex: 1; min-width: 0; }
    .gl-saved .amt { font-family: var(--fn-font-brand); font-weight: 700; font-size: 26px; letter-spacing: -0.5px; line-height: 1.1; color: var(--fn-ink); }
    [data-theme="dark"] .gl-saved .amt { color: #fff; }
    .gl-saved .amt small { font-family: var(--fn-font-ui); font-size: 15px; font-weight: 500; color: var(--fn-muted); letter-spacing: 0; }
    .gl-status { display: inline-flex; align-items: center; gap: 6px; margin: 6px 0 4px; font-size: 13px; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
    .gl-status.ok   { background: #ecfdf3; color: #067647; }
    .gl-status.late { background: #fffaeb; color: #93370d; }
    .gl-status.done { background: var(--fn-gold-soft); color: #7a4e00; }
    [data-theme="dark"] .gl-status.ok   { background: rgba(117,224,167,.12); color: #75e0a7; }
    [data-theme="dark"] .gl-status.late { background: rgba(254,200,75,.12); color: #fedf89; }
    [data-theme="dark"] .gl-status.done { color: #f0b53a; }
    .gl-saved p { margin: 0 0 10px; font-size: 13.5px; color: var(--fn-muted); }
    .main-content .gl-saved .btn-s { min-height: 38px; }
    @media (max-width: 480px) { .gl-saved { flex-direction: column; text-align: center; } }

    .gl-proj-label { margin-top: 18px; font-size: 14px; color: var(--fn-muted); }
    .gl-proj { font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.5px; line-height: 1.1; color: var(--fn-ink); }
    .gl-proj small { font-family: var(--fn-font-ui); font-size: 16px; font-weight: 500; color: var(--fn-muted); letter-spacing: 0; }
    .gl-meter { height: 10px; border-radius: 5px; background: var(--fn-paper); overflow: hidden; margin: 12px 0 18px; }
    .gl-meter span { display: block; height: 100%; border-radius: 5px; }
    .gl-meter .success { background: #12b76a; }
    .gl-meter .warning { background: #d97706; }
    .gl-meter .danger  { background: #d92d20; }

    /* A voz do "eu do futuro" */
    .gl-future { border-radius: 14px; padding: 14px 16px; border-left: 4px solid; }
    .gl-future .who { font-size: 12px; font-weight: 700; letter-spacing: .02em; color: var(--fn-muted); margin-bottom: 4px; }
    .gl-future .t { font-weight: 700; font-size: 15px; margin-bottom: 2px; }
    .gl-future p { margin: 0; font-size: 14px; color: var(--fn-text); }
    .gl-future.success { background: #ecfdf3; border-color: #12b76a; } .gl-future.success .t { color: #067647; }
    .gl-future.warning { background: #fffaeb; border-color: #d97706; } .gl-future.warning .t { color: #93370d; }
    .gl-future.danger  { background: #fef3f2; border-color: #d92d20; } .gl-future.danger  .t { color: #b42318; }
    [data-theme="dark"] .gl-future.success { background: rgba(117,224,167,.08); } [data-theme="dark"] .gl-future.success .t { color: #75e0a7; }
    [data-theme="dark"] .gl-future.warning { background: rgba(254,200,75,.08); }  [data-theme="dark"] .gl-future.warning .t { color: #fedf89; }
    [data-theme="dark"] .gl-future.danger  { background: rgba(253,162,155,.08); } [data-theme="dark"] .gl-future.danger  .t { color: #fda29b; }

    .gl-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--fn-line); }
    .gl-stats div { font-size: 13px; color: var(--fn-muted); }
    .gl-stats strong { display: block; font-size: 16px; color: var(--fn-text); }

    /* Formulário */
    .gl-form { position: sticky; top: 20px; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; padding: 22px; }
    .gl-form h2 { margin: 0 0 4px; font-size: 18px; font-weight: 700; color: var(--fn-text); }
    .gl-form > p { margin: 0 0 18px; font-size: 14px; color: var(--fn-muted); }
    .gl-field { margin-bottom: 14px; }
    .gl-form .btn-s { width: 100%; justify-content: center; }

    .gl-empty { padding: 48px 24px; text-align: center; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; }
    .gl-empty-art { display: inline-grid; place-items: center; width: 72px; height: 72px; margin-bottom: 18px; border-radius: 20px; background: var(--fn-gold-soft); color: var(--fn-gold); font-size: 32px; }
    .gl-empty h2 { margin: 0 0 6px; font-size: 20px; font-weight: 700; color: var(--fn-text); }
    .gl-empty p { margin: 0 auto; max-width: 44ch; color: var(--fn-muted); font-size: 15px; }

    /* Editar: <dialog> nativo (fecha com Esc e gere o foco) */
    .gl-dialog { border: 1px solid var(--fn-line); border-radius: 18px; padding: 24px; width: min(480px, calc(100% - 32px)); background: var(--fn-surface); color: var(--fn-text); }
    .gl-dialog::backdrop { background: rgba(14, 20, 32, .45); }
    .gl-dialog h2 { margin: 0 0 16px; font-family: var(--fn-font-brand); font-size: 20px; font-weight: 700; color: var(--fn-ink); }
    .gl-dialog .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .gl-dialog .acts { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; }
    .gl-dialog .form-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .gl-dialog .form-control { min-height: 44px; border-radius: 10px; border: 1px solid var(--fn-line); background: var(--fn-surface); color: var(--fn-text); }

    @media (max-width: 960px) {
      .gl-layout { grid-template-columns: 1fr; }
      .gl-form { position: static; }
    }
    @media (max-width: 560px) {
      .gl-head h1 { font-size: 26px; }
      .gl-stats { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="gl">

  <div class="gl-head">
    <h1><?= te('Metas') ?></h1>
    <p class="gl-intro"><?= te('Diz quanto queres juntar e até quando. Com o teu rendimento e o que costumas gastar, a Finora mostra o que o teu eu do futuro vai ter nessa data.') ?></p>
  </div>
  <p class="gl-context">
    <?php if ($avgMonthlySpend > 0): ?>
      <?= t('Gastas em média {avg} por mês (últimos 6 meses) · este mês já vais em {month}.', ['avg' => '<strong>' . fmt_money($avgMonthlySpend) . '</strong>', 'month' => '<strong>' . fmt_money($thisMonthSpend) . '</strong>']) ?>
    <?php else: ?>
      <?= te('Ainda não tens gastos registados, por isso a projeção assume que poupas todo o rendimento. Regista as tuas transações para uma previsão realista.') ?>
    <?php endif; ?>
  </p>

  <?php if ($message): ?>
    <div class="s-alert s-alert-<?= $messageType === 'info' ? 'info' : $messageType ?>" role="status">
      <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'info-circle' ?>"></i>
      <span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <div class="gl-layout">
    <div class="gl-list">
      <?php if (empty($goals)): ?>
        <div class="gl-empty">
          <span class="gl-empty-art" aria-hidden="true"><i class="bi bi-flag"></i></span>
          <h2><?= te('Ainda não tens metas') ?></h2>
          <p><?= te('Cria a primeira ao lado: umas férias, um portátil novo, um fundo de emergência…') ?></p>
        </div>
      <?php endif; ?>

      <?php foreach ($goals as $g):
        $p   = goal_projection($g, (float) $avgMonthlySpend);
        $pct = $g['target_amount'] > 0 ? max(0, min(100, $p['projected'] / $g['target_amount'] * 100)) : 0;
        $dTs = strtotime($g['target_date']);
      ?>
        <article class="gl-tile" aria-labelledby="g<?= $g['id'] ?>">
          <div class="gl-top">
            <div>
              <h2 class="gl-name" id="g<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></h2>
              <p class="gl-due"><?= te('Até {date}', ['date' => fmt_date_long($dTs, true)]) ?> · <?= htmlspecialchars(months_left_label($g['target_date'])) ?></p>
            </div>
            <div class="gl-icons">
              <button type="button" class="js-edit" aria-label="<?= te('Editar {name}', ['name' => $g['name']]) ?>" title="<?= te('Editar') ?>"
                      data-id="<?= (int) $g['id'] ?>" data-name="<?= htmlspecialchars($g['name']) ?>"
                      data-amount="<?= htmlspecialchars($g['target_amount']) ?>" data-income="<?= htmlspecialchars($g['monthly_income']) ?>"
                      data-date="<?= htmlspecialchars($g['target_date']) ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Apagar esta meta? O dinheiro poupado nela passa para as poupanças sem meta.')) ?>)">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="goal_id" value="<?= (int) $g['id'] ?>">
                <button type="submit" class="del" aria-label="<?= te('Apagar {name}', ['name' => $g['name']]) ?>" title="<?= te('Apagar') ?>"><i class="bi bi-trash3"></i></button>
              </form>
            </div>
          </div>

          <?php
            $saved    = max($savedByGoal[(int) $g['id']] ?? 0, 0);
            $target   = (float) $g['target_amount'];
            $expected = goal_expected_today($g, $todayYmd);
            $savedPct = $target > 0 ? $saved / $target * 100 : 0;
            $markPct  = $target > 0 ? $expected / $target * 100 : null;
            $mLeft    = max(1, (strtotime($g['target_date']) - strtotime($todayYmd)) / (30 * 86400));
            if ($saved >= $target)        { $st = 'done'; $stTxt = t('Meta cumprida'); $stMsg = t('Já tens tudo o que precisavas. Parabéns!'); }
            elseif ($saved >= $expected)  { $st = 'ok';   $stTxt = t('No caminho');    $stMsg = t('Estás acima da linha de hoje. Faltam {amount}.', ['amount' => fmt_money($target - $saved, 0)]); }
            else                          { $st = 'late'; $stTxt = t('Atrasado');      $stMsg = t('Junta {amount} por mês para chegares a tempo.', ['amount' => fmt_money(($target - $saved) / $mLeft, 0)]); }
          ?>
          <div class="gl-saved">
            <?= piggy_html($savedPct, t('Mealheiro da meta {pct}% cheio; devias estar em {mark}%', ['pct' => round(min($savedPct, 100)), 'mark' => round(min((float) $markPct, 100))]), 'sm', $markPct) ?>
            <div class="info">
              <div class="amt"><?= fmt_money($saved, 0) ?> <small><?= te('de {amount} já poupados', ['amount' => fmt_money($target, 0)]) ?></small></div>
              <span class="gl-status <?= $st ?>"><i class="bi bi-<?= $st === 'late' ? 'exclamation-circle' : ($st === 'done' ? 'trophy' : 'check-circle') ?>"></i> <?= htmlspecialchars($stTxt) ?></span>
              <p><?= htmlspecialchars($stMsg) ?> <?php if ($st !== 'done'): ?><?= te('A linha tracejada mostra onde devias estar hoje ({amount}).', ['amount' => fmt_money($expected, 0)]) ?><?php endif; ?></p>
              <a class="btn-s btn-s-primary" href="savings.php?goal=<?= (int) $g['id'] ?>#juntar"><i class="bi bi-plus-lg"></i> <?= te('Juntar') ?></a>
            </div>
          </div>

          <div class="gl-proj-label"><?= te('Se continuares a gastar como agora, no prazo vais ter cerca de') ?></div>
          <div class="gl-proj"><?= fmt_money(max($p['projected'], 0), 0) ?> <small><?= te('de {amount}', ['amount' => fmt_money($g['target_amount'], 0)]) ?></small></div>
          <div class="gl-meter" role="img" aria-label="<?= te('Projeção: {pct}% da meta', ['pct' => round($pct)]) ?>"><span class="<?= $p['tone'] ?>" style="width:<?= round($pct, 1) ?>%"></span></div>

          <div class="gl-future <?= $p['tone'] ?>">
            <div class="who"><?= te('O teu eu do futuro diz') ?></div>
            <div class="t"><?= $p['title'] ?></div>
            <p><?= $p['msg'] ?></p>
          </div>

          <div class="gl-stats">
            <div><strong><?= fmt_money($g['target_amount'], 0) ?></strong><?= te('Meta') ?></div>
            <div><strong><?= fmt_money($p['perMonth'], 0) ?></strong><?= te('Poupas por mês agora') ?></div>
            <div><strong><?= fmt_money($p['neededMonth'], 0) ?></strong><?= te('Precisas por mês') ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <aside class="gl-form" aria-labelledby="newGoal">
      <h2 id="newGoal"><?= te('Nova meta') ?></h2>
      <p><?= te('A projeção usa o teu rendimento e a média de gastos dos últimos 6 meses.') ?></p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="gl-field">
          <label class="form-label" for="gName"><?= te('Nome') ?></label>
          <input id="gName" type="text" name="name" class="form-control" placeholder="<?= te('Ex: Férias de verão') ?>" required minlength="2" maxlength="120">
        </div>
        <div class="gl-field">
          <label class="form-label" for="gAmount"><?= te('Quanto queres juntar ({symbol})', ['symbol' => currency_symbol()]) ?></label>
          <input id="gAmount" type="number" name="target_amount" class="form-control" step="0.01" min="1" inputmode="decimal" placeholder="2000" required>
        </div>
        <div class="gl-field">
          <label class="form-label" for="gIncome"><?= te('Rendimento mensal líquido ({symbol})', ['symbol' => currency_symbol()]) ?></label>
          <input id="gIncome" type="number" name="monthly_income" class="form-control" step="0.01" min="1" inputmode="decimal" placeholder="1200" required>
          <div class="form-hint"><?= te('O que recebes por mês, já sem impostos.') ?></div>
        </div>
        <div class="gl-field">
          <label class="form-label" for="gDate"><?= te('Até quando') ?></label>
          <input id="gDate" type="date" name="target_date" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
        </div>
        <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-flag"></i> <?= te('Criar meta') ?></button>
      </form>
    </aside>
  </div>

</div><!-- /gl -->
</div><!-- /page-content -->
</main>
</div>

<dialog class="gl-dialog" id="editGoal" aria-labelledby="editGoalTitle">
  <h2 id="editGoalTitle"><?= te('Editar meta') ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="goal_id" id="edit_goal_id">
    <div class="mb-3">
      <label class="form-label" for="edit_name"><?= te('Nome') ?></label>
      <input type="text" name="name" id="edit_name" class="form-control" required minlength="2" maxlength="120">
    </div>
    <div class="row2 mb-3">
      <div>
        <label class="form-label" for="edit_target_amount"><?= te('Meta ({symbol})', ['symbol' => currency_symbol()]) ?></label>
        <input type="number" name="target_amount" id="edit_target_amount" class="form-control" step="0.01" min="1" required>
      </div>
      <div>
        <label class="form-label" for="edit_monthly_income"><?= te('Rendimento ({symbol}/mês)', ['symbol' => currency_symbol()]) ?></label>
        <input type="number" name="monthly_income" id="edit_monthly_income" class="form-control" step="0.01" min="1" required>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="edit_target_date"><?= te('Até quando') ?></label>
      <input type="date" name="target_date" id="edit_target_date" class="form-control" required>
    </div>
    <div class="acts">
      <button type="button" class="btn-s" onclick="this.closest('dialog').close()"><?= te('Cancelar') ?></button>
      <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check2"></i> <?= te('Guardar') ?></button>
    </div>
  </form>
</dialog>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Os dados vêm de atributos data-* (o browser trata do escape), não de strings JS montadas à mão
document.querySelectorAll('.js-edit').forEach(btn => btn.addEventListener('click', () => {
  const d = btn.dataset, dlg = document.getElementById('editGoal');
  document.getElementById('edit_goal_id').value        = d.id;
  document.getElementById('edit_name').value           = d.name;
  document.getElementById('edit_target_amount').value  = d.amount;
  document.getElementById('edit_monthly_income').value = d.income;
  document.getElementById('edit_target_date').value    = d.date;
  dlg.showModal();
}));
document.getElementById('editGoal').addEventListener('click', e => { if (e.target === e.currentTarget) e.currentTarget.close(); });
</script>
</body>
</html>
