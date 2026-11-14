<?php
// site/pages/pro.php — Finora Pro: o que inclui, subscrever (Stripe Checkout) e gerir a subscrição (portal do Stripe).
// O pagamento acontece todo na página do Stripe: a Finora nunca vê nem guarda dados do cartão.
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/stripe.php';
$uid = (int) $_SESSION['user_id'];
$currentTheme = getUserTheme($pdo, $uid);

$features = pro_features();
$sub      = user_subscription($pdo, $uid);

function pro_back(string $query = ''): void {
    header('Location: pro.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

// ── Subscrever / gerir (POST → Stripe) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!stripe_configured()) {
        $_SESSION['flash_pro'] = ['danger', t('Os pagamentos ainda não estão disponíveis. Tenta mais tarde.')];
        pro_back();
    }
    try {
        if ($action === 'checkout' && !is_pro()) {
            $email = $pdo->prepare('SELECT email FROM users WHERE id = :u');
            $email->execute([':u' => $uid]);
            $base   = app_base_url();
            $params = [
                'mode'                => 'subscription',
                'client_reference_id' => (string) $uid,
                'success_url'         => $base . '/pages/pro.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'          => $base . '/pages/pro.php?canceled=1',
                'locale'              => current_lang() === 'en' ? 'en' : 'pt',
                'subscription_data'   => ['metadata' => ['user_id' => (string) $uid]],
                'line_items'          => [[
                    'quantity' => 1,
                    ...(defined('STRIPE_PRICE_ID')
                        ? ['price' => STRIPE_PRICE_ID]
                        : ['price_data' => ['currency' => strtolower(PRO_CURRENCY), 'unit_amount' => PRO_PRICE_CENTS,
                                            'recurring' => ['interval' => 'month'], 'product_data' => ['name' => 'Finora Pro', 'tax_code' => PRO_TAX_CODE]]]),
                ]],
            ];
            // Se já pagou antes, usa o mesmo cliente do Stripe (histórico de faturas num só sítio)
            if (!empty($sub['stripe_customer_id'])) $params['customer'] = $sub['stripe_customer_id'];
            else $params['customer_email'] = (string) $email->fetchColumn();

            // Um duplo clique (ou voltar atrás e carregar outra vez) não pode abrir dois checkouts:
            // se os dois fossem pagos, ficavam duas subscrições a cobrar e a Finora só conhecia uma.
            // 1) nesta sessão, reutiliza o checkout que ainda está aberto;
            // 2) a Idempotency-Key faz o Stripe devolver a mesma sessão a pedidos iguais nos mesmos 30 min.
            $pending = $_SESSION['pro_checkout'] ?? null;
            if (is_array($pending) && ($pending['uid'] ?? 0) === $uid && ($pending['until'] ?? 0) > time()) {
                header('Location: ' . $pending['url'], true, 303);
                exit;
            }
            $key = 'finora-checkout-' . $uid . '-' . intdiv(time(), 1800) . '-' . substr(hash('sha256', json_encode($params)), 0, 16);
            $session = stripe_request('POST', '/checkout/sessions', $params, $key);
            if (empty($session['url']) || !str_starts_with($session['url'], 'https://checkout.stripe.com/')) throw new RuntimeException('URL de checkout inesperado');
            $_SESSION['pro_checkout'] = ['uid' => $uid, 'url' => $session['url'],
                                         'until' => min((int) ($session['expires_at'] ?? 0), time() + 1800)];
            header('Location: ' . $session['url'], true, 303);
            exit;
        }
        if ($action === 'portal' && !empty($sub['stripe_customer_id'])) {
            $portal = stripe_request('POST', '/billing_portal/sessions', [
                'customer'   => $sub['stripe_customer_id'],
                'return_url' => app_base_url() . '/pages/pro.php',
                'locale'     => current_lang() === 'en' ? 'en' : 'pt',
            ]);
            if (empty($portal['url']) || !str_starts_with($portal['url'], 'https://billing.stripe.com/')) throw new RuntimeException('URL do portal inesperado');
            header('Location: ' . $portal['url'], true, 303);
            exit;
        }
    } catch (Throwable $e) {
        error_log('[pro] ' . $e->getMessage());
        $_SESSION['flash_pro'] = ['danger', t('Não foi possível abrir a página de pagamento. Tenta outra vez.')];
    }
    pro_back();
}

