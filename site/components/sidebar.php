<?php
// sidebar.php — cabeçalho fixo + menu lateral (escondido até ser aberto)
// Variáveis necessárias antes do include:
//   $activePage    — 'dashboard','cards','transactions','budgets','reminders','analytics','settings', ...
//   $sidebarPrefix — '' para dashboard.php, '../' para pages/*.php

require_once __DIR__ . '/../helpers/brand.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/theme_helper.php';

$_sp  = $sidebarPrefix ?? '../';
$_ap  = $activePage    ?? '';

function _sidebarClass($page, $current) {
    return $page === $current ? 'sidebar-link active' : 'sidebar-link';
}
function _ariaCurrent($page, $current) {
    return $page === $current ? ' aria-current="page"' : '';
}

// Título mostrado no cabeçalho, para saberes onde estás com o menu fechado
$_pageTitles = [
    'dashboard'    => t('Dashboard'),
    'transactions' => t('Transações'),
    'cards'        => t('Cartões'),
    'budgets'      => t('Orçamentos'),
    'savings'      => t('Poupanças'),
    'futuro'       => t('Metas'),
    'investments'  => t('Investimentos'),
    'bolsa'        => t('Bolsa'),
    'reminders'    => t('Lembretes'),
    'analytics'    => t('Análise'),
    'persona'      => t('Personalidade financeira'),
    'ai'           => t('Assistente IA'),
    'scan_fatura'  => t('Ler fatura'),
    'dados'        => t('Dados e relatórios'),
    'settings'     => t('Definições'),
    'notifications' => t('Notificações'),
    'pro'          => t('Finora Pro'),
];
$_pageTitle = $_pageTitles[$_ap] ?? '';

