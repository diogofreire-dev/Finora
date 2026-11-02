<?php
// site/auth/forgot_password.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/EmailService.php';

$errors = [];
$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $errors[] = 'Por favor, preenche o campo de email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            $resetToken  = bin2hex(random_bytes(32));
            $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $update = $pdo->prepare('UPDATE users SET password_reset_token = :token, password_reset_expires = :expires WHERE id = :id');
            $update->execute([':token' => $resetToken, ':expires' => $tokenExpiry, ':id' => $user['id']]);
            $emailService = new EmailService();
            if (!$emailService->sendPasswordResetEmail($user['email'], $user['username'], $resetToken)) {
                error_log('[forgot_password] Falha ao enviar email de recuperação para o utilizador ' . $user['id']);
            }
        }
        // Resposta igual quer o email exista quer não (evita enumeração de contas)
        $success = true;
        $message = 'Se o email existir na nossa base de dados, receberás um link de recuperação.';
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar Palavra-passe - Finora</title>
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
    .auth-card-header .hdr-icon{width:52px;height:52px;border-radius:14px;background:rgba(52,152,219,.1);border:1px solid rgba(52,152,219,.2);display:flex;align-items:center;justify-content:center;font-size:22px;color:#3498db;margin:0 auto 16px;}
    .auth-card-header h2{font-size:20px;font-weight:700;color:var(--text-primary);margin-bottom:4px;}
    .auth-card-header p{font-size:13px;color:var(--text-secondary);margin:0;line-height:1.5;}
    .auth-card-body{padding:28px 32px;}
    .auth-card-footer{padding:14px 32px;border-top:1px solid var(--border-color);background:var(--bg-primary);display:flex;flex-direction:column;gap:6px;align-items:center;}
    .auth-card-footer a{font-size:13px;color:var(--text-secondary);text-decoration:none;display:flex;align-items:center;gap:5px;}
    .auth-card-footer a:hover{color:#3498db;}
    .auth-card-footer .sep{width:1px;height:14px;background:var(--border-color);}
    .auth-card-footer .links-row{display:flex;gap:16px;align-items:center;}
    .f-group{margin-bottom:18px;}
    .f-label{font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:7px;}
    .f-input-wrap{position:relative;}
    .f-input-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:14px;pointer-events:none;}
    .f-input{width:100%;font-size:14px;padding:9px 12px 9px 34px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-primary);color:var(--text-primary);transition:border-color .15s;outline:none;}
    .f-input:focus{border-color:#3498db;}
    [data-theme="dark"] .f-input{background:var(--bg-hover);}
    .btn-s-primary{background:#3498db;color:#fff;border:1px solid #3498db;border-radius:10px;width:100%;padding:10px 16px;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:7px;}
    .btn-s-primary:hover{background:#2980b9;border-color:#2980b9;}
    .a-error{border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#b91c1c;}
    [data-theme="dark"] .a-error{color:#fca5a5;background:rgba(239,68,68,.1);}
    .a-success{border-radius:10px;padding:20px;font-size:13px;border:1px solid rgba(34,197,94,.3);background:rgba(34,197,94,.07);color:#166534;text-align:center;}
    [data-theme="dark"] .a-success{color:#86efac;background:rgba(34,197,94,.1);}
    .a-success .s-icon{font-size:36px;color:#22c55e;display:block;margin-bottom:12px;}
    .a-success h3{font-size:15px;font-weight:600;margin-bottom:6px;}
    .a-success p{font-size:13px;opacity:.8;margin:0;}
    .btn-back{display:inline-flex;align-items:center;gap:6px;margin-top:16px;font-size:13px;color:#3498db;text-decoration:none;font-weight:500;}
    .btn-back:hover{text-decoration:underline;}
  </style>
</head>
<body>
<button class="theme-toggle" id="themeBtn" title="Alternar tema"><i class="bi bi-moon-fill" id="themeIcon"></i></button>

<div class="auth-card">
  <div class="auth-card-header">
    <div class="hdr-icon"><i class="bi bi-key"></i></div>
    <h2>Recuperar Palavra-passe</h2>
    <p>Introduz o teu email e enviaremos um link para redefines a tua palavra-passe.</p>
  </div>
  <div class="auth-card-body">
    <?php if ($success): ?>
      <div class="a-success">
        <i class="bi bi-envelope-check s-icon"></i>
        <h3>Pedido recebido</h3>
        <p><?= htmlspecialchars($message) ?> Verifica também a pasta de spam.</p>
      </div>
      <div class="text-center">
        <a href="login.php" class="btn-back"><i class="bi bi-arrow-left"></i> Voltar ao login</a>
      </div>
    <?php else: ?>
      <?php if (!empty($errors)): ?>
        <div class="a-error">
          <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
          <div><?php foreach($errors as $e): ?><?=htmlspecialchars($e)?><?php endforeach; ?></div>
        </div>
      <?php endif; ?>
      <form method="POST">
        <div class="f-group">
          <label class="f-label">Email</label>
          <div class="f-input-wrap">
            <i class="bi bi-envelope f-input-icon"></i>
            <input type="email" name="email" class="f-input" placeholder="o teu email"
                   value="<?=htmlspecialchars($_POST['email'] ?? '')?>" required autofocus>
          </div>
        </div>
        <button type="submit" class="btn-s-primary">
          <i class="bi bi-send"></i> Enviar link de recuperação
        </button>
      </form>
    <?php endif; ?>
  </div>
  <div class="auth-card-footer">
    <div class="links-row">
      <a href="login.php"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
      <div class="sep"></div>
      <a href="register.php"><i class="bi bi-person-plus"></i> Registar</a>
    </div>
  </div>
</div>

<script>
const html = document.documentElement, btn = document.getElementById('themeBtn'), icon = document.getElementById('themeIcon');
applyTheme(localStorage.getItem('fc_theme') || 'light');
btn.addEventListener('click', () => { const n = html.dataset.theme === 'dark' ? 'light' : 'dark'; applyTheme(n); localStorage.setItem('fc_theme', n); });
function applyTheme(t) { html.dataset.theme = t; icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill'; }
</script>
</body>
</html>
