<?php
// site/auth/login.php
session_start();

// Se já há sessão activa, destruí-la para forçar login limpo
if (!empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    session_start();
}

// Impede cache desta página
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/paths.php';

// Proteção contra força bruta à password: bloqueio TEMPORÁRIO da conta
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Preenche todos os campos.';
    } else {
        // O tempo de bloqueio é calculado pelo MySQL (mesmo relógio que grava o NOW())
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, email_verified,
                                      GREATEST(TIMESTAMPDIFF(SECOND, NOW(), login_locked_until), 0) AS lock_seconds
                               FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        // Conta bloqueada: recusar sem sequer verificar a password
        if ($user && $user['lock_seconds'] > 0) {
            $min = (int) ceil($user['lock_seconds'] / 60);
            $errors[] = "Demasiadas tentativas. Tenta novamente daqui a $min minuto" . ($min > 1 ? 's' : '') . '.';
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            // Login certo: o contador volta a zero
            $pdo->prepare('UPDATE users SET failed_login_attempts = 0, login_locked_until = NULL WHERE id = :id')
                ->execute([':id' => $user['id']]);

            if (!$user['email_verified']) {
                $_SESSION['pending_verification_email'] = $user['email'];
                header('Location: verify.php');
                exit;
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                // Voltar à página pedida antes do login, só se for um caminho interno
                header('Location: ' . (safe_next($_GET['next'] ?? null) ?? '../dashboard.php'));
                exit;
            }
        } else {
            if ($user) {
                // Contar a falha num UPDATE atómico (pedidos em paralelo não se perdem)
                $pdo->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id')
                    ->execute([':id' => $user['id']]);
                // À 5.ª falha: bloqueio temporário e o contador recomeça
                $pdo->prepare('UPDATE users SET login_locked_until = NOW() + INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE, failed_login_attempts = 0
                               WHERE id = :id AND failed_login_attempts >= :max')
                    ->execute([':id' => $user['id'], ':max' => LOGIN_MAX_ATTEMPTS]);
            }
            // Mesma mensagem para email inexistente e password errada (evita enumeração de contas)
            $errors[] = 'Credenciais inválidas.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="light">
<head>
<script>
  (function() {
    var t = localStorage.getItem("fc_theme") || "light";
    document.documentElement.setAttribute("data-theme", t);
  })();
</script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar — FreeCard</title>
  <meta name="description" content="Entra na tua conta FreeCard e controla os teus cartões, transações e orçamentos.">
  <meta name="robots" content="noindex, follow">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root[data-theme="light"] {
      --bg-primary: #f0f2f5;
      --bg-secondary: #ffffff;
      --bg-hover: #f0f0f0;
      --text-primary: #2c3e50;
      --text-secondary: #7f8c8d;
      --border-color: #e2e8f0;
      --shadow: rgba(0,0,0,0.08);
      --brand-panel-bg: linear-gradient(145deg, #1e3a5f 0%, #2c5282 50%, #2b4c7e 100%);
    }
    :root[data-theme="dark"] {
      --bg-primary: #1a1d29;
      --bg-secondary: #252936;
      --bg-hover: #2d3142;
      --text-primary: #ecf0f1;
      --text-secondary: #95a5a6;
      --border-color: #2e3448;
      --shadow: rgba(0,0,0,0.35);
      --brand-panel-bg: linear-gradient(145deg, #0f1923 0%, #1a2744 50%, #1e2d50 100%);
    }
    [data-theme="dark"] body {
      background-color: #0e1420;
      background-image:
        radial-gradient(ellipse 70% 55% at 0%   0%,   rgba(0,40,80,.80)    0%, transparent 65%),
        radial-gradient(ellipse 50% 40% at 100%  0%,   rgba(200,120,60,.30) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 50% 110%,  rgba(0,20,60,.70)    0%, transparent 65%);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { transition: background-color .35s ease, color .25s ease; }
    body {
      min-height: 100vh;
      background-color: #f0f2f5;
      background-image:
        radial-gradient(ellipse 65% 55% at 0%   0%,   rgba(0,40,80,.22)    0%, transparent 65%),
        radial-gradient(ellipse 55% 45% at 100%  0%,   rgba(200,120,60,.20) 0%, transparent 60%),
        radial-gradient(ellipse 55% 45% at 50% 115%,  rgba(0,40,80,.15)    0%, transparent 65%);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--text-primary);
      transition: background .3s, color .3s;
      display: flex;
      align-items: stretch;
    }

    /* ── Theme toggle ── */
    .theme-toggle {
      position: fixed; top: 16px; right: 16px; z-index: 100;
      width: 36px; height: 36px; border-radius: 10px;
      border: 1px solid var(--border-color);
      background: var(--bg-secondary);
      color: var(--text-secondary);
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      font-size: 15px; transition: all .2s; box-shadow: 0 2px 8px var(--shadow);
    }
    .theme-toggle:hover { color: var(--text-primary); border-color: #3498db; }

    .btn-back {
      position: fixed; top: 16px; left: 16px; z-index: 100;
      display: flex; align-items: center; gap: 7px;
      padding: 7px 14px; border-radius: 10px;
      border: 1px solid var(--border-color);
      background: var(--bg-secondary);
      color: var(--text-secondary);
      font-size: 13px; font-weight: 500;
      text-decoration: none; transition: all .2s;
      box-shadow: 0 2px 8px var(--shadow);
    }
    .btn-back:hover { color: var(--text-primary); border-color: #3498db; text-decoration: none; }

    /* ── Brand panel (left) ── */
    .brand-panel {
      width: 420px; flex-shrink: 0;
      background: var(--brand-panel-bg);
      display: flex; flex-direction: column;
      justify-content: center; align-items: flex-start;
      padding: 60px 48px;
      position: relative; overflow: hidden;
    }
    .brand-panel::before {
      content: ''; position: absolute;
      width: 350px; height: 350px;
      background: rgba(52,152,219,.12);
      border-radius: 50%;
      top: -80px; right: -80px;
    }
    .brand-panel::after {
      content: ''; position: absolute;
      width: 250px; height: 250px;
      background: rgba(52,152,219,.08);
      border-radius: 50%;
      bottom: -50px; left: -50px;
    }
    .brand-panel .bp-logo { width: 56px; height: 56px; margin-bottom: 32px; position: relative; z-index: 1; }
    .brand-panel .bp-logo img { width: 100%; filter: drop-shadow(-1px 0px 0 rgba(255,255,255,0.9)) drop-shadow(1px 0px 0 rgba(255,255,255,0.9)) drop-shadow(0px -1px 0 rgba(255,255,255,0.9)) drop-shadow(0px 1px 0 rgba(255,255,255,0.9)); }
    .brand-panel h1 { font-size: 28px; font-weight: 700; color: #fff; margin-bottom: 12px; position: relative; z-index: 1; line-height: 1.3; }
    .brand-panel p { font-size: 15px; color: rgba(255,255,255,.65); margin-bottom: 40px; position: relative; z-index: 1; line-height: 1.6; }
    .brand-features { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 16px; }
    .brand-feature { display: flex; align-items: center; gap: 12px; }
    .brand-feature-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,255,255,.1); display: flex; align-items: center; justify-content: center; color: #7ec8e3; font-size: 14px; flex-shrink: 0; }
    .brand-feature span { font-size: 13px; color: rgba(255,255,255,.7); }

    /* ── Form panel (right) ── */
    .form-panel {
      flex: 1; display: flex; align-items: center; justify-content: center;
      padding: 40px 24px;
    }
    .auth-card {
      width: 100%; max-width: 400px;
      background: var(--bg-secondary);
      border: 1px solid var(--border-color);
      border-radius: 18px;
      box-shadow: 0 4px 32px var(--shadow);
      overflow: hidden;
      animation: fadeUp .45s ease-out;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .auth-card-header {
      padding: 32px 32px 24px;
      border-bottom: 1px solid var(--border-color);
    }
    .auth-card-header .logo-sm { display: none; }
    .auth-card-header h2 { font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
    .auth-card-header p { font-size: 13px; color: var(--text-secondary); margin: 0; }
    .auth-card-body { padding: 28px 32px; }
    .auth-card-footer {
      padding: 16px 32px;
      border-top: 1px solid var(--border-color);
      text-align: center;
      background: var(--bg-primary);
    }
    .auth-card-footer p { margin: 0; font-size: 13px; color: var(--text-secondary); }
    .auth-card-footer a { color: #3498db; font-weight: 600; text-decoration: none; }
    .auth-card-footer a:hover { text-decoration: underline; }

    /* ── Form ── */
    .f-group { margin-bottom: 18px; }
    .f-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: 7px; }
    .f-input-wrap { position: relative; }
    .f-input-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 14px; pointer-events: none; }
    .f-input {
      width: 100%; font-size: 14px; padding: 9px 12px 9px 34px;
      border: 1px solid var(--border-color); border-radius: 10px;
      background: var(--bg-primary); color: var(--text-primary);
      transition: border-color .15s; outline: none;
    }
    .f-input:focus { border-color: #3498db; }
    .f-input.has-toggle { padding-right: 38px; }
    [data-theme="dark"] .f-input { background: var(--bg-hover); }
    .f-toggle-pw {
      position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--text-secondary);
      cursor: pointer; font-size: 14px; padding: 2px;
      transition: color .15s;
    }
    .f-toggle-pw:hover { color: var(--text-primary); }

    /* ── Buttons ── */
    .btn-s { font-size: 13px; font-weight: 500; padding: 8px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; width: 100%; justify-content: center; padding: 10px 16px; font-size: 14px; }
    .btn-s-primary:hover { background: #2980b9; border-color: #2980b9; color: #fff; }

    /* ── Alert ── */
    .a-error { border-radius: 10px; padding: 11px 14px; font-size: 13px; margin-bottom: 18px; display: flex; align-items: flex-start; gap: 9px; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.08); color: #b91c1c; }
    [data-theme="dark"] .a-error { color: #fca5a5; background: rgba(239,68,68,.1); }

    .forgot-link { display: block; text-align: center; margin-top: 14px; font-size: 12px; color: var(--text-secondary); text-decoration: none; }
    .forgot-link:hover { color: #3498db; }

    /* ── Mobile ── */
    @media (max-width: 768px) {
      .brand-panel { display: none; }
      .form-panel { padding: 24px 16px; align-items: flex-start; padding-top: 60px; }
      .auth-card-header .logo-sm { display: block; width: 40px; margin-bottom: 16px; }
    }

    /* ── Partículas canvas ───────────────────────────────── */
    #particles-canvas {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 0;
    }
    .auth-layout-inner, .auth-wrapper, form, .auth-card,
    .brand-panel, .form-panel { position: relative; z-index: 1; }

    /* ── Google OAuth button ── */
    .btn-google { width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 10px 16px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all .15s; margin-bottom: 16px; }
    .btn-google:hover { border-color: #4285f4; background: var(--bg-hover); color: var(--text-primary); text-decoration: none; }
    .btn-google svg { flex-shrink: 0; }
    .or-divider { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
    .or-divider::before, .or-divider::after { content: ''; flex: 1; height: 1px; background: var(--border-color); }
    .or-divider span { font-size: 12px; color: var(--text-secondary); white-space: nowrap; }
  </style>
</head>
<body>
<canvas id="particles-canvas"></canvas>
<a href="../../index.php" class="btn-back">
  <i class="bi bi-arrow-left"></i> Página inicial
</a>
<button class="theme-toggle" id="themeBtn" title="Alternar tema">
  <i class="bi bi-moon-fill" id="themeIcon"></i>
</button>

<!-- Brand panel -->
<div class="brand-panel">
  <div class="bp-logo"><img src="../assets/logo2.png" alt="FreeCard"></div>
  <h1>Bem-vindo de volta!</h1>
  <p>Gere os teus cartões e despesas num só lugar, de forma simples e visual.</p>
  <div class="brand-features">
    <div class="brand-feature">
      <div class="brand-feature-icon"><i class="bi bi-credit-card-2-front"></i></div>
      <span>Gestão de cartões em tempo real</span>
    </div>
    <div class="brand-feature">
      <div class="brand-feature-icon"><i class="bi bi-bar-chart-line"></i></div>
      <span>Análise de gastos por categoria</span>
    </div>
    <div class="brand-feature">
      <div class="brand-feature-icon"><i class="bi bi-bell"></i></div>
      <span>Lembretes e alertas de orçamento</span>
    </div>
    <div class="brand-feature">
      <div class="brand-feature-icon"><i class="bi bi-shield-check"></i></div>
      <span>Os teus dados em segurança</span>
    </div>
  </div>
</div>

<!-- Form panel -->
<div class="form-panel">
  <div class="auth-card">
    <div class="auth-card-header">
      <img class="logo-sm" src="../assets/logo2.png" alt="FreeCard">
      <h2>Entrar na conta</h2>
      <p>Introduz o teu email e palavra-passe para continuar</p>
    </div>
    <div class="auth-card-body">
<?php
require_once __DIR__ . '/../../config/google_oauth.php';
if (!empty($_SESSION['google_error'])): $ge = $_SESSION['google_error']; unset($_SESSION['google_error']); ?>
  <div class="a-error"><i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i><span><?=htmlspecialchars($ge)?></span></div>
<?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="a-error">
          <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
          <div><?php foreach($errors as $e): ?><?=htmlspecialchars($e)?><?php endforeach; ?></div>
        </div>
      <?php endif; ?>

      <a href="google_redirect.php" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 6.293C4.672 4.169 6.656 3.58 9 3.58z"/></svg>
        Continuar com Google
      </a>
      <div class="or-divider"><span>ou entra com email</span></div>

      <form method="post">
        <div class="f-group">
          <label class="f-label">Email</label>
          <div class="f-input-wrap">
            <i class="bi bi-envelope f-input-icon"></i>
            <input type="email" name="email" class="f-input"
                   placeholder="o teu email"
                   value="<?=htmlspecialchars($email ?? '')?>"
                   required autofocus autocomplete="email">
          </div>
        </div>
        <div class="f-group">
          <label class="f-label">Palavra-passe</label>
          <div class="f-input-wrap">
            <i class="bi bi-lock f-input-icon"></i>
            <input type="password" name="password" id="pw" class="f-input has-toggle"
                   placeholder="a tua palavra-passe" required>
            <button type="button" class="f-toggle-pw" onclick="togglePw('pw','pwEye')">
              <i class="bi bi-eye" id="pwEye"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-s btn-s-primary">
          <i class="bi bi-box-arrow-in-right"></i> Entrar
        </button>
        <a href="forgot_password.php" class="forgot-link">
          <i class="bi bi-key me-1"></i>Esqueci a palavra-passe
        </a>
      </form>
    </div>
    <div class="auth-card-footer">
      <p>Ainda não tens conta? <a href="register.php">Regista-te grátis</a></p>
    </div>
  </div>
</div>

<script>
// Theme
const html = document.documentElement;
const btn = document.getElementById('themeBtn');
const icon = document.getElementById('themeIcon');
const saved = localStorage.getItem('fc_theme') || 'light';
applyTheme(saved);

btn.addEventListener('click', () => {
  const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
  applyTheme(next);
  localStorage.setItem('fc_theme', next);
});
function applyTheme(t) {
  html.dataset.theme = t;
  icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
}

// Toggle password
function togglePw(inputId, iconId) {
  const inp = document.getElementById(inputId);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}

// ── Partículas ─────────────────────────────────────────────
(function() {
  const canvas = document.getElementById('particles-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, dots = [];

  function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
  resize();
  window.addEventListener('resize', resize);

  function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }

  for (let i = 0; i < 55; i++) {
    dots.push({ x: Math.random()*window.innerWidth, y: Math.random()*window.innerHeight,
      vx: (Math.random()-.5)*.4, vy: (Math.random()-.5)*.4, r: Math.random()*2+1 });
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    const dark     = isDark();
    const dotColor = dark ? 'rgba(200,160,100,.55)' : 'rgba(0,40,80,.28)';
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
          ctx.strokeStyle = `rgba(${lineBase},${(.18*(1-dist/120)).toFixed(3)})`; ctx.lineWidth=.8; ctx.stroke();
        }
      }
    }
    requestAnimationFrame(draw);
  }
  draw();
})();
</script>
</body>
</html>