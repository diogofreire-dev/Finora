<?php
// site/cards.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

$cardColors = [
    'purple' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'blue'   => 'linear-gradient(135deg, #2196F3 0%, #1565C0 100%)',
    'green'  => 'linear-gradient(135deg, #13d168 0%, #0f9c4e 100%)',
    'orange' => 'linear-gradient(135deg, #FF9800 0%, #E65100 100%)',
    'red'    => 'linear-gradient(135deg, #f44336 0%, #c62828 100%)',
    'pink'   => 'linear-gradient(135deg, #E91E63 0%, #880E4F 100%)',
    'teal'   => 'linear-gradient(135deg, #00BCD4 0%, #006064 100%)',
    'indigo' => 'linear-gradient(135deg, #3F51B5 0%, #1A237E 100%)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'toggle') {
    $cardId = intval($_POST['card_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE cards SET active = NOT active WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $cardId, ':uid' => $uid]);
        $message = 'Estado do cartão alterado com sucesso!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Erro ao executar a ação.';
        $messageType = 'danger';
    }
}

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color, c.limit_amount, c.balance, c.active, c.created_at,
           COALESCE(SUM(t.amount), 0) as total_spent,
           COUNT(t.id) as transaction_count
    FROM cards c
    LEFT JOIN transactions t ON t.card_id = c.id
    WHERE c.user_id = :uid
    GROUP BY c.id
    ORDER BY c.active DESC, c.created_at DESC
");
$stmt->execute([':uid' => $uid]);
$allCards = $stmt->fetchAll();

$activeCards   = array_filter($allCards, fn($c) => $c['active']);
$inactiveCards = array_filter($allCards, fn($c) => !$c['active']);

$activeTotalLimit   = array_sum(array_column(array_values($activeCards), 'limit_amount'));
$activeTotalBalance = array_sum(array_column(array_values($activeCards), 'balance'));
$activeAvailable    = $activeTotalLimit - $activeTotalBalance;

// ── Overdraft alerts: cartões activos com saldo negativo ─────────────────────
// "negativo" = balance > limit_amount  (balance é o gasto; limit é o tecto)
// OU balance negativo (caso em que se registou como crédito/reembolso)
$overdraftCards = [];
foreach ($activeCards as $c) {
    $available = $c['limit_amount'] - $c['balance'];
    if ($available < 0) {
        // Calcular há quanto tempo está negativo
        // Busca a transação mais antiga que empurrou o cartão para cima do limite
        $stmtOD = $pdo->prepare("
            SELECT MIN(transaction_date) as first_over
            FROM (
                SELECT transaction_date,
                       SUM(amount) OVER (ORDER BY transaction_date, id) as running_total
                FROM transactions
                WHERE card_id = :cid AND user_id = :uid
            ) sub
            WHERE running_total > :lim
        ");
        $stmtOD->execute([':cid' => $c['id'], ':uid' => $uid, ':lim' => $c['limit_amount']]);
        $row = $stmtOD->fetch();
        $firstOver = $row['first_over'] ?? null;
        $daysSince = 0;
        $since = '';
        if ($firstOver) {
            $diff = (new DateTime())->diff(new DateTime($firstOver));
            $daysSince = $diff->days;
            if ($daysSince === 0)      $since = 'desde hoje';
            elseif ($daysSince === 1)  $since = 'há 1 dia';
            elseif ($daysSince < 7)    $since = "há {$daysSince} dias";
            elseif ($daysSince < 30)   { $weeks = floor($daysSince/7); $since = "há {$weeks} semana".($weeks>1?'s':''); }
            else                       { $months = floor($daysSince/30); $since = "há {$months} mês".($months>1?' meses':''); }
        }
        $overdraftCards[] = array_merge($c, [
            'available' => $available,
            'days_since' => $daysSince,
            'since_label' => $since,
        ]);
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gerir Cartões - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary); }
    .settings-wrapper { max-width: 920px; margin: 0 auto; padding: 0 16px 60px; }

    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary); }
    .page-header p  { font-size: 13px; color: var(--text-secondary); margin: 0; }

    .settings-group { margin-bottom: 28px; }
    .group-label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-secondary); margin: 0 0 12px 2px; }

    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; }
    .btn-s-primary:hover { background: #2980b9; color: #fff; border-color: #2980b9; }

    .s-alert { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
    .s-alert-success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    [data-theme="dark"] .s-alert-success { color: #6ee7b7; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }

    /* ── Overdraft banner ── */
    .overdraft-banner { background: rgba(239,68,68,.08); border: 1.5px solid rgba(239,68,68,.35); border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
    .overdraft-banner-header { background: rgba(239,68,68,.14); padding: 12px 18px; display: flex; align-items: center; gap: 10px; }
    .overdraft-banner-header i { font-size: 18px; color: #dc2626; }
    .overdraft-banner-header span { font-size: 13px; font-weight: 700; color: #dc2626; }
    .overdraft-item { padding: 14px 18px; border-top: 1px solid rgba(239,68,68,.15); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .overdraft-item-name { font-size: 14px; font-weight: 600; color: var(--text-primary); }
    .overdraft-item-meta { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
    .overdraft-item-meta .warn { color: #dc2626; font-weight: 600; }
    .overdraft-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #dc2626; padding: 4px 10px; border-radius: 20px; }
    .overdraft-badge-pulse { animation: pulse-red 2s infinite; }
    @keyframes pulse-red { 0%,100%{opacity:1} 50%{opacity:.6} }
    .weekly-notice { font-size: 11px; color: var(--text-secondary); display: flex; align-items: center; gap: 5px; margin-top: 4px; }
    [data-theme="dark"] .overdraft-banner { background: rgba(239,68,68,.07); border-color: rgba(239,68,68,.25); }
    [data-theme="dark"] .overdraft-banner-header { background: rgba(239,68,68,.12); }

    .stats-bar { display: grid; grid-template-columns: repeat(3,1fr); gap: 1px; background: var(--border-color); border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
    .stat-cell { background: var(--bg-secondary); padding: 16px; text-align: center; }
    .stat-cell h4 { font-size: 20px; font-weight: 700; margin: 0 0 3px; }
    .stat-cell p  { font-size: 11px; color: var(--text-secondary); margin: 0; }

    .card-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    @media (max-width: 640px) { .card-grid { grid-template-columns: 1fr; } }

    .card-tile { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .2s, transform .2s; }
    .card-tile:hover { box-shadow: 0 6px 24px rgba(0,0,0,.1); transform: translateY(-2px); }
    .card-tile.inactive { opacity: .6; }
    .card-tile.inactive:hover { transform: none; box-shadow: none; }

    .card-visual { padding: 20px 22px; color: #fff; min-height: 160px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; }
    .card-visual::before { content: ''; position: absolute; top: -60%; right: -40%; width: 220%; height: 220%; background: radial-gradient(circle, rgba(255,255,255,.15) 0%, transparent 65%); pointer-events: none; }
    .cv-icon   { font-size: 22px; position: relative; }
    .cv-number { font-size: 14px; letter-spacing: 4px; font-weight: 600; opacity: .9; position: relative; margin-top: 8px; }
    .cv-name   { font-size: 13px; text-transform: uppercase; font-weight: 700; position: relative; }
    .cv-badge  { font-size: 10px; font-weight: 700; background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.35); color: #fff; padding: 3px 8px; border-radius: 5px; flex-shrink: 0; }
    .cv-badge-over { background: rgba(239,68,68,.7); border-color: rgba(239,68,68,.9); }
    .cv-meta   { font-size: 11px; opacity: .85; position: relative; display: flex; justify-content: space-between; margin-top: 6px; }

    .card-body-wrap { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 12px; }
    .usage-row { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
    .usage-pct { font-weight: 700; color: var(--text-primary); }
    .usage-track { height: 5px; border-radius: 3px; background: var(--bg-primary); overflow: hidden; }
    .usage-bar   { height: 100%; border-radius: 3px; }
    .usage-labels { display: flex; justify-content: space-between; font-size: 11px; color: var(--text-secondary); margin-top: 4px; }

    .mini-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--border-color); border-radius: 10px; overflow: hidden; }
    .mini-stat { background: var(--bg-primary); padding: 10px 12px; text-align: center; }
    .mini-stat .v { font-size: 14px; font-weight: 700; }
    .mini-stat .l { font-size: 11px; color: var(--text-secondary); margin-top: 1px; }

    .card-actions { display: flex; gap: 6px; }
    .card-actions .btn-s { flex: 1; justify-content: center; font-size: 12px; padding: 7px 10px; }
    .card-actions form { flex: 1; margin: 0; display: flex; }
    .card-actions form .btn-s { flex: 1; justify-content: center; font-size: 12px; padding: 7px 10px; }
    .card-date { font-size: 11px; color: var(--text-secondary); text-align: center; padding-top: 2px; }

    .settings-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; }
    .s-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 13px 18px; }
    .s-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }

    .empty-state { padding: 40px 20px; text-align: center; }
    .empty-state i { font-size: 48px; color: var(--border-color); display: block; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; color: var(--text-secondary); margin-bottom: 16px; }

    @media (max-width: 640px) { .stats-bar { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage = 'cards'; $sidebarPrefix = '../'; include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <!-- Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-wallet2"></i></div>
    <div style="flex:1">
      <h2>Os Meus Cartões</h2>
      <p>Gere os teus cartões e acompanha os limites</p>
    </div>
    <a href="add_card.php" class="btn-s btn-s-primary">
      <i class="bi bi-plus-circle"></i> Adicionar
    </a>
  </div>

  <!-- Alert -->
  <?php if ($message): ?>
    <div class="s-alert s-alert-<?=$messageType?>">
      <i class="bi bi-<?=$messageType==='success'?'check-circle':'exclamation-circle'?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?=htmlspecialchars($message)?></span>
    </div>
  <?php endif; ?>

  <!-- ── OVERDRAFT BANNERS ─────────────────────────────────────────────── -->
  <?php if (!empty($overdraftCards)): ?>
  <div class="overdraft-banner">
    <div class="overdraft-banner-header">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span>⚠️ <?=count($overdraftCards)?> cartão(ões) acima do limite — atenção necessária</span>
    </div>
    <?php foreach($overdraftCards as $oc):
      $excess = abs($oc['available']);
    ?>
    <div class="overdraft-item">
      <div>
        <div class="overdraft-item-name"><?=htmlspecialchars($oc['name'])?></div>
        <div class="overdraft-item-meta">
          Saldo: <strong style="color:#dc2626">€<?=number_format($oc['balance'],2)?></strong>
          &nbsp;/&nbsp;Limite: €<?=number_format($oc['limit_amount'],2)?>
          &nbsp;·&nbsp;<span class="warn">Excedido em €<?=number_format($excess,2)?></span>
          <?php if($oc['since_label']): ?>
            &nbsp;·&nbsp;<?=htmlspecialchars($oc['since_label'])?>
          <?php endif; ?>
        </div>
        <?php if($oc['days_since'] >= 7): ?>
        <div class="weekly-notice">
          <i class="bi bi-bell-fill" style="color:#f59e0b;font-size:10px"></i>
          Aviso semanal ativo — cartão acima do limite há <?=$oc['days_since']?> dias
        </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span class="overdraft-badge overdraft-badge-pulse">
          <i class="bi bi-arrow-up-circle-fill"></i>
          +€<?=number_format($excess,2)?>
        </span>
        <a href="edit_card.php?id=<?=$oc['id']?>" class="btn-s" style="font-size:12px;padding:6px 12px">
          <i class="bi bi-pencil"></i> Corrigir
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Weekly alert script (runs once on page load, simulates weekly push) -->
  <script>
  (function(){
    const overdrafts = <?=json_encode(array_values(array_map(fn($c)=>['name'=>$c['name'],'excess'=>abs($c['available']),'days'=>$c['days_since']],$overdraftCards)))?>;
    const KEY = 'finora_overdraft_notif_' + <?=$uid?>;
    const last = parseInt(localStorage.getItem(KEY) || '0');
    const now  = Date.now();
    const WEEK = 7 * 24 * 60 * 60 * 1000;
    if (now - last > WEEK && overdrafts.length > 0 && 'Notification' in window) {
      if (Notification.permission === 'granted') {
        overdrafts.forEach(o => {
          new Notification('⚠️ Finora — Cartão acima do limite', {
            body: `${o.name}: excedido em €${o.excess.toFixed(2)} há ${o.days} dias`,
            icon: '/favicon.ico'
          });
        });
        localStorage.setItem(KEY, now);
      } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(p => {
          if (p === 'granted') {
            overdrafts.forEach(o => {
              new Notification('⚠️ Finora — Cartão acima do limite', {
                body: `${o.name}: excedido em €${o.excess.toFixed(2)} há ${o.days} dias`,
                icon: '/favicon.ico'
              });
            });
            localStorage.setItem(KEY, now);
          }
        });
      }
    }
  })();
  </script>
  <?php endif; ?>

  <?php if (empty($allCards)): ?>
    <div class="settings-card">
      <div class="empty-state">
        <i class="bi bi-credit-card-2-front"></i>
        <p>Ainda não tens cartões registados.<br>Adiciona o teu primeiro cartão para começares a gerir as tuas finanças.</p>
        <a href="add_card.php" class="btn-s btn-s-primary"><i class="bi bi-plus-circle"></i> Adicionar Primeiro Cartão</a>
      </div>
    </div>

  <?php else: ?>

    <!-- ATIVOS -->
    <?php if (!empty($activeCards)): ?>
    <div class="settings-group">
      <p class="group-label">Resumo — cartões ativos</p>
      <div class="stats-bar">
        <div class="stat-cell">
          <h4 style="color:#3498db">€<?=number_format($activeTotalLimit,2)?></h4>
          <p>Limite Total</p>
        </div>
        <div class="stat-cell">
          <h4 style="color:#ef4444">€<?=number_format($activeTotalBalance,2)?></h4>
          <p>Gasto Atual</p>
        </div>
        <div class="stat-cell">
          <h4 style="color:<?=$activeAvailable<0?'#ef4444':'#22c55e'?>">€<?=number_format($activeAvailable,2)?></h4>
          <p>Disponível</p>
        </div>
      </div>
    </div>

    <div class="settings-group">
      <p class="group-label">Cartões ativos</p>
      <div class="card-grid">
        <?php foreach ($activeCards as $c):
          $pct       = $c['limit_amount'] > 0 ? ($c['balance'] / $c['limit_amount']) * 100 : 0;
          $barColor  = $pct >= 100 ? '#ef4444' : ($pct >= 80 ? '#f59e0b' : '#22c55e');
          $available = $c['limit_amount'] - $c['balance'];
          $gradient  = $cardColors[$c['color'] ?? 'purple'] ?? $cardColors['purple'];
          $isOver    = $available < 0;
        ?>
        <div class="card-tile<?=$isOver?' style="border-color:rgba(239,68,68,.4)!important"':''?>">
          <!-- Visual card -->
          <div class="card-visual" style="background:<?=$gradient?>">
            <div>
              <i class="bi bi-credit-card cv-icon"></i>
              <div class="cv-number">•••• •••• •••• ••••</div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:4px">
                <div class="cv-name"><?=htmlspecialchars($c['name'])?></div>
                <?php if($isOver): ?>
                  <span class="cv-badge cv-badge-over"><i class="bi bi-exclamation-triangle-fill"></i> EXCEDIDO</span>
                <?php else: ?>
                  <span class="cv-badge">ATIVO</span>
                <?php endif; ?>
              </div>
              <div class="cv-meta">
                <span>Limite: <strong>€<?=number_format($c['limit_amount'],2)?></strong></span>
                <span>Gasto: <strong>€<?=number_format($c['balance'],2)?></strong></span>
              </div>
            </div>
          </div>

          <!-- Body -->
          <div class="card-body-wrap">
            <!-- Usage -->
            <div>
              <div class="usage-row">
                <span>Utilização<?=$isOver?' <span style="color:#ef4444;font-size:10px">▲ EXCEDIDO</span>':''?></span>
                <span class="usage-pct" style="<?=$isOver?'color:#ef4444':''?>"><?=round($pct)?>%</span>
              </div>
              <div class="usage-track">
                <div class="usage-bar" style="width:<?=min($pct,100)?>%;background:<?=$barColor?>"></div>
              </div>
              <div class="usage-labels">
                <span>€<?=number_format($c['balance'],2)?> usado</span>
                <span>€<?=number_format($c['limit_amount'],2)?> limite</span>
              </div>
            </div>

            <!-- Mini stats -->
            <div class="mini-stats">
              <div class="mini-stat">
                <div class="v" style="color:<?=$available<0?'#ef4444':'#22c55e'?>">€<?=number_format($available,2)?></div>
                <div class="l"><?=$available<0?'Excedido':'Disponível'?></div>
              </div>
              <div class="mini-stat">
                <div class="v"><?=$c['transaction_count']?></div>
                <div class="l">Transações</div>
              </div>
            </div>

            <!-- Actions -->
            <div class="card-actions">
              <a href="edit_card.php?id=<?=$c['id']?>" class="btn-s"><i class="bi bi-pencil"></i> Editar</a>
              <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="card_id" value="<?=$c['id']?>">
                <input type="hidden" name="action" value="toggle">
                <button type="submit" class="btn-s"><i class="bi bi-pause-circle"></i> Desativar</button>
              </form>
            </div>

            <div class="card-date"><i class="bi bi-calendar3"></i> Criado em <?=date('d/m/Y', strtotime($c['created_at']))?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- INATIVOS -->
    <?php if (!empty($inactiveCards)): ?>
    <div class="settings-group">
      <p class="group-label">Cartões inativos <span style="font-size:10px;font-weight:400;color:var(--text-secondary)">(dados ocultos em análise e transações)</span></p>
      <div class="settings-card">
        <?php foreach ($inactiveCards as $c):
          $gradient = $cardColors[$c['color'] ?? 'purple'] ?? $cardColors['purple'];
        ?>
        <div class="s-row">
          <div style="display:flex;align-items:center;gap:12px;min-width:0">
            <div style="width:36px;height:36px;border-radius:10px;background:<?=$gradient?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;filter:grayscale(.5);opacity:.7">
              <i class="bi bi-credit-card" style="color:#fff;font-size:15px"></i>
            </div>
            <div style="min-width:0">
              <div style="font-size:14px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($c['name'])?></div>
              <div style="font-size:12px;color:var(--text-secondary)">€<?=number_format($c['balance'],2)?> / €<?=number_format($c['limit_amount'],2)?> &middot; <?=$c['transaction_count']?> transações</div>
            </div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <a href="edit_card.php?id=<?=$c['id']?>" class="btn-s" style="padding:5px 10px;font-size:12px"><i class="bi bi-pencil"></i></a>
            <form method="post" style="margin:0"><?= csrf_field() ?>
              <input type="hidden" name="card_id" value="<?=$c['id']?>">
              <input type="hidden" name="action" value="toggle">
              <button type="submit" class="btn-s" style="padding:5px 12px;font-size:12px"><i class="bi bi-play-circle"></i> Ativar</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <?php endif; ?>

</div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>