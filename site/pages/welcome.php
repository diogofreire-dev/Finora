<?php
// site/pages/welcome.php — boas-vindas de uma conta nova: língua, moeda, tema, rendimento e alertas.
// O dashboard manda para aqui enquanto user_settings.onboarded = 0 (ver helpers/auth.php).
// Tudo o que se escolhe aqui também se muda depois nas Definições.
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/brand.php';
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/notifications.php';

$uid = (int) $_SESSION['user_id'];

// Por onde começar depois de guardar: só estes destinos (nada que venha do pedido vai parar a um Location)
const WELCOME_START = [
    'card'   => ['add_card.php',           'credit-card-2-front', 'Adicionar um cartão',   'Escolhe o banco e o limite. Não é preciso o número do cartão.'],
    'expense'=> ['create_transaction.php', 'receipt',             'Registar um gasto',     'O que gastaste hoje, à mão ou a partir do QR de uma fatura.'],
    'budget' => ['add_budget.php',         'pie-chart',           'Criar um orçamento',    'Quanto queres gastar por mês, no total ou numa categoria.'],
    'home'   => ['../dashboard.php',       'grid-1x2',            'Ver o painel',          'Começa pelo resumo e explora a app ao teu ritmo.'],
];
const WELCOME_MAX_INCOME = 10000000;

// As definições podem ainda não existir (conta acabada de criar)
$pdo->prepare('INSERT IGNORE INTO user_settings (user_id, language, onboarded) VALUES (:u, :l, 0)')
    ->execute([':u' => $uid, ':l' => current_lang()]);
$st = $pdo->prepare('SELECT u.username, s.theme, s.currency, s.monthly_income, s.notif_prefs
                     FROM users u JOIN user_settings s ON s.user_id = u.id WHERE u.id = :u');
$st->execute([':u' => $uid]);
$me = $st->fetch();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'lang') {
        // Mudar de língua recarrega a página já traduzida; o resto ainda não foi escolhido
        $lang = $_POST['language'] ?? '';
        if (isset(LANGS[$lang])) {
            $pdo->prepare('UPDATE user_settings SET language = :l WHERE user_id = :u')->execute([':l' => $lang, ':u' => $uid]);
            remember_lang($lang);
        }
        header('Location: welcome.php');
        exit;
    }

    if ($action === 'skip') {
        // Saltar: fica tudo como está (língua detetada, euro, tema claro) e não volta a aparecer
        $pdo->prepare('UPDATE user_settings SET onboarded = 1 WHERE user_id = :u')->execute([':u' => $uid]);
        header('Location: ../dashboard.php');
        exit;
    }

    $lang     = $_POST['language'] ?? '';
    $currency = $_POST['currency'] ?? '';
    $theme    = valid_theme($_POST['theme'] ?? '');
    $start    = $_POST['start'] ?? 'home';
    $incomeIn = trim(str_replace([' ', "\u{00A0}"], '', (string) ($_POST['monthly_income'] ?? '')));
    $income   = null;
    if ($incomeIn !== '') {
        $incomeIn = str_replace(',', '.', $incomeIn);
        if (!is_numeric($incomeIn) || (float) $incomeIn < 0 || (float) $incomeIn > WELCOME_MAX_INCOME) {
            $error = t('O rendimento tem de ser um número entre 0 e 10 000 000, ou ficar vazio.');
        } else {
            $income = round((float) $incomeIn, 2);
        }
    }
    if (!isset(LANGS[$lang]) || !isset(CURRENCIES[$currency]) || !isset(WELCOME_START[$start])) {
        $error = t('Escolhe uma opção de cada lista.');
    }

    if ($error === '') {
        // Junta o alerta por email às preferências que já existam (não apaga as outras)
        $prefs = json_decode((string) $me['notif_prefs'], true) ?: [];
        $prefs['budget_email'] = isset($_POST['budget_email']);
        $pdo->prepare('UPDATE user_settings SET language = :l, currency = :c, theme = :t, monthly_income = :i,
                              notif_prefs = :p, onboarded = 1 WHERE user_id = :u')
            ->execute([':l' => $lang, ':c' => $currency, ':t' => $theme, ':i' => $income,
                       ':p' => json_encode($prefs), ':u' => $uid]);
        remember_lang($lang);
        header('Location: ' . WELCOME_START[$start][0]);
        exit;
    }
}

$theme    = valid_theme($me['theme'] ?? 'light');
$currency = valid_currency($_POST['currency'] ?? $me['currency'] ?? 'EUR');
$income   = $_POST['monthly_income'] ?? ($me['monthly_income'] !== null ? (string) (float) $me['monthly_income'] : '');
$budgetEmail = $_SERVER['REQUEST_METHOD'] === 'POST' ? isset($_POST['budget_email']) : notif_prefs($pdo, $uid)['budget_email'];
$start    = $_POST['start'] ?? 'card';
$firstName = explode(' ', trim($me['username']))[0];
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?= $theme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Bem-vindo - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <!-- sidebar.css tem as cores (--fn-*) e as letras da app, mesmo sem o menu -->
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=10">
  <style>
    body { background: var(--fn-paper); }
    .wl { max-width: 720px; margin: 0 auto; padding: 28px 16px 64px; }
    .wl-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .wl-top .fn-logo { height: 30px; width: auto; }
    .wl-head h1 { font-family: var(--fn-font-brand); font-weight: 700; font-size: 32px; letter-spacing: -0.6px; color: var(--fn-ink); margin: 0 0 6px; }
    .wl-head p { color: var(--fn-muted); font-size: 16px; margin: 0 0 26px; }
    .wl .pg-card { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; margin: 0 0 16px; }
    .wl-q { padding: 18px 20px 20px; min-width: 0; }
    /* legend com float (como no Bootstrap) fica dentro do cartão em vez de em cima da borda */
    .wl-q legend { float: left; width: 100%; display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; color: var(--fn-text); margin-bottom: 6px; }
    .wl-q legend::before { content: ""; width: 10px; height: 10px; border-radius: 3px; background: var(--tone, var(--fn-accent)); flex-shrink: 0; }
    .wl-q legend + * { clear: left; }
    .wl-q .help { font-size: 14px; color: var(--fn-muted); margin: 0 0 14px; }

    /* Opções em cartões: o rádio fica escondido e o cartão inteiro é o alvo */
    .wl-opts { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
    .wl-opts.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .wl-opt { position: relative; margin: 0; cursor: pointer; }
    .wl-opt input { position: absolute; opacity: 0; pointer-events: none; }
    .wl-opt > span { display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 10px 14px; border-radius: 12px;
      border: 2px solid var(--fn-line); background: var(--fn-surface); color: var(--fn-text); font-size: 15px; font-weight: 600; transition: border-color .15s, background .15s; }
    .wl-opt > span small { display: block; font-weight: 400; font-size: 13px; color: var(--fn-muted); }
    .wl-opt .sym { flex-shrink: 0; min-width: 40px; height: 32px; padding: 0 6px; border-radius: 8px; display: grid; place-items: center;
      background: color-mix(in srgb, var(--tone, var(--fn-accent)) 14%, transparent); color: var(--tone, var(--fn-accent)); font-weight: 800; font-size: 14px; }
    .wl-opt:hover > span { border-color: var(--fn-muted); }
    .wl-opt input:checked + span { border-color: var(--tone, var(--fn-accent)); background: color-mix(in srgb, var(--tone, var(--fn-accent)) 7%, var(--fn-surface)); }
    .wl-opt input:focus-visible + span { outline: 2px solid var(--fn-gold); outline-offset: 2px; }

    .wl-start .wl-opt > span { align-items: flex-start; }
    .wl-start .wl-opt i { font-size: 20px; color: var(--tone, var(--fn-accent)); margin-top: 2px; }

    .wl-income { display: flex; align-items: center; gap: 8px; max-width: 280px; }
    .wl-income .form-control { font-size: 16px; }
    .wl-income .cur { font-weight: 700; color: var(--fn-muted); }
    .wl-switch { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .wl-switch label { font-size: 15px; color: var(--fn-text); margin: 0; }

    .wl-error { padding: 12px 16px; border-radius: 12px; background: #fef3f2; border: 1px solid #fecdca; color: #b42318; margin-bottom: 18px; font-size: 14px; }
    [data-theme="dark"] .wl-error { background: rgba(253,162,155,.1); border-color: rgba(253,162,155,.25); color: #fda29b; }
    .wl-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-top: 8px; }
    .wl-go { min-height: 48px; padding: 0 28px; border: 0; border-radius: 12px; background: var(--fn-accent); color: #fff; font: 600 16px var(--fn-font-ui); cursor: pointer; }
    .wl-go:hover { background: var(--fn-accent-hover); }
    .wl-go:focus-visible { outline: 2px solid var(--fn-gold); outline-offset: 2px; }
    .wl-skip { background: none; border: 0; color: var(--fn-muted); font-size: 14px; text-decoration: underline; padding: 8px 0; cursor: pointer; }
    @media (max-width: 520px) {
      .wl-head h1 { font-size: 26px; }
      .wl-opts { grid-template-columns: repeat(2, minmax(0, 1fr)); }   /* moedas: duas por linha */
      .wl-opt > span { padding: 10px; gap: 8px; font-size: 14px; }
      .wl-opt .sym { min-width: 34px; }
      .wl-opts.two { grid-template-columns: 1fr; }
      .wl-go { width: 100%; }
    }
  </style>
</head>
<body>
<main class="wl">
  <div class="wl-top">
    <?= finora_logo('fn-logo') ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="skip">
      <button type="submit" class="wl-skip"><?= te('Saltar por agora') ?></button></form>
  </div>

  <div class="wl-head">
    <h1><?= te('Bem-vindo à Finora, {name}!', ['name' => $firstName]) ?></h1>
    <p><?= te('Algumas perguntas rápidas para a app ficar à tua medida. Podes mudar tudo depois nas Definições.') ?></p>
  </div>

  <?php if ($error): ?><div class="wl-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Língua: muda logo a página para a língua escolhida -->
  <form method="post" id="langForm" class="pg-card wl-q" style="--tone: var(--cat-1)">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="lang">
    <fieldset>
      <legend><?= te('Em que língua queres usar a Finora?') ?></legend>
      <div class="wl-opts two">
        <?php foreach (LANGS as $code => [$name]): ?>
          <label class="wl-opt" lang="<?= LANGS[$code][1] ?>">
            <input type="radio" name="language" value="<?= $code ?>" <?= current_lang() === $code ? 'checked' : '' ?>>
            <span><span class="sym"><?= $code === 'en' ? 'EN' : 'PT' ?></span><?= htmlspecialchars($name) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <noscript><button type="submit" class="wl-go" style="margin-top:12px"><?= te('Mudar língua') ?></button></noscript>
    </fieldset>
  </form>

  <form method="post" id="welcomeForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="language" value="<?= current_lang() ?>">

    <fieldset class="pg-card wl-q" style="--tone: var(--cat-3)">
      <legend><?= te('Que moeda usas?') ?></legend>
      <p class="help"><?= te('Os valores aparecem nesta moeda em toda a app.') ?></p>
      <div class="wl-opts">
        <?php foreach (CURRENCIES as $code => [$sym, $name]): ?>
          <label class="wl-opt">
            <input type="radio" name="currency" value="<?= $code ?>" <?= $currency === $code ? 'checked' : '' ?>>
            <span><span class="sym"><?= htmlspecialchars($sym) ?></span><?= te($name) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="pg-card wl-q" style="--tone: var(--cat-7)">
      <legend><?= te('Claro ou escuro?') ?></legend>
      <div class="wl-opts two">
        <?php foreach (['light' => [t('Claro'), 'sun'], 'dark' => [t('Escuro'), 'moon-stars']] as $val => [$label, $ico]): ?>
          <label class="wl-opt">
            <input type="radio" name="theme" value="<?= $val ?>" <?= $theme === $val ? 'checked' : '' ?>>
            <span><span class="sym"><i class="bi bi-<?= $ico ?>" aria-hidden="true"></i></span><?= htmlspecialchars($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="pg-card wl-q" style="--tone: var(--cat-4)">
      <legend><label for="wlIncome"><?= te('Quanto recebes por mês? (opcional)') ?></label></legend>
      <p class="help" id="wlIncomeHelp"><?= te('Rendimento líquido, já sem impostos. Serve para prever as tuas metas e fica só na tua conta.') ?></p>
      <div class="wl-income">
        <input id="wlIncome" type="text" name="monthly_income" class="form-control" inputmode="decimal" autocomplete="off"
               placeholder="1200" value="<?= htmlspecialchars((string) $income) ?>" aria-describedby="wlIncomeHelp">
        <span class="cur" id="wlCur"><?= htmlspecialchars(currency_symbol($currency)) ?></span>
      </div>
    </fieldset>

    <fieldset class="pg-card wl-q" style="--tone: var(--cat-2)">
      <legend><?= te('Avisos') ?></legend>
      <div class="wl-switch">
        <label for="wlBudgetEmail"><?= te('Avisar-me por email quando um orçamento chegar aos 80%') ?></label>
        <input type="checkbox" role="switch" class="st-switch" id="wlBudgetEmail" name="budget_email" <?= $budgetEmail ? 'checked' : '' ?>>
      </div>
      <p class="help" style="margin:10px 0 0"><?= te('Os lembretes de pagamento têm o seu próprio aviso, em cada lembrete.') ?></p>
    </fieldset>

    <fieldset class="pg-card wl-q wl-start" style="--tone: var(--cat-5)">
      <legend><?= te('Por onde queres começar?') ?></legend>
      <div class="wl-opts two">
        <?php foreach (WELCOME_START as $key => [, $ico, $label, $desc]): ?>
          <label class="wl-opt">
            <input type="radio" name="start" value="<?= $key ?>" <?= $start === $key ? 'checked' : '' ?>>
            <span><i class="bi bi-<?= $ico ?>" aria-hidden="true"></i><span><?= te($label) ?><small><?= te($desc) ?></small></span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="wl-actions">
      <button type="submit" class="wl-go"><?= te('Guardar e começar') ?></button>
    </div>
  </form>
</main>

<script>
(function () {
  // Língua: guarda e recarrega a página traduzida
  document.querySelectorAll('#langForm input[name="language"]').forEach(function (r) {
    r.addEventListener('change', function () { document.getElementById('langForm').submit(); });
  });
  // Tema: pré-visualização imediata
  document.querySelectorAll('#welcomeForm input[name="theme"]').forEach(function (r) {
    r.addEventListener('change', function () { document.documentElement.setAttribute('data-theme', r.value); });
  });
  // Símbolo ao lado do rendimento acompanha a moeda escolhida
  var symbols = <?= json_encode(array_map(fn($c) => $c[0], CURRENCIES), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  document.querySelectorAll('#welcomeForm input[name="currency"]').forEach(function (r) {
    r.addEventListener('change', function () { document.getElementById('wlCur').textContent = symbols[r.value] || ''; });
  });
})();
</script>
</body>
</html>
