<?php
// site/helpers/auth_layout.php
// Moldura comum das páginas de autenticação (login, registo, recuperar, verificar).
// Também envia os cabeçalhos de segurança que estas páginas precisam.
require_once __DIR__ . '/brand.php';

// $o['panel'] = ['title' => ..., 'text' => ...] mostra o painel azul à esquerda (login e registo).
function auth_page_start(string $title, array $o = []): void {
    header('Cache-Control: no-store');          // não guardar formulários com dados em cache
    header('X-Frame-Options: DENY');            // ninguém mete esta página num iframe (clickjacking)
    header('Referrer-Policy: same-origin');     // tokens no URL não vão para a CDN no Referer
    header('X-Content-Type-Options: nosniff');

    $panel = $o['panel'] ?? null;
    $e = fn($s) => htmlspecialchars($s, ENT_QUOTES);
    ?>
<!doctype html>
<html lang="pt-PT" data-theme="light">
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
  <title><?= $e($title) ?> — Finora</title>
<?php if (!empty($o['description'])): ?>
  <meta name="description" content="<?= $e($o['description']) ?>">
<?php endif; ?>
  <meta name="robots" content="<?= $e($o['robots'] ?? 'noindex, nofollow') ?>">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css"
        integrity="sha384-fFxL8wXRpg9gVqGpY+URMtLr3fLL0WBbo4NBQ+IWwMDYjIjI5VQ46XlJm5+BUlXJ" crossorigin="anonymous">
  <link rel="stylesheet" href="../css/auth.css?v=1">
</head>
<body>
<div class="au-shell<?= $panel ? ' au-split' : '' ?>">
<?php if ($panel): ?>
  <aside class="au-panel">
    <a href="../../index.php" class="au-panel-brand" aria-label="Finora — página inicial"><?= finora_logo('au-logo') ?></a>
    <div class="au-pitch">
      <h1><?= $e($panel['title']) ?></h1>
      <p><?= $e($panel['text']) ?></p>
    </div>
    <svg class="au-rise" viewBox="0 0 400 520" preserveAspectRatio="none" aria-hidden="true">
      <g class="au-grid"><path d="M0 130H400M0 260H400M0 390H400"/></g>
      <path class="au-rise-line" d="M-20 500 C120 494 250 420 360 70"/>
      <path class="au-rise-line" d="M322 92 L360 70 L368 114"/>
    </svg>
  </aside>
<?php endif; ?>
  <main class="au-main">
    <div class="au-top">
      <a href="../../index.php" class="au-chip"><i class="bi bi-arrow-left" aria-hidden="true"></i> Página inicial</a>
      <button type="button" class="au-chip au-chip-icon" id="auTheme" aria-label="Mudar para tema escuro">
        <i class="bi bi-moon-stars" aria-hidden="true"></i>
      </button>
    </div>
    <div class="au-center">
      <a href="../../index.php" class="au-brand" aria-label="Finora — página inicial"><?= finora_logo('au-logo') ?></a>
<?php
}

// Fecha a página. $script é JS próprio da página (já escrito por nós, nunca dados do utilizador).
function auth_page_end(string $script = ''): void {
    ?>
    </div>
  </main>
</div>
<script>
(function () {
  var root = document.documentElement, btn = document.getElementById('auTheme');
  function paint() {
    var dark = root.dataset.theme === 'dark';
    btn.innerHTML = dark ? '<i class="bi bi-sun" aria-hidden="true"></i>' : '<i class="bi bi-moon-stars" aria-hidden="true"></i>';
    btn.setAttribute('aria-label', dark ? 'Mudar para tema claro' : 'Mudar para tema escuro');
  }
  paint();
  btn.addEventListener('click', function () {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('fc_theme', root.dataset.theme); } catch (e) {}
    paint();
  });
  // Botões de mostrar/esconder palavra-passe: <button data-reveal="idDoCampo">
  document.querySelectorAll('[data-reveal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var input = document.getElementById(b.dataset.reveal);
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
      b.setAttribute('aria-label', show ? 'Esconder palavra-passe' : 'Mostrar palavra-passe');
      b.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });
  // Regras da palavra-passe a acender enquanto se escreve
  var rules = {
    len:   function (v) { return v.length >= 8; },
    upper: function (v) { return /[A-Z]/.test(v); },
    lower: function (v) { return /[a-z]/.test(v); },
    num:   function (v) { return /[0-9]/.test(v); },
    sym:   function (v) { return /[^A-Za-z0-9]/.test(v); }
  };
  document.querySelectorAll('.au-reqs[data-for]').forEach(function (list) {
    var input = document.getElementById(list.dataset.for), meter = list.previousElementSibling;
    input.addEventListener('input', function () {
      var score = 0;
      list.querySelectorAll('li').forEach(function (li) {
        var ok = rules[li.dataset.rule](input.value);
        if (ok) score++;
        li.classList.toggle('ok', ok);
        li.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
      });
      meter.dataset.score = score;
    });
  });
  // Evita duplo envio: desativa o botão depois do primeiro clique
  document.querySelectorAll('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function () {
      var s = f.querySelector('button[type=submit]');
      if (s) setTimeout(function () { s.disabled = true; }, 0);
    });
  });
})();
<?= $script ?>
</script>
</body>
</html>
<?php
}

// Caixa de mensagem. $type: err | ok | info
function auth_alert(string $type, array $lines): string {
    if (!$lines) return '';
    $icon = ['err' => 'exclamation-circle', 'ok' => 'check-circle', 'info' => 'info-circle'][$type] ?? 'info-circle';
    $role = $type === 'err' ? 'alert' : 'status';
    $html = '<div class="au-alert au-alert-' . $type . '" role="' . $role . '"><i class="bi bi-' . $icon . '" aria-hidden="true"></i><div>';
    foreach ($lines as $l) $html .= '<p>' . htmlspecialchars($l, ENT_QUOTES) . '</p>';
    return $html . '</div></div>';
}

// Botão "Continuar com Google" (o mesmo no login e no registo)
function auth_google_button(string $label): string {
    return '<a href="google_redirect.php" class="au-google">'
         . '<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 6.293C4.672 4.169 6.656 3.58 9 3.58z"/></svg>'
         . htmlspecialchars($label, ENT_QUOTES) . '</a>';
}

// Barra de força + regras por baixo do campo $forId (o JS comum atualiza-as enquanto se escreve).
// As regras são as mesmas de helpers/password_rules.php — o servidor volta sempre a validar.
function auth_password_rules(string $forId): string {
    $rules = ['len' => '8 caracteres', 'upper' => 'Maiúscula', 'lower' => 'Minúscula', 'num' => 'Número', 'sym' => 'Símbolo'];
    $html = '<div class="au-meter" data-score="0" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>'
          . '<ul class="au-reqs" id="' . $forId . 'Reqs" data-for="' . $forId . '" aria-label="Regras da palavra-passe">';
    foreach ($rules as $k => $label) $html .= '<li data-rule="' . $k . '"><i class="bi bi-circle" aria-hidden="true"></i>' . $label . '</li>';
    return $html . '</ul>';
}

// Campo de palavra-passe com botão de mostrar
function auth_password_input(string $id, string $name, string $autocomplete, string $extra = ''): string {
    return '<div class="au-input-wrap"><i class="bi bi-lock au-input-ico" aria-hidden="true"></i>'
         . '<input type="password" id="' . $id . '" name="' . $name . '" class="au-input au-has-btn" autocomplete="' . $autocomplete . '" required ' . $extra . '>'
         . '<button type="button" class="au-reveal" data-reveal="' . $id . '" aria-label="Mostrar palavra-passe" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div>';
}
