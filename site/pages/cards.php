<?php
// site/cards.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/banks.php';
require_once __DIR__ . '/../helpers/bank_watermark.php';
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

// Pagar o extrato: regista o pagamento e baixa o saldo usado. Não é um gasto, por isso
// não entra em transactions (contava duas vezes na análise).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    require_once __DIR__ . '/../helpers/ownership.php';
    $cardId = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? 0);   // 403 se não for do utilizador
    $amount = round((float) str_replace(',', '.', $_POST['amount'] ?? '0'), 2);
    $paidOn = (string) ($_POST['paid_on'] ?? '');
    $d = DateTime::createFromFormat('!Y-m-d', $paidOn);
    $flash = null;
    if (!$cardId || $amount <= 0) {
        $flash = ['danger', 'Indica um valor maior que zero.'];
    } elseif (!$d || $d->format('Y-m-d') !== $paidOn || $paidOn > date('Y-m-d', strtotime('+1 day'))) {
        $flash = ['danger', 'A data do pagamento não é válida.'];
    } else {
        try {
            $pdo->beginTransaction();
            // Só paga se houver saldo suficiente (a condição no WHERE evita pagar duas vezes com dois cliques)
            $st = $pdo->prepare("UPDATE cards SET balance = balance - :a WHERE id = :id AND user_id = :uid AND balance >= :a2");
            $st->execute([':a' => $amount, ':a2' => $amount, ':id' => $cardId, ':uid' => $uid]);
            if ($st->rowCount() === 1) {
                $pdo->prepare("INSERT INTO card_payments (user_id, card_id, amount, paid_on) VALUES (:uid, :id, :a, :d)")
                    ->execute([':uid' => $uid, ':id' => $cardId, ':a' => $amount, ':d' => $paidOn]);
                $pdo->commit();
                $flash = ['success', 'Pagamento de ' . fmt_eur($amount) . ' registado.'];
            } else {
                $pdo->rollBack();
                $flash = ['danger', 'O valor é maior do que o que está em uso no cartão.'];
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[cards] pagar extrato: ' . $e->getMessage());
            $flash = ['danger', 'Não foi possível registar o pagamento.'];
        }
    }
    $_SESSION['flash_cards'] = $flash;
    header('Location: cards.php' . ($flash[0] === 'success' ? '#card-' . $cardId : ''));
    exit;
}
if (!empty($_SESSION['flash_cards'])) {
    [$messageType, $message] = $_SESSION['flash_cards'];
    unset($_SESSION['flash_cards']);
}

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

