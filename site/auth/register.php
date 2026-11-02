<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';

function validatePassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}
function isEmailDeliverable($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if ($domain === '') return false;
    if (function_exists('checkdnsrr')) {
        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }
    return true;
}

$errors = [];
$success = false;
$username = ''; $email = ''; $emailConfirm = '';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) $errors[] = 'Sessão inválida. Atualiza a página e tenta novamente.';
    $username      = trim($_POST['username'] ?? '');
    $email         = strtolower(trim($_POST['email'] ?? ''));
    $emailConfirm  = strtolower(trim($_POST['email_confirm'] ?? ''));
    $password      = $_POST['password'] ?? '';
    $password2     = $_POST['password2'] ?? '';

    if ($username === '' || strlen($username) < 3)                     $errors[] = 'O nome deve ter pelo menos 3 caracteres.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                    $errors[] = 'Email inválido.';
    if ($email !== $emailConfirm)                                      $errors[] = 'Os emails não coincidem.';
    if ($email !== '' && !isEmailDeliverable($email))                  $errors[] = 'O domínio do email parece inválido ou não recebe emails.';
    if (!validatePassword($password))                                  $errors[] = 'A palavra-passe deve ter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais.';
    if ($password !== $password2)                                      $errors[] = 'As palavras-passe não coincidem.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Este email já está em uso. Tenta iniciar sessão.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            try {
                $pdo->beginTransaction();
                // Validade calculada pelo MySQL (o verify.php compara com o NOW() do MySQL)
                $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, email_verified, verification_token, verification_expires, verification_sent_at) VALUES (:u, :e, :p, 0, :token, NOW() + INTERVAL 15 MINUTE, NOW())');
                $ins->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':token' => $verificationCode]);
                $emailService = new EmailService();
                if (!$emailService->sendVerificationEmail($email, $username, $verificationCode)) throw new RuntimeException('Falha ao enviar email.');
                $pdo->commit();
                $_SESSION['pending_verification_email'] = $email;
                header('Location: verify.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Não foi possível enviar o email de verificação. A conta não foi criada. Tenta novamente.';
            }
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
  <title>Criar conta gratuita — FreeCard</title>
  <meta name="description" content="Cria a tua conta FreeCard gratuitamente e começa a gerir os teus cartões e despesas hoje.">
  <meta name="robots" content="noindex, follow">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root[data-theme="light"] {
      --bg-primary: #f0f2f5; --bg-secondary: #ffffff; --bg-hover: #f0f0f0;
      --text-primary: #2c3e50; --text-secondary: #7f8c8d; --border-color: #e2e8f0;
      --shadow: rgba(0,0,0,0.08);
      --brand-panel-bg: linear-gradient(145deg, #1e3a5f 0%, #2c5282 50%, #2b4c7e 100%);
    }
    :root[data-theme="dark"] {
      --bg-primary: #1a1d29; --bg-secondary: #252936; --bg-hover: #2d3142;
      --text-primary: #ecf0f1; --text-secondary: #95a5a6; --border-color: #2e3448;
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

    .theme-toggle { position: fixed; top: 16px; right: 16px; z-index: 100; width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 15px; transition: all .2s; box-shadow: 0 2px 8px var(--shadow); }
    .theme-toggle:hover { color: var(--text-primary); border-color: #3498db; }

    .btn-back { position: fixed; top: 16px; left: 16px; z-index: 100; display: flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); font-size: 13px; font-weight: 500; text-decoration: none; transition: all .2s; box-shadow: 0 2px 8px var(--shadow); }
    .btn-back:hover { color: var(--text-primary); border-color: #3498db; text-decoration: none; }

    .brand-panel { width: 380px; flex-shrink: 0; background: var(--brand-panel-bg); display: flex; flex-direction: column; justify-content: center; align-items: flex-start; padding: 60px 44px; position: relative; overflow: hidden; }
    .brand-panel::before { content: ''; position: absolute; width: 300px; height: 300px; background: rgba(52,152,219,.12); border-radius: 50%; top: -60px; right: -60px; }
    .brand-panel::after  { content: ''; position: absolute; width: 200px; height: 200px; background: rgba(52,152,219,.08); border-radius: 50%; bottom: -40px; left: -40px; }
    .brand-panel .bp-logo { width: 52px; margin-bottom: 28px; position: relative; z-index: 1; }
    .brand-panel .bp-logo img { width: 100%; filter: drop-shadow(-1px 0px 0 rgba(255,255,255,0.9)) drop-shadow(1px 0px 0 rgba(255,255,255,0.9)) drop-shadow(0px -1px 0 rgba(255,255,255,0.9)) drop-shadow(0px 1px 0 rgba(255,255,255,0.9)); }
    .brand-panel h1 { font-size: 26px; font-weight: 700; color: #fff; margin-bottom: 10px; position: relative; z-index: 1; line-height: 1.3; }
    .brand-panel p { font-size: 14px; color: rgba(255,255,255,.6); margin-bottom: 36px; position: relative; z-index: 1; line-height: 1.6; }
    .brand-steps { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 14px; }
    .brand-step { display: flex; align-items: flex-start; gap: 12px; }
    .brand-step-num { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,.15); color: #fff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
    .brand-step span { font-size: 13px; color: rgba(255,255,255,.7); }

    .form-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; overflow-y: auto; }
    .auth-card { width: 100%; max-width: 420px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 18px; box-shadow: 0 4px 32px var(--shadow); overflow: hidden; animation: fadeUp .45s ease-out; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    .auth-card-header { padding: 28px 32px 22px; border-bottom: 1px solid var(--border-color); }
    .auth-card-header .logo-sm { display: none; }
    .auth-card-header h2 { font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
    .auth-card-header p { font-size: 13px; color: var(--text-secondary); margin: 0; }
    .auth-card-body { padding: 24px 32px; }
    .auth-card-footer { padding: 14px 32px; border-top: 1px solid var(--border-color); text-align: center; background: var(--bg-primary); }
    .auth-card-footer p { margin: 0; font-size: 13px; color: var(--text-secondary); }
    .auth-card-footer a { color: #3498db; font-weight: 600; text-decoration: none; }
    .auth-card-footer a:hover { text-decoration: underline; }

    .f-group { margin-bottom: 16px; }
    .f-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: 7px; }
    .f-hint  { font-size: 11px; color: var(--text-secondary); margin-top: 5px; }
    .f-input-wrap { position: relative; }
    .f-input-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 14px; pointer-events: none; }
    .f-input { width: 100%; font-size: 14px; padding: 9px 12px 9px 34px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); transition: border-color .15s; outline: none; }
    .f-input:focus { border-color: #3498db; }
    .f-input.has-toggle { padding-right: 38px; }
    [data-theme="dark"] .f-input { background: var(--bg-hover); }
    .f-toggle-pw { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 14px; padding: 2px; transition: color .15s; }
    .f-toggle-pw:hover { color: var(--text-primary); }

    /* Two-col row */
    .f-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 480px) { .f-row { grid-template-columns: 1fr; } }

    /* Password strength */
    .pw-strength { height: 4px; border-radius: 2px; background: var(--border-color); margin-top: 8px; overflow: hidden; }
    .pw-strength-bar { height: 100%; border-radius: 2px; width: 0; transition: width .35s, background .35s; }
    .pw-req { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-top: 8px; }
    .pw-req-item { font-size: 11px; color: var(--text-secondary); display: flex; align-items: center; gap: 4px; }
    .pw-req-item i { font-size: 10px; }
    .pw-req-item.ok { color: #22c55e; }

    .btn-s { font-size: 13px; font-weight: 500; padding: 8px 16px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; transition: all .15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-primary { background: #3498db; color: #fff; border-color: #3498db; width: 100%; justify-content: center; padding: 10px 16px; font-size: 14px; }
    .btn-s-primary:hover { background: #2980b9; border-color: #2980b9; color: #fff; }

    .a-error { border-radius: 10px; padding: 11px 14px; font-size: 13px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 9px; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.08); color: #b91c1c; }
    [data-theme="dark"] .a-error { color: #fca5a5; background: rgba(239,68,68,.1); }

    @media (max-width: 768px) {
      .brand-panel { display: none; }
      .form-panel { padding: 24px 16px; align-items: flex-start; padding-top: 60px; }
      .auth-card-header .logo-sm { display: block; width: 40px; margin-bottom: 14px; }
      .auth-card-body { padding: 20px 24px; }
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

<div class="brand-panel">
  <div class="bp-logo"><img src="../assets/logo2.png" alt="FreeCard"></div>
  <h1>Cria a tua conta grátis</h1>
  <p>Em menos de 2 minutos começas a gerir as tuas finanças.</p>
  <div class="brand-steps">
    <div class="brand-step"><div class="brand-step-num">1</div><span>Cria a tua conta com email e palavra-passe</span></div>
    <div class="brand-step"><div class="brand-step-num">2</div><span>Confirma o email com o código que recebes</span></div>
    <div class="brand-step"><div class="brand-step-num">3</div><span>Adiciona os teus cartões e começa a registar despesas</span></div>
  </div>
</div>

<div class="form-panel">
  <div class="auth-card">
    <div class="auth-card-header">
      <img class="logo-sm" src="../assets/logo2.png" alt="FreeCard">
      <h2>Criar conta</h2>
      <p>Preenche os campos abaixo para te registares</p>
    </div>
    <div class="auth-card-body">
      <?php if (!empty($errors)): ?>
        <div class="a-error">
          <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
          <div><?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?></div>
        </div>
      <?php endif; ?>

      <a href="google_redirect.php" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 6.293C4.672 4.169 6.656 3.58 9 3.58z"/></svg>
        Registar com Google
      </a>
      <div class="or-divider"><span>ou regista-te com email</span></div>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">

        <div class="f-group">
          <label class="f-label">Nome</label>
          <div class="f-input-wrap">
            <i class="bi bi-person f-input-icon"></i>
            <input type="text" name="username" class="f-input" placeholder="ex: João Silva"
                   value="<?=htmlspecialchars($username)?>" required autofocus>
          </div>
          <p class="f-hint">Mínimo 3 caracteres. Pode conter espaços.</p>
        </div>

        <div class="f-row">
          <div class="f-group">
            <label class="f-label">Email</label>
            <div class="f-input-wrap">
              <i class="bi bi-envelope f-input-icon"></i>
              <input type="email" name="email" class="f-input" placeholder="o teu email"
                     value="<?=htmlspecialchars($email)?>" required>
            </div>
          </div>
          <div class="f-group">
            <label class="f-label">Confirmar email</label>
            <div class="f-input-wrap">
              <i class="bi bi-envelope-check f-input-icon"></i>
              <input type="email" name="email_confirm" class="f-input" placeholder="repete o email"
                     value="<?=htmlspecialchars($emailConfirm)?>" required>
            </div>
          </div>
        </div>

        <div class="f-group">
          <label class="f-label">Palavra-passe</label>
          <div class="f-input-wrap">
            <i class="bi bi-lock f-input-icon"></i>
            <input type="password" name="password" id="pw1" class="f-input has-toggle"
                   placeholder="mínimo 8 caracteres" required>
            <button type="button" class="f-toggle-pw" onclick="togglePw('pw1','eye1')">
              <i class="bi bi-eye" id="eye1"></i>
            </button>
          </div>
          <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
          <div class="pw-req" id="pwReq">
            <div class="pw-req-item" id="req-len"><i class="bi bi-circle"></i> 8+ caracteres</div>
            <div class="pw-req-item" id="req-upper"><i class="bi bi-circle"></i> Maiúscula</div>
            <div class="pw-req-item" id="req-lower"><i class="bi bi-circle"></i> Minúscula</div>
            <div class="pw-req-item" id="req-num"><i class="bi bi-circle"></i> Número</div>
            <div class="pw-req-item" id="req-special"><i class="bi bi-circle"></i> Especial (!@#…)</div>
          </div>
        </div>

        <div class="f-group">
          <label class="f-label">Confirmar palavra-passe</label>
          <div class="f-input-wrap">
            <i class="bi bi-lock-fill f-input-icon"></i>
            <input type="password" name="password2" id="pw2" class="f-input has-toggle"
                   placeholder="repete a palavra-passe" required>
            <button type="button" class="f-toggle-pw" onclick="togglePw('pw2','eye2')">
              <i class="bi bi-eye" id="eye2"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-s btn-s-primary">
          <i class="bi bi-person-check"></i> Criar conta
        </button>
      </form>
    </div>
    <div class="auth-card-footer">
      <p>Já tens conta? <a href="login.php">Entra aqui</a></p>
    </div>
  </div>
</div>

<script>
// Theme
const html = document.documentElement;
const btn = document.getElementById('themeBtn');
const icon = document.getElementById('themeIcon');
applyTheme(localStorage.getItem('fc_theme') || 'light');
btn.addEventListener('click', () => {
  const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
  applyTheme(next); localStorage.setItem('fc_theme', next);
});
function applyTheme(t) {
  html.dataset.theme = t;
  icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
}

// Toggle password
function togglePw(inputId, iconId) {
  const inp = document.getElementById(inputId);
  const ico = document.getElementById(iconId);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  ico.className = inp.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

// Password strength
const pw1 = document.getElementById('pw1');
const bar = document.getElementById('pwBar');
const reqs = {
  'req-len':     v => v.length >= 8,
  'req-upper':   v => /[A-Z]/.test(v),
  'req-lower':   v => /[a-z]/.test(v),
  'req-num':     v => /[0-9]/.test(v),
  'req-special': v => /[^A-Za-z0-9]/.test(v),
};
pw1.addEventListener('input', function() {
  const v = this.value;
  let score = 0;
  for (const [id, fn] of Object.entries(reqs)) {
    const el = document.getElementById(id);
    if (fn(v)) { score++; el.classList.add('ok'); el.querySelector('i').className = 'bi bi-check-circle-fill'; }
    else { el.classList.remove('ok'); el.querySelector('i').className = 'bi bi-circle'; }
  }
  const colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#22c55e', '#16a34a'];
  const widths = ['0%', '20%', '40%', '65%', '85%', '100%'];
  bar.style.width = widths[score];
  bar.style.background = colors[score] || '';
});

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