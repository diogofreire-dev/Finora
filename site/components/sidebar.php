<?php
// sidebar.php — cabeçalho fixo + menu lateral (escondido até ser aberto)
// Variáveis necessárias antes do include:
//   $activePage    — 'dashboard','cards','transactions','budgets','reminders','analytics','settings', ...
//   $sidebarPrefix — '' para dashboard.php, '../' para pages/*.php

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
    'dashboard'    => 'Dashboard',
    'transactions' => 'Transações',
    'cards'        => 'Cartões',
    'budgets'      => 'Orçamentos',
    'futuro'       => 'Metas',
    'reminders'    => 'Lembretes',
    'analytics'    => 'Análise',
    'persona'      => 'Personalidade financeira',
    'ai'           => 'Assistente IA',
    'scan_fatura'  => 'Ler fatura',
    'dados'        => 'Dados e relatórios',
    'settings'     => 'Definições',
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
$_username = $_SESSION['username'] ?? '';
$_initial  = strtoupper(mb_substr($_username !== '' ? $_username : 'U', 0, 1));
?>
<!-- Particles canvas (visible in all post-login pages) -->
<canvas id="particles-canvas" style="position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;"></canvas>

<header class="app-header">
  <button class="app-header-menu" id="appMenuBtn" type="button"
          aria-controls="appSidebar" aria-expanded="false" aria-label="Abrir menu">
    <i class="bi bi-list"></i>
  </button>

  <a href="<?= $_sp ?>dashboard.php" class="app-brand">
    <img src="<?= $_sp ?>assets/logo2.png" alt="">
    <span>Finora</span>
  </a>

  <?php if ($_pageTitle !== '' && $_ap !== 'dashboard'): ?>
    <span class="app-header-title"><?= htmlspecialchars($_pageTitle) ?></span>
  <?php endif; ?>

  <div class="app-header-actions">
    <a href="<?= $_sp ?>pages/scan_fatura.php" class="app-header-btn app-header-btn-ghost" title="Ler fatura">
      <i class="bi bi-qr-code-scan"></i><span>Ler fatura</span>
    </a>
    <a href="<?= $_sp ?>pages/create_transaction.php" class="app-header-btn app-header-btn-primary">
      <i class="bi bi-plus-lg"></i><span>Nova transação</span>
    </a>

    <div class="app-user">
      <button class="app-user-btn" id="appUserBtn" type="button"
              aria-haspopup="menu" aria-expanded="false" aria-controls="appUserMenu">
        <span class="app-user-avatar">
          <?php if ($_avatarPath): ?>
            <img src="<?= $_sp ?><?= htmlspecialchars($_avatarPath) ?>" alt="">
          <?php else: ?>
            <?= htmlspecialchars($_initial) ?>
          <?php endif; ?>
        </span>
        <span class="app-user-name"><?= htmlspecialchars($_username) ?></span>
        <i class="bi bi-chevron-down"></i>
      </button>
      <div class="app-user-menu" id="appUserMenu" role="menu" hidden>
        <a href="<?= $_sp ?>pages/settings.php" role="menuitem"><i class="bi bi-gear"></i> Definições</a>
        <a href="<?= $_sp ?>pages/dados.php" role="menuitem"><i class="bi bi-database"></i> Dados e relatórios</a>
        <a href="<?= $_sp ?>auth/logout.php" role="menuitem" class="app-user-logout"><i class="bi bi-box-arrow-right"></i> Terminar sessão</a>
      </div>
    </div>
  </div>
</header>

