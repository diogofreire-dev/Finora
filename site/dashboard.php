<?php
// site/dashboard.php
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers/budget_alert_helper.php';
require_once __DIR__ . '/helpers/banks.php';
require_once __DIR__ . '/helpers/bank_watermark.php';
require_once __DIR__ . '/components/notifications_panel.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$stmt = $pdo->prepare("SELECT notifications FROM user_settings WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$userSettings = $stmt->fetch();
$notificationsEnabled = $userSettings['notifications'] ?? 1;

if (!$uid) { header('Location: auth/login.php'); exit; }

if ($notificationsEnabled) {
    $userStmt = $pdo->prepare("SELECT email, username FROM users WHERE id = :uid");
    $userStmt->execute([':uid' => $uid]);
    $user = $userStmt->fetch();
    if ($user) { checkAndSendBudgetAlerts($pdo, $uid, $user['email'], $user['username']); }
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total_month FROM transactions WHERE user_id=:uid AND transaction_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')");
$stmt->execute([':uid'=>$uid]); $totalMonth = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total_last_month FROM transactions WHERE user_id=:uid AND transaction_date>=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-01') AND transaction_date<DATE_FORMAT(CURDATE(),'%Y-%m-01')");
$stmt->execute([':uid'=>$uid]); $totalLastMonth = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id=:uid AND transaction_date>=NOW()-INTERVAL 30 DAY");
$stmt->execute([':uid'=>$uid]); $count30 = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(category,'Sem Categoria') as category, SUM(amount) as total, COUNT(*) as count FROM transactions WHERE user_id=:uid AND transaction_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01') GROUP BY category ORDER BY total DESC");
$stmt->execute([':uid'=>$uid]); $categoryData = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT description, amount, category FROM transactions WHERE user_id=:uid AND transaction_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01') ORDER BY amount DESC LIMIT 1");
$stmt->execute([':uid'=>$uid]); $biggestExpense = $stmt->fetch();

$stmt = $pdo->prepare("SELECT t.*, c.name AS card_name FROM transactions t LEFT JOIN cards c ON c.id=t.card_id WHERE t.user_id=:uid ORDER BY t.created_at DESC LIMIT 8");
$stmt->execute([':uid'=>$uid]); $recent = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT c.id,c.name,c.color,c.bank,c.limit_amount,c.balance,c.active,c.created_at, COALESCE(SUM(t.amount),0) as total_spent,COUNT(t.id) as transaction_count FROM cards c LEFT JOIN transactions t ON t.card_id=c.id WHERE c.user_id=:uid GROUP BY c.id ORDER BY c.active DESC,c.created_at DESC");
$stmt->execute([':uid'=>$uid]); $cards = $stmt->fetchAll();

$cardColors = [
    'purple'=>'linear-gradient(135deg,#667eea 0%,#764ba2 100%)',
    'blue'  =>'linear-gradient(135deg,#2196F3 0%,#1565C0 100%)',
    'green' =>'linear-gradient(135deg,#13d168 0%,#0f9c4e 100%)',
    'orange'=>'linear-gradient(135deg,#FF9800 0%,#E65100 100%)',
    'red'   =>'linear-gradient(135deg,#f44336 0%,#c62828 100%)',
    'pink'  =>'linear-gradient(135deg,#E91E63 0%,#880E4F 100%)',
    'teal'  =>'linear-gradient(135deg,#00BCD4 0%,#006064 100%)',
    'indigo'=>'linear-gradient(135deg,#3F51B5 0%,#1A237E 100%)'
];

$alerts = [];
foreach ($cards as $card) {
    if ($card['limit_amount']>0) {
        $pct = ($card['balance']/$card['limit_amount'])*100;
        if ($pct>=80) $alerts[] = "Cartão {$card['name']} atingiu ".round($pct)."% do limite.";
    }
}

$stmt = $pdo->prepare("SELECT b.*, COALESCE(SUM(CASE WHEN b.period='monthly' AND t.transaction_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND t.transaction_date<=LAST_DAY(CURDATE()) THEN t.amount WHEN b.period='weekly' AND t.transaction_date>=DATE_SUB(CURDATE(),INTERVAL WEEKDAY(CURDATE()) DAY) AND t.transaction_date<=CURDATE() THEN t.amount WHEN b.period='yearly' AND YEAR(t.transaction_date)=YEAR(CURDATE()) THEN t.amount ELSE 0 END),0) as current_spent FROM budgets b LEFT JOIN transactions t ON t.user_id=b.user_id AND (b.category IS NULL OR t.category=b.category) AND (b.card_id IS NULL OR t.card_id=b.card_id) WHERE b.user_id=:uid AND b.active=1 GROUP BY b.id ORDER BY b.created_at DESC LIMIT 1");
$stmt->execute([':uid'=>$uid]); $mainBudget=$stmt->fetch();

$budgetPercentage=0; $budgetRemaining=0; $budgetAlert=null;
if ($mainBudget) {
    $budgetPercentage = ($mainBudget['current_spent']/$mainBudget['amount'])*100;
    $budgetRemaining  = $mainBudget['amount']-$mainBudget['current_spent'];
    if ($budgetPercentage>=100) $budgetAlert=['type'=>'danger','icon'=>'exclamation-triangle-fill','message'=>'Orçamento excedido! Já gastaste €'.number_format($mainBudget['current_spent']-$mainBudget['amount'],2).' acima do limite.'];
    elseif ($budgetPercentage>=80) $budgetAlert=['type'=>'warning','icon'=>'exclamation-circle-fill','message'=>'Atenção! Já usaste '.round($budgetPercentage).'% do teu orçamento mensal.'];
}

$categoryColors=['Compras'=>'#3498db','Alimentação'=>'#e74c3c','Transporte'=>'#f39c12','Saúde'=>'#1abc9c','Entretenimento'=>'#9b59b6','Educação'=>'#34495e','Casa'=>'#e67e22','Outros'=>'#95a5a6','Sem Categoria'=>'#bdc3c7'];

$stmt = $pdo->prepare("SELECT r.*, c.name as card_name, CASE WHEN r.due_date<CURDATE() THEN 'overdue' WHEN r.due_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 'upcoming' ELSE 'future' END as status, DATEDIFF(r.due_date,CURDATE()) as days_until FROM payment_reminders r LEFT JOIN cards c ON c.id=r.card_id WHERE r.user_id=:uid AND r.active=1 AND r.due_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) ORDER BY r.due_date ASC LIMIT 5");
$stmt->execute([':uid'=>$uid]); $upcomingReminders=$stmt->fetchAll();
$overdueReminders = array_filter($upcomingReminders, fn($r)=>$r['status']==='overdue');
$soonReminders    = array_filter($upcomingReminders, fn($r)=>$r['status']==='upcoming');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM budgets WHERE user_id=:uid AND active=1");
$stmt->execute([':uid'=>$uid]); $activeBudgetsCount=$stmt->fetchColumn();
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="css/theme.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary); }
    .settings-wrapper { max-width: 900px; margin: 0 auto; padding: 0 16px 60px; }

    /* ── Page header ── */
    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary); }
    .page-header p  { font-size: 13px; color: var(--text-secondary); margin: 0; }

    /* ── Groups & Cards ── */
    .settings-group { margin-bottom: 24px; }
    .group-label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-secondary); margin: 0 0 8px 4px; }
    .settings-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; }
    .s-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 20px; transition: background .15s; }
    .s-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .s-row:hover { background: var(--bg-hover); }

    /* ── Buttons ── */
    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; }
    .btn-s-primary:hover { background: #2980b9; color: #fff; border-color: #2980b9; }

    /* ── Alerts ── */
    .s-alert { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    .s-alert-warning { background: rgba(245,158,11,.1); border-color: rgba(245,158,11,.3); color: #78350f; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }
    [data-theme="dark"] .s-alert-warning { color: #fcd34d; }
    .s-alert ul { margin: 6px 0 0 4px; padding-left: 16px; }
    .s-alert ul li { margin-bottom: 2px; }
    .s-alert a { color: inherit; font-weight: 600; }

    /* ── Stats grid ── */
    .stats-row { display: grid; gap: 1px; background: var(--border-color); border-radius: 14px; overflow: hidden; }
    .stats-row-3 { grid-template-columns: repeat(3,1fr); }
    .stats-row-2 { grid-template-columns: repeat(2,1fr); }
    .stat-cell { background: var(--bg-secondary); padding: 18px 16px; }
    .stat-cell .lbl { font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; }
    .stat-cell .val { font-size: 26px; font-weight: 800; line-height: 1; color: var(--text-primary); }
    .stat-cell .sub { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }

    /* ── Category bars ── */
    .cat-bar-wrap { padding: 14px 20px; }
    .cat-bar-wrap:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .cat-bar-label { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
    .cat-bar-track { background: var(--bg-primary); border-radius: 10px; height: 8px; overflow: hidden; }
    .cat-bar { height: 100%; border-radius: 10px; transition: width 1s ease-out; width: 0; }

    /* ── Transaction rows ── */
    .tx-row { padding: 12px 20px; display: flex; align-items: center; gap: 12px; }
    .tx-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .tx-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }

    /* ── Card visual (credit card) ── */
    .card-visual { border-radius: 10px; padding: 14px 16px; color: #fff; min-height: 95px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; margin-bottom: 12px; }
    .card-visual::before { content: ''; position: absolute; top: -50%; right: -30%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,.12) 0%, transparent 70%); pointer-events: none; }
    .card-number { font-size: 13px; letter-spacing: 2px; font-weight: 600; position: relative; margin-top: 4px; }
    .card-name   { font-size: 10px; text-transform: uppercase; font-weight: 700; position: relative; }
    .card-progress-wrap { height: 5px; border-radius: 3px; background: var(--bg-primary); overflow: hidden; margin: 5px 0 4px; }
    .card-bar { height: 100%; border-radius: 3px; }

    /* ── Budget bar ── */
    .budget-progress { height: 6px; border-radius: 3px; background: var(--bg-primary); overflow: hidden; margin: 8px 0 6px; }
    .budget-bar { height: 100%; border-radius: 3px; transition: width 1s ease-out; width: 0; }

    /* ── Quick link banner ── */
    .quick-banner { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .quick-banner-info { display: flex; align-items: center; gap: 12px; }
    .quick-banner-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }

    /* ── Dashboard 2-col layout ── */
    .dash-grid { display: grid; grid-template-columns: 300px 1fr; gap: 20px; }
    @media (max-width: 860px) {
      .dash-grid { grid-template-columns: 1fr; }
      .stats-row-3 { grid-template-columns: 1fr; }
      .stats-row-2 { grid-template-columns: 1fr; }
    }
    @media (max-width: 500px) {
      .stat-cell .val { font-size: 22px; }
    }

    [data-theme="dark"] .text-muted { color: var(--text-secondary) !important; }
    [data-theme="dark"] .badge.bg-info { background: #3498db !important; color: #fff !important; }
    [data-theme="dark"] .badge.bg-success { background: #27ae60 !important; color: #fff !important; }
    [data-theme="dark"] .badge.bg-secondary { background: #7f8c8d !important; color: #fff !important; }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/components/notifications_panel.php'; ?>

<div class="app-layout">
<?php $activePage='dashboard'; $sidebarPrefix=''; include __DIR__.'/components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <!-- ── Page Header ── -->
  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-speedometer2"></i></div>
    <div style="flex:1">
      <h2>Olá, <?=htmlspecialchars($_SESSION['username'] ?? 'utilizador')?>! 👋</h2>
      <p>Visão geral das tuas finanças</p>
    </div>
    <a href="pages/create_transaction.php" class="btn-s btn-s-primary">
      <i class="bi bi-plus-circle"></i> Nova Transação
    </a>
  </div>

  <!-- ── System alerts ── -->
  <?php if ($notificationsEnabled && !empty($alerts)): ?>
    <div class="s-alert s-alert-warning" style="margin-bottom:12px">
      <i class="bi bi-exclamation-triangle" style="flex-shrink:0;margin-top:1px"></i>
      <div><strong>Alertas de limite:</strong>
        <ul><?php foreach($alerts as $a): ?><li><?=htmlspecialchars($a)?></li><?php endforeach; ?></ul>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($notificationsEnabled && !empty($overdueReminders)): ?>
    <div class="s-alert s-alert-danger" style="margin-bottom:12px">
      <i class="bi bi-exclamation-triangle" style="flex-shrink:0;margin-top:1px"></i>
      <div><strong>Pagamentos atrasados:</strong>
        <ul><?php foreach($overdueReminders as $r): ?><li><strong><?=htmlspecialchars($r['name'])?></strong> — €<?=number_format($r['amount'],2)?> (<?=abs($r['days_until'])?> dia(s) atrasado) <a href="pages/reminders.php">Ver</a></li><?php endforeach; ?></ul>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($notificationsEnabled && !empty($soonReminders)): ?>
    <div class="s-alert s-alert-warning" style="margin-bottom:12px">
      <i class="bi bi-clock-history" style="flex-shrink:0;margin-top:1px"></i>
      <div><strong>Vencimentos próximos:</strong>
        <ul><?php foreach($soonReminders as $r): ?><li><strong><?=htmlspecialchars($r['name'])?></strong> — €<?=number_format($r['amount'],2)?> (vence em <?=$r['days_until']?> dia(s)) <a href="pages/reminders.php">Gerir</a></li><?php endforeach; ?></ul>
      </div>
    </div>
  <?php endif; ?>

  <!-- ── Quick stats ── -->
  <div class="settings-group">
    <p class="group-label">Este mês</p>
    <div class="stats-row stats-row-3">
      <div class="stat-cell">
        <div class="lbl">Total gasto</div>
        <div class="val" style="color:#ef4444">€<?=number_format($totalMonth,2)?></div>
        <?php $diff=$totalMonth-$totalLastMonth; ?>
        <div class="sub"><?=$diff>=0?'+':''?>€<?=number_format(abs($diff),2)?> vs mês anterior</div>
      </div>
      <div class="stat-cell">
        <div class="lbl">Transações (30d)</div>
        <div class="val" style="color:#3498db"><?=intval($count30)?></div>
        <div class="sub">movimentos registados</div>
      </div>
      <div class="stat-cell">
        <div class="lbl">Cartões ativos</div>
        <div class="val" style="color:#22c55e"><?=count(array_filter($cards,fn($c)=>$c['active']))?></div>
        <div class="sub">de <?=count($cards)?> no total</div>
      </div>
    </div>
  </div>

  <!-- ── Main 2-col grid ── -->
  <div class="dash-grid">

    <!-- ════ LEFT COLUMN ════ -->
    <div>

      <!-- Cartões -->
      <div class="settings-group">
        <p class="group-label">Os teus cartões</p>
        <div class="settings-card">
          <?php if (empty($cards)): ?>
            <div style="padding:28px 20px;text-align:center">
              <i class="bi bi-credit-card-2-front" style="font-size:36px;color:var(--border-color);display:block;margin-bottom:10px"></i>
              <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">Ainda não tens cartões</p>
              <a href="pages/add_card.php" class="btn-s btn-s-primary" style="justify-content:center"><i class="bi bi-plus-circle"></i> Adicionar</a>
            </div>
          <?php else: ?>
            <?php $displayCards=array_slice($cards,0,2); $remaining=count($cards)-2;
            foreach ($displayCards as $c):
              $pct=$c['limit_amount']>0?($c['balance']/$c['limit_amount'])*100:0;
              $barColor=$pct>=80?'#ef4444':($pct>=60?'#f59e0b':'#22c55e');
              $available=$c['limit_amount']-$c['balance'];
              $bankKey  = $c['bank'] ?? '';
              $bankData = $BANKS[$bankKey] ?? null;
              $gradient = ($bankData && $bankData['gradient']) ? $bankData['gradient'] : ($cardColors[$c['color']??'purple']??$cardColors['purple']);
              $bankLabel = $bankData['label'] ?? '';
            ?>
            <div style="padding:14px 16px;<?= !$c['active']?'opacity:.6':''?>">
              <div class="card-visual" style="background:<?=$gradient?>">
                <?= bankWatermark($bankKey) ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                  <i class="bi bi-credit-card" style="font-size:16px"></i>
                  <?php if ($bankLabel): ?>
                    <span style="font-size:10px;font-weight:800;letter-spacing:.5px;color:rgba(255,255,255,.9);background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);padding:2px 7px;border-radius:4px"><?=htmlspecialchars($bankLabel)?></span>
                  <?php endif; ?>
                </div>
                <div class="card-number">•••• •••• •••• ••••</div>
                <div style="display:flex;justify-content:space-between;align-items:flex-end">
                  <div class="card-name"><?=htmlspecialchars($c['name'])?></div>
                  <span style="font-size:9px;font-weight:700;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;padding:1px 7px;border-radius:4px"><?=$c['active']?'ATIVO':'INATIVO'?></span>
                </div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary);margin-bottom:3px">
                <span>Utilização</span><span style="font-weight:600;color:var(--text-primary)"><?=round($pct)?>%</span>
              </div>
              <div class="card-progress-wrap">
                <div class="card-bar" style="width:<?=min($pct,100)?>%;background:<?=$barColor?>"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary)">
                <span>€<?=number_format($c['balance'],2)?> / €<?=number_format($c['limit_amount'],2)?></span>
                <span style="color:#22c55e;font-weight:600">€<?=number_format($available,2)?> livre</span>
              </div>
            </div>
            <?php if(!($c===end($displayCards)||$remaining>0)): ?><hr style="margin:0;border-color:var(--border-color)"><?php endif; ?>
            <?php endforeach; ?>
            <?php if ($remaining>0): ?>
              <div style="padding:10px 16px;text-align:center;border-top:1px solid var(--border-color)">
                <span style="font-size:12px;color:var(--text-secondary)">+<?=$remaining?> cartão<?=$remaining>1?'ões':'ão'?></span>
              </div>
            <?php endif; ?>
            <div style="padding:12px 16px;border-top:1px solid var(--border-color)">
              <a href="pages/cards.php" class="btn-s" style="width:100%;justify-content:center"><i class="bi bi-grid"></i> Gerir todos os cartões</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Acções rápidas -->
      <div class="settings-group">
        <p class="group-label">Acções rápidas</p>
        <div class="settings-card">
          <div class="s-row">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:9px;background:rgba(52,152,219,.1);border:1px solid rgba(52,152,219,.2);display:flex;align-items:center;justify-content:center;color:#3498db;font-size:15px"><i class="bi bi-plus-circle"></i></div>
              <div>
                <div style="font-size:13px;font-weight:500;color:var(--text-primary)">Nova transação</div>
                <div style="font-size:11px;color:var(--text-secondary)">Registar um novo gasto</div>
              </div>
            </div>
            <a href="pages/create_transaction.php" class="btn-s" style="padding:5px 12px;font-size:12px"><i class="bi bi-arrow-right"></i></a>
          </div>
          <div class="s-row">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:9px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);display:flex;align-items:center;justify-content:center;color:#22c55e;font-size:15px"><i class="bi bi-credit-card-2-front"></i></div>
              <div>
                <div style="font-size:13px;font-weight:500;color:var(--text-primary)">Adicionar cartão</div>
                <div style="font-size:11px;color:var(--text-secondary)">Registar novo cartão</div>
              </div>
            </div>
            <a href="pages/add_card.php" class="btn-s" style="padding:5px 12px;font-size:12px"><i class="bi bi-arrow-right"></i></a>
          </div>
          <div class="s-row">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:9px;background:rgba(231,76,60,.1);border:1px solid rgba(231,76,60,.2);display:flex;align-items:center;justify-content:center;color:#e74c3c;font-size:15px"><i class="bi bi-file-earmark-pdf"></i></div>
              <div>
                <div style="font-size:13px;font-weight:500;color:var(--text-primary)">Dados & Relatórios</div>
                <div style="font-size:11px;color:var(--text-secondary)">Exportar em PDF ou CSV</div>
              </div>
            </div>
            <a href="pages/dados.php" class="btn-s" style="padding:5px 12px;font-size:12px"><i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>

    </div><!-- /left col -->

    <!-- ════ RIGHT COLUMN ════ -->
    <div>

      <!-- Maior despesa + categoria top -->
      <?php if (!empty($categoryData) || $biggestExpense): ?>
      <div class="settings-group">
        <p class="group-label">Destaques do mês</p>
        <div class="stats-row stats-row-2">
          <?php if ($biggestExpense): ?>
          <div class="stat-cell">
            <div class="lbl">Maior despesa</div>
            <div class="val" style="color:#ef4444;font-size:20px">€<?=number_format($biggestExpense['amount'],2)?></div>
            <div class="sub" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($biggestExpense['description'])?></div>
          </div>
          <?php endif; ?>
          <?php if (!empty($categoryData)): ?>
          <div class="stat-cell">
            <div class="lbl">Categoria top</div>
            <div class="val" style="color:#3498db;font-size:18px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($categoryData[0]['category'])?></div>
            <div class="sub">€<?=number_format($categoryData[0]['total'],2)?> em <?=$categoryData[0]['count']?> transações</div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Gastos por categoria -->
      <?php if (!empty($categoryData)): ?>
      <div class="settings-group">
        <p class="group-label">Gastos por categoria</p>
        <div class="settings-card">
          <?php $maxAmt=max(array_column($categoryData,'total'));
          $displayCats=array_slice($categoryData,0,4);
          $remainingCats=count($categoryData)-4;
          foreach ($displayCats as $cat):
            $pct=($cat['total']/$maxAmt)*100;
            $color=$categoryColors[$cat['category']]??'#95a5a6';
          ?>
          <div class="cat-bar-wrap">
            <div class="cat-bar-label">
              <span style="font-weight:500;color:var(--text-primary)"><?=htmlspecialchars($cat['category'])?> <span style="font-size:11px;color:var(--text-secondary)">(<?=$cat['count']?>)</span></span>
              <span style="font-weight:600;color:#ef4444">€<?=number_format($cat['total'],2)?></span>
            </div>
            <div class="cat-bar-track">
              <div class="cat-bar" style="background:<?=$color?>" data-width="<?=$pct?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if ($remainingCats>0): ?>
          <div style="padding:10px 20px;text-align:center;border-top:1px solid var(--border-color)">
            <span style="font-size:12px;color:var(--text-secondary)">+<?=$remainingCats?> categoria<?=$remainingCats>1?'s':''?></span>
          </div>
          <?php endif; ?>
          <div style="padding:12px 20px;border-top:1px solid var(--border-color);text-align:center">
            <a href="pages/analytics.php" class="btn-s" style="justify-content:center"><i class="bi bi-bar-chart"></i> Ver análise completa</a>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Últimas transações -->
      <div class="settings-group">
        <p class="group-label">Últimas transações</p>
        <div class="settings-card">
          <?php if (empty($recent)): ?>
            <div style="padding:28px 20px;text-align:center">
              <i class="bi bi-receipt" style="font-size:36px;color:var(--border-color);display:block;margin-bottom:10px"></i>
              <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">Ainda não tens transações</p>
              <a href="pages/create_transaction.php" class="btn-s btn-s-primary" style="justify-content:center"><i class="bi bi-plus-circle"></i> Criar primeira</a>
            </div>
          <?php else:
            $displayTx=array_slice($recent,0,5);
            $remainingTx=count($recent)-5;
            foreach ($displayTx as $r):
              $cat=$r['category']??'Outros';
              $color=$categoryColors[$cat]??'#95a5a6';
          ?>
          <div class="tx-row">
            <div class="tx-icon" style="background:<?=$color?>1a;color:<?=$color?>;border:1px solid <?=$color?>33">
              <i class="bi bi-receipt"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:500;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($r['description']?:'-')?></div>
              <div style="font-size:11px;color:var(--text-secondary)">
                <?=$cat?><?=$r['card_name']?' · '.htmlspecialchars($r['card_name']):''?>
              </div>
            </div>
            <div style="font-size:14px;font-weight:700;color:#ef4444;flex-shrink:0">-€<?=number_format($r['amount'],2)?></div>
          </div>
          <?php endforeach; ?>
          <?php if ($remainingTx>0): ?>
          <div style="padding:10px 20px;text-align:center;border-top:1px solid var(--border-color)">
            <span style="font-size:12px;color:var(--text-secondary)">+<?=$remainingTx?> transaç<?=$remainingTx>1?'ões':'ão'?></span>
          </div>
          <?php endif; ?>
          <div style="padding:12px 20px;border-top:1px solid var(--border-color);text-align:center">
            <a href="pages/transactions.php" class="btn-s" style="justify-content:center"><i class="bi bi-list-ul"></i> Ver todas as transações</a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Orçamentos -->
      <div class="settings-group">
        <p class="group-label">Orçamentos</p>
        <div class="settings-card">
          <?php if ($mainBudget): ?>
          <div style="padding:16px 20px">
            <?php if ($budgetAlert): ?>
              <div class="s-alert s-alert-<?=$budgetAlert['type']?>" style="margin-bottom:12px">
                <i class="bi bi-<?=$budgetAlert['icon']?>" style="flex-shrink:0"></i>
                <span><?=htmlspecialchars($budgetAlert['message'])?></span>
              </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
              <div>
                <div style="font-size:14px;font-weight:600;color:var(--text-primary)"><?=htmlspecialchars($mainBudget['name'])?></div>
                <div style="font-size:12px;color:var(--text-secondary)">€<?=number_format($mainBudget['current_spent'],2)?> de €<?=number_format($mainBudget['amount'],2)?></div>
              </div>
              <span style="font-size:13px;font-weight:700;color:<?=$budgetPercentage>=100?'#ef4444':($budgetPercentage>=80?'#d97706':'#16a34a')?>"><?=round($budgetPercentage)?>%</span>
            </div>
            <?php $barColor=$budgetPercentage>=100?'#ef4444':($budgetPercentage>=80?'#f59e0b':'#22c55e'); ?>
            <div class="budget-progress">
              <div class="budget-bar" style="background:<?=$barColor?>" data-width="<?=min($budgetPercentage,100)?>%"></div>
            </div>
            <?php if($activeBudgetsCount>1): ?>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:6px">+<?=$activeBudgetsCount-1?> orçamento<?=$activeBudgetsCount-1>1?'s':''?> ativo<?=$activeBudgetsCount-1>1?'s':''?></div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div style="padding:20px;text-align:center">
            <i class="bi bi-piggy-bank" style="font-size:36px;color:var(--border-color);display:block;margin-bottom:10px"></i>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">Nenhum orçamento definido</p>
          </div>
          <?php endif; ?>
          <div style="padding:12px 20px;border-top:1px solid var(--border-color);text-align:center">
            <a href="pages/budgets.php" class="btn-s" style="justify-content:center">
              <?=$activeBudgetsCount>0?'<i class="bi bi-arrow-right"></i> Ver detalhes':'<i class="bi bi-plus-circle"></i> Criar orçamento'?>
            </a>
          </div>
        </div>
      </div>

    </div><!-- /right col -->
  </div><!-- /dash-grid -->

</div><!-- /settings-wrapper -->
</div><!-- /page-content -->
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    document.querySelectorAll('.cat-bar').forEach(b => { b.style.width = b.dataset.width; });
    document.querySelectorAll('.budget-bar').forEach(b => { b.style.width = b.dataset.width; });
  }, 100);
});
</script>
</body>
</html>