// ── Volta do Stripe depois de pagar ─────────────────────────────────────────
// O webhook é quem manda, mas pode demorar uns segundos; aqui confirmamos já a sessão de checkout
// (lida ao Stripe pelo servidor, e só se for desta conta) para o Pro aparecer logo.
if (isset($_GET['session_id'])) {
    unset($_SESSION['pro_checkout']);   // esse checkout já foi usado
    $sid = (string) $_GET['session_id'];
    if (stripe_configured() && preg_match('/^cs_(test|live)_\w+$/', $sid)) {
        try {
            $cs = stripe_request('GET', '/checkout/sessions/' . $sid);
            if (($cs['client_reference_id'] ?? '') === (string) $uid && !empty($cs['subscription'])) {
                stripe_sync_subscription($pdo, (string) $cs['subscription'], $uid);
                $_SESSION['flash_pro'] = ['success', t('O Finora Pro está ativo. Já tens tudo desbloqueado.')];
            }
        } catch (Throwable $e) {
            error_log('[pro] ' . $e->getMessage());
            $_SESSION['flash_pro'] = ['info', t('Pagamento recebido. O Pro fica ativo dentro de momentos.')];
        }
    }
    pro_back();
}
if (isset($_GET['canceled'])) {
    $_SESSION['flash_pro'] = ['info', t('Não foi feito nenhum pagamento.')];
    pro_back();
}

[$flashType, $flashText] = $_SESSION['flash_pro'] ?? ['', ''];
unset($_SESSION['flash_pro']);

$want = $_GET['feature'] ?? '';
$want = (!is_pro() && isset($features[$want])) ? $want : '';
$price = fmt_money(PRO_PRICE_CENTS / 100, 2, PRO_CURRENCY);
$renews = !empty($sub['current_period_end']) ? fmt_date_long(strtotime($sub['current_period_end']), true) : null;

