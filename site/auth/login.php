<?php
// site/auth/login.php
session_start();
require_once __DIR__ . '/../../config/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrUsername = trim($_POST['email_or_username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($emailOrUsername === '' || $password === '') {
        $errors[] = 'Preenche todos os campos.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, email_verified FROM users WHERE email = :e OR username = :u LIMIT 1');
        $stmt->execute([':e' => $emailOrUsername, ':u' => $emailOrUsername]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['email_verified']) {
                $_SESSION['pending_verification_email'] = $user['email'];
                header('Location: verify.php');
                exit;
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                session_regenerate_id(true);
                header('Location: ../dashboard.php');
                exit;
            }
        } else {
            $errors[] = 'Credenciais inválidas.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar - FreeCard</title>
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
      --border-color: #34495e;
      --shadow: rgba(0,0,0,0.35);
      --brand-panel-bg: linear-gradient(145deg, #0f1923 0%, #1a2744 50%, #1e2d50 100%);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      background: var(--bg-primary);
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
    .brand-panel .bp-logo img { width: 100%; filter: brightness(0) invert(1); }
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
  </style>
</head>
<body>
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
      <p>Introduz as tuas credenciais para continuar</p>
    </div>
    <div class="auth-card-body">
      <?php if (!empty($errors)): ?>
        <div class="a-error">
          <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
          <div><?php foreach($errors as $e): ?><?=htmlspecialchars($e)?><?php endforeach; ?></div>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="f-group">
          <label class="f-label">Email ou utilizador</label>
          <div class="f-input-wrap">
            <i class="bi bi-person f-input-icon"></i>
            <input type="text" name="email_or_username" class="f-input"
                   placeholder="email ou nome de utilizador"
                   value="<?=htmlspecialchars($emailOrUsername ?? '')?>"
                   required autofocus>
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
</script>
</body>
</html>
