<?php
// sidebar.php
// Required variables before including:
//   $activePage    — 'dashboard','cards','transactions','budgets','reminders','analytics','settings'
//   $sidebarPrefix — '' for dashboard.php, '../' for pages/*.php

$_sp  = $sidebarPrefix ?? '../';
$_ap  = $activePage    ?? '';

function _sidebarClass($page, $current) {
    return $page === $current ? 'sidebar-link active' : 'sidebar-link';
}
?>
<!-- Particles canvas (visible in all post-login pages) -->
<canvas id="particles-canvas" style="position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;"></canvas>

<aside class="sidebar" id="appSidebar">
  <!-- Logo -->
  <div class="sidebar-logo">
    <a href="<?= $_sp ?>dashboard.php" class="sidebar-brand">
      <img src="<?= $_sp ?>assets/logo2.png" alt="FreeCard">
      <span>FreeCard</span>
    </a>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <div class="sidebar-section-label">Principal</div>
    <a href="<?= $_sp ?>dashboard.php"         class="<?= _sidebarClass('dashboard',    $_ap) ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= $_sp ?>pages/cards.php"       class="<?= _sidebarClass('cards',        $_ap) ?>">
      <i class="bi bi-wallet2"></i> Cartões
    </a>
    <a href="<?= $_sp ?>pages/transactions.php" class="<?= _sidebarClass('transactions', $_ap) ?>">
      <i class="bi bi-arrow-left-right"></i> Transações
    </a>

    <div class="sidebar-section-label">Gestão</div>
    <a href="<?= $_sp ?>pages/budgets.php"     class="<?= _sidebarClass('budgets',      $_ap) ?>">
      <i class="bi bi-piggy-bank"></i> Orçamentos
    </a>
    <a href="<?= $_sp ?>pages/futuro.php"      class="<?= _sidebarClass('futuro',      $_ap) ?>">
      <i class="bi bi-hourglass-split"></i> Metas
    </a>
    <a href="<?= $_sp ?>pages/reminders.php"   class="<?= _sidebarClass('reminders',    $_ap) ?>">
      <i class="bi bi-bell"></i> Lembretes
    </a>
    <a href="<?= $_sp ?>pages/analytics.php"   class="<?= _sidebarClass('analytics',    $_ap) ?>">
      <i class="bi bi-bar-chart-line"></i> Análise
    </a>

    <div class="sidebar-section-label">Percepções</div>
    <a href="<?= $_sp ?>pages/persona.php"     class="<?= _sidebarClass('persona',     $_ap) ?> sidebar-link-special sidebar-link-persona">
      <i class="bi bi-person-hearts"></i> Personalidade Financeira <span class="sidebar-ai-badge">Novo</span>
    </a>

    <div class="sidebar-section-label">Ferramentas</div>
    <a href="<?= $_sp ?>pages/scan_fatura.php"  class="<?= _sidebarClass('scan_fatura',  $_ap) ?>">
      <i class="bi bi-qr-code-scan"></i> Ler Fatura QR
    </a>
    <a href="<?= $_sp ?>pages/dados.php"       class="<?= _sidebarClass('dados',        $_ap) ?>">
      <i class="bi bi-database"></i> Dados & Relatórios
    </a>
    <a href="<?= $_sp ?>pages/ai.php"          class="<?= _sidebarClass('ai',           $_ap) ?> sidebar-link-special sidebar-link-ai">
      <i class="bi bi-stars"></i> Assistente IA <span class="sidebar-ai-badge">Novo</span>
    </a>
    <a href="<?= $_sp ?>pages/settings.php"    class="<?= _sidebarClass('settings',     $_ap) ?>">
      <i class="bi bi-gear"></i> Definições
    </a>

  </nav>

  <!-- User footer -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <?php
        // Buscar avatar do utilizador
        $_avatarPath = null;
        if (isset($pdo) && isset($uid)) {
            try {
                $_s = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :uid");
                $_s->execute([':uid' => $uid]);
                $_avatarPath = $_s->fetchColumn();
            } catch (Exception $_e) {}
        }
      ?>
      <div class="sidebar-user-avatar" style="overflow:hidden;padding:0">
        <?php if ($_avatarPath): ?>
          <img src="<?=$_sp?><?=htmlspecialchars($_avatarPath)?>?v=<?=time()?>"
               alt="avatar"
               style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
        <?php else: ?>
          <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>
        <div class="sidebar-user-email">Conta pessoal</div>
      </div>
    </div>
    <a href="<?= $_sp ?>auth/logout.php" class="sidebar-logout">
      <i class="bi bi-box-arrow-right"></i> Terminar Sessão
    </a>
  </div>
</aside>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Mobile toggle button -->
<button class="sidebar-mobile-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Menu">
  <i class="bi bi-list"></i>
</button>

<script>
function toggleSidebar() {
  const sb = document.getElementById('appSidebar');
  const ov = document.getElementById('sidebarOverlay');
  sb.classList.toggle('open');
  ov.classList.toggle('visible');
}
function closeSidebar() {
  document.getElementById('appSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('visible');
}
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