<aside class="sidebar" id="appSidebar" aria-label="Menu principal" aria-hidden="true">
  <div class="sidebar-top">
    <a href="<?= $_sp ?>dashboard.php" class="app-brand">
      <img src="<?= $_sp ?>assets/logo2.png" alt="">
      <span>Finora</span>
    </a>
    <button class="sidebar-close" type="button" onclick="closeSidebar()" aria-label="Fechar menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <a href="<?= $_sp ?>dashboard.php"          class="<?= _sidebarClass('dashboard',    $_ap) ?>"<?= _ariaCurrent('dashboard', $_ap) ?>>
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="<?= $_sp ?>pages/transactions.php" class="<?= _sidebarClass('transactions', $_ap) ?>"<?= _ariaCurrent('transactions', $_ap) ?>>
      <i class="bi bi-arrow-left-right"></i> Transações
    </a>
    <a href="<?= $_sp ?>pages/cards.php"        class="<?= _sidebarClass('cards',        $_ap) ?>"<?= _ariaCurrent('cards', $_ap) ?>>
      <i class="bi bi-credit-card-2-front"></i> Cartões
    </a>

    <div class="sidebar-section-label">Planeamento</div>
    <a href="<?= $_sp ?>pages/budgets.php"      class="<?= _sidebarClass('budgets',      $_ap) ?>"<?= _ariaCurrent('budgets', $_ap) ?>>
      <i class="bi bi-piggy-bank"></i> Orçamentos
    </a>
    <a href="<?= $_sp ?>pages/futuro.php"       class="<?= _sidebarClass('futuro',       $_ap) ?>"<?= _ariaCurrent('futuro', $_ap) ?>>
      <i class="bi bi-flag"></i> Metas
    </a>
    <a href="<?= $_sp ?>pages/reminders.php"    class="<?= _sidebarClass('reminders',    $_ap) ?>"<?= _ariaCurrent('reminders', $_ap) ?>>
      <i class="bi bi-calendar-check"></i> Lembretes
    </a>

    <div class="sidebar-section-label">Perceber os gastos</div>
    <a href="<?= $_sp ?>pages/analytics.php"    class="<?= _sidebarClass('analytics',    $_ap) ?>"<?= _ariaCurrent('analytics', $_ap) ?>>
      <i class="bi bi-bar-chart-line"></i> Análise
    </a>
    <a href="<?= $_sp ?>pages/persona.php"      class="<?= _sidebarClass('persona',      $_ap) ?>"<?= _ariaCurrent('persona', $_ap) ?>>
      <i class="bi bi-person-hearts"></i> Personalidade financeira
    </a>
    <a href="<?= $_sp ?>pages/ai.php"           class="<?= _sidebarClass('ai',           $_ap) ?>"<?= _ariaCurrent('ai', $_ap) ?>>
      <i class="bi bi-stars"></i> Assistente IA
    </a>

    <div class="sidebar-section-label">Ferramentas</div>
    <a href="<?= $_sp ?>pages/scan_fatura.php"  class="<?= _sidebarClass('scan_fatura',  $_ap) ?>"<?= _ariaCurrent('scan_fatura', $_ap) ?>>
      <i class="bi bi-qr-code-scan"></i> Ler fatura
    </a>
    <a href="<?= $_sp ?>pages/dados.php"        class="<?= _sidebarClass('dados',        $_ap) ?>"<?= _ariaCurrent('dados', $_ap) ?>>
      <i class="bi bi-database"></i> Dados e relatórios
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= $_sp ?>pages/settings.php" class="<?= _sidebarClass('settings', $_ap) ?>"<?= _ariaCurrent('settings', $_ap) ?>>
      <i class="bi bi-gear"></i> Definições
    </a>
    <a href="<?= $_sp ?>auth/logout.php" class="sidebar-logout">
      <i class="bi bi-box-arrow-right"></i> Terminar sessão
    </a>
  </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<script>
(function () {
  const sb   = document.getElementById('appSidebar');
  const ov   = document.getElementById('sidebarOverlay');
  const btn  = document.getElementById('appMenuBtn');
  const uBtn = document.getElementById('appUserBtn');
  const uMenu = document.getElementById('appUserMenu');

  function openSidebar() {
    sb.classList.add('open'); ov.classList.add('visible');
    sb.setAttribute('aria-hidden', 'false'); btn.setAttribute('aria-expanded', 'true');
    (sb.querySelector('.sidebar-link.active') || sb.querySelector('.sidebar-link')).focus();
  }
  function closeSidebar() {
    if (!sb.classList.contains('open')) return;
    sb.classList.remove('open'); ov.classList.remove('visible');
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
  uBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleUserMenu(); });
  document.addEventListener('click', (e) => { if (!uMenu.hidden && !uMenu.contains(e.target)) toggleUserMenu(false); });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!uMenu.hidden) { toggleUserMenu(false); uBtn.focus(); }
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
// ── Partículas (injetadas pelo sidebar em todas as páginas) ─
(function() {
  const canvas = document.getElementById('particles-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, dots = [];

  function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
  resize();
  window.addEventListener('resize', resize);

  function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }

  for (let i = 0; i < 60; i++) {
    dots.push({ x: Math.random()*window.innerWidth, y: Math.random()*window.innerHeight,
      vx: (Math.random()-.5)*.4, vy: (Math.random()-.5)*.4, r: Math.random()*2+1 });
  }

  function draw() {
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
    requestAnimationFrame(draw);
  }
  draw();
})();
</script>

<?php /* ── Tutorial System ── */ ?>
<link rel="stylesheet" href="<?= $_sp ?>css/tutorial.css">
<script>window._tutPage = "<?= htmlspecialchars($_ap) ?>";</script>
<script src="<?= $_sp ?>js/tutorial.js?v=2"></script>
