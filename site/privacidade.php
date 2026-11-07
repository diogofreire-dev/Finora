<?php
// site/privacidade.php — política de privacidade (pública). Escrita a partir do que a app guarda de facto:
// se uma funcionalidade nova guardar ou enviar dados pessoais, esta página tem de ser atualizada.
require_once __DIR__ . '/helpers/brand.php';
require_once __DIR__ . '/helpers/format.php';
@include_once __DIR__ . '/../config/brevo.php';
$contact = defined('APP_CONTACT_EMAIL') ? APP_CONTACT_EMAIL : '';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$updated = fmt_date_long(mktime(0, 0, 0, 11, 3, 2026), true);

// Quem recebe dados para a Finora funcionar (subcontratantes / serviços externos)
$partners = [
    ['Brevo',            t('Envio de emails (verificação, recuperação de palavra-passe, alertas).'), t('O teu email, nome e o conteúdo do email.')],
    ['Google',           t('Entrar com a conta Google, se escolheres essa opção.'),                 t('O teu email, nome e identificador Google.')],
    ['Groq',             t('Responder às perguntas no Assistente.'),                                t('A tua pergunta e um resumo dos gastos do mês (categorias, descrições e valores). Sem nome nem email.')],
    [t('VIES (Comissão Europeia)'), t('Saber o nome da empresa quando lês o QR de uma fatura.'), t('Só o NIF da empresa que emitiu a fatura.')],
    ['Yahoo Finance',    t('Cotações na Bolsa e nos Investimentos.'),                               t('Só os símbolos (ex.: VWCE.DE). Nada sobre ti.')],
    [t('Frankfurter (BCE)'), t('Câmbios para converter cotações para euros.'),                         t('Nada sobre ti.')],
    [t('jsDelivr e Google Fonts'), t('Ícones, estilos e tipos de letra das páginas.'),                 t('O teu endereço IP e o browser, como em qualquer pedido na Internet.')],
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
  <title><?= te('Privacidade — Finora') ?></title>
  <meta name="description" content="<?= te('Que dados a Finora guarda, para quê, com quem os partilha e como os apagas.') ?>">
  <meta name="theme-color" content="#14213d">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css"
        integrity="sha384-fFxL8wXRpg9gVqGpY+URMtLr3fLL0WBbo4NBQ+IWwMDYjIjI5VQ46XlJm5+BUlXJ" crossorigin="anonymous">
  <link rel="stylesheet" href="css/landing.css?v=5">
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
    .pv-table { width: 100%; margin-top: 16px; border-collapse: collapse; font-size: 15px; background: var(--ln-surface); border: 1px solid var(--ln-line); border-radius: 14px; overflow: hidden; }
    .pv-table th, .pv-table td { text-align: left; vertical-align: top; padding: 12px 14px; border-top: 1px solid var(--ln-line); }
    .pv-table thead th { border-top: 0; font-size: 13px; font-weight: 600; color: var(--ln-muted); background: var(--ln-tint); }
    .pv-table td:first-child { font-weight: 600; white-space: nowrap; }
    @media (max-width: 640px) {
      .pv-table thead { display: none; }
      .pv-table tr { display: block; padding: 12px 14px; border-top: 1px solid var(--ln-line); }
      .pv-table tr:first-child { border-top: 0; }
      .pv-table td { display: block; padding: 2px 0; border: 0; white-space: normal !important; }
      .pv-table td:nth-child(3) { color: var(--ln-muted); }
      .ln-links { display: none; }
    }
  </style>
</head>
<body>

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
  <h1><?= te('Privacidade') ?></h1>
  <p class="pv-lead"><?= te('O que a Finora guarda sobre ti, para quê, com quem partilha e como apagas tudo.') ?></p>
  <p class="pv-date"><?= te('Última atualização: {date}', ['date' => $updated]) ?></p>
  <?php if (current_lang() !== 'pt-PT'): ?><p class="pv-date"><?= te('Esta é uma tradução. Em caso de dúvida, vale a versão em português.') ?></p><?php endif; ?>

  <div class="pv-short">
    <h2><?= te('Em resumo') ?></h2>
    <ul>
      <li><?= te('Guardamos só o que precisas para usar a app: a tua conta e os dados financeiros que tu registas.') ?></li>
      <li><?= te('Não vendemos dados, não mostramos anúncios e não usamos cookies de publicidade ou de estatísticas.') ?></li>
      <li><?= te('Podes exportar os teus dados e apagar a conta a qualquer momento, nas Definições.') ?></li>
    </ul>
  </div>

  <section id="quem">
    <h2><?= te('Quem é responsável') ?></h2>
    <p><?= te('A Finora é um projeto de Diogo Freire e Jandro Antunes, que decidem como os dados são tratados.') ?>
      <?php if ($contact !== ''): ?><?= t('Para qualquer pedido sobre os teus dados escreve para {email}.', ['email' => '<a href="mailto:' . htmlspecialchars($contact) . '">' . htmlspecialchars($contact) . '</a>']) ?><?php endif; ?></p>
  </section>

  <section id="dados">
    <h2><?= te('Que dados guardamos') ?></h2>
    <ul>
      <li><b><?= te('Conta:') ?></b> <?= te('nome, email, palavra-passe (guardada como hash, nunca em texto), foto de perfil se a carregares e, se entrares com o Google, o identificador da tua conta Google.') ?></li>
      <li><b><?= te('O que registas:') ?></b> <?= te('cartões (nome e limite — nunca o número do cartão) e pagamentos de extrato, transações, orçamentos, lembretes, poupanças, metas, investimentos e a lista de títulos que segues.') ?></li>
      <li><b><?= te('Preferências:') ?></b> <?= te('tema, notificações e as notificações geradas para ti.') ?></li>
      <li><b><?= te('Segurança:') ?></b> <?= te('tentativas de entrada falhadas e pedidos ao Assistente (só a hora, para limitar abusos). Os endereços IP usados para travar ataques são guardados como hash e apagados ao fim de 1 dia.') ?></li>
    </ul>
    <p><?= te('A câmara do Ler fatura funciona só no teu browser: a imagem nunca é enviada, apenas o texto do QR lido.') ?></p>
  </section>

  <section id="para-que">
    <h2><?= te('Para que os usamos') ?></h2>
    <ul>
      <li><?= te('Para a app funcionar: mostrar os teus gastos, calcular orçamentos e metas, avisar-te de pagamentos. A base legal é a execução do serviço que pediste ao criar conta.') ?></li>
      <li><?= te('Para te enviar emails necessários (confirmar o email, recuperar a palavra-passe, avisar de alterações à conta) e os alertas que ligares.') ?></li>
      <li><?= te('Para proteger as contas contra acessos indevidos (interesse legítimo).') ?></li>
    </ul>
    <p><?= te('Não fazemos perfis para publicidade nem tomamos decisões automáticas com efeitos sobre ti.') ?></p>
  </section>

  <section id="partilha">
    <h2><?= te('Com quem partilhamos') ?></h2>
    <p><?= te('Só com os serviços abaixo, e só o necessário para cada funcionalidade. Alguns ficam fora da União Europeia (por exemplo nos EUA), com as garantias previstas no RGPD.') ?></p>
    <table class="pv-table">
      <thead><tr><th scope="col"><?= te('Serviço') ?></th><th scope="col"><?= te('Para quê') ?></th><th scope="col"><?= te('O que recebe') ?></th></tr></thead>
      <tbody>
        <?php foreach ($partners as [$name, $why, $what]): ?>
          <tr><td><?= htmlspecialchars($name) ?></td><td><?= htmlspecialchars($why) ?></td><td><?= htmlspecialchars($what) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p><?= te('A empresa que aloja o site e a base de dados também tem acesso técnico aos dados guardados.') ?></p>
  </section>

  <section id="tempo">
    <h2><?= te('Quanto tempo guardamos') ?></h2>
    <ul>
      <li><?= te('Os dados da conta e o que registas: enquanto tiveres conta. Quando a apagas, tudo é apagado de imediato.') ?></li>
      <li><?= te('Notificações já lidas: 6 meses.') ?></li>
      <li><?= te('Registos de segurança (tentativas por IP): 1 dia.') ?></li>
    </ul>
  </section>

  <section id="cookies">
    <h2><?= te('Cookies e armazenamento no browser') ?></h2>
    <ul>
      <li><?= te('Um cookie de sessão, essencial para te manter com sessão iniciada. É apagado quando sais.') ?></li>
      <li><?= te('No armazenamento local do browser: o tema (claro ou escuro) e quais tutoriais já viste.') ?></li>
    </ul>
    <p><?= te('Como são todos essenciais ou preferências tuas, não pedimos consentimento para eles.') ?></p>
  </section>

  <section id="seguranca">
    <h2><?= te('Como protegemos os dados') ?></h2>
    <p><?= te('Palavras-passe com hash, proteção contra pedidos forjados (CSRF), limite de tentativas no login, cada conta só acede aos seus próprios dados e emails de aviso quando algo importante muda na conta.') ?></p>
  </section>

  <section id="direitos">
    <h2><?= te('Os teus direitos') ?></h2>
    <ul>
      <li><b><?= te('Ver e levar os teus dados:') ?></b> <?= t('em {link} exportas as transações em CSV ou PDF.', ['link' => '<a href="pages/dados.php">' . te('Dados e relatórios') . '</a>']) ?></li>
      <li><b><?= te('Corrigir:') ?></b> <?= t('podes editar tudo o que registaste e o teu perfil nas {link}.', ['link' => '<a href="pages/settings.php">' . te('Definições') . '</a>']) ?></li>
      <li><b><?= te('Apagar:') ?></b> <?= t('em {link}. É imediato e não dá para voltar atrás.', ['link' => '<a href="pages/settings.php#apagar-conta">' . te('Definições › Apagar conta') . '</a>']) ?></li>
      <li><b><?= te('Opor-te ou limitar:') ?></b> <?= te('desliga as notificações ou não uses o Assistente e o Ler fatura — nenhum dado vai para esses serviços.') ?></li>
      <li><b><?= te('Reclamar:') ?></b> <?= te('se achares que os teus dados não foram bem tratados, podes queixar-te à') ?> <a href="https://www.cnpd.pt" rel="noopener">CNPD</a>.</li>
    </ul>
  </section>

  <section id="alteracoes">
    <h2><?= te('Alterações a esta política') ?></h2>
    <p><?= te('Se mudarmos o que guardamos ou com quem partilhamos, atualizamos esta página e a data no topo. Mudanças importantes são avisadas na app.') ?></p>
  </section>
</main>

<footer class="ln-footer">
  <div class="ln-wrap ln-footer-inner">
    <a href="index.php" class="ln-brand" aria-label="<?= te('Finora — início') ?>"><?= finora_logo('ln-logo') ?></a>
    <nav aria-label="<?= te('Rodapé') ?>">
      <a href="index.php"><?= te('Início') ?></a>
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
</body>
</html>
