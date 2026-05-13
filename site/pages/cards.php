<?php
// site/cards.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

require_once __DIR__ . '/../helpers/banks.php';
require_once __DIR__ . '/../helpers/bank_watermark.php';

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
        $message = 'Estado do cartao alterado com sucesso!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Erro ao executar a acao.';
        $messageType = 'danger';
    }
}

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color, c.bank, c.limit_amount, c.balance, c.active, c.created_at,
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

$activeTotalLimit   = array_sum(array_column($activeCards, 'limit_amount'));
$activeTotalBalance = array_sum(array_column($activeCards, 'balance'));
$activeAvailable    = $activeTotalLimit - $activeTotalBalance;
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gerir Cartoes - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-primary); color: var(--text-primary); }
    .settings-wrapper { max-width: 920px; margin: 0 auto; padding: 0 16px 60px; }

    /* ── Page header ── */
    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary); }
    .page-header p  { font-size: 13px; color: var(--text-secondary); margin: 0; }

    /* ── Groups ── */
    .settings-group { margin-bottom: 28px; }
    .group-label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-secondary); margin: 0 0 12px 2px; }

    /* ── Buttons ── */
    .btn-s { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; }
    .btn-s-primary:hover { background: #2980b9; color: #fff; border-color: #2980b9; }

    /* ── Alert ── */
    .s-alert { border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent; }
    .s-alert-success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    [data-theme="dark"] .s-alert-success { color: #6ee7b7; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }

    /* ── Summary stats bar ── */
    .stats-bar { display: grid; grid-template-columns: repeat(3,1fr); gap: 1px; background: var(--border-color); border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
    .stat-cell { background: var(--bg-secondary); padding: 16px; text-align: center; }
    .stat-cell h4 { font-size: 20px; font-weight: 700; margin: 0 0 3px; }
    .stat-cell p  { font-size: 11px; color: var(--text-secondary); margin: 0; }

    /* ── Card grid ── */
    .card-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    @media (max-width: 640px) { .card-grid { grid-template-columns: 1fr; } }

    /* ── Card tile ── */
    .card-tile { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .2s, transform .2s; }
    .card-tile:hover { box-shadow: 0 6px 24px rgba(0,0,0,.1); transform: translateY(-2px); }
    .card-tile.inactive { opacity: .6; }
    .card-tile.inactive:hover { transform: none; box-shadow: none; }

    /* ── Credit card visual (tall, like preview) ── */
    .card-visual { padding: 20px 22px; color: #fff; min-height: 160px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; }
    .card-visual::before { content: ''; position: absolute; top: -60%; right: -40%; width: 220%; height: 220%; background: radial-gradient(circle, rgba(255,255,255,.15) 0%, transparent 65%); pointer-events: none; }
    .cv-icon   { font-size: 22px; position: relative; }
    .cv-number { font-size: 14px; letter-spacing: 4px; font-weight: 600; opacity: .9; position: relative; margin-top: 8px; }
    .cv-name   { font-size: 13px; text-transform: uppercase; font-weight: 700; position: relative; }
    .cv-badge  { font-size: 10px; font-weight: 700; background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.35); color: #fff; padding: 3px 8px; border-radius: 5px; flex-shrink: 0; }
    .cv-meta   { font-size: 11px; opacity: .85; position: relative; display: flex; justify-content: space-between; margin-top: 6px; }
    .bank-logo-badge { font-size: 11px; font-weight: 800; letter-spacing: .5px; color: rgba(255,255,255,.9); background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3); padding: 3px 9px; border-radius: 5px; }

    /* ── Card body ── */
    .card-body-wrap { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 12px; }

    /* ── Usage bar ── */
    .usage-row { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
    .usage-pct { font-weight: 700; color: var(--text-primary); }
    .usage-track { height: 5px; border-radius: 3px; background: var(--bg-primary); overflow: hidden; }
    .usage-bar   { height: 100%; border-radius: 3px; }
    .usage-labels { display: flex; justify-content: space-between; font-size: 11px; color: var(--text-secondary); margin-top: 4px; }

    /* ── Mini 2-col stat ── */
    .mini-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--border-color); border-radius: 10px; overflow: hidden; }
    .mini-stat { background: var(--bg-primary); padding: 10px 12px; text-align: center; }
    .mini-stat .v { font-size: 14px; font-weight: 700; }
    .mini-stat .l { font-size: 11px; color: var(--text-secondary); margin-top: 1px; }

    /* ── Card actions ── */
    .card-actions { display: flex; gap: 6px; }
    .card-actions .btn-s { flex: 1; justify-content: center; font-size: 12px; padding: 7px 10px; }
    .card-actions form { flex: 1; margin: 0; display: flex; }
    .card-actions form .btn-s { flex: 1; justify-content: center; font-size: 12px; padding: 7px 10px; }

    /* ── Created date ── */
    .card-date { font-size: 11px; color: var(--text-secondary); text-align: center; padding-top: 2px; }

    /* ── Inactive list ── */
    .settings-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; }
    .s-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 13px 18px; }
    .s-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }

    /* ── Empty ── */
    .empty-state { padding: 40px 20px; text-align: center; }
    .empty-state i { font-size: 48px; color: var(--border-color); display: block; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; color: var(--text-secondary); margin-bottom: 16px; }

    @media (max-width: 640px) {
      .stats-bar { grid-template-columns: 1fr; }
    }
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
      <h2>Os Meus Cartoes</h2>
      <p>Gere os teus cartoes e acompanha os limites</p>
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

  <?php if (empty($allCards)): ?>
    <div class="settings-card">
      <div class="empty-state">
        <i class="bi bi-credit-card-2-front"></i>
        <p>Ainda nao tens cartoes registados.<br>Adiciona o teu primeiro cartao para comecares a gerir as tuas financas.</p>
        <a href="add_card.php" class="btn-s btn-s-primary"><i class="bi bi-plus-circle"></i> Adicionar Primeiro Cartao</a>
      </div>
    </div>

  <?php else: ?>

    <!-- ATIVOS -->
    <?php if (!empty($activeCards)): ?>
    <div class="settings-group">
      <p class="group-label">Resumo — cartoes ativos</p>
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
          <h4 style="color:#22c55e">€<?=number_format($activeAvailable,2)?></h4>
          <p>Disponivel</p>
        </div>
      </div>
    </div>

    <div class="settings-group">
      <p class="group-label">Cartoes ativos</p>
      <div class="card-grid">
        <?php foreach ($activeCards as $c):
          $pct       = $c['limit_amount'] > 0 ? ($c['balance'] / $c['limit_amount']) * 100 : 0;
          $barColor  = $pct >= 80 ? '#ef4444' : ($pct >= 60 ? '#f59e0b' : '#22c55e');
          $available = $c['limit_amount'] - $c['balance'];
          $bankKey   = $c['bank'] ?? '';
          $bankData  = $BANKS[$bankKey] ?? null;
          $gradient  = ($bankData && $bankData['gradient']) ? $bankData['gradient'] : ($cardColors[$c['color'] ?? 'purple'] ?? $cardColors['purple']);
          $bankLabel = $bankData['label'] ?? '';
        ?>
        <div class="card-tile">
          <!-- Visual card -->
          <div class="card-visual" style="background:<?=$gradient?>">
            <?= bankWatermark($bankKey) ?>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <i class="bi bi-credit-card cv-icon"></i>
                <?php if ($bankLabel): ?>
                  <span class="bank-logo-badge"><?=htmlspecialchars($bankLabel)?></span>
                <?php endif; ?>
              </div>
              <div class="cv-number">•••• •••• •••• ••••</div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:4px">
                <div class="cv-name"><?=htmlspecialchars($c['name'])?></div>
                <span class="cv-badge">ATIVO</span>
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
                <span>Utilizacao</span>
                <span class="usage-pct"><?=round($pct)?>%</span>
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
                <div class="l">Disponivel</div>
              </div>
              <div class="mini-stat">
                <div class="v"><?=$c['transaction_count']?></div>
                <div class="l">Transacoes</div>
              </div>
            </div>

            <!-- Actions -->
            <div class="card-actions">
              <a href="edit_card.php?id=<?=$c['id']?>" class="btn-s"><i class="bi bi-pencil"></i> Editar</a>
              <form method="post">
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
      <p class="group-label">Cartoes inativos</p>
      <div class="settings-card">
        <?php foreach ($inactiveCards as $c):
          $bankKey  = $c['bank'] ?? '';
          $bankData = $BANKS[$bankKey] ?? null;
          $gradient = ($bankData && $bankData['gradient']) ? $bankData['gradient'] : ($cardColors[$c['color'] ?? 'purple'] ?? $cardColors['purple']);
        ?>
        <div class="s-row">
          <div style="display:flex;align-items:center;gap:12px;min-width:0">
            <div style="width:36px;height:36px;border-radius:10px;background:<?=$gradient?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;filter:grayscale(.5);opacity:.7">
              <i class="bi bi-credit-card" style="color:#fff;font-size:15px"></i>
            </div>
            <div style="min-width:0">
              <div style="font-size:14px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($c['name'])?></div>
              <div style="font-size:12px;color:var(--text-secondary)">€<?=number_format($c['balance'],2)?> / €<?=number_format($c['limit_amount'],2)?> &middot; <?=$c['transaction_count']?> transacoes</div>
            </div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <a href="edit_card.php?id=<?=$c['id']?>" class="btn-s" style="padding:5px 10px;font-size:12px"><i class="bi bi-pencil"></i></a>
            <form method="post" style="margin:0">
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

</div><!-- /settings-wrapper -->
</div><!-- /page-content -->
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>