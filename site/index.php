<?php
// site/index.php — página inicial pública
require_once __DIR__ . '/helpers/brand.php';
require_once __DIR__ . '/helpers/i18n.php';

// Endereço público do site (o mesmo dos links nos emails). As redes sociais precisam de URLs absolutos.
// Vem da configuração e não do cabeçalho Host, que quem faz o pedido pode inventar.
@include_once __DIR__ . '/../config/brevo.php';
$siteUrl = rtrim(defined('APP_URL') ? APP_URL : '', '/');

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$features = [
    ['icon' => 'credit-card-2-front', 'title' => t('Cartões'),             'text' => t('Todos os teus cartões num só sítio, com o limite e o que ainda tens disponível.')],
    ['icon' => 'receipt',             'title' => t('Transações'),          'text' => t('Regista cada gasto com categoria e cartão. Pesquisa por texto, mês ou categoria.')],
    ['icon' => 'qr-code-scan',        'title' => t('Ler fatura'),          'text' => t('Aponta a câmara ao QR de uma fatura portuguesa e o gasto fica registado pelo NIF.')],
    ['icon' => 'pie-chart',           'title' => t('Orçamentos'),          'text' => t('Define um limite por categoria e vê, dia a dia, quanto ainda podes gastar.')],
    ['icon' => 'piggy-bank',          'title' => t('Poupanças e metas'),   'text' => t('Põe dinheiro de lado num mealheiro e vê se chegas a cada meta a tempo.')],
    ['icon' => 'calendar-check',      'title' => t('Lembretes'),           'text' => t('Rendas e subscrições num calendário, com aviso antes de cada pagamento.')],
    ['icon' => 'bar-chart-line',      'title' => t('Análise'),             'text' => t('Para onde foi o dinheiro este mês, comparado com o anterior.')],
    ['icon' => 'graph-up-arrow',      'title' => t('Investimentos'),       'text' => t('Regista as tuas posições e acompanha quanto valem.')],
    ['icon' => 'stars',               'title' => t('Assistente'),          'text' => t('Faz perguntas sobre os teus gastos e recebe sugestões concretas para poupar.')],
];
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="light">
<head>
<script>
  (function () {
    var t = 'light';
    try { t = localStorage.getItem('fc_theme') === 'dark' ? 'dark' : 'light'; } catch (e) {}
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Finora — os teus cartões e gastos, com clareza') ?></title>
  <meta name="description" content="<?= te('A Finora é uma aplicação gratuita para gerir cartões, gastos, orçamentos e lembretes num só sítio. Feita em Portugal.') ?>">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#14213d">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<?php if ($siteUrl !== ''): ?>
  <link rel="canonical" href="<?= htmlspecialchars($siteUrl) ?>/">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($siteUrl) ?>/">
  <meta property="og:image" content="<?= htmlspecialchars($siteUrl) ?>/assets/icon-512.png">
<?php endif; ?>
  <meta property="og:title" content="<?= te('Finora — os teus cartões e gastos, com clareza') ?>">
  <meta property="og:description" content="<?= te('Cartões, gastos, orçamentos e lembretes num só sítio. Grátis.') ?>">
  <meta property="og:locale" content="<?= current_lang() === 'en' ? 'en_GB' : 'pt_PT' ?>">
  <meta property="og:site_name" content="Finora">
  <meta name="twitter:card" content="summary">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css"
        integrity="sha384-fFxL8wXRpg9gVqGpY+URMtLr3fLL0WBbo4NBQ+IWwMDYjIjI5VQ46XlJm5+BUlXJ" crossorigin="anonymous">
  <link rel="stylesheet" href="css/landing.css?v=5">
  <link rel="modulepreload" href="js/lib/three/three.module.min.js">
  <script type="importmap">
  { "imports": { "three": "./js/lib/three/three.module.min.js", "three/addons/": "./js/lib/three/addons/" } }
  </script>
</head>
<body>

<header class="ln-nav">
  <div class="ln-wrap ln-nav-inner">
    <a href="index.php" class="ln-brand" aria-label="<?= te('Finora — início') ?>"><?= finora_logo('ln-logo') ?></a>
    <nav class="ln-links" aria-label="<?= te('Secções') ?>">
      <a href="#funcionalidades"><?= te('Funcionalidades') ?></a>
      <a href="#seguranca"><?= te('Segurança') ?></a>
      <a href="#quem-somos"><?= te('Quem somos') ?></a>
    </nav>
    <div class="ln-actions">
      <?php $__other = current_lang() === 'en' ? 'pt-PT' : 'en'; ?>
      <a href="<?= htmlspecialchars(lang_url($__other)) ?>" class="ln-icon-btn ln-lang" lang="<?= LANGS[$__other][1] ?>" hreflang="<?= LANGS[$__other][1] ?>" title="<?= htmlspecialchars(LANGS[$__other][0]) ?>"><?= current_lang() === 'en' ? 'PT' : 'EN' ?></a>
      <button type="button" class="ln-icon-btn" id="lnTheme" aria-label="<?= te('Mudar para tema escuro') ?>"><i class="bi bi-moon-stars" aria-hidden="true"></i></button>
      <a href="auth/login.php" class="ln-btn ln-btn-quiet"><?= te('Entrar') ?></a>
      <a href="auth/register.php" class="ln-btn ln-btn-main"><?= te('Criar conta') ?></a>
    </div>
  </div>
</header>

<main>
<?php if (($_GET['conta'] ?? '') === 'apagada'): ?>
  <div class="ln-wrap"><p class="ln-notice" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i> <?= te('A tua conta e todos os dados foram apagados. Obrigado por teres usado a Finora.') ?></p></div>
<?php endif; ?>
  <section class="ln-hero">
    <div class="ln-wrap ln-hero-grid">
      <div class="ln-hero-copy">
        <h1><?= te('Sabe para onde vai cada cêntimo.') ?></h1>
        <p class="ln-lead"><?= te('Junta os teus cartões, regista os gastos e define orçamentos. A Finora mostra-te o mês inteiro num só ecrã, de graça.') ?></p>
        <div class="ln-cta">
          <a href="auth/register.php" class="ln-btn ln-btn-main ln-btn-lg"><?= te('Criar conta grátis') ?></a>
          <a href="auth/login.php" class="ln-btn ln-btn-quiet ln-btn-lg"><?= te('Já tenho conta') ?></a>
        </div>
        <p class="ln-note"><?= te('Sem cartão de crédito para te registares. Entras com email ou com Google.') ?></p>
      </div>

      <div class="ln-stage" id="cardStage">
        <img class="ln-stage-poster" src="assets/3d/finora-card.webp" width="1100" height="786"
             alt="<?= te('Cartão Finora azul-marinho com o logo dourado') ?>" fetchpriority="high">
        <canvas class="ln-stage-canvas" id="cardCanvas" tabindex="0" role="img"
                aria-label="<?= te('Cartão Finora em 3D. Arrasta ou usa as setas para o rodar.') ?>"></canvas>
        <p class="ln-stage-hint" aria-hidden="true"><i class="bi bi-arrows-move"></i> <?= te('Arrasta para rodar') ?></p>
      </div>
    </div>
  </section>

  <section class="ln-section" id="funcionalidades">
    <div class="ln-wrap">
      <div class="ln-head">
        <h2><?= te('Tudo o que a Finora faz') ?></h2>
        <p><?= te('Começa só pelos cartões e pelos gastos. O resto está lá quando precisares.') ?></p>
      </div>
      <ul class="ln-features">
<?php foreach ($features as $f): ?>
        <li>
          <span class="ln-ico"><i class="bi bi-<?= $f['icon'] ?>" aria-hidden="true"></i></span>
          <h3><?= htmlspecialchars($f['title']) ?></h3>
          <p><?= htmlspecialchars($f['text']) ?></p>
        </li>
<?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="ln-section ln-section-tint">
    <div class="ln-wrap">
      <div class="ln-head">
        <h2><?= te('Começa em três passos') ?></h2>
      </div>
      <ol class="ln-steps">
        <li>
          <h3><?= te('Cria a conta') ?></h3>
          <p><?= te('Com email e palavra-passe, ou com a tua conta Google. Confirmas o email com um código.') ?></p>
        </li>
        <li>
          <h3><?= te('Adiciona um cartão') ?></h3>
          <p><?= te('Escolhe o banco, o limite e uma cor. Não precisas de dar o número do cartão.') ?></p>
        </li>
        <li>
          <h3><?= te('Regista o primeiro gasto') ?></h3>
          <p><?= te('Escreve-o à mão ou lê o QR da fatura. O painel começa logo a mostrar o teu mês.') ?></p>
        </li>
      </ol>
    </div>
  </section>

  <section class="ln-security" id="seguranca">
    <div class="ln-wrap ln-security-grid">
      <div>
        <h2><?= te('Os teus dados ficam contigo') ?></h2>
        <p><?= te('A Finora nunca pede o número do cartão nem o acesso ao teu banco. O que registas serve só para ti.') ?></p>
      </div>
      <ul class="ln-sec-list">
        <li><i class="bi bi-shield-lock" aria-hidden="true"></i><div><b><?= te('Palavras-passe cifradas') ?></b><span><?= te('Guardadas com bcrypt. Nem nós as conseguimos ler.') ?></span></div></li>
        <li><i class="bi bi-hourglass-split" aria-hidden="true"></i><div><b><?= te('Contra adivinhas') ?></b><span><?= te('Depois de várias tentativas erradas, a conta e a ligação ficam em pausa.') ?></span></div></li>
        <li><i class="bi bi-envelope-check" aria-hidden="true"></i><div><b><?= te('Email confirmado') ?></b><span><?= te('Cada conta confirma o email com um código antes de entrar.') ?></span></div></li>
        <li><i class="bi bi-person-lock" aria-hidden="true"></i><div><b><?= te('Só tu vês os teus dados') ?></b><span><?= te('Cada pedido é verificado para não dar acesso a dados de outra conta.') ?></span></div></li>
      </ul>
    </div>
  </section>

  <section class="ln-section" id="quem-somos">
    <div class="ln-wrap ln-about">
      <div class="ln-head">
        <h2><?= te('Quem somos') ?></h2>
        <p><?= te('A Finora começou como a nossa Prova de Aptidão Profissional: uma forma simples e gratuita de qualquer pessoa perceber o que gasta.') ?></p>
      </div>
      <ul class="ln-team">
        <li><span class="ln-avatar" aria-hidden="true">DF</span><div><b>Diogo Freire</b><span><?= te('Arquitetura, PHP, base de dados e segurança') ?></span></div></li>
        <li><span class="ln-avatar" aria-hidden="true">JA</span><div><b>Jandro Antunes</b><span><?= te('Design da interface e experiência de utilização') ?></span></div></li>
      </ul>
    </div>
  </section>

  <section class="ln-final">
    <div class="ln-wrap ln-final-inner">
      <h2><?= te('Vê o teu mês com outros olhos.') ?></h2>
      <a href="auth/register.php" class="ln-btn ln-btn-gold ln-btn-lg"><?= te('Criar conta grátis') ?></a>
    </div>
  </section>
</main>

<footer class="ln-footer">
  <div class="ln-wrap ln-footer-inner">
    <a href="index.php" class="ln-brand" aria-label="<?= te('Finora — início') ?>"><?= finora_logo('ln-logo') ?></a>
    <nav aria-label="<?= te('Rodapé') ?>">
      <a href="auth/register.php"><?= te('Criar conta') ?></a>
      <a href="auth/login.php"><?= te('Entrar') ?></a>
      <a href="#seguranca"><?= te('Segurança') ?></a>
      <a href="privacidade.php"><?= te('Privacidade') ?></a>
    </nav>
    <p>&copy; <?= date('Y') ?> Finora. <?= te('Prova de Aptidão Profissional de Diogo Freire e Jandro Antunes.') ?></p>
  </div>
</footer>

<script>
(function () {
  var root = document.documentElement, btn = document.getElementById('lnTheme');
  function paint() {
    var dark = root.dataset.theme === 'dark';
    btn.innerHTML = dark ? '<i class="bi bi-sun" aria-hidden="true"></i>' : '<i class="bi bi-moon-stars" aria-hidden="true"></i>';
    btn.setAttribute('aria-label', dark ? <?= tj('Mudar para tema claro') ?> : <?= tj('Mudar para tema escuro') ?>);
  }
  paint();
  btn.addEventListener('click', function () {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('fc_theme', root.dataset.theme); } catch (e) {}
    paint();
  });
})();
</script>
<script type="module" src="js/landing-card.js?v=1"></script>
</body>
</html>
