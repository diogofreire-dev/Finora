<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';

$status = 'pending'; // 'pending', 'invalid', 'expired', 'success', 'already_verified'
$message = '';
$username = '';
$email = $_SESSION['pending_verification_email'] ?? '';
$errors = [];

// Processar código de verificação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $errors[] = 'Por favor, introduz um código válido de 6 dígitos.';
    } else {
        $stmt = $pdo->prepare('
            SELECT id, username, email, email_verified, verification_expires
            FROM users
            WHERE verification_token = :code
            LIMIT 1
        ');
        $stmt->execute([':code' => $code]);
        $user = $stmt->fetch();

        if ($user) {
            $username = $user['username'];

            if ($user['email_verified'] == 1) {
                $status = 'already_verified';
                $message = 'Esta conta já foi verificada anteriormente!';
            } elseif (strtotime($user['verification_expires']) < time()) {
                $status = 'expired';
                $message = 'O código de verificação expirou. Por favor, solicita um novo código.';
            } else {
                // Verificar a conta
                $update = $pdo->prepare('
                    UPDATE users
                    SET email_verified = 1, verification_token = NULL, verification_expires = NULL
                    WHERE id = :id
                ');
                $update->execute([':id' => $user['id']]);

                // Enviar email de boas-vindas
                $emailService = new EmailService();
                $emailService->sendWelcomeEmail($user['email'], $user['username']);

                $status = 'success';
                $message = 'Conta verificada com sucesso! Bem-vindo ao FreeCard!';

                // Limpar sessão de verificação pendente
                unset($_SESSION['pending_verification_email']);

                // Login automático
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                session_regenerate_id(true);
            }
        } else {
            $errors[] = 'Código de verificação inválido.';
        }
    }
}

// Reenviar código
if (isset($_POST['resend']) && $email) {
    $stmt = $pdo->prepare('SELECT id, username, email_verified FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && $user['email_verified'] == 0) {
        // Gerar novo código
        $newCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $update = $pdo->prepare('UPDATE users SET verification_token = :code, verification_expires = :expires WHERE id = :id');
        $update->execute([':code' => $newCode, ':expires' => $tokenExpiry, ':id' => $user['id']]);

        // Enviar novo email
        $emailService = new EmailService();
        if ($emailService->sendVerificationEmail($email, $user['username'], $newCode)) {
            $message = 'Novo código enviado para o teu email!';
        } else {
            $errors[] = 'Erro ao enviar email. Tenta novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificação de Email - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root {
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
      --success-green: #27ae60;
      --error-red: #e74c3c;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: linear-gradient(135deg, #1e3c72 0%, #2c3e50 50%, #1e3c72 100%);
      background-attachment: fixed;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }
    
    .verify-container {
      max-width: 600px;
      width: 100%;
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      animation: slideIn 0.6s ease-out;
    }
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .status-header {
      padding: 60px 40px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    
    .status-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      opacity: 0.1;
    }
    
    .status-success::before {
      background: linear-gradient(135deg, #27ae60, #2ecc71);
    }
    
    .status-error::before {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
    }
    
    .status-warning::before {
      background: linear-gradient(135deg, #f39c12, #e67e22);
    }
    
    .status-icon {
      font-size: 80px;
      margin-bottom: 20px;
      animation: bounceIn 0.8s;
      position: relative;
      z-index: 1;
    }
    
    @keyframes bounceIn {
      0% { transform: scale(0); }
      50% { transform: scale(1.2); }
      100% { transform: scale(1); }
    }
    
    .status-success .status-icon {
      color: var(--success-green);
    }
    
    .status-error .status-icon {
      color: var(--error-red);
    }
    
    .status-warning .status-icon {
      color: #f39c12;
    }
    
    .status-title {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 15px;
      position: relative;
      z-index: 1;
    }
    
    .status-success .status-title {
      color: var(--success-green);
    }
    
    .status-error .status-title {
      color: var(--error-red);
    }
    
    .status-warning .status-title {
      color: #f39c12;
    }
    
    .status-message {
      font-size: 16px;
      color: #7f8c8d;
      position: relative;
      z-index: 1;
    }
    
    .username-badge {
      display: inline-block;
      background: rgba(52, 152, 219, 0.1);
      color: var(--primary-blue);
      padding: 8px 20px;
      border-radius: 20px;
      font-weight: 600;
      margin: 15px 0;
      border: 2px solid var(--primary-blue);
    }
    
    .verify-content {
      padding: 40px;
      border-top: 1px solid #e9ecef;
    }
    
    .info-box {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 25px;
    }
    
    .info-box h4 {
      color: #2c3e50;
      font-size: 18px;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .info-box p {
      color: #7f8c8d;
      margin: 0;
      font-size: 14px;
    }
    
    .feature-list {
      list-style: none;
      padding: 0;
      margin: 20px 0;
    }
    
    .feature-list li {
      padding: 12px 0;
      color: #2c3e50;
      display: flex;
      align-items: center;
      gap: 12px;
      border-bottom: 1px solid #e9ecef;
    }
    
    .feature-list li:last-child {
      border-bottom: none;
    }
    
    .feature-list i {
      color: var(--success-green);
      font-size: 20px;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
      border: none;
      border-radius: 12px;
      padding: 14px 32px;
      font-weight: 600;
      font-size: 16px;
      color: white;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s;
      width: 100%;
      text-align: center;
    }
    
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
      color: white;
    }
    
    .btn-secondary {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 14px 32px;
      font-weight: 600;
      font-size: 16px;
      color: #7f8c8d;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s;
      width: 100%;
      text-align: center;
      margin-top: 10px;
    }
    
    .btn-secondary:hover {
      background: #e9ecef;
      border-color: #dee2e6;
      color: #495057;
    }
    
    @media (max-width: 576px) {
      .status-header {
        padding: 40px 30px;
      }
      
      .verify-content {
        padding: 30px;
      }
      
      .status-title {
        font-size: 24px;
      }
      
      .status-icon {
        font-size: 60px;
      }
    }
  </style>
</head>
<body>

<div class="verify-container">
  <?php if ($status === 'success'): ?>
    <div class="status-header status-success">
      <div class="status-icon">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <h1 class="status-title">Conta Verificada!</h1>
      <p class="status-message"><?= htmlspecialchars($message) ?></p>
      <?php if ($username): ?>
        <div class="username-badge">
          <i class="bi bi-person-check"></i> <?= htmlspecialchars($username) ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="verify-content">
      <div class="info-box">
        <h4>
          <i class="bi bi-gift"></i>
          Bem-vindo ao FreeCard!
        </h4>
        <p>A tua conta está agora ativa e pronta para usar. Já podes começar a gerir as tuas finanças!</p>
      </div>

      <h4 style="color: #2c3e50; margin-bottom: 15px;">O que podes fazer agora:</h4>
      <ul class="feature-list">
        <li>
          <i class="bi bi-credit-card"></i>
          Adicionar os teus cartões de crédito
        </li>
        <li>
          <i class="bi bi-receipt"></i>
          Registar as tuas despesas
        </li>
        <li>
          <i class="bi bi-piggy-bank"></i>
          Criar orçamentos personalizados
        </li>
        <li>
          <i class="bi bi-bell"></i>
          Configurar lembretes de pagamento
        </li>
        <li>
          <i class="bi bi-graph-up"></i>
          Visualizar relatórios financeiros
        </li>
      </ul>

      <a href="../dashboard.php" class="btn-primary">
        <i class="bi bi-speedometer2 me-2"></i>
        Ir para o Dashboard
      </a>
    </div>

  <?php elseif ($status === 'already_verified'): ?>
    <div class="status-header status-warning">
      <div class="status-icon">
        <i class="bi bi-info-circle-fill"></i>
      </div>
      <h1 class="status-title">Já Verificado</h1>
      <p class="status-message"><?= htmlspecialchars($message) ?></p>
    </div>

    <div class="verify-content">
      <div class="info-box">
        <h4>
          <i class="bi bi-shield-check"></i>
          A tua conta está ativa
        </h4>
        <p>Podes fazer login normalmente e começar a usar o FreeCard.</p>
      </div>

      <a href="login.php" class="btn-primary">
        <i class="bi bi-box-arrow-in-right me-2"></i>
        Fazer Login
      </a>
      <a href="../index.php" class="btn-secondary">
        <i class="bi bi-house me-2"></i>
        Página Inicial
      </a>
    </div>

  <?php elseif ($status === 'expired'): ?>
    <div class="status-header status-error">
      <div class="status-icon">
        <i class="bi bi-clock-history"></i>
      </div>
      <h1 class="status-title">Código Expirado</h1>
      <p class="status-message"><?= htmlspecialchars($message) ?></p>
    </div>

    <div class="verify-content">
      <div class="info-box">
        <h4>
          <i class="bi bi-exclamation-triangle"></i>
          O que fazer agora?
        </h4>
        <p>Por motivos de segurança, os códigos de verificação expiram após 24 horas.</p>
      </div>

      <?php if ($email): ?>
      <form method="post">
        <input type="hidden" name="resend" value="1">
        <button type="submit" class="btn-primary">
          <i class="bi bi-envelope me-2"></i>
          Enviar Novo Código
        </button>
      </form>
      <?php endif; ?>
      <a href="login.php" class="btn-secondary">
        <i class="bi bi-box-arrow-in-right me-2"></i>
        Voltar ao Login
      </a>
    </div>

  <?php elseif ($status === 'pending'): ?>
    <div class="status-header" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.1));">
      <div class="status-icon" style="color: var(--primary-blue);">
        <i class="bi bi-envelope-paper"></i>
      </div>
      <h1 class="status-title" style="color: var(--primary-blue);">Verificar Conta</h1>
      <p class="status-message">Introduz o código de 6 dígitos que enviamos para o teu email</p>
      <?php if ($email): ?>
        <div class="username-badge">
          <i class="bi bi-envelope"></i> <?= htmlspecialchars($email) ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="verify-content">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3" style="background: #fdeaea; padding: 12px 16px; border-radius: 12px;">
          <i class="bi bi-exclamation-circle" style="color: #e74c3c;"></i>
          <?php foreach($errors as $e): ?>
            <span style="color: #c0392b;"><?= htmlspecialchars($e) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($message && empty($errors)): ?>
        <div class="alert alert-success mb-3" style="background: #d4edda; padding: 12px 16px; border-radius: 12px;">
          <i class="bi bi-check-circle" style="color: #27ae60;"></i>
          <span style="color: #1e7e34;"><?= htmlspecialchars($message) ?></span>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group mb-4">
          <label class="form-label" style="font-weight: 600; color: #2c3e50; margin-bottom: 10px;">
            <i class="bi bi-key"></i> Código de Verificação
          </label>
          <input
            type="text"
            name="code"
            class="form-control text-center"
            placeholder="000000"
            maxlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            style="font-size: 32px; letter-spacing: 10px; font-weight: 700; padding: 20px; border: 2px solid #e9ecef; border-radius: 12px;"
            autofocus
            required
          >
        </div>

        <button type="submit" class="btn-primary">
          <i class="bi bi-check-circle me-2"></i>
          Verificar Conta
        </button>
      </form>

      <div class="info-box" style="margin-top: 25px;">
        <h4>
          <i class="bi bi-info-circle"></i>
          Não recebeste o código?
        </h4>
        <p>Verifica a pasta de spam ou solicita um novo código.</p>
      </div>

      <?php if ($email): ?>
      <form method="post" style="margin-top: 15px;">
        <input type="hidden" name="resend" value="1">
        <button type="submit" class="btn-secondary">
          <i class="bi bi-arrow-clockwise me-2"></i>
          Reenviar Código
        </button>
      </form>
      <?php endif; ?>

      <a href="login.php" class="btn-secondary" style="margin-top: 10px;">
        <i class="bi bi-arrow-left me-2"></i>
        Voltar ao Login
      </a>
    </div>

  <?php else: ?>
    <div class="status-header status-error">
      <div class="status-icon">
        <i class="bi bi-x-circle-fill"></i>
      </div>
      <h1 class="status-title">Código Inválido</h1>
      <p class="status-message">O código introduzido não é válido.</p>
    </div>

    <div class="verify-content">
      <div class="info-box">
        <h4>
          <i class="bi bi-exclamation-triangle"></i>
          O que aconteceu?
        </h4>
        <p>O código que introduziste não é válido. Verifica se copiaste corretamente ou solicita um novo código.</p>
      </div>

      <a href="register.php" class="btn-primary">
        <i class="bi bi-person-plus me-2"></i>
        Criar Nova Conta
      </a>
      <a href="login.php" class="btn-secondary">
        <i class="bi bi-box-arrow-in-right me-2"></i>
        Fazer Login
      </a>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($status === 'success'): ?>
<script>
// Auto-redirect após 5 segundos
setTimeout(function() {
  window.location.href = '../dashboard.php';
}, 5000);
</script>
<?php endif; ?>
</body>
</html>