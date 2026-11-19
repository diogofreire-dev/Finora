<?php
// site/termos.php — termos de serviço (públicos). Têm de bater certo com o que a app faz:
// preço e regras do plano em helpers/plan.php, pagamento pelo Stripe Managed Payments (vendido através do Link).
// Se mudares o preço, o que o Pro inclui ou a forma de cancelar, atualiza esta página e a data.
require_once __DIR__ . '/helpers/brand.php';
require_once __DIR__ . '/helpers/format.php';
require_once __DIR__ . '/helpers/plan.php';
@include_once __DIR__ . '/../config/brevo.php';
$contact = defined('APP_CONTACT_EMAIL') ? APP_CONTACT_EMAIL : '';
$mail    = $contact !== '' ? '<a href="mailto:' . htmlspecialchars($contact) . '">' . htmlspecialchars($contact) . '</a>' : '';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$updated = fmt_date_long(mktime(0, 0, 0, 11, 14, 2026), true);
$price   = fmt_money(PRO_PRICE_CENTS / 100, 2, PRO_CURRENCY);
$proList = implode(', ', array_map(fn($f) => $f[1], array_values(pro_features())));
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
  <title><?= te('Termos — Finora') ?></title>
  <meta name="description" content="<?= te('As regras de uso da Finora e do Finora Pro: o que inclui, quanto custa, como cancelas e como pedes o reembolso.') ?>">
  <meta name="theme-color" content="#14213d">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css"
        integrity="sha384-fFxL8wXRpg9gVqGpY+URMtLr3fLL0WBbo4NBQ+IWwMDYjIjI5VQ46XlJm5+BUlXJ" crossorigin="anonymous">
  <link rel="stylesheet" href="css/landing.css?v=10">
  <style>
    .pv { max-width: 760px; padding-top: 32px; padding-bottom: 72px; }
    .pv h1 { font: 800 clamp(36px, 5vw, 52px)/1.05 var(--ln-display); letter-spacing: -.03em; color: var(--ln-ink); }
    [data-theme="dark"] .pv h1 { color: #fff; }
    .pv-lead { margin-top: 14px; font-size: 18px; color: var(--ln-muted); max-width: 60ch; }
    .pv-date { margin-top: 8px; font-size: 14px; color: var(--ln-muted); }
    .pv-short { margin: 32px 0 8px; padding: 20px 22px; border-radius: 16px; background: var(--ln-surface); border: 1px solid var(--ln-line); }
    .pv-short h2 { font: 700 18px var(--ln-display); margin-bottom: 10px; }
    .pv-short ul { padding-left: 20px; display: grid; gap: 6px; }
    .pv section { margin-top: 40px; scroll-margin-top: 90px; }
    .pv h2 { font: 700 24px/1.2 var(--ln-display); letter-spacing: -.01em; color: var(--ln-text); }
    .pv p, .pv ul { margin-top: 12px; max-width: 68ch; }
    .pv ul { padding-left: 20px; }
    .pv li + li { margin-top: 6px; }
    .pv a { color: var(--ln-gold); text-underline-offset: 3px; }
    [data-theme="dark"] .pv a { color: var(--ln-gold-hi); }
    @media (max-width: 640px) { .ln-links { display: none; } }
  </style>
</head>
<body>
<?= finora_backdrop() ?>

<header class="ln-nav">
  <div class="ln-wrap ln-nav-inner">
    <a href="index.php" class="ln-brand" aria-label="<?= te('Finora — início') ?>"><?= finora_logo('ln-logo') ?></a>
    <div class="ln-actions">
      <?php $__other = current_lang() === 'en' ? 'pt-PT' : 'en'; ?>
      <a href="<?= htmlspecialchars(lang_url($__other)) ?>" class="ln-icon-btn ln-lang" lang="<?= LANGS[$__other][1] ?>" hreflang="<?= LANGS[$__other][1] ?>" title="<?= htmlspecialchars(LANGS[$__other][0]) ?>"><?= current_lang() === 'en' ? 'PT' : 'EN' ?></a>
      <button type="button" class="ln-icon-btn" id="lnTheme" aria-label="<?= te('Mudar para tema escuro') ?>"><i class="bi bi-moon-stars" aria-hidden="true"></i></button>
      <a href="auth/login.php" class="ln-btn ln-btn-quiet"><?= te('Entrar') ?></a>
    </div>
  </div>
</header>

<main class="ln-wrap pv">
  <h1><?= te('Termos de serviço') ?></h1>
  <p class="pv-lead"><?= te('As regras para usares a Finora e o Finora Pro, escritas para se perceberem.') ?></p>
  <p class="pv-date"><?= te('Última atualização: {date}', ['date' => $updated]) ?></p>
  <?php if (current_lang() !== 'pt-PT'): ?><p class="pv-date"><?= te('Esta é uma tradução. Em caso de dúvida, vale a versão em português.') ?></p><?php endif; ?>

  <div class="pv-short">
    <h2><?= te('Em resumo') ?></h2>
    <ul>
      <li><?= te('A Finora é grátis. O Finora Pro custa {price} por mês, com IVA incluído, e renova todos os meses.', ['price' => $price]) ?></li>
      <li><?= te('Cancelas quando quiseres e manténs o Pro até ao fim do mês pago. Os teus dados nunca são apagados por cancelares.') ?></li>
      <li><?= te('Nos primeiros 14 dias da primeira subscrição podes pedir o reembolso total, sem dar explicações.') ?></li>
      <li><?= te('A Finora ajuda-te a organizar o teu dinheiro, mas não é um banco nem dá aconselhamento financeiro.') ?></li>
    </ul>
  </div>

  <section id="quem">
    <h2><?= te('Quem somos') ?></h2>
    <p><?= te('A Finora é um projeto de Diogo Freire e Jandro Antunes.') ?>
      <?php if ($mail !== ''): ?><?= t('Para qualquer questão sobre estes termos escreve para {email}.', ['email' => $mail]) ?><?php endif; ?></p>
    <p><?= t('Ao criar conta aceitas estes termos e a forma como tratamos os teus dados, descrita na {link}.', ['link' => '<a href="privacidade.php">' . te('Política de privacidade') . '</a>']) ?></p>
  </section>

  <section id="servico">
    <h2><?= te('O que é a Finora') ?></h2>
    <p><?= te('Uma app para registares e perceberes os teus gastos: cartões, transações, orçamentos, poupanças, metas e lembretes. Os valores são os que tu registas; a Finora não se liga ao teu banco nem movimenta dinheiro.') ?></p>
    <p><?= te('As cotações, as previsões das metas e as respostas do Assistente IA são informação para te ajudar a decidir. Podem ter atrasos ou erros e não são aconselhamento financeiro.') ?></p>
  </section>

  <section id="conta">
    <h2><?= te('A tua conta') ?></h2>
    <ul>
      <li><?= te('Usa um email teu e uma palavra-passe que não uses noutros sítios. És responsável pelo que for feito com a tua conta.') ?></li>
      <li><?= te('Se suspeitares que alguém entrou na tua conta, muda a palavra-passe nas Definições e avisa-nos.') ?></li>
      <li><?= t('Podes apagar a conta a qualquer momento em {link}. Se tiveres o Pro, a subscrição é cancelada no mesmo momento.', ['link' => '<a href="pages/settings.php#apagar-conta">' . te('Definições › Apagar conta') . '</a>']) ?></li>
    </ul>
  </section>

  <section id="pro">
    <h2><?= te('Plano grátis e Finora Pro') ?></h2>
    <p><?= te('O plano grátis inclui tudo o que é essencial e não tem prazo. Tem até {n} cartões ativos.', ['n' => FREE_CARD_LIMIT]) ?></p>
    <p><?= te('O Finora Pro acrescenta: {list}.', ['list' => $proList]) ?></p>
    <ul>
      <li><b><?= te('Preço:') ?></b> <?= te('{price} por mês, com IVA incluído. O valor final é o que vês antes de pagar.', ['price' => $price]) ?></li>
      <li><b><?= te('Renovação:') ?></b> <?= te('a subscrição renova automaticamente todos os meses, no mesmo dia, até a cancelares.') ?></li>
      <li><b><?= te('Cancelar:') ?></b> <?= t('em {link} ou na tua conta Link. Manténs o Pro até ao fim do mês já pago; o mês em curso não é reembolsado em parte.', ['link' => '<a href="pages/pro.php">' . te('Finora Pro › Gerir subscrição') . '</a>']) ?></li>
      <li><b><?= te('Voltar ao grátis:') ?></b> <?= te('nada é apagado. As funcionalidades do Pro ficam bloqueadas e os cartões a mais continuam guardados, só não podes ter mais de {n} ativos.', ['n' => FREE_CARD_LIMIT]) ?></li>
      <li><b><?= te('Mudanças de preço:') ?></b> <?= te('avisamos por email pelo menos 30 dias antes. Se não concordares, cancelas antes da data.') ?></li>
    </ul>
  </section>

  <section id="pagamento">
    <h2><?= te('Pagamento') ?></h2>
    <p><?= te('O Finora Pro é vendido através do Link, o serviço de pagamentos do Stripe, que é o vendedor registado da compra. É o Link que cobra, calcula o IVA do teu país, envia os recibos e as faturas e trata das questões de pagamento. No extrato do cartão aparece LINK.COM*.') ?></p>
    <p><?= te('Os dados do cartão são escritos na página do Stripe e nunca passam pela Finora. Na compra aplicam-se também os termos de pagamento mostrados nessa página.') ?></p>
  </section>

  <section id="reembolso">
    <h2><?= te('Desistência e reembolsos') ?></h2>
    <ul>
      <li><?= te('Tens 14 dias a contar da primeira subscrição para desistir. Nesse prazo devolvemos o valor total, mesmo que já tenhas usado o Pro.') ?></li>
      <li><?= te('Para pedir o reembolso, usa o suporte do Link (o link vem no recibo do pagamento)') ?><?php if ($mail !== ''): ?> <?= t('ou escreve-nos para {email}', ['email' => $mail]) ?><?php endif; ?>.</li>
      <li><?= te('Depois desses 14 dias, cancelar evita as próximas cobranças, mas o mês em curso não é reembolsado.') ?></li>
      <li><?= te('Se a Finora deixar de funcionar por culpa nossa durante vários dias, ou se encerrarmos o serviço, devolvemos a parte do mês que não pudeste usar.') ?></li>
    </ul>
  </section>

  <section id="uso">
    <h2><?= te('Uso aceitável') ?></h2>
    <p><?= te('Não podes usar a Finora para:') ?></p>
    <ul>
      <li><?= te('tentar aceder a contas ou dados de outras pessoas;') ?></li>
      <li><?= te('sobrecarregar o serviço com pedidos automáticos ou contornar os limites de uso;') ?></li>
      <li><?= te('enviar conteúdo ilegal ou usar o Assistente IA para fins que não sejam as tuas finanças.') ?></li>
    </ul>
    <p><?= te('Se encontrares uma falha de segurança, conta-nos antes de a divulgares. Agradecemos e corrigimos o mais depressa possível.') ?></p>
    <p><?= te('Podemos suspender uma conta que viole estas regras. Se tiver o Pro, devolvemos a parte do mês que não foi usada, exceto em caso de fraude.') ?></p>
  </section>

  <section id="dados">
    <h2><?= te('Os teus dados') ?></h2>
    <p><?= t('O que registas é teu. Podes exportar tudo em {link}, no plano grátis ou no Pro. A forma como tratamos os dados está na {privacy}.', ['link' => '<a href="pages/dados.php">' . te('Dados e relatórios') . '</a>', 'privacy' => '<a href="privacidade.php">' . te('Política de privacidade') . '</a>']) ?></p>
  </section>

  <section id="disponibilidade">
    <h2><?= te('Disponibilidade e responsabilidade') ?></h2>
    <p><?= te('Fazemos cópias de segurança e cuidamos da segurança do serviço, mas a Finora pode ter interrupções para manutenção ou por falhas. Se decidirmos encerrar o serviço, avisamos com pelo menos 30 dias para exportares os teus dados.') ?></p>
    <p><?= te('Não somos responsáveis por decisões financeiras tomadas com base na Finora, nem por perdas causadas por informação que registaste mal. Nada nestes termos limita os direitos que a lei te dá como consumidor.') ?></p>
  </section>

  <section id="alteracoes">
    <h2><?= te('Alterações a estes termos') ?></h2>
    <p><?= te('Se mudarmos estes termos, atualizamos esta página e a data no topo. Mudanças que te afetem, como o preço ou o que o Pro inclui, são avisadas por email e na app antes de entrarem em vigor.') ?></p>
  </section>

  <section id="lei">
    <h2><?= te('Lei aplicável e litígios') ?></h2>
    <p><?= te('Estes termos regem-se pela lei portuguesa. Se tivermos um desacordo, tentamos primeiro resolvê-lo contigo. Também podes recorrer a um centro de resolução alternativa de litígios de consumo, como o') ?> <a href="https://www.cniacc.pt" rel="noopener">CNIACC</a>.</p>
  </section>
</main>

<footer class="ln-footer">
  <div class="ln-wrap ln-footer-inner">
    <a href="index.php" class="ln-brand" aria-label="<?= te('Finora — início') ?>"><?= finora_logo('ln-logo') ?></a>
    <nav aria-label="<?= te('Rodapé') ?>">
      <a href="index.php"><?= te('Início') ?></a>
      <a href="privacidade.php"><?= te('Privacidade') ?></a>
      <a href="auth/register.php"><?= te('Criar conta') ?></a>
      <a href="auth/login.php"><?= te('Entrar') ?></a>
    </nav>
    <p>&copy; <?= date('Y') ?> Finora.</p>
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
<script src="js/landing-bg.js?v=2" defer></script>
</body>
</html>