$free = [
    t('Transações, orçamentos, metas e poupanças sem limite'),
    t('Até {n} cartões ativos', ['n' => FREE_CARD_LIMIT]),
    t('Lembretes de pagamento e alertas por email'),
    t('Análise e personalidade financeira'),
    t('Exportar CSV e cópia de segurança completa'),
];
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Finora Pro - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=10">
  <style>
    .main-content .pg-wrap.pro-wrap { max-width: 960px; }
    .pro-need { display: flex; gap: 14px; align-items: flex-start; padding: 16px 18px; margin-bottom: 22px; border-radius: 16px;
                background: var(--fn-gold-soft); border: 1px solid color-mix(in srgb, var(--fn-gold) 35%, transparent); }
    .pro-need i { font-size: 22px; color: var(--fn-gold); line-height: 1.2; }
    .pro-need b { display: block; color: var(--fn-text); font-size: 16px; }
    .pro-need span { color: var(--fn-muted); font-size: 14.5px; }

    .pro-plans { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr); gap: 18px; align-items: stretch; }
    .pro-plan { border-radius: 22px; padding: 26px 26px 24px; display: flex; flex-direction: column; }
    .pro-plan h2 { margin: 0; font-family: var(--fn-font-brand); font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
    .pro-plan .price { margin: 10px 0 18px; font-family: var(--fn-font-brand); font-size: 38px; font-weight: 700; letter-spacing: -1px; line-height: 1; }
    .pro-plan .price small { font-family: var(--fn-font-ui); font-size: 15px; font-weight: 500; letter-spacing: 0; opacity: .75; }
    .pro-plan ul { list-style: none; margin: 0 0 22px; padding: 0; display: flex; flex-direction: column; gap: 11px; flex: 1; }
    .pro-plan li { display: flex; gap: 11px; font-size: 14.5px; line-height: 1.4; }
    .pro-plan li i { flex-shrink: 0; font-size: 17px; line-height: 1.2; }

    .pro-free { background: var(--fn-surface); border: 1px solid var(--fn-line); color: var(--fn-text); }
    .pro-free li i { color: var(--fn-muted); }
    .pro-free .now { margin-top: auto; font-size: 14px; color: var(--fn-muted); text-align: center; padding: 11px; border: 1px dashed var(--fn-line); border-radius: 12px; }

    /* O Pro parece um cartão da Finora: azul-marinho com o dourado do logo */
    .pro-card { position: relative; overflow: hidden; color: #f3e7c8;
                background: radial-gradient(120% 90% at 100% 0%, #24365f 0%, #14213d 55%, #0f1a31 100%);
                border: 1px solid #2a3b62; box-shadow: 0 18px 40px -18px rgba(20, 33, 61, .55); }
    .pro-card::after { content: ''; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 50%;
                       border: 28px solid rgba(226, 176, 74, .10); pointer-events: none; }
    .pro-card h2 { color: #fff; display: flex; align-items: center; gap: 10px; }
    .pro-card .price { color: #e2b04a; }
    .pro-card li { color: #f3e7c8; }
    .pro-card li b { color: #fff; font-weight: 600; }
    .pro-card li span { display: block; font-size: 13px; color: rgba(243, 231, 200, .7); }
    .pro-card li i { color: #e2b04a; }
    .pro-card li.want { margin: -6px -10px; padding: 6px 10px; border-radius: 10px; background: rgba(226, 176, 74, .14); }
    .pro-go { position: relative; z-index: 1; width: 100%; min-height: 50px; border: 0; border-radius: 14px; font-weight: 700; font-size: 16px;
              background: #e2b04a; color: #14213d; cursor: pointer; transition: background .15s, transform .15s; }
    .pro-go:hover { background: #f0c260; }
    .pro-go:active { transform: translateY(1px); }
    .pro-go:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }
    .pro-go[disabled] { opacity: .6; cursor: not-allowed; }
    .pro-fine { position: relative; z-index: 1; margin: 12px 0 0; font-size: 12.5px; color: rgba(243, 231, 200, .7); text-align: center; }
    .pro-fine a { color: #f3e7c8; }

    .pro-status { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; padding: 18px 20px; }
    .pro-status b { color: var(--fn-text); }
    .pro-status p { margin: 2px 0 0; color: var(--fn-muted); font-size: 14px; }
    .pro-test { margin-top: 18px; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; color: var(--fn-muted);
                background: var(--fn-paper); border: 1px dashed var(--fn-line); }
    .pro-test code { color: var(--fn-text); font-size: 13px; }
    @media (max-width: 760px) { .pro-plans { grid-template-columns: 1fr; } .pro-card { order: -1; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage = 'pro'; $sidebarPrefix = '../'; include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="pg-wrap pro-wrap">

  <div class="pg-head">
    <h1><?= te('Finora Pro') ?></h1>
    <p><?= is_pro() ? te('Tens tudo desbloqueado. Obrigado por apoiares a Finora.') : te('Tudo o que já usas continua grátis. O Pro junta as ferramentas que custam mais a manter.') ?></p>
  </div>

  <?php if ($flashText): ?>
    <div class="s-alert s-alert-<?= htmlspecialchars($flashType) ?>" role="status"><i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : ($flashType === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i><span><?= htmlspecialchars($flashText) ?></span></div>
  <?php endif; ?>

  <?php if ($want): [$ico, $name, $desc] = $features[$want]; ?>
    <div class="pro-need" role="status">
      <i class="bi bi-<?= $ico ?>" aria-hidden="true"></i>
      <div><b><?= te('{feature} é do Finora Pro', ['feature' => $name]) ?></b><span><?= htmlspecialchars($desc) ?></span></div>
    </div>
  <?php endif; ?>

  <?php if (is_pro()): ?>
    <section class="pg-card" aria-labelledby="pro-sub" style="--tone:var(--fn-gold)">
      <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-gem"></i></span>
        <div><h2 id="pro-sub"><?= te('A tua subscrição') ?></h2><p><?= te('Finora Pro, {price} por mês', ['price' => $price]) ?></p></div></header>
      <div class="pro-status">
        <div>
          <?php if (($sub['status'] ?? '') === 'comp'): ?>
            <b><?= te('Pro oferecido') ?></b><p><?= te('Esta conta tem o Pro sem pagamento.') ?></p>
          <?php elseif (!empty($sub['cancel_at_period_end'])): ?>
            <b><?= te('Cancelada') ?></b><p><?= $renews ? te('Manténs o Pro até {date}. Depois voltas ao plano grátis, sem perder dados.', ['date' => $renews]) : te('Voltas ao plano grátis no fim do período pago.') ?></p>
          <?php elseif (($sub['status'] ?? '') === 'past_due'): ?>
            <b><?= te('Pagamento em atraso') ?></b><p><?= te('O último pagamento falhou. Atualiza o cartão para não perderes o Pro.') ?></p>
          <?php else: ?>
            <b><?= te('Ativa') ?></b><p><?= $renews ? te('Renova a {date}.', ['date' => $renews]) : te('Renova todos os meses.') ?></p>
          <?php endif; ?>
        </div>
        <?php if (!empty($sub['stripe_customer_id'])): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="portal">
            <button class="btn-s" type="submit"><i class="bi bi-box-arrow-up-right"></i> <?= te('Gerir subscrição') ?></button></form>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="pro-plans">
    <section class="pro-plan pro-free" aria-labelledby="plan-free">
      <h2 id="plan-free"><?= te('Grátis') ?></h2>
      <div class="price"><?= fmt_money(0, 0, PRO_CURRENCY) ?></div>
      <ul>
        <?php foreach ($free as $f): ?><li><i class="bi bi-check2" aria-hidden="true"></i><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
      </ul>
      <?php if (!is_pro()): ?><div class="now"><?= te('É o teu plano atual') ?></div><?php endif; ?>
    </section>

    <section class="pro-plan pro-card" aria-labelledby="plan-pro">
      <h2 id="plan-pro"><i class="bi bi-gem" aria-hidden="true"></i> <?= te('Finora Pro') ?></h2>
      <div class="price"><?= htmlspecialchars($price) ?> <small><?= te('/ mês') ?></small></div>
      <ul>
        <li><i class="bi bi-plus-lg" aria-hidden="true"></i><div><b><?= te('Tudo o que o plano grátis tem') ?></b></div></li>
        <?php foreach ($features as $key => [$ico, $name, $desc]): ?>
          <li class="<?= $key === $want ? 'want' : '' ?>"><i class="bi bi-<?= $ico ?>" aria-hidden="true"></i><div><b><?= htmlspecialchars($name) ?></b><span><?= htmlspecialchars($desc) ?></span></div></li>
        <?php endforeach; ?>
      </ul>
      <?php if (!is_pro()): ?>
        <form method="post" id="proCheckout"><?= csrf_field() ?><input type="hidden" name="action" value="checkout">
          <button class="pro-go" type="submit" <?= stripe_configured() ? '' : 'disabled' ?>><?= te('Passar para o Pro') ?></button>
        </form>
        <p class="pro-fine"><?= stripe_configured()
            ? te('Pagamento seguro pelo Stripe. Cancelas quando quiseres e manténs o Pro até ao fim do mês pago.')
            : te('Os pagamentos ainda não estão disponíveis.') ?></p>
      <?php endif; ?>
    </section>
  </div>

  <?php if (!is_pro() && stripe_configured() && stripe_test_mode()): ?>
    <p class="pro-test"><i class="bi bi-cone-striped" aria-hidden="true"></i>
      <?= te('Modo de teste: nenhum pagamento é real. Usa o cartão') ?> <code>4242 4242 4242 4242</code>, <?= te('uma data futura e qualquer CVC.') ?></p>
  <?php endif; ?>

</div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Um clique chega: o botão fica desativado enquanto o Stripe prepara o pagamento
  document.getElementById('proCheckout')?.addEventListener('submit', e => {
    const b = e.target.querySelector('button');
    if (b.disabled) { e.preventDefault(); return; }
    setTimeout(() => { b.disabled = true; }, 0);
  });
</script>
</body>
</html>