// Avatar do utilizador
$_avatarPath = null;
if (isset($pdo) && isset($uid)) {
    try {
        $_s = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :uid");
        $_s->execute([':uid' => $uid]);
        $_avatarPath = $_s->fetchColumn();
    } catch (Exception $_e) {}
}
// Só mostrar a foto se o ficheiro existir; ?v= muda quando a foto muda (evita a versão antiga em cache)
$_avatarFile = $_avatarPath ? __DIR__ . '/../' . $_avatarPath : null;
$_avatarPath = ($_avatarFile && is_file($_avatarFile)) ? $_avatarPath . '?v=' . filemtime($_avatarFile) : null;
// Notificações: verificação diária (sem cron) + o que mostrar no sino
require_once __DIR__ . '/../helpers/notifications.php';
$_notifCount = 0; $_notifs = [];
if (isset($pdo) && !empty($_SESSION['user_id'])) {
    try {
        notif_generate($pdo, (int) $_SESSION['user_id']);
        $_notifCount = notif_unread_count($pdo, (int) $_SESSION['user_id']);
        $_notifs     = notif_recent($pdo, (int) $_SESSION['user_id'], 8);
    } catch (Throwable $_e) { error_log('[notifications] ' . $_e->getMessage()); }   // nunca deita a página abaixo
}
// Plano grátis: as entradas do Finora Pro levam uma etiqueta (ao abrir, a página explica o Pro)
$_isPro  = function_exists('is_pro') && is_pro();
$_proTag = $_isPro ? '' : ' <span class="sidebar-pro">' . te('Pro') . '</span>';
// Fundo escolhido nas Definições (whitelist em theme_helper.php)
$_bg = (isset($pdo) && !empty($_SESSION['user_id'])) ? getUserBackground($pdo, (int) $_SESSION['user_id']) : 'constelacao';
$_username = $_SESSION['username'] ?? '';
$_initial  = strtoupper(mb_substr($_username !== '' ? $_username : 'U', 0, 1));
?>
<style>
  .sidebar-pro { margin-left: auto; padding: 1px 7px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .2px;
                 background: var(--fn-gold-soft); color: #9a6a0b; }
  [data-theme="dark"] .sidebar-pro { color: var(--fn-gold); }
  .sidebar-link-pro > i { color: var(--fn-gold); }
  /* Conta Pro: diamante dourado no canto da foto, no cabeçalho */
  .app-user-av { position: relative; display: inline-flex; flex-shrink: 0; }
  .app-user-pro { position: absolute; right: -4px; bottom: -3px; width: 17px; height: 17px; border-radius: 50%;
                  display: grid; place-items: center; background: #14213d; color: #e2b04a; font-size: 9px;
                  box-shadow: 0 0 0 2px var(--fn-surface); }
  .app-user-pro i { line-height: 1; }

  /* ── Fundo da app (Definições → Aparência) ──────────────────
     .app-bg fica atrás de tudo; data-bg diz qual é. A "constelação" é a de sempre:
     os brilhos de theme.css (.main-content::before) e o canvas das partículas.
     Os outros fundos desligam os dois. */
  .app-bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; --bg-grid: rgba(20, 33, 61, .05); --bg-line-op: .22; --bg-arrow-op: .5; --bg-cat-op: .14; }
  [data-theme="dark"] .app-bg { --bg-grid: rgba(255, 255, 255, .045); --bg-line-op: .24; --bg-arrow-op: .55; --bg-cat-op: .18; }
  .app-bg:not([data-bg="constelacao"]) ~ #particles-canvas,
  .app-bg:not([data-bg="constelacao"]) ~ .main-content::before { display: none; }
  .app-bg[data-bg="quadriculado"] {
    background:
      radial-gradient(44rem 30rem at 6% 14%, color-mix(in srgb, var(--fn-gold) 12%, transparent), transparent 70%),
      radial-gradient(48rem 34rem at 94% 82%, color-mix(in srgb, var(--cat-1) 9%, transparent), transparent 70%),
      linear-gradient(var(--bg-grid) 1px, transparent 1px) 0 0 / 32px 32px,
      linear-gradient(90deg, var(--bg-grid) 1px, transparent 1px) 0 0 / 32px 32px;
  }
  .app-bg[data-bg="linha"] {
    background:
      radial-gradient(44rem 30rem at 6% 14%, color-mix(in srgb, var(--fn-gold) 10%, transparent), transparent 70%),
      radial-gradient(48rem 34rem at 94% 82%, color-mix(in srgb, var(--cat-1) 8%, transparent), transparent 70%);
  }
  .app-bg svg { display: none; width: 100%; height: 100%; }
  .app-bg[data-bg="linha"] svg { display: block; }
  .app-bg .bl-gold { stroke: var(--fn-gold); stroke-opacity: var(--bg-line-op); stroke-width: 3; }
  .app-bg .bl-cat  { stroke-opacity: var(--bg-cat-op); stroke-width: 2.5; }
  .app-bg .bl-tip  { stroke-opacity: var(--bg-arrow-op); stroke-width: 5; }
  .app-bg .bl-cat.bl-tip { stroke-opacity: calc(var(--bg-arrow-op) * .7); }
  .app-bg .bl-prev { stroke: var(--fn-muted, #5f6982); stroke-opacity: .18; stroke-width: 2; stroke-dasharray: 6 9; vector-effect: non-scaling-stroke; }
  .app-bg path { fill: none; stroke-linecap: round; stroke-linejoin: round; }
</style>
<!-- Fundo da app: constelação (canvas a seguir), liso, quadriculado ou linha dourada -->
<div class="app-bg" id="appBg" data-bg="<?= htmlspecialchars($_bg) ?>" aria-hidden="true">
  <svg viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice" focusable="false">
    <path class="bl-prev" d="M-40 965 C640 955 1180 890 1640 460"/>
    <path class="bl-cat" style="stroke:var(--cat-1)" d="M-30 640 C150 636 250 590 310 470"/>
    <path class="bl-cat bl-tip" style="stroke:var(--cat-1)" transform="translate(310 470) rotate(-63.4) scale(.7)" d="M-44 -30 L0 0 L-44 30"/>
    <path class="bl-cat" style="stroke:var(--cat-3)" d="M960 310 C1060 306 1140 270 1200 180"/>
    <path class="bl-cat bl-tip" style="stroke:var(--cat-3)" transform="translate(1200 180) rotate(-56.3) scale(.55)" d="M-44 -30 L0 0 L-44 30"/>
    <path class="bl-cat" style="stroke:var(--cat-7)" d="M1060 1040 C1200 1015 1320 965 1420 860"/>
    <path class="bl-cat bl-tip" style="stroke:var(--cat-7)" transform="translate(1420 860) rotate(-46.4) scale(.6)" d="M-44 -30 L0 0 L-44 30"/>
    <path class="bl-gold" d="M-40 930 C620 925 1120 820 1540 200"/>
    <path class="bl-gold bl-tip" transform="translate(1540 200) rotate(-55.9)" d="M-44 -30 L0 0 L-44 30"/>
  </svg>
</div>
<!-- Particles canvas (visible in all post-login pages) -->
<canvas id="particles-canvas" style="position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;"></canvas>

<header class="app-header">
  <button class="app-header-menu" id="appMenuBtn" type="button"
          aria-controls="appSidebar" aria-expanded="false" aria-label="<?= te('Abrir menu') ?>">
    <i class="bi bi-list"></i>
  </button>

  <a href="<?= $_sp ?>dashboard.php" class="app-brand" aria-label="<?= te('Finora — início') ?>">
    <?= finora_logo('app-logo') ?>
  </a>

  <?php if ($_pageTitle !== '' && $_ap !== 'dashboard'): ?>
    <span class="app-header-title"><?= htmlspecialchars($_pageTitle) ?></span>
  <?php endif; ?>

  <div class="app-header-actions">
    <a href="<?= $_sp ?>pages/scan_fatura.php" class="app-header-btn app-header-btn-ghost" title="<?= te('Ler fatura') ?>">
      <i class="bi bi-qr-code-scan"></i><span><?= te('Ler fatura') ?></span>
    </a>
    <a href="<?= $_sp ?>pages/create_transaction.php" class="app-header-btn app-header-btn-primary">
      <i class="bi bi-plus-lg"></i><span><?= te('Nova transação') ?></span>
    </a>

    <div class="app-notif">
      <button class="app-notif-btn" id="appNotifBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="appNotifPanel"
              aria-label="<?= $_notifCount ? te('Notificações, {n} por ler', ['n' => $_notifCount]) : te('Notificações') ?>">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span class="app-notif-badge" id="appNotifBadge" <?= $_notifCount ? '' : 'hidden' ?>><?= $_notifCount > 9 ? '9+' : $_notifCount ?></span>
      </button>
      <div class="app-notif-panel" id="appNotifPanel" hidden>
        <div class="app-notif-head">
          <b><?= te('Notificações') ?></b>
          <button type="button" id="appNotifReadAll" <?= $_notifCount ? '' : 'hidden' ?>><?= te('Marcar todas como lidas') ?></button>
        </div>
        <?php if (!$_notifs): ?>
          <p class="app-notif-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i> <?= te('Está tudo em dia.') ?></p>
        <?php else: ?>
          <ul class="app-notif-list">
            <?php foreach ($_notifs as $_n): [$_lbl, $_ic, $_tone] = NOTIF_TYPES[$_n['type']] ?? ['', 'bell', 'cat-1']; ?>
              <li class="<?= $_n['is_read'] ? '' : 'unread' ?>">
                <a href="<?= $_sp . htmlspecialchars(notif_link($_n)) ?>" data-notif-id="<?= (int) $_n['id'] ?>">
                  <span class="app-notif-ico tone-<?= $_tone ?>" aria-hidden="true"><i class="bi bi-<?= $_ic ?>"></i></span>
                  <span class="app-notif-txt">
                    <b><?= htmlspecialchars($_n['title']) ?></b>
                    <span><?= htmlspecialchars($_n['message']) ?></span>
                    <small><?= notif_ago((int) $_n['mins']) ?></small>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <a class="app-notif-all" href="<?= $_sp ?>pages/notifications.php"><?= te('Ver todas') ?></a>
      </div>
    </div>

    <div class="app-user">
      <button class="app-user-btn" id="appUserBtn" type="button"
              aria-haspopup="menu" aria-expanded="false" aria-controls="appUserMenu">
        <span class="app-user-av">
          <span class="app-user-avatar">
            <?php if ($_avatarPath): ?>
              <img src="<?= $_sp ?><?= htmlspecialchars($_avatarPath) ?>" alt="">
            <?php else: ?>
              <?= htmlspecialchars($_initial) ?>
            <?php endif; ?>
          </span>
          <?php if ($_isPro): ?><span class="app-user-pro" title="<?= te('Finora Pro') ?>"><i class="bi bi-gem" aria-hidden="true"></i><span class="visually-hidden"><?= te('Finora Pro') ?></span></span><?php endif; ?>
        </span>
        <span class="app-user-name"><?= htmlspecialchars($_username) ?></span>
        <i class="bi bi-chevron-down"></i>
      </button>
      <div class="app-user-menu" id="appUserMenu" role="menu" hidden>
        <a href="<?= $_sp ?>pages/settings.php" role="menuitem"><i class="bi bi-gear"></i> <?= te('Definições') ?></a>
        <a href="<?= $_sp ?>pages/dados.php" role="menuitem"><i class="bi bi-database"></i> <?= te('Dados e relatórios') ?></a>
        <a href="<?= $_sp ?>auth/logout.php" role="menuitem" class="app-user-logout"><i class="bi bi-box-arrow-right"></i> <?= te('Terminar sessão') ?></a>
      </div>
    </div>
  </div>
</header>

<aside class="sidebar" id="appSidebar" aria-label="<?= te('Menu principal') ?>" aria-hidden="true">
  <div class="sidebar-top">
    <a href="<?= $_sp ?>dashboard.php" class="app-brand" aria-label="<?= te('Finora — início') ?>">
      <?= finora_logo('app-logo') ?>
    </a>
    <button class="sidebar-close" type="button" data-close-sidebar aria-label="<?= te('Fechar menu') ?>">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <a href="<?= $_sp ?>dashboard.php"          class="<?= _sidebarClass('dashboard',    $_ap) ?>"<?= _ariaCurrent('dashboard', $_ap) ?>>
      <i class="bi bi-grid-1x2"></i> <?= te('Dashboard') ?>
    </a>
    <a href="<?= $_sp ?>pages/transactions.php" class="<?= _sidebarClass('transactions', $_ap) ?>"<?= _ariaCurrent('transactions', $_ap) ?>>
      <i class="bi bi-arrow-left-right"></i> <?= te('Transações') ?>
    </a>
    <a href="<?= $_sp ?>pages/cards.php"        class="<?= _sidebarClass('cards',        $_ap) ?>"<?= _ariaCurrent('cards', $_ap) ?>>
      <i class="bi bi-credit-card-2-front"></i> <?= te('Cartões') ?>
    </a>

    <div class="sidebar-section-label"><?= te('Planeamento') ?></div>
    <a href="<?= $_sp ?>pages/budgets.php"      class="<?= _sidebarClass('budgets',      $_ap) ?>"<?= _ariaCurrent('budgets', $_ap) ?>>
      <i class="bi bi-pie-chart"></i> <?= te('Orçamentos') ?>
    </a>
    <a href="<?= $_sp ?>pages/savings.php"      class="<?= _sidebarClass('savings',      $_ap) ?>"<?= _ariaCurrent('savings', $_ap) ?>>
      <i class="bi bi-piggy-bank"></i> <?= te('Poupanças') ?>
    </a>
    <a href="<?= $_sp ?>pages/futuro.php"       class="<?= _sidebarClass('futuro',       $_ap) ?>"<?= _ariaCurrent('futuro', $_ap) ?>>
      <i class="bi bi-flag"></i> <?= te('Metas') ?>
    </a>
    <a href="<?= $_sp ?>pages/investments.php"  class="<?= _sidebarClass('investments',  $_ap) ?>"<?= _ariaCurrent('investments', $_ap) ?>>
      <i class="bi bi-graph-up-arrow"></i> <?= te('Investimentos') ?><?= $_proTag ?>
    </a>
    <a href="<?= $_sp ?>pages/bolsa.php"        class="<?= _sidebarClass('bolsa',        $_ap) ?>"<?= _ariaCurrent('bolsa', $_ap) ?>>
      <i class="bi bi-activity"></i> <?= te('Bolsa') ?><?= $_proTag ?>
    </a>
    <a href="<?= $_sp ?>pages/reminders.php"    class="<?= _sidebarClass('reminders',    $_ap) ?>"<?= _ariaCurrent('reminders', $_ap) ?>>
      <i class="bi bi-calendar-check"></i> <?= te('Lembretes') ?>
    </a>

    <div class="sidebar-section-label"><?= te('Perceber os gastos') ?></div>
    <a href="<?= $_sp ?>pages/analytics.php"    class="<?= _sidebarClass('analytics',    $_ap) ?>"<?= _ariaCurrent('analytics', $_ap) ?>>
      <i class="bi bi-bar-chart-line"></i> <?= te('Análise') ?>
    </a>
    <a href="<?= $_sp ?>pages/persona.php"      class="<?= _sidebarClass('persona',      $_ap) ?>"<?= _ariaCurrent('persona', $_ap) ?>>
      <i class="bi bi-person-hearts"></i> <?= te('Personalidade financeira') ?>
    </a>
    <a href="<?= $_sp ?>pages/ai.php"           class="<?= _sidebarClass('ai',           $_ap) ?>"<?= _ariaCurrent('ai', $_ap) ?>>
      <i class="bi bi-stars"></i> <?= te('Assistente IA') ?><?= $_proTag ?>
    </a>

    <div class="sidebar-section-label"><?= te('Ferramentas') ?></div>
    <a href="<?= $_sp ?>pages/scan_fatura.php"  class="<?= _sidebarClass('scan_fatura',  $_ap) ?>"<?= _ariaCurrent('scan_fatura', $_ap) ?>>
      <i class="bi bi-qr-code-scan"></i> <?= te('Ler fatura') ?><?= $_proTag ?>
    </a>
    <a href="<?= $_sp ?>pages/dados.php"        class="<?= _sidebarClass('dados',        $_ap) ?>"<?= _ariaCurrent('dados', $_ap) ?>>
      <i class="bi bi-database"></i> <?= te('Dados e relatórios') ?>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= $_sp ?>pages/pro.php" class="<?= _sidebarClass('pro', $_ap) ?> sidebar-link-pro"<?= _ariaCurrent('pro', $_ap) ?>>
      <i class="bi bi-gem"></i> <?= $_isPro ? te('Finora Pro') : te('Passar para o Pro') ?>
    </a>
    <a href="<?= $_sp ?>pages/settings.php" class="<?= _sidebarClass('settings', $_ap) ?>"<?= _ariaCurrent('settings', $_ap) ?>>
      <i class="bi bi-gear"></i> <?= te('Definições') ?>
    </a>
    <a href="<?= $_sp ?>auth/logout.php" class="sidebar-logout">
      <i class="bi bi-box-arrow-right"></i> <?= te('Terminar sessão') ?>
    </a>
  </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" data-close-sidebar></div>

<script>
(function () {
  const sb   = document.getElementById('appSidebar');
  const ov   = document.getElementById('sidebarOverlay');
  const btn  = document.getElementById('appMenuBtn');
  const uBtn = document.getElementById('appUserBtn');
  const uMenu = document.getElementById('appUserMenu');

  function openSidebar() {
    sb.classList.add('open'); ov.classList.add('visible');
    document.body.classList.add('sidebar-is-open');        // esconde o botão do tutorial
    sb.setAttribute('aria-hidden', 'false'); btn.setAttribute('aria-expanded', 'true');
    (sb.querySelector('.sidebar-link.active') || sb.querySelector('.sidebar-link')).focus();
  }
  function closeSidebar() {
    if (!sb.classList.contains('open')) return;
    sb.classList.remove('open'); ov.classList.remove('visible');
    document.body.classList.remove('sidebar-is-open');
    sb.setAttribute('aria-hidden', 'true'); btn.setAttribute('aria-expanded', 'false');
    btn.focus();
  }
  function toggleUserMenu(force) {
    const open = force ?? uMenu.hidden;
    uMenu.hidden = !open;
    uBtn.setAttribute('aria-expanded', String(open));
    if (open) uMenu.querySelector('a').focus();
  }

  btn.addEventListener('click', () => sb.classList.contains('open') ? closeSidebar() : openSidebar());
  uBtn.addEventListener('click', (e) => { e.stopPropagation(); if (!nPanel.hidden) toggleNotif(false); toggleUserMenu(); });
  document.addEventListener('click', (e) => { if (!uMenu.hidden && !uMenu.contains(e.target)) toggleUserMenu(false); });
  // ── Notificações (sino) ──
  const nBtn = document.getElementById('appNotifBtn'), nPanel = document.getElementById('appNotifPanel');
  const nUrl = <?= json_encode($_sp . 'pages/notifications.php') ?>, nToken = <?= json_encode(csrf_token()) ?>;
  function toggleNotif(force) {
    const open = force ?? nPanel.hidden;
    nPanel.hidden = !open;
    nBtn.setAttribute('aria-expanded', String(open));
    if (open) { toggleUserMenu(false); (nPanel.querySelector('a, button:not([hidden])') || nPanel).focus(); }
  }
  function notifPost(body) {
    return fetch(nUrl, { method: 'POST', keepalive: true, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': nToken, 'Accept': 'application/json' },
      body: new URLSearchParams(body) }).then(r => r.ok ? r.json() : null).catch(() => null);
  }
  function setBadge(n) {
    const b = document.getElementById('appNotifBadge');
    b.hidden = n <= 0; b.textContent = n > 9 ? '9+' : String(n);
    nBtn.setAttribute('aria-label', n > 0 ? <?= tj('Notificações, {n} por ler') ?>.replace('{n}', n) : <?= tj('Notificações') ?>);
    document.getElementById('appNotifReadAll').hidden = n <= 0;
  }
  nBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleNotif(); });
  nPanel.addEventListener('click', (e) => e.stopPropagation());
  document.addEventListener('click', () => { if (!nPanel.hidden) toggleNotif(false); });
  // Abrir uma notificação: marca como lida e segue para a página
  nPanel.querySelectorAll('a[data-notif-id]').forEach(a => a.addEventListener('click', (e) => {
    const li = a.closest('li');
    if (!li.classList.contains('unread')) return;
    e.preventDefault();
    notifPost({ action: 'read', id: a.dataset.notifId }).finally(() => { location.href = a.href; });
  }));
  document.getElementById('appNotifReadAll').addEventListener('click', () => {
    notifPost({ action: 'read_all' }).then(res => {
      if (!res) return;
      nPanel.querySelectorAll('li.unread').forEach(li => li.classList.remove('unread'));
      setBadge(0);
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!nPanel.hidden) { toggleNotif(false); nBtn.focus(); }
    else if (!uMenu.hidden) { toggleUserMenu(false); uBtn.focus(); }
    else closeSidebar();
  });

  // Os modais do Bootstrap têm de ser filhos diretos do <body>. Dentro do .main-content,
  // a regra ".main-content > *" do theme.css tira-lhes o position:fixed e ficam partidos.
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.main-content .modal').forEach(m => document.body.appendChild(m));
  });

  // Usados pelo overlay, pelo botão de fechar e pelo tutorial
  window.openSidebar  = openSidebar;
  window.closeSidebar = closeSidebar;
  window.toggleSidebar = () => sb.classList.contains('open') ? closeSidebar() : openSidebar();
})();
</script>
<script>
// ── Ações sem JavaScript inline ─────────────────────────────
// A Content-Security-Policy (helpers/security_headers.php) não deixa correr onclick="…" nem onsubmit="…".
// Em vez disso os elementos têm atributos data-*, tratados aqui para todas as páginas da app:
//   <form data-confirm="Texto">  pergunta antes de enviar (o texto é HTML escapado, nunca código)
//   data-close-dialog            fecha o <dialog> onde está
//   data-autosubmit              envia o formulário ao mudar (filtros)
//   data-close-sidebar           fecha o menu lateral
document.addEventListener('submit', (e) => {
  const f = e.target.closest('form[data-confirm]');
  if (f && !confirm(f.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); }
}, true);
document.addEventListener('click', (e) => {
  const d = e.target.closest('[data-close-dialog]');
  if (d) { d.closest('dialog')?.close(); return; }
  if (e.target.closest('[data-close-sidebar]') && window.closeSidebar) window.closeSidebar();
});
document.addEventListener('change', (e) => {
  const el = e.target.closest('[data-autosubmit]');
  if (el && el.form) el.form.submit();
});
</script>
<script>
// ── Partículas (injetadas pelo sidebar em todas as páginas) ─
(function() {
  const canvas = document.getElementById('particles-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, dots = [];

  function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
  resize();
  window.addEventListener('resize', () => { resize(); if (!running) start(); });

  function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }

  for (let i = 0; i < 60; i++) {
    dots.push({ x: Math.random()*window.innerWidth, y: Math.random()*window.innerHeight,
      vx: (Math.random()-.5)*.4, vy: (Math.random()-.5)*.4, r: Math.random()*2+1 });
  }

  // Só anda com o fundo "constelação" (Definições → Aparência). Com "reduzir movimento" fica parado.
  const bg   = document.getElementById('appBg');
  const calm = window.matchMedia('(prefers-reduced-motion: reduce)');
  let running = false;
  const active = () => !bg || bg.dataset.bg === 'constelacao';
  function start() { if (!running && active() && !document.hidden) { running = true; requestAnimationFrame(draw); } }
  if (bg) new MutationObserver(start).observe(bg, { attributes: true, attributeFilter: ['data-bg'] });
  document.addEventListener('visibilitychange', start);

  function draw() {
    if (!active() || document.hidden) { running = false; return; }
    ctx.clearRect(0, 0, W, H);
    const dark     = isDark();
    const dotColor = dark ? 'rgba(200,160,100,.50)' : 'rgba(0,40,80,.25)';
    const lineBase = dark ? '200,160,100' : '0,40,80';
    dots.forEach(d => {
      d.x += d.vx; d.y += d.vy;
      if (d.x < 0 || d.x > W) d.vx *= -1;
      if (d.y < 0 || d.y > H) d.vy *= -1;
      ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI*2);
      ctx.fillStyle = dotColor; ctx.fill();
    });
    for (let i = 0; i < dots.length; i++) {
      for (let j = i+1; j < dots.length; j++) {
        const dx = dots[i].x-dots[j].x, dy = dots[i].y-dots[j].y;
        const dist = Math.sqrt(dx*dx+dy*dy);
        if (dist < 120) {
          ctx.beginPath(); ctx.moveTo(dots[i].x, dots[i].y); ctx.lineTo(dots[j].x, dots[j].y);
          ctx.strokeStyle = `rgba(${lineBase},${(.15*(1-dist/120)).toFixed(3)})`; ctx.lineWidth=.8; ctx.stroke();
        }
      }
    }
    if (calm.matches) { running = false; return; }   // desenha uma vez e fica parado
    requestAnimationFrame(draw);
  }
  start();
})();
</script>

<?php /* ── Tutorial System ── */ ?>
<link rel="stylesheet" href="<?= $_sp ?>css/tutorial.css?v=3">
<link rel="stylesheet" href="<?= $_sp ?>css/notifications.css?v=1">
<script>window._tutPage = "<?= htmlspecialchars($_ap) ?>";</script>
<?php if (is_file(__DIR__ . '/../js/lang/tutorial-' . current_lang() . '.js')): ?><script src="<?= $_sp ?>js/lang/tutorial-<?= current_lang() ?>.js?v=1"></script><?php endif; ?>
<script src="<?= $_sp ?>js/tutorial.js?v=21"></script>
