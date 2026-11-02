<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/EmailService.php';

if (isset($_SESSION['user_id'])) { header('Location: ../dashboard.php'); exit; }

// Proteção contra força bruta: o código só vale para a conta desta sessão,
// tem poucas tentativas e expira depressa.
const VERIFY_MAX_ATTEMPTS   = 5;
const VERIFY_CODE_MINUTES   = 15;
const VERIFY_RESEND_SECONDS = 60;

$status   = 'pending';
$message  = '';
$username = '';
$email    = $_SESSION['pending_verification_email'] ?? '';
$errors   = [];

// Processar código de verificação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend'])) {
    // Juntar os 6 dígitos separados ou aceitar campo único
    $digits = '';
    for ($i = 1; $i <= 6; $i++) {
        $digits .= $_POST["d$i"] ?? '';
    }
    if ($digits === '') $digits = trim($_POST['code'] ?? '');

    if ($email === '') {
        $errors[] = 'Sessão expirada. Faz login para receberes um novo código.';
    } elseif (strlen($digits) !== 6 || !ctype_digit($digits)) {
        $errors[] = 'Por favor, introduz um código válido de 6 dígitos.';
    } else {
        // Procurar a conta pelo email da sessão — nunca só pelo código
        $stmt = $pdo->prepare('SELECT id, username, email, email_verified, verification_token, verification_attempts,
                                      verification_expires < NOW() AS is_expired
                               FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Gastar uma tentativa ANTES de comparar. Um único UPDATE atómico evita que
        // pedidos em paralelo leiam o mesmo contador e ultrapassem o limite.
        $reserved = false;
        if ($user && !$user['email_verified']) {
            $upd = $pdo->prepare('UPDATE users SET verification_attempts = verification_attempts + 1
                                  WHERE id = :id AND email_verified = 0 AND verification_token IS NOT NULL
                                    AND verification_expires > NOW() AND verification_attempts < :max');
            $upd->execute([':id' => $user['id'], ':max' => VERIFY_MAX_ATTEMPTS]);
            $reserved = $upd->rowCount() === 1;
        }

        if (!$user) {
            $errors[] = 'Código de verificação inválido.';
        } elseif ($user['email_verified'] == 1) {
            $status = 'already_verified';
        } elseif (!$reserved && $user['verification_token'] !== null && $user['is_expired']) {
            $status = 'expired';
        } elseif (!$reserved) {
            $errors[] = 'Demasiadas tentativas. Pede um novo código.';
        } elseif (hash_equals($user['verification_token'], $digits)) {
            $pdo->prepare('UPDATE users SET email_verified = 1, verification_token = NULL, verification_expires = NULL, verification_attempts = 0 WHERE id = :id')
                ->execute([':id' => $user['id']]);
            try { (new EmailService())->sendWelcomeEmail($user['email'], $user['username']); } catch (Exception $e) {}
            $username = $user['username'];
            $status = 'success';
            session_regenerate_id(true);
            unset($_SESSION['pending_verification_email']);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
        } else {
            $remaining = VERIFY_MAX_ATTEMPTS - ($user['verification_attempts'] + 1);
            if ($remaining <= 0) {
                // Esgotou as tentativas: o código deixa de existir
                $pdo->prepare('UPDATE users SET verification_token = NULL WHERE id = :id')->execute([':id' => $user['id']]);
                $errors[] = 'Demasiadas tentativas. Pede um novo código.';
            } else {
                $errors[] = "Código de verificação inválido. Restam $remaining tentativa" . ($remaining > 1 ? 's' : '') . '.';
            }
        }
    }
}

// Reenviar código
if (isset($_POST['resend']) && $email) {
    // Todos os cálculos de tempo são feitos pelo MySQL, para usar um só relógio
    $stmt = $pdo->prepare('SELECT id, username, email_verified,
                                  TIMESTAMPDIFF(SECOND, NOW(), verification_sent_at + INTERVAL ' . VERIFY_RESEND_SECONDS . ' SECOND) AS wait_seconds
                           FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if ($user && $user['email_verified'] == 0) {
        $wait = (int) $user['wait_seconds'];
        if ($wait > 0) {
            $errors[] = "Aguarda $wait segundos antes de pedires um novo código.";
        } else {
            $newCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE users SET verification_token = :code, verification_expires = NOW() + INTERVAL ' . VERIFY_CODE_MINUTES . ' MINUTE,
                                  verification_attempts = 0, verification_sent_at = NOW() WHERE id = :id')
                ->execute([':code' => $newCode, ':id' => $user['id']]);
            try {
                if ((new EmailService())->sendVerificationEmail($email, $user['username'], $newCode)) $message = 'Novo código enviado!';
                else $errors[] = 'Erro ao enviar email. Tenta novamente.';
            } catch (Exception $e) { $errors[] = 'Erro ao enviar email.'; }
        }
    }
}

// Auto-redirect on success after short delay
$autoRedirect = ($status === 'success');
?>
<!doctype html>
<html lang="pt-PT" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificação de Email - Finora</title>
  <?php if ($autoRedirect): ?>
  <meta http-equiv="refresh" content="3;url=../dashboard.php">
  <?php endif; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root[data-theme="light"] { --bg-primary:#f0f2f5;--bg-secondary:#ffffff;--bg-hover:#f0f0f0;--text-primary:#2c3e50;--text-secondary:#7f8c8d;--border-color:#e2e8f0;--shadow:rgba(0,0,0,0.08); }
    :root[data-theme="dark"]  { --bg-primary:#1a1d29;--bg-secondary:#252936;--bg-hover:#2d3142;--text-primary:#ecf0f1;--text-secondary:#95a5a6;--border-color:#34495e;--shadow:rgba(0,0,0,0.35); }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{min-height:100vh;background:var(--bg-primary);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:var(--text-primary);transition:background .3s,color .3s;display:flex;align-items:center;justify-content:center;padding:40px 16px;}
    .theme-toggle{position:fixed;top:16px;right:16px;z-index:100;width:36px;height:36px;border-radius:10px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-secondary);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .2s;box-shadow:0 2px 8px var(--shadow);}
    .theme-toggle:hover{color:var(--text-primary);border-color:#3498db;}
    .auth-card{width:100%;max-width:440px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:18px;box-shadow:0 4px 32px var(--shadow);overflow:hidden;animation:fadeUp .45s ease-out;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .auth-card-header{padding:32px 32px 24px;border-bottom:1px solid var(--border-color);text-align:center;}
    .hdr-icon{width:56px;height:56px;border-radius:14px;background:rgba(52,152,219,.1);border:1px solid rgba(52,152,219,.2);display:flex;align-items:center;justify-content:center;font-size:24px;color:#3498db;margin:0 auto 16px;}
    .hdr-icon.success{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.2);color:#22c55e;}
    .hdr-icon.warning{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.2);color:#f59e0b;}
    .auth-card-header h2{font-size:20px;font-weight:700;color:var(--text-primary);margin-bottom:4px;}
    .auth-card-header p{font-size:13px;color:var(--text-secondary);margin:0;line-height:1.6;}
    .auth-card-header .email-badge{display:inline-flex;align-items:center;gap:6px;background:var(--bg-hover);border:1px solid var(--border-color);border-radius:20px;padding:4px 12px;font-size:12px;color:var(--text-secondary);margin-top:10px;}
    .auth-card-body{padding:28px 32px;}
    .auth-card-footer{padding:14px 32px;border-top:1px solid var(--border-color);background:var(--bg-primary);text-align:center;}
    .auth-card-footer a{font-size:13px;color:var(--text-secondary);text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
    .auth-card-footer a:hover{color:#3498db;}

    /* OTP input row */
    .otp-row{display:flex;gap:8px;justify-content:center;margin:8px 0 20px;}
    .otp-digit{width:48px;height:56px;border:2px solid var(--border-color);border-radius:12px;background:var(--bg-primary);color:var(--text-primary);font-size:22px;font-weight:700;text-align:center;outline:none;transition:border-color .15s,box-shadow .15s;caret-color:transparent;}
    .otp-digit:focus{border-color:#3498db;box-shadow:0 0 0 3px rgba(52,152,219,.15);}
    [data-theme="dark"] .otp-digit{background:var(--bg-hover);}
    .otp-hint{font-size:12px;color:var(--text-secondary);text-align:center;margin-bottom:20px;}

    .btn-s-primary{background:#3498db;color:#fff;border:1px solid #3498db;border-radius:10px;width:100%;padding:10px 16px;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:7px;}
    .btn-s-primary:hover{background:#2980b9;border-color:#2980b9;}
    .btn-ghost{background:none;border:none;color:var(--text-secondary);font-size:13px;cursor:pointer;display:flex;align-items:center;gap:5px;margin:14px auto 0;padding:6px 10px;border-radius:8px;transition:all .15s;}
    .btn-ghost:hover{background:var(--bg-hover);color:var(--text-primary);}

    .a-error{border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#b91c1c;}
    [data-theme="dark"] .a-error{color:#fca5a5;background:rgba(239,68,68,.1);}
    .a-info{border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px;border:1px solid rgba(52,152,219,.3);background:rgba(52,152,219,.07);color:#1d4ed8;}
    [data-theme="dark"] .a-info{color:#93c5fd;background:rgba(52,152,219,.1);}

    .status-body{text-align:center;padding:8px 0;}
    .status-body p{font-size:14px;color:var(--text-secondary);margin-bottom:20px;line-height:1.6;}
    .btn-go{display:inline-flex;align-items:center;gap:7px;background:#3498db;color:#fff;border:1px solid #3498db;border-radius:10px;padding:10px 20px;font-size:14px;font-weight:500;text-decoration:none;transition:all .15s;}
    .btn-go:hover{background:#2980b9;color:#fff;}
    .progress-redirect{height:3px;background:var(--border-color);border-radius:2px;margin-top:20px;overflow:hidden;}
    .progress-redirect-bar{height:100%;background:#22c55e;border-radius:2px;animation:countdown 3s linear forwards;}
    @keyframes countdown{from{width:100%}to{width:0%}}
  </style>
</head>
<body>
<button class="theme-toggle" id="themeBtn"><i class="bi bi-moon-fill" id="themeIcon"></i></button>

<div class="auth-card">
  <?php if ($status === 'success'): ?>
    <div class="auth-card-header">
      <div class="hdr-icon success"><i class="bi bi-check-lg"></i></div>
      <h2>Email verificado!</h2>
      <p>Bem-vindo ao Finora, <strong><?=htmlspecialchars($username)?></strong>! A tua conta está ativa.</p>
    </div>
    <div class="auth-card-body">
      <div class="status-body">
        <p>A redirecionar para o dashboard em 3 segundos…</p>
        <a href="../dashboard.php" class="btn-go"><i class="bi bi-speedometer2"></i> Ir para o dashboard</a>
        <div class="progress-redirect"><div class="progress-redirect-bar"></div></div>
      </div>
    </div>

  <?php elseif ($status === 'already_verified'): ?>
    <div class="auth-card-header">
      <div class="hdr-icon success"><i class="bi bi-patch-check-fill"></i></div>
      <h2>Conta já verificada</h2>
      <p>Esta conta já foi verificada anteriormente. Podes fazer login normalmente.</p>
    </div>
    <div class="auth-card-body">
      <div class="status-body">
        <a href="login.php" class="btn-go"><i class="bi bi-box-arrow-in-right"></i> Ir para o login</a>
      </div>
    </div>

  <?php elseif ($status === 'expired'): ?>
    <div class="auth-card-header">
      <div class="hdr-icon warning"><i class="bi bi-clock-history"></i></div>
      <h2>Código expirado</h2>
      <p>O código de verificação expirou. Solicita um novo abaixo.</p>
    </div>
    <div class="auth-card-body">
      <div class="status-body">
        <?php if ($email): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="resend" value="1">
          <button type="submit" class="btn-go"><i class="bi bi-send"></i> Reenviar código</button>
        </form>
        <?php else: ?>
        <a href="register.php" class="btn-go"><i class="bi bi-person-plus"></i> Criar nova conta</a>
        <?php endif; ?>
      </div>
    </div>

  <?php else: /* pending */ ?>
    <div class="auth-card-header">
      <div class="hdr-icon"><i class="bi bi-envelope-open"></i></div>
      <h2>Verifica o teu email</h2>
      <p>Enviámos um código de 6 dígitos para</p>
      <?php if ($email): ?>
        <div class="email-badge"><i class="bi bi-envelope"></i><?=htmlspecialchars($email)?></div>
      <?php endif; ?>
    </div>
    <div class="auth-card-body">
      <?php if (!empty($errors)): ?>
        <div class="a-error">
          <i class="bi bi-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
          <div><?php foreach($errors as $e): ?><?=htmlspecialchars($e)?><?php endforeach; ?></div>
        </div>
      <?php endif; ?>
      <?php if ($message): ?>
        <div class="a-info">
          <i class="bi bi-info-circle" style="flex-shrink:0;margin-top:1px"></i>
          <?=htmlspecialchars($message)?>
        </div>
      <?php endif; ?>

      <form method="POST" id="otpForm">
        <!-- Visible OTP boxes -->
        <div class="otp-row">
          <?php for($i=1;$i<=6;$i++): ?>
            <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                   class="otp-digit" id="d<?=$i?>" name="d<?=$i?>" autocomplete="off">
          <?php endfor; ?>
        </div>
        <p class="otp-hint">Verifica a pasta de spam se não recebeste o email.</p>
        <button type="submit" class="btn-s-primary">
          <i class="bi bi-shield-check"></i> Verificar código
        </button>
      </form>

      <?php if ($email): ?>
      <form method="POST" style="display:flex;justify-content:center">
        <input type="hidden" name="resend" value="1">
        <button type="submit" class="btn-ghost">
          <i class="bi bi-arrow-clockwise"></i> Reenviar código
        </button>
      </form>
      <?php endif; ?>
    </div>
    <div class="auth-card-footer">
      <a href="login.php"><i class="bi bi-arrow-left"></i> Voltar ao login</a>
    </div>
  <?php endif; ?>
</div>

<script>
// Theme
const html = document.documentElement, btn = document.getElementById('themeBtn'), icon = document.getElementById('themeIcon');
applyTheme(localStorage.getItem('fc_theme') || 'light');
btn.addEventListener('click', () => { const n = html.dataset.theme === 'dark' ? 'light' : 'dark'; applyTheme(n); localStorage.setItem('fc_theme', n); });
function applyTheme(t) { html.dataset.theme = t; icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill'; }

// OTP input logic
const digits = document.querySelectorAll('.otp-digit');
if (digits.length) {
  digits[0].focus();
  digits.forEach((el, i) => {
    el.addEventListener('input', function() {
      // Accept paste of full code
      if (this.value.length > 1) {
        const val = this.value.replace(/\D/g, '').slice(0, 6);
        digits.forEach((d, j) => { d.value = val[j] || ''; });
        if (val.length === 6) document.getElementById('otpForm').submit();
        else digits[Math.min(val.length, 5)].focus();
        return;
      }
      this.value = this.value.replace(/\D/g, '');
      if (this.value && i < 5) digits[i + 1].focus();
      if (digits[5].value) document.getElementById('otpForm').submit();
    });
    el.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && !this.value && i > 0) digits[i - 1].focus();
    });
    el.addEventListener('paste', function(e) {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
      digits.forEach((d, j) => { d.value = text[j] || ''; });
      if (text.length === 6) document.getElementById('otpForm').submit();
    });
  });
}
</script>
</body>
</html>
