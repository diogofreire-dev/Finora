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
// ── Ritmo: quanto do período já passou e quantos dias faltam ───────────────
function period_progress(string $period): array {
    $now = time();
    switch ($period) {
        case 'weekly':
            $day = (int) date('N', $now);                       // 1 (segunda) … 7 (domingo)
            return ['done' => $day / 7, 'left' => 7 - $day + 1, 'unit' => 'até domingo'];
        case 'yearly':
            $days = (int) date('L', $now) ? 366 : 365;
            $day  = (int) date('z', $now) + 1;
            return ['done' => $day / $days, 'left' => $days - $day + 1, 'unit' => 'até ao fim do ano'];
        default:
            $days = (int) date('t', $now);
            $day  = (int) date('j', $now);
            return ['done' => $day / $days, 'left' => $days - $day + 1, 'unit' => 'até ao fim do mês'];
    }
}

$activeB   = array_values(array_filter($budgets, fn($b) => $b['active']));
$inactiveB = array_values(array_filter($budgets, fn($b) => !$b['active']));

$counts = ['ok' => 0, 'warn' => 0, 'over' => 0];
foreach ($activeB as &$b) {
    $b['pct']   = $b['amount'] > 0 ? $b['current_spent'] / $b['amount'] * 100 : 0;
    $b['left']  = $b['amount'] - $b['current_spent'];
    $b['pace']  = period_progress($b['period']);
    $b['ahead'] = $b['pct'] > $b['pace']['done'] * 100 + 5;    // a gastar mais depressa do que o tempo passa
    $b['state'] = $b['left'] < 0 ? 'over' : (($b['pct'] >= 80 || $b['ahead']) ? 'warn' : 'ok');
    $counts[$b['state']]++;
}
unset($b);
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
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
  <link rel="stylesheet" href="../css/finora.css?v=5">
  <style>
    .bg { max-width: 1200px; margin: 0 auto; padding-bottom: 56px; }
    .bg-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 6px; }
    .bg-head h1 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .bg-summary { margin: 0 0 26px; font-size: 15px; color: var(--fn-muted); display: flex; gap: 18px; flex-wrap: wrap; }
    .bg-summary span { display: inline-flex; align-items: center; gap: 7px; }
    .bg-summary i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
    .bg-summary strong { color: var(--fn-text); }

    .bg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
    .bg-tile { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; padding: 20px 20px 14px; display: flex; flex-direction: column; }
    .bg-tile.over { border-color: #fda29b; }
    .bg-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .bg-name { margin: 0; font-size: 17px; font-weight: 700; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bg-scope { margin: 2px 0 0; font-size: 13px; color: var(--fn-muted); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .bg-scope .dot { width: 8px; height: 8px; border-radius: 50%; }
    .bg-scope .bg-cat { display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
    .bg-status { font-size: 12px; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap; }
    .bg-status.ok   { background: #ecfdf3; color: #067647; }
    .bg-status.warn { background: #fffaeb; color: #93370d; }
    .bg-status.over { background: #fef3f2; color: #b42318; }
    [data-theme="dark"] .bg-status.ok   { background: rgba(117,224,167,.12); color: #75e0a7; }
    [data-theme="dark"] .bg-status.warn { background: rgba(254,200,75,.12);  color: #fedf89; }
    [data-theme="dark"] .bg-status.over { background: rgba(253,162,155,.12); color: #fda29b; }

    .bg-left-label { margin-top: 18px; font-size: 13px; color: var(--fn-muted); }
    .bg-left { font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.5px; line-height: 1.1; color: var(--fn-ink); }
    .bg-left.over { color: #b42318; }
    [data-theme="dark"] .bg-left.over { color: #fda29b; }

    /* Barra com o marcador "onde devias estar" */
    .bg-meter { position: relative; height: 10px; border-radius: 5px; background: var(--fn-paper); margin: 14px 0 8px; }
    .bg-meter .fill { position: absolute; inset: 0 auto 0 0; border-radius: 5px; background: var(--fn-accent); }
    .bg-meter .fill.warn { background: #d97706; }
    .bg-meter .fill.over { background: #d92d20; }
    .bg-meter .pace { position: absolute; top: -4px; bottom: -4px; width: 2px; background: var(--fn-text); border-radius: 1px; opacity: .55; }
    .bg-line { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--fn-muted); }
    .bg-hint { margin: 12px 0 0; font-size: 14px; color: var(--fn-text); }
    .bg-hint.warn { color: #93370d; }
    .bg-hint.over { color: #b42318; }
    [data-theme="dark"] .bg-hint.warn { color: #fedf89; }
    [data-theme="dark"] .bg-hint.over { color: #fda29b; }

    .bg-actions { display: flex; gap: 2px; margin-top: 14px; padding-top: 10px; border-top: 1px solid var(--fn-line); }
    .bg-actions form { margin: 0; }
    .bg-actions a, .bg-actions button {
      display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 10px; border-radius: 9px;
      border: 0; background: transparent; color: var(--fn-muted); font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer;
    }
    .bg-actions a:hover, .bg-actions button:hover { background: var(--fn-paper); color: var(--fn-text); }
    .bg-actions .del { margin-left: auto; }
    .bg-actions .del:hover { background: #fef3f2; color: #b42318; }
    [data-theme="dark"] .bg-actions .del:hover { background: rgba(253,162,155,.1); color: #fda29b; }

    .bg-section-title { margin: 36px 0 12px; font-size: 17px; font-weight: 700; color: var(--fn-text); }
    .bg-section-title small { font-weight: 400; font-size: 14px; color: var(--fn-muted); }
    .bg-paused { list-style: none; margin: 0; padding: 0; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; }
    .bg-paused li { display: flex; align-items: center; gap: 12px; padding: 12px 18px; }
    .bg-paused li + li { border-top: 1px solid var(--fn-line); }
    .bg-paused .main { flex: 1; min-width: 0; }
    .bg-paused .nm { font-weight: 600; color: var(--fn-text); }
    .bg-paused .mt { font-size: 13px; color: var(--fn-muted); }
    .bg-paused form { margin: 0; }

    .bg-empty { padding: 56px 24px; text-align: center; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; }
    .bg-empty-art { width: 140px; height: 18px; margin: 0 auto 24px; border-radius: 9px; background: var(--fn-paper); position: relative; overflow: hidden; }
    .bg-empty-art::before { content: ''; position: absolute; inset: 0 45% 0 0; background: var(--fn-gold); border-radius: 9px; }
    .bg-empty-art::after { content: ''; position: absolute; top: 0; bottom: 0; left: 70%; width: 3px; background: var(--fn-ink); }
    .bg-empty h2 { margin: 0 0 6px; font-size: 20px; font-weight: 700; color: var(--fn-text); }
    .bg-empty p { margin: 0 auto 20px; max-width: 48ch; color: var(--fn-muted); font-size: 15px; }

    @media (max-width: 560px) {
      .bg-head h1 { font-size: 26px; }
      .bg-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='budgets'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="bg">

  <div class="bg-head">
    <h1>Orçamentos</h1>
    <a href="add_budget.php" class="btn-s btn-s-primary"><i class="bi bi-plus-lg"></i> Criar orçamento</a>
  </div>

  <?php if ($activeB): ?>
    <p class="bg-summary">
      <?php if ($counts['ok']): ?><span><i style="background:#12b76a"></i><strong><?= $counts['ok'] ?></strong> no bom caminho</span><?php endif; ?>
      <?php if ($counts['warn']): ?><span><i style="background:#d97706"></i><strong><?= $counts['warn'] ?></strong> a precisar de atenção</span><?php endif; ?>
      <?php if ($counts['over']): ?><span><i style="background:#d92d20"></i><strong><?= $counts['over'] ?></strong> ultrapassado<?= $counts['over'] > 1 ? 's' : '' ?></span><?php endif; ?>
    </p>
  <?php else: ?>
    <p class="bg-summary">&nbsp;</p>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="s-alert s-alert-<?= $messageType ?>" role="status">
      <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($budgets)): ?>
    <div class="bg-empty">
      <div class="bg-empty-art" aria-hidden="true"></div>
      <h2>Ainda não tens orçamentos</h2>
      <p>Define quanto queres gastar por mês, semana ou ano, no total ou numa categoria. A Finora mostra quanto ainda podes gastar e avisa-te se estiveres a gastar depressa demais.</p>
      <a href="add_budget.php" class="btn-s btn-s-primary"><i class="bi bi-plus-lg"></i> Criar o primeiro orçamento</a>
    </div>
  <?php else: ?>

    <?php if ($activeB): ?>
    <div class="bg-grid">
      <?php foreach ($activeB as $b):
        $state = $b['state'];
        $pace  = $b['pace'];
        $perDay = $b['left'] > 0 ? $b['left'] / max($pace['left'], 1) : 0;
      ?>
        <article class="bg-tile <?= $state === 'over' ? 'over' : '' ?>" aria-labelledby="b<?= $b['id'] ?>">
          <div class="bg-top">
            <div style="min-width:0">
              <h2 class="bg-name" id="b<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></h2>
              <p class="bg-scope">
                <span><?= $periodLabels[$b['period']] ?? 'Mensal' ?></span>·
                <?php if ($b['category']): ?><span class="bg-cat"><span class="dot" style="background:<?= category_color($b['category']) ?>"></span><?= htmlspecialchars($b['category']) ?></span><?php else: ?><span>Todas as categorias</span><?php endif; ?>
                <?php if ($b['card_name']): ?>·<span><?= htmlspecialchars($b['card_name']) ?></span><?php endif; ?>
              </p>
            </div>
            <span class="bg-status <?= $state ?>"><?= $state === 'over' ? 'Ultrapassado' : ($state === 'warn' ? 'Atenção' : 'No bom caminho') ?></span>
          </div>

          <div class="bg-left-label"><?= $b['left'] < 0 ? 'Passaste o orçamento em' : 'Ainda podes gastar' ?></div>
          <div class="bg-left <?= $b['left'] < 0 ? 'over' : '' ?>"><?= fmt_eur(abs($b['left'])) ?></div>

          <div class="bg-meter" role="img" aria-label="<?= round($b['pct']) ?>% gasto; já passou <?= round($pace['done'] * 100) ?>% do período">
            <span class="fill <?= $state === 'ok' ? '' : $state ?>" style="width:<?= min(round($b['pct'], 1), 100) ?>%"></span>
            <span class="pace" style="left:<?= round($pace['done'] * 100, 1) ?>%" title="Onde devias estar hoje"></span>
          </div>
          <div class="bg-line">
            <span><?= fmt_eur($b['current_spent']) ?> de <?= fmt_eur($b['amount']) ?></span>
            <span><?= round($b['pct']) ?>%</span>
          </div>

          <?php if ($b['left'] < 0): ?>
            <p class="bg-hint over">Ultrapassaste o limite deste período.</p>
          <?php elseif ($b['ahead']): ?>
            <p class="bg-hint warn">Estás a gastar mais depressa do que o previsto. Para não passares, gasta até <?= fmt_eur($perDay) ?> por dia <?= $pace['unit'] ?>.</p>
          <?php else: ?>
            <p class="bg-hint">Podes gastar cerca de <?= fmt_eur($perDay) ?> por dia <?= $pace['unit'] ?>.</p>
          <?php endif; ?>

          <div class="bg-actions">
            <a href="edit_budget.php?id=<?= $b['id'] ?>"><i class="bi bi-pencil"></i> Editar</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
              <button type="submit"><i class="bi bi-pause-circle"></i> Pausar</button>
            </form>
            <form method="post" class="del" onsubmit="return confirm('Apagar o orçamento «<?= htmlspecialchars(addslashes($b['name'])) ?>»? Não é possível desfazer.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
              <button type="submit" class="del" aria-label="Apagar <?= htmlspecialchars($b['name']) ?>"><i class="bi bi-trash3"></i></button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($inactiveB): ?>
      <h2 class="bg-section-title">Em pausa <small>· não geram alertas</small></h2>
      <ul class="bg-paused">
        <?php foreach ($inactiveB as $b): ?>
          <li>
            <div class="main">
              <div class="nm"><?= htmlspecialchars($b['name']) ?></div>
              <div class="mt"><?= fmt_eur($b['amount']) ?> · <?= $periodLabels[$b['period']] ?? 'Mensal' ?><?= $b['category'] ? ' · ' . htmlspecialchars($b['category']) : '' ?></div>
            </div>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
              <button type="submit" class="btn-s"><i class="bi bi-play-circle"></i> Retomar</button>
            </form>
            <form method="post" onsubmit="return confirm('Apagar o orçamento «<?= htmlspecialchars(addslashes($b['name'])) ?>»? Não é possível desfazer.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
              <button type="submit" class="btn-s btn-s-danger" aria-label="Apagar <?= htmlspecialchars($b['name']) ?>"><i class="bi bi-trash3"></i></button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

  <?php endif; ?>

</div><!-- /bg -->
</div><!-- /page-content -->
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