// Último pagamento de extrato de cada cartão
$lastPay = [];
try {
    $st = $pdo->prepare("SELECT p.card_id, p.amount, p.paid_on FROM card_payments p
                         JOIN (SELECT card_id, MAX(id) AS id FROM card_payments WHERE user_id = :uid GROUP BY card_id) m ON m.id = p.id");
    $st->execute([':uid' => $uid]);
    foreach ($st as $r) $lastPay[(int) $r['card_id']] = $r;
} catch (PDOException $e) { error_log('[cards] card_payments: ' . $e->getMessage()); }   // migração por correr

$activeCards   = array_filter($allCards, fn($c) => $c['active']);
$inactiveCards = array_filter($allCards, fn($c) => !$c['active']);

// Totais só dos cartões com limite (um cartão sem limite, ex. débito, não tem "disponível")
$limitedCards       = array_filter($activeCards, fn($c) => $c['limit_amount'] > 0);
$activeTotalLimit   = array_sum(array_column($limitedCards, 'limit_amount'));
$activeTotalBalance = array_sum(array_column($limitedCards, 'balance'));
$activeAvailable    = array_sum(array_map(fn($c) => max($c['limit_amount'] - $c['balance'], 0), $limitedCards));

// ── Alertas: cartões ativos com limite que já o ultrapassaram ────────────────
// (balance é o gasto; limit_amount é o teto. Sem limite definido, não há excesso)
$overdraftCards = [];
foreach ($limitedCards as $c) {
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
$activeCount = count($activeCards);
$activePct   = $activeTotalLimit > 0 ? min($activeTotalBalance / $activeTotalLimit * 100, 100) : 0;

// Fundo do cartão: cor do banco se existir, senão a cor escolhida
function card_gradient(array $c, array $BANKS, array $cardColors): string {
    $bank = $BANKS[$c['bank'] ?? ''] ?? null;
    if ($bank && !empty($bank['gradient'])) return $bank['gradient'];
    return $cardColors[$c['color'] ?? 'purple'] ?? $cardColors['purple'];
}

// Cor sólida para o cartão 3D: a do banco, senão a cor mais escura do degradê escolhido
function card_solid_color(array $c, array $BANKS, array $cardColors): string {
    $bank = $BANKS[$c['bank'] ?? ''] ?? null;
    if ($bank && !empty($bank['bg'])) return $bank['bg'];
    preg_match_all('/#[0-9a-fA-F]{6}/', $cardColors[$c['color'] ?? 'purple'] ?? $cardColors['purple'], $m);
    return $m[0][1] ?? $m[0][0] ?? '#14213d';
}

// Carteira 3D: os primeiros 5 cartões ativos (os restantes ficam só na lista)
const WALLET_MAX_CARDS = 5;
$walletCards = array_map(fn($c) => [
    'id'    => (int) $c['id'],
    'name'  => mb_substr($c['name'], 0, 28),
    'bank'  => $BANKS[$c['bank'] ?? '']['label'] ?? '',
    'color' => card_solid_color($c, $BANKS, $cardColors),
], array_slice(array_values($activeCards), 0, WALLET_MAX_CARDS));
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cartões - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=7">
  <style>
    .cd { max-width: 1200px; margin: 0 auto; padding-bottom: 56px; }
    .cd-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 6px; }
    .cd-head h1 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .cd-summary { margin: 0 0 10px; font-size: 15px; color: var(--fn-muted); }
    .cd-summary strong { color: var(--fn-text); }
    .cd-total-meter { height: 8px; border-radius: 4px; background: var(--fn-line); overflow: hidden; max-width: 520px; margin-bottom: 28px; }
    .cd-total-meter span { display: block; height: 100%; background: var(--fn-gold); border-radius: 4px; }

    /* Alerta de limite excedido */
    .cd-alert { background: var(--fn-surface); border: 1px solid #fecdca; border-left: 4px solid #d92d20; border-radius: 14px; margin-bottom: 28px; }
    [data-theme="dark"] .cd-alert { border-color: rgba(253,162,155,.3); border-left-color: #f97066; }
    .cd-alert h2 { margin: 0; padding: 14px 18px 6px; font-size: 16px; font-weight: 700; color: #b42318; }
    [data-theme="dark"] .cd-alert h2 { color: #fda29b; }
    .cd-alert ul { list-style: none; margin: 0; padding: 0 0 6px; }
    .cd-alert li { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 18px; }
    .cd-alert li + li { border-top: 1px solid var(--fn-line); }
    .cd-alert .nm { font-weight: 600; color: var(--fn-text); }
    .cd-alert .mt { font-size: 13px; color: var(--fn-muted); }

    /* Grelha de cartões: ocupa a largura toda */
    .cd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 22px; }
    /* Carteira 3D */
    .cd-wallet { display: none; position: relative; height: 380px; margin: 4px 0 22px; }
    .cd-wallet.is-on { display: block; }
    .cd-wallet::before {
      content: ''; position: absolute; inset: 10% 22%; border-radius: 50%; pointer-events: none;
      background: radial-gradient(closest-side, rgba(196, 138, 18, .14), transparent 70%);
    }
    .cd-wallet canvas { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; transition: opacity .5s; touch-action: pan-y; }
    .cd-wallet.is-live canvas { opacity: 1; }
    .cd-wallet canvas.is-pointing { cursor: pointer; }
    .cd-wallet-hint { position: absolute; left: 0; right: 0; bottom: 0; margin: 0; text-align: center; font-size: 13px; color: var(--fn-muted); }
    .cd-tile { scroll-margin-top: 90px; }
    .cd-tile.is-picked { box-shadow: 0 0 0 3px var(--fn-gold); transition: box-shadow .3s; }
    .cd-tile:focus { outline: none; }
    /* Telemóvel: só decorativa, o toque passa para a página */
    .cd-wallet.is-touch { height: 230px; margin-bottom: 14px; }
    .cd-wallet.is-touch canvas { pointer-events: none; }
    .cd-wallet.is-touch .cd-wallet-hint { display: none; }
    .cd-tile { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; padding: 14px; display: flex; flex-direction: column; gap: 16px; }
    .cd-tile.over { border-color: #fda29b; }

    /* Cartão com as proporções de um cartão real (85,6 × 54 mm) */
    .cd-visual {
      position: relative; aspect-ratio: 1.586; border-radius: 14px; padding: 18px 20px; overflow: hidden;
      color: #fff; display: flex; flex-direction: column; justify-content: space-between;
      box-shadow: 0 10px 24px -12px rgba(20, 33, 61, .45);
    }
    .cd-visual::after { content: ''; position: absolute; inset: 0; background: linear-gradient(120deg, rgba(255,255,255,.18), transparent 45%); pointer-events: none; }
    .cd-visual .top { display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1; }
    .cd-chip { width: 38px; height: 28px; border-radius: 6px; background: linear-gradient(135deg, #f6d58e, #c99a3a); box-shadow: inset 0 0 0 1px rgba(0,0,0,.15); }
    .cd-bank { font-size: 12px; font-weight: 800; letter-spacing: .5px; padding: 3px 8px; border-radius: 6px; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3); }
    .cd-visual .num { position: relative; z-index: 1; font-size: 15px; letter-spacing: 3px; opacity: .85; }
    .cd-visual .bottom { display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 1; }
    .cd-visual .name { font-size: 15px; font-weight: 700; max-width: 75%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cd-visual .flag { font-size: 12px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: #fff; color: #b42318; }

    .cd-body { padding: 0 6px; }
    .cd-avail-label { font-size: 13px; color: var(--fn-muted); }
    .cd-avail { font-family: var(--fn-font-brand); font-weight: 700; font-size: 28px; letter-spacing: -0.5px; color: var(--fn-ink); line-height: 1.15; }
    .cd-avail.over { color: #b42318; }
    [data-theme="dark"] .cd-avail.over { color: #fda29b; }
    .cd-meter { height: 8px; border-radius: 4px; background: var(--fn-paper); overflow: hidden; margin: 12px 0 8px; }
    .cd-meter span { display: block; height: 100%; border-radius: 4px; background: var(--fn-accent); }
    .cd-meter span.warn { background: #d97706; }
    .cd-meter span.over { background: #d92d20; }
    .cd-line { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--fn-muted); }
    .cd-actions { display: flex; gap: 8px; flex-wrap: wrap; padding: 0 6px 4px; }
    .cd-actions form { margin: 0; }
    .cd-actions .btn-s { min-height: 36px; padding: 6px 12px; font-size: 13px; }
    .cd-paid { margin-top: 6px; color: #067647; }
    [data-theme="dark"] .cd-paid { color: #75e0a7; }

    /* Pagar extrato: <dialog> nativo (fecha com Esc e gere o foco) */
    .pay-dialog { border: 1px solid var(--fn-line); border-radius: 18px; padding: 24px; width: min(440px, calc(100% - 32px)); background: var(--fn-surface); color: var(--fn-text); }
    .pay-dialog::backdrop { background: rgba(14, 20, 32, .45); }
    .pay-dialog h2 { margin: 0 0 4px; font-family: var(--fn-font-brand); font-size: 20px; font-weight: 700; color: var(--fn-ink); }
    .pay-dialog .sub { margin: 0 0 18px; font-size: 14px; color: var(--fn-muted); }
    .pay-dialog .form-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; }
    .pay-dialog .form-control { min-height: 44px; border-radius: 10px; border: 1px solid var(--fn-line); background: var(--fn-surface); color: var(--fn-text); }
    .pay-dialog .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .pay-dialog .quick { display: flex; gap: 6px; margin-top: 8px; }
    .pay-dialog .quick button { border: 1px solid var(--fn-line); background: var(--fn-paper); color: var(--fn-text); border-radius: 999px; padding: 3px 10px; font-size: 13px; cursor: pointer; }
    .pay-dialog .quick button:hover { border-color: var(--fn-accent); }
    .pay-dialog .note { margin: 14px 0 0; font-size: 13px; color: var(--fn-muted); }
    .pay-dialog .acts { display: flex; gap: 8px; justify-content: flex-end; margin-top: 18px; }
    @media (max-width: 420px) { .pay-dialog .row2 { grid-template-columns: 1fr; } }

    .cd-section-title { margin: 36px 0 12px; font-size: 17px; font-weight: 700; color: var(--fn-text); }
    .cd-section-title small { font-weight: 400; font-size: 14px; color: var(--fn-muted); }
    .cd-inactive { list-style: none; margin: 0; padding: 0; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; }
    .cd-inactive li { display: flex; align-items: center; gap: 14px; padding: 12px 18px; }
    .cd-inactive li + li { border-top: 1px solid var(--fn-line); }
    .cd-inactive .mini { width: 46px; height: 30px; border-radius: 6px; flex-shrink: 0; filter: grayscale(.6); opacity: .7; }
    .cd-inactive .main { flex: 1; min-width: 0; }
    .cd-inactive .nm { font-weight: 600; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cd-inactive .mt { font-size: 13px; color: var(--fn-muted); }
    .cd-inactive form { margin: 0; }

    .cd-empty { padding: 56px 24px; text-align: center; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 20px; }
    .cd-empty-art { width: 120px; aspect-ratio: 1.586; margin: 0 auto 22px; border-radius: 12px; background: linear-gradient(135deg, var(--fn-ink), #3a5794); position: relative; box-shadow: 18px 14px 0 -4px var(--fn-gold-soft); }
    .cd-empty-art::before { content: ''; position: absolute; left: 14px; top: 16px; width: 22px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #f6d58e, #c99a3a); }
    .cd-empty h2 { margin: 0 0 6px; font-size: 20px; font-weight: 700; color: var(--fn-text); }
    .cd-empty p { margin: 0 auto 20px; max-width: 46ch; color: var(--fn-muted); font-size: 15px; }

    @media (max-width: 560px) {
      .cd-head h1 { font-size: 26px; }
      .cd-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage='cards'; $sidebarPrefix='../'; include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="cd">

  <div class="cd-head">
    <h1>Cartões</h1>
    <a href="add_card.php" class="btn-s btn-s-primary"><i class="bi bi-plus-lg"></i> Adicionar cartão</a>
  </div>

  <?php if ($activeCount > 0): ?>
    <p class="cd-summary">
      <?php if ($activeTotalLimit > 0): ?>
        Tens <strong><?= fmt_eur(max($activeAvailable, 0)) ?> disponíveis</strong> de <?= fmt_eur($activeTotalLimit) ?>
        <?php $nLimited = count($limitedCards); ?>
        <?= $nLimited === 1 ? 'no teu cartão com limite' : "nos {$nLimited} cartões com limite" ?>.
      <?php else: ?>
        <?= $activeCount ?> cartão<?= $activeCount === 1 ? '' : 'ões' ?> ativo<?= $activeCount === 1 ? '' : 's' ?>, sem limite definido.
      <?php endif; ?>
    </p>
    <?php if ($activeTotalLimit > 0): ?>
      <div class="cd-total-meter" role="img" aria-label="<?= round($activePct) ?>% do limite total usado"><span style="width:<?= round($activePct, 1) ?>%"></span></div>
    <?php endif; ?>
  <?php else: ?>
    <p class="cd-summary">&nbsp;</p>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="s-alert s-alert-<?= $messageType ?>" role="status">
      <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($overdraftCards)): ?>
  <section class="cd-alert" aria-labelledby="cdAlertTitle">
    <h2 id="cdAlertTitle"><i class="bi bi-exclamation-triangle-fill"></i>
      <?= count($overdraftCards) === 1 ? 'Um cartão passou o limite' : count($overdraftCards) . ' cartões passaram o limite' ?></h2>
    <ul>
      <?php foreach ($overdraftCards as $oc): ?>
        <li>
          <div>
            <div class="nm"><?= htmlspecialchars($oc['name']) ?></div>
            <div class="mt">
              Excedido em <?= fmt_eur(abs($oc['available'])) ?> · <?= fmt_eur($oc['balance']) ?> de <?= fmt_eur($oc['limit_amount']) ?>
              <?= $oc['since_label'] ? ' · ' . htmlspecialchars($oc['since_label']) : '' ?>
            </div>
          </div>
          <a href="edit_card.php?id=<?= $oc['id'] ?>" class="btn-s">Rever cartão</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- Aviso semanal no browser enquanto um cartão estiver acima do limite -->
  <script>
  (function(){
    const overdrafts = <?= json_encode(array_values(array_map(fn($c) => ['name' => $c['name'], 'excess' => abs($c['available']), 'days' => $c['days_since']], $overdraftCards))) ?>;
    const KEY = 'finora_overdraft_notif_' + <?= (int) $uid ?>;
    let last = 0;
    try { last = parseInt(localStorage.getItem(KEY) || '0'); } catch (e) {}
    const now  = Date.now();
    const WEEK = 7 * 24 * 60 * 60 * 1000;
    const eur  = v => v.toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
    const notify = () => {
      overdrafts.forEach(o => new Notification('Finora — cartão acima do limite', {
        body: `${o.name}: excedido em ${eur(o.excess)} há ${o.days} dias`, icon: '/favicon.ico'
      }));
      try { localStorage.setItem(KEY, now); } catch (e) {}
    };
    if (now - last > WEEK && overdrafts.length > 0 && 'Notification' in window) {
      if (Notification.permission === 'granted') notify();
      else if (Notification.permission !== 'denied') Notification.requestPermission().then(p => { if (p === 'granted') notify(); });
    }
  })();
  </script>
  <?php endif; ?>

  <?php if (empty($allCards)): ?>
    <div class="cd-empty">
      <div class="cd-empty-art" aria-hidden="true"></div>
      <h2>Ainda não tens cartões</h2>
      <p>Adiciona os teus cartões para veres o limite, quanto já gastaste e quanto ainda tens disponível em cada um.</p>
      <a href="add_card.php" class="btn-s btn-s-primary"><i class="bi bi-plus-lg"></i> Adicionar o primeiro cartão</a>
    </div>
  <?php else: ?>

    <?php if ($activeCount > 0): ?>
    <!-- Carteira 3D (só aparece se o browser tiver WebGL; a lista em baixo tem a mesma informação) -->
    <section class="cd-wallet" id="walletStage" aria-hidden="true">
      <canvas id="walletCanvas"></canvas>
      <p class="cd-wallet-hint"><i class="bi bi-hand-index"></i> Toca num cartão para o veres em baixo</p>
    </section>
    <?php // JSON_HEX_TAG: um nome de cartão com "</script>" não consegue fechar este bloco ?>
    <script type="application/json" id="walletData"><?= json_encode($walletCards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <div class="cd-grid">
      <?php foreach ($activeCards as $c):
        $limit     = (float) $c['limit_amount'];
        $available = $limit - $c['balance'];
        $pct       = $limit > 0 ? $c['balance'] / $limit * 100 : 0;
        $state     = ($limit > 0 && $available < 0) ? 'over' : ($pct >= 80 ? 'warn' : '');
        $bank      = $BANKS[$c['bank'] ?? ''] ?? null;
        $bankLabel = $bank['label'] ?? '';
      ?>
        <article class="cd-tile <?= $state === 'over' ? 'over' : '' ?>" id="card-<?= (int) $c['id'] ?>" tabindex="-1" aria-label="<?= htmlspecialchars($c['name']) ?>">
          <div class="cd-visual" style="background:<?= card_gradient($c, $BANKS, $cardColors) ?>">
            <?= bankWatermark($c['bank'] ?? '') ?>
            <div class="top">
              <span class="cd-chip" aria-hidden="true"></span>
              <?php if ($bankLabel): ?><span class="cd-bank"><?= htmlspecialchars($bankLabel) ?></span><?php endif; ?>
            </div>
            <div class="num" aria-hidden="true">•••• •••• •••• ••••</div>
            <div class="bottom">
              <span class="name"><?= htmlspecialchars($c['name']) ?></span>
              <?php if ($state === 'over'): ?><span class="flag">Limite excedido</span><?php endif; ?>
            </div>
          </div>

          <div class="cd-body">
            <?php if ($limit > 0): ?>
              <div class="cd-avail-label"><?= $available < 0 ? 'Acima do limite' : 'Disponível' ?></div>
              <div class="cd-avail <?= $available < 0 ? 'over' : '' ?>"><?= fmt_eur(abs($available)) ?></div>
              <div class="cd-meter"><span class="<?= $state ?>" style="width:<?= min(round($pct, 1), 100) ?>%"></span></div>
              <div class="cd-line">
                <span><?= fmt_eur($c['balance']) ?> usados de <?= fmt_eur($limit) ?></span>
                <span><?= round($pct) ?>%</span>
              </div>
            <?php else: ?>
              <div class="cd-avail-label">Usado</div>
              <div class="cd-avail"><?= fmt_eur($c['balance']) ?></div>
              <div class="cd-line" style="margin-top:8px"><span>Sem limite definido</span></div>
            <?php endif; ?>
            <div class="cd-line" style="margin-top:6px">
              <span><?= (int) $c['transaction_count'] ?> transaç<?= $c['transaction_count'] == 1 ? 'ão' : 'ões' ?></span>
              <span>desde <?= mb_substr(month_name_pt((int) date('n', strtotime($c['created_at']))), 0, 3) ?>. <?= date('Y', strtotime($c['created_at'])) ?></span>
            </div>
            <?php if ($lp = $lastPay[(int) $c['id']] ?? null): ?>
              <div class="cd-line cd-paid"><span><i class="bi bi-check2-circle" aria-hidden="true"></i> Pago <?= fmt_eur($lp['amount']) ?> a <?= (int) date('j', strtotime($lp['paid_on'])) ?> <?= mb_substr(month_name_pt((int) date('n', strtotime($lp['paid_on']))), 0, 3) ?>.</span></div>
            <?php endif; ?>
          </div>

          <div class="cd-actions">
            <?php if ($c['balance'] > 0): ?>
              <button type="button" class="btn-s btn-s-primary" data-pay="<?= (int) $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-balance="<?= number_format((float) $c['balance'], 2, '.', '') ?>"><i class="bi bi-cash-coin"></i> Pagar extrato</button>
            <?php endif; ?>
            <a href="transactions.php?card_id=<?= $c['id'] ?>" class="btn-s"><i class="bi bi-list-ul"></i> Ver transações</a>
            <a href="edit_card.php?id=<?= $c['id'] ?>" class="btn-s"><i class="bi bi-pencil"></i> Editar</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="card_id" value="<?= $c['id'] ?>">
              <input type="hidden" name="action" value="toggle">
              <button type="submit" class="btn-s" title="Esconde o cartão da análise e das transações"><i class="bi bi-pause-circle"></i> Desativar</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($inactiveCards)): ?>
      <h2 class="cd-section-title">Cartões inativos <small>· não contam na análise nem nas transações</small></h2>
      <ul class="cd-inactive">
        <?php foreach ($inactiveCards as $c): ?>
          <li>
            <span class="mini" style="background:<?= card_gradient($c, $BANKS, $cardColors) ?>"></span>
            <div class="main">
              <div class="nm"><?= htmlspecialchars($c['name']) ?></div>
              <div class="mt"><?= fmt_eur($c['balance']) ?> de <?= fmt_eur($c['limit_amount']) ?> · <?= (int) $c['transaction_count'] ?> transações</div>
            </div>
            <a href="edit_card.php?id=<?= $c['id'] ?>" class="btn-s" aria-label="Editar <?= htmlspecialchars($c['name']) ?>"><i class="bi bi-pencil"></i></a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="card_id" value="<?= $c['id'] ?>">
              <input type="hidden" name="action" value="toggle">
              <button type="submit" class="btn-s"><i class="bi bi-play-circle"></i> Reativar</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

  <?php endif; ?>

</div><!-- /cd -->
</div><!-- /page-content -->
</main>
</div>
<dialog class="pay-dialog" id="payDialog" aria-labelledby="payTitle">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="pay">
    <input type="hidden" name="card_id" id="payCard">
    <h2 id="payTitle">Pagar extrato</h2>
    <p class="sub" id="paySub"></p>
    <div class="row2">
      <div>
        <label class="form-label" for="payAmount">Valor pago (€)</label>
        <input type="number" class="form-control" id="payAmount" name="amount" step="0.01" min="0.01" required inputmode="decimal">
        <div class="quick"><button type="button" data-frac="1">Tudo</button><button type="button" data-frac="0.5">Metade</button></div>
      </div>
      <div>
        <label class="form-label" for="payDate">Data</label>
        <input type="date" class="form-control" id="payDate" name="paid_on" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <p class="note">O pagamento repõe o disponível do cartão. Não conta como gasto na análise, porque as compras já lá estão.</p>
    <div class="acts">
      <button type="button" class="btn-s" onclick="this.closest('dialog').close()">Cancelar</button>
      <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check2"></i> Registar pagamento</button>
    </div>
  </form>
</dialog>
<script>
(function () {
  const dlg = document.getElementById('payDialog'), amt = document.getElementById('payAmount');
  const eur = (v) => v.toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
  let bal = 0;
  document.querySelectorAll('[data-pay]').forEach((b) => b.addEventListener('click', () => {
    bal = parseFloat(b.dataset.balance);
    document.getElementById('payCard').value = b.dataset.pay;
    document.getElementById('payTitle').textContent = 'Pagar extrato · ' + b.dataset.name;
    document.getElementById('paySub').textContent = 'Em uso neste cartão: ' + eur(bal);
    amt.max = bal.toFixed(2); amt.value = bal.toFixed(2);
    dlg.showModal(); amt.select();
  }));
  dlg.querySelectorAll('[data-frac]').forEach((b) => b.addEventListener('click', () => {
    amt.value = (Math.round(bal * parseFloat(b.dataset.frac) * 100) / 100).toFixed(2); amt.focus();
  }));
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($activeCount > 0): ?>
<script type="importmap">
{ "imports": { "three": "../js/lib/three/three.module.min.js", "three/addons/": "../js/lib/three/addons/" } }
</script>
<script type="module" src="../js/cards-wallet.js?v=2"></script>
<?php endif; ?>
</body>
</html>
