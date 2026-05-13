<?php
// site/auth/reset_password.php
session_start();
require_once __DIR__ . '/../../config/db.php';

$errors = [];
$token  = $_GET['token'] ?? '';
$user   = null;
$status = 'invalid';

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, username, email, password_reset_expires FROM users WHERE password_reset_token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();
    if ($user) {
        $status = strtotime($user['password_reset_expires']) < time() ? 'expired' : 'valid';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status === 'valid') {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if (strlen($password) < 8)                     $errors[] = 'Mínimo 8 caracteres.';
    elseif (!preg_match('/[A-Z]/', $password))      $errors[] = 'Inclui pelo menos uma maiúscula.';
    elseif (!preg_match('/[a-z]/', $password))      $errors[] = 'Inclui pelo menos uma minúscula.';
    elseif (!preg_match('/[0-9]/', $password))      $errors[] = 'Inclui pelo menos um número.';
    elseif (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Inclui pelo menos um caractere especial.';
    elseif ($password !== $password2)               $errors[] = 'As palavras-passe não coincidem.';
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = :hash, password_reset_token = NULL, password_reset_expires = NULL, last_password_change = NOW() WHERE id = :id')
            ->execute([':hash' => $hash, ':id' => $user['id']]);
        $status = 'success';
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redefinir Palavra-passe - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root[data-theme="light"] { --bg-primary:#f0f2f5;--bg-secondary:#ffffff;--bg-hover:#f0f0f0;--text-primary:#2c3e50;--text-secondary:#7f8c8d;--border-color:#e2e8f0;--shadow:rgba(0,0,0,0.08); }
    :root[data-theme="dark"]  { --bg-primary:#1a1d29;--bg-secondary:#252936;--bg-hover:#2d3142;--text-primary:#ecf0f1;--text-secondary:#95a5a6;--border-color:#2e3448;--shadow:rgba(0,0,0,0.35); }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{min-height:100vh;background:var(--bg-primary);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:var(--text-primary);transition:background .3s,color .3s;display:flex;align-items:center;justify-content:center;padding:40px 16px;}
    .theme-toggle{position:fixed;top:16px;right:16px;z-index:100;width:36px;height:36px;border-radius:10px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-secondary);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .2s;box-shadow:0 2px 8px var(--shadow);}
    .theme-toggle:hover{color:var(--text-primary);border-color:#3498db;}
    .auth-card{width:100%;max-width:420px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:18px;box-shadow:0 4px 32px var(--shadow);overflow:hidden;animation:fadeUp .45s ease-out;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .auth-card-header{padding:32px 32px 24px;border-bottom:1px solid var(--border-color);text-align:center;}
    .hdr-icon{width:52px;height:52px;border-radius:14px;background:rgba(52,152,219,.1);border:1px solid rgba(52,152,219,.2);display:flex;align-items:center;justify-content:center;font-size:22px;color:#3498db;margin:0 auto 16px;}
    .hdr-icon.success{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.2);color:#22c55e;}
    .hdr-icon.danger{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.2);color:#ef4444;}
    .auth-card-header h2{font-size:20px;font-weight:700;color:var(--text-primary);margin-bottom:4px;}
    .auth-card-header p{font-size:13px;color:var(--text-secondary);margin:0;line-height:1.5;}
    .auth-card-body{padding:28px 32px;}
    .auth-card-footer{padding:14px 32px;border-top:1px solid var(--border-color);background:var(--bg-primary);text-align:center;}
    .auth-card-footer a{font-size:13px;color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
    .auth-card-footer a:hover{color:#3498db;}
    .f-group{margin-bottom:16px;}
    .f-label{font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:7px;}
    .f-input-wrap{position:relative;}
    .f-input-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:14px;pointer-events:none;}
    .f-input{width:100%;font-size:14px;padding:9px 12px 9px 34px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-primary);color:var(--text-primary);transition:border-color .15s;outline:none;}
    .f-input:focus{border-color:#3498db;}
    .f-input.has-toggle{padding-right:38px;}
    [data-theme="dark"] .f-input{background:var(--bg-hover);}
    .f-toggle-pw{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:14px;padding:2px;transition:color .15s;}
    .f-toggle-pw:hover{color:var(--text-primary);}
    .pw-strength{height:4px;border-radius:2px;background:var(--border-color);margin-top:8px;overflow:hidden;}
    .pw-strength-bar{height:100%;border-radius:2px;width:0;transition:width .35s,background .35s;}
    .btn-s-primary{background:#3498db;color:#fff;border:1px solid #3498db;border-radius:10px;width:100%;padding:10px 16px;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:7px;}
    .btn-s-primary:hover{background:#2980b9;border-color:#2980b9;}
    .a-error{border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#b91c1c;}
    [data-theme="dark"] .a-error{color:#fca5a5;background:rgba(239,68,68,.1);}
    .status-body{text-align:center;padding:8px 0;}
    .status-body p{font-size:14px;color:var(--text-secondary);margin-bottom:20px;line-height:1.6;}
    .btn-go{display:inline-flex;align-items:center;gap:7px;background:#3498db;color:#fff;border:1px solid #3498db;border-radius:10px;padding:10px 20px;font-size:14px;font-weight:500;text-decoration:none;transition:all .15s;}
    .btn-go:hover{background:#2980b9;color:#fff;}
    .btn-ghost{display:inline-flex;align-items:center;gap:7px;color:#3498db;font-size:13px;text-decoration:none;margin-top:14px;}
    .btn-ghost:hover{text-decoration:underline;}
  </style>
</head>
<body>
<button class="theme-toggle" id="themeBtn"><i class="bi bi-moon-fill" id="themeIcon"></i></button>

<div class="auth-card">
  <?php if ($status === 'success'): ?>
    <div class="auth-card-header">
      <div class="hdr-icon success"><i class="bi bi-check-lg"></i></div>
      <h2>Palavra-passe alterada!</h2>
      <p>A tua palavra-passe foi atualizada com sucesso.</p>
    </div>
    <div class="auth-card-body">
      <div class="status-body">
        <p>Já podes entrar na tua conta com a nova palavra-passe.</p>
        <a href="login.php" class="btn-go"><i class="bi bi-box-arrow-in-right"></i> Ir para o login</a>
      </div>
    </div>

  <?php elseif ($status === 'expired'): ?>
    <div class="auth-card-header">
      <div class="hdr-icon danger"><i class="bi bi-clock-history"></i></div>
      <h2>Link expirado</h2>
      <p>O link de recuperação expirou. Os links são válidos por 1 hora.</p>
    </div>
    <div class="auth-card-body">
      <div class="status-body">
        <p>Solicita um novo link de recuperação para continuares.</p>
        <a href="forgot_password.php" class="btn-go"><i class="bi bi-send"></i> Solicitar novo link</a>
      </div>
    </div>

  <?php elseif ($status === 'invalid'): ?>
    <div class="auth-card-header">
      <div class="hdr-icon danger"><i class="bi bi-x-circle"></i></div>
      <h2>Link inválido</h2>
      <p>Este link de recuperação não é válido ou já foi utilizado.</p>
    </div>
    <div class="auth-card-body">
      <div class="status-body">
        <a href="forgot_password.php" class="btn-go"><i class="bi bi-send"></i> Solicitar novo link</a>
      </div>
    </div>

  <?php else: /* valid */ ?>
    <div class="auth-card-header">
      <div class="hdr-icon"><i class="bi bi-shield-lock"></i></div>
      <h2>Nova Palavra-passe</h2>
      <p>Escolhe uma nova palavra-passe segura para a conta <strong><?=htmlspecialchars($user['username'])?></strong>.</p>
    </div>
    <div class="auth-card-body">
      <?php if (!empty($errors)): ?>
        <div class="a-error">
          <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
          <div><?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?></div>
        </div>
      <?php endif; ?>
      <form method="POST" action="?token=<?=urlencode($token)?>">
        <div class="f-group">
          <label class="f-label">Nova palavra-passe</label>
          <div class="f-input-wrap">
            <i class="bi bi-lock f-input-icon"></i>
            <input type="password" name="password" id="pw1" class="f-input has-toggle" placeholder="mínimo 8 caracteres" required autofocus>
            <button type="button" class="f-toggle-pw" onclick="togglePw('pw1','e1')"><i class="bi bi-eye" id="e1"></i></button>
          </div>
          <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
        </div>
        <div class="f-group">
          <label class="f-label">Confirmar palavra-passe</label>
          <div class="f-input-wrap">
            <i class="bi bi-lock-fill f-input-icon"></i>
            <input type="password" name="password2" id="pw2" class="f-input has-toggle" placeholder="repete a palavra-passe" required>
            <button type="button" class="f-toggle-pw" onclick="togglePw('pw2','e2')"><i class="bi bi-eye" id="e2"></i></button>
          </div>
        </div>
        <button type="submit" class="btn-s-primary">
          <i class="bi bi-check-circle"></i> Guardar nova palavra-passe
        </button>
      </form>
    </div>
    <div class="auth-card-footer">
      <a href="login.php"><i class="bi bi-arrow-left"></i> Voltar ao login</a>
    </div>
  <?php endif; ?>
</div>

<script>
const html = document.documentElement, btn = document.getElementById('themeBtn'), icon = document.getElementById('themeIcon');
applyTheme(localStorage.getItem('fc_theme') || 'light');
btn.addEventListener('click', () => { const n = html.dataset.theme === 'dark' ? 'light' : 'dark'; applyTheme(n); localStorage.setItem('fc_theme', n); });
function applyTheme(t) { html.dataset.theme = t; icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill'; }
function togglePw(id, eid) { const i = document.getElementById(id), e = document.getElementById(eid); i.type = i.type === 'password' ? 'text' : 'password'; e.className = i.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye'; }
const pw1el = document.getElementById('pw1');
if (pw1el) {
  const bar = document.getElementById('pwBar');
  pw1el.addEventListener('input', function() {
    const v = this.value; let s = 0;
    if(v.length>=8) s++; if(/[A-Z]/.test(v)) s++; if(/[a-z]/.test(v)) s++; if(/[0-9]/.test(v)) s++; if(/[^A-Za-z0-9]/.test(v)) s++;
    const c=['','#ef4444','#f59e0b','#3b82f6','#22c55e','#16a34a'],w=['0%','20%','40%','65%','85%','100%'];
    bar.style.width=w[s]; bar.style.background=c[s]||'';
  });
}
</script>
</body>
</html>
