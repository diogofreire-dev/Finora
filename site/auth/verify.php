<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';

// Se o utilizador já está autenticado, redirecionar para o dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$status = 'pending'; // 'pending', 'invalid', 'expired', 'success', 'already_verified'
$message = '';
$username = '';
$email = $_SESSION['pending_verification_email'] ?? '';
$errors = [];

// Verificar se houve problema ao enviar email
$emailWarning = false;
if (isset($_SESSION['email_send_warning'])) {
    $emailWarning = true;
    unset($_SESSION['email_send_warning']);
}

// Processar código de verificação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend'])) {
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
                try {
                    $emailService = new EmailService();
                    $emailService->sendWelcomeEmail($user['email'], $user['username']);
                } catch (Exception $e) {
                    error_log("Erro ao enviar email de boas-vindas: " . $e->getMessage());
                }

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
        try {
            $emailService = new EmailService();
            if ($emailService->sendVerificationEmail($email, $user['username'], $newCode)) {
                $message = 'Novo código enviado para o teu email!';
            } else {
                $errors[] = 'Erro ao enviar email. Tenta novamente.';
            }
        } catch (Exception $e) {
            error_log("Erro ao reenviar email: " . $e->getMessage());
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
      --light-bg: #f8f9fa;
      --success-green: #27ae60;
      --error-red: #e74c3c;
      --warning-orange: #f39c12;
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
      padding: 40px 0;
      position: relative;
      overflow-x: hidden;
      overflow-y: auto;
    }

    /* Background animado */
    .bg-animation {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
    }

    .floating-shape {
      position: absolute;
      border-radius: 50%;
      background: rgba(52, 152, 219, 0.1);
      animation: float 20s infinite ease-in-out;
    }

    .shape1 {
      width: 300px;
      height: 300px;
      top: -100px;
      right: -100px;
      animation-delay: 0s;
    }

    .shape2 {
      width: 200px;
      height: 200px;
      bottom: -50px;
      left: -50px;
      animation-delay: 5s;
    }

    .shape3 {
      width: 150px;
      height: 150px;
      top: 40%;
      left: 10%;
      animation-delay: 2s;
    }

    .shape4 {
      width: 100px;
      height: 100px;
      bottom: 30%;
      right: 15%;
      animation-delay: 7s;
    }

    @keyframes float {
      0%, 100% {
        transform: translateY(0) rotate(0deg);
        opacity: 0.3;
      }
      50% {
        transform: translateY(-50px) rotate(180deg);
        opacity: 0.6;
      }
    }

    /* Partículas */
    .particle {
      position: absolute;
      width: 4px;
      height: 4px;
      background: rgba(52, 152, 219, 0.4);
      border-radius: 50%;
      animation: rise 15s infinite ease-in;
    }

    @keyframes rise {
      0% {
        transform: translateY(0) translateX(0);
        opacity: 0;
      }
      10% {
        opacity: 1;
      }
      90% {
        opacity: 1;
      }
      100% {
        transform: translateY(-100vh) translateX(50px);
        opacity: 0;
      }
    }

    .verify-container {
      min-width: 320px;
      max-width: 480px;
      width: 100%;
      margin: 0 auto;
      padding: 20px;
      position: relative;
      z-index: 1;
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

    .verify-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .verify-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      padding: 40px 40px 30px;
      text-align: center;
      border-bottom: 1px solid #f0f0f0;
      position: relative;
      overflow: hidden;
    }

    .verify-header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(52, 152, 219, 0.05) 0%, transparent 70%);
      animation: pulse 4s infinite ease-in-out;
    }

    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
        opacity: 0.5;
      }
      50% {
        transform: scale(1.1);
        opacity: 0.8;
      }
    }

    .logo-container {
      margin-bottom: 20px;
      position: relative;
      z-index: 1;
    }

    .logo-container img {
      width: 120px;
      height: auto;
      filter: drop-shadow(0 4px 8px rgba(52, 152, 219, 0.2));
    }

    .verify-header h1 {
      font-size: 28px;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 8px;
      position: relative;
      z-index: 1;
    }

    .verify-header p {
      color: #7f8c8d;
      font-size: 15px;
      margin: 0;
      position: relative;
      z-index: 1;
    }

    .verify-header.success {
      background: linear-gradient(135deg, rgba(39, 174, 96, 0.1) 0%, rgba(46, 204, 113, 0.05) 100%);
    }

    .verify-header.success h1 {
      color: var(--success-green);
    }

    .verify-header.warning {
      background: linear-gradient(135deg, rgba(243, 156, 18, 0.1) 0%, rgba(230, 126, 34, 0.05) 100%);
    }

    .verify-header.warning h1 {
      color: var(--warning-orange);
    }

    .verify-header.error {
      background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.05) 100%);
    }

    .verify-header.error h1 {
      color: var(--error-red);
    }

    .status-icon {
      font-size: 60px;
      margin-bottom: 15px;
      animation: bounceIn 0.8s;
      position: relative;
      z-index: 1;
    }

    @keyframes bounceIn {
      0% { transform: scale(0); }
      50% { transform: scale(1.2); }
      100% { transform: scale(1); }
    }

    .email-badge {
      display: inline-block;
      background: rgba(52, 152, 219, 0.1);
      color: var(--primary-blue);
      padding: 8px 20px;
      border-radius: 20px;
      font-weight: 600;
      margin-top: 15px;
      border: 2px solid var(--primary-blue);
      font-size: 14px;
    }

    .verify-body {
      padding: 40px;
      position: relative;
    }

    .form-group {
      margin-bottom: 20px;
      animation: fadeInUp 0.6s ease-out backwards;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .form-label {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 8px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .code-input {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 16px;
      font-size: 28px;
      text-align: center;
      letter-spacing: 8px;
      font-weight: 700;
      font-family: 'Courier New', Courier, monospace;
      transition: all 0.3s;
      background: white;
      width: 100%;
    }

    .code-input:focus {
      border-color: var(--primary-blue);
      box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
      outline: none;
    }

    .code-input::placeholder {
      color: #dee2e6;
      letter-spacing: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
      border: none;
      border-radius: 12px;
      padding: 14px;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s;
      width: 100%;
      position: relative;
      overflow: hidden;
      color: white;
    }

    .btn-primary::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.5s;
    }

    .btn-primary:hover::before {
      left: 100%;
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
    }

    .btn-primary:active {
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 14px;
      font-weight: 600;
      font-size: 16px;
      color: #7f8c8d;
      text-decoration: none;
      display: block;
      text-align: center;
      transition: all 0.3s;
      width: 100%;
      margin-top: 12px;
    }

    .btn-secondary:hover {
      background: #e9ecef;
      border-color: #dee2e6;
      color: #495057;
      transform: translateY(-2px);
    }

    .alert {
      border-radius: 12px;
      border: none;
      padding: 14px 18px;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .alert-danger {
      background: #fdeaea;
      color: #c0392b;
    }

    .alert-success {
      background: #d4edda;
      color: #1e7e34;
    }

    .alert-warning {
      background: #fff3cd;
      color: #856404;
    }

    .alert i {
      font-size: 18px;
      margin-top: 2px;
    }

    .info-box {
      background: #f8f9fa;
      padding: 18px 20px;
      border-radius: 12px;
      margin-top: 25px;
    }

    .info-box h4 {
      color: #2c3e50;
      font-size: 15px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
    }

    .info-box p {
      color: #7f8c8d;
      margin: 0;
      font-size: 14px;
      line-height: 1.5;
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
      font-size: 14px;
    }

    .feature-list li:last-child {
      border-bottom: none;
    }

    .feature-list i {
      color: var(--success-green);
      font-size: 20px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.3s;
    }

    .back-link:hover {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      transform: translateX(-5px);
    }

    .login-link {
      text-align: center;
      padding: 20px 40px 30px;
      background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
      border-top: 1px solid #e9ecef;
    }

    .login-link p {
      margin: 0;
      color: #7f8c8d;
    }

    .login-link a {
      color: var(--primary-blue);
      font-weight: 600;
      text-decoration: none;
      position: relative;
      transition: color 0.3s;
    }

    .login-link a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--primary-blue);
      transition: width 0.3s;
    }

    .login-link a:hover {
      color: var(--dark-blue);
    }

    .login-link a:hover::after {
      width: 100%;
    }

    /* Responsivo */
    @media (max-width: 576px) {
      body {
        padding: 20px 0;
      }

      .verify-container {
        padding: 15px;
      }

      .verify-header, .verify-body, .login-link {
        padding-left: 25px;
        padding-right: 25px;
      }

      .floating-shape {
        opacity: 0.5;
      }

      .code-input {
        font-size: 22px;
        letter-spacing: 6px;
      }
    }

    @media (max-height: 800px) {
      body {
        padding: 30px 0;
      }

      .logo-container img {
        width: 100px;
      }

      .verify-header {
        padding: 30px 40px 20px;
      }

      .verify-body {
        padding: 30px;
      }
    }
  </style>
</head>
<body>

<!-- Background animado -->
<div class="bg-animation">
  <div class="floating-shape shape1"></div>
  <div class="floating-shape shape2"></div>
  <div class="floating-shape shape3"></div>
  <div class="floating-shape shape4"></div>
</div>

<div class="verify-container">
  <div class="verify-card">

    <?php if ($status === 'success'): ?>
      <!-- Estado: Sucesso -->
      <div class="verify-header success">
        <div class="logo-container">
          <img src="../assets/logo.png" alt="Freecard">
        </div>
        <div class="status-icon" style="color: var(--success-green);">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <h1>Conta Verificada!</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <?php if ($username): ?>
          <div class="email-badge" style="border-color: var(--success-green); color: var(--success-green); background: rgba(39, 174, 96, 0.1);">
            <i class="bi bi-person-check"></i> <?= htmlspecialchars($username) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="verify-body">
        <div class="info-box" style="background: rgba(39, 174, 96, 0.1);">
          <h4>
            <i class="bi bi-gift" style="color: var(--success-green);"></i>
            Bem-vindo ao FreeCard!
          </h4>
          <p>A tua conta está agora ativa e pronta para usar. Já podes começar a gerir as tuas finanças!</p>
        </div>

        <h4 style="color: #2c3e50; margin: 25px 0 15px; font-size: 15px; font-weight: 600;">O que podes fazer agora:</h4>
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
        </ul>

        <a href="../dashboard.php" class="btn btn-primary">
          <i class="bi bi-speedometer2 me-2"></i>
          Ir para o Dashboard
        </a>
      </div>

    <?php elseif ($status === 'already_verified'): ?>
      <!-- Estado: Já verificado -->
      <div class="verify-header warning">
        <div class="logo-container">
          <img src="../assets/logo.png" alt="Freecard">
        </div>
        <div class="status-icon" style="color: var(--warning-orange);">
          <i class="bi bi-info-circle-fill"></i>
        </div>
        <h1>Já Verificado</h1>
        <p><?= htmlspecialchars($message) ?></p>
      </div>

      <div class="verify-body">
        <div class="info-box">
          <h4>
            <i class="bi bi-shield-check" style="color: var(--primary-blue);"></i>
            A tua conta está ativa
          </h4>
          <p>Podes fazer login normalmente e começar a usar o FreeCard.</p>
        </div>

        <a href="login.php" class="btn btn-primary">
          <i class="bi bi-box-arrow-in-right me-2"></i>
          Fazer Login
        </a>
        <a href="../index.php" class="btn-secondary">
          <i class="bi bi-house me-2"></i>
          Página Inicial
        </a>
      </div>

    <?php elseif ($status === 'expired'): ?>
      <!-- Estado: Código expirado -->
      <div class="verify-header error">
        <div class="logo-container">
          <img src="../assets/logo.png" alt="Freecard">
        </div>
        <div class="status-icon" style="color: var(--error-red);">
          <i class="bi bi-clock-history"></i>
        </div>
        <h1>Código Expirado</h1>
        <p><?= htmlspecialchars($message) ?></p>
      </div>

      <div class="verify-body">
        <div class="info-box" style="background: rgba(231, 76, 60, 0.1);">
          <h4>
            <i class="bi bi-exclamation-triangle" style="color: var(--error-red);"></i>
            O que fazer agora?
          </h4>
          <p>Por motivos de segurança, os códigos de verificação expiram após 24 horas. Podes solicitar um novo código abaixo.</p>
        </div>

        <?php if ($email): ?>
        <form method="post">
          <input type="hidden" name="resend" value="1">
          <button type="submit" class="btn btn-primary">
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
      <!-- Estado: Pendente (formulário de verificação) -->
      <div class="verify-header">
        <div class="logo-container">
          <img src="../assets/logo.png" alt="Freecard">
        </div>
        <div class="status-icon" style="color: var(--primary-blue);">
          <i class="bi bi-envelope-paper"></i>
        </div>
        <h1>Verificar Conta</h1>
        <p>Introduz o código de 6 dígitos que enviamos para o teu email</p>
        <?php if ($email): ?>
          <div class="email-badge">
            <i class="bi bi-envelope"></i> <?= htmlspecialchars($email) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="verify-body">
        <?php if ($emailWarning): ?>
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
              <strong>Atenção:</strong> Houve um problema ao enviar o email. Usa o botão "Reenviar Código" abaixo para tentar novamente.
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>
              <?php foreach($errors as $e): ?>
                <?= htmlspecialchars($e) ?><br>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($message && empty($errors)): ?>
          <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= htmlspecialchars($message) ?></div>
          </div>
        <?php endif; ?>

        <form method="post">
          <div class="form-group">
            <label class="form-label">
              <i class="bi bi-key"></i> Código de Verificação
            </label>
            <input
              type="text"
              name="code"
              class="code-input"
              placeholder="000000"
              maxlength="6"
              pattern="[0-9]{6}"
              inputmode="numeric"
              autocomplete="one-time-code"
              autofocus
              required
            >
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-2"></i>
            Verificar Conta
          </button>
        </form>

        <div class="info-box">
          <h4>
            <i class="bi bi-info-circle" style="color: var(--primary-blue);"></i>
            Não recebeste o código?
          </h4>
          <p>Verifica a pasta de spam ou solicita um novo código clicando no botão abaixo.</p>
        </div>

        <?php if ($email): ?>
        <form method="post">
          <input type="hidden" name="resend" value="1">
          <button type="submit" class="btn-secondary" style="margin-top: 15px;">
            <i class="bi bi-arrow-clockwise me-2"></i>
            Reenviar Código
          </button>
        </form>
        <?php endif; ?>
      </div>

      <div class="login-link">
        <p>Já tens conta verificada? <a href="login.php">Entra aqui</a></p>
      </div>

    <?php else: ?>
      <!-- Estado: Inválido -->
      <div class="verify-header error">
        <div class="logo-container">
          <img src="../assets/logo.png" alt="Freecard">
        </div>
        <div class="status-icon" style="color: var(--error-red);">
          <i class="bi bi-x-circle-fill"></i>
        </div>
        <h1>Código Inválido</h1>
        <p>O código introduzido não é válido.</p>
      </div>

      <div class="verify-body">
        <div class="info-box" style="background: rgba(231, 76, 60, 0.1);">
          <h4>
            <i class="bi bi-exclamation-triangle" style="color: var(--error-red);"></i>
            O que aconteceu?
          </h4>
          <p>O código que introduziste não foi encontrado. Verifica se copiaste corretamente ou cria uma nova conta.</p>
        </div>

        <a href="register.php" class="btn btn-primary">
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

  <div class="text-center mt-4">
    <a href="../index.php" class="back-link">
      <i class="bi bi-arrow-left"></i>
      <span>Voltar à página inicial</span>
    </a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Criar partículas animadas
function createParticles() {
  const container = document.querySelector('.bg-animation');
  const particleCount = 20;

  for (let i = 0; i < particleCount; i++) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    particle.style.left = Math.random() * 100 + '%';
    particle.style.animationDelay = Math.random() * 15 + 's';
    particle.style.animationDuration = (15 + Math.random() * 10) + 's';
    container.appendChild(particle);
  }
}

createParticles();

// Auto-formatar código
const codeInput = document.querySelector('.code-input');
if (codeInput) {
  codeInput.addEventListener('input', function(e) {
    // Remover caracteres não numéricos
    this.value = this.value.replace(/[^0-9]/g, '');
  });
}
</script>
<?php if ($status === 'success'): ?>
<script>
// Auto-redirect após 3 segundos
setTimeout(function() {
  window.location.href = '../dashboard.php';
}, 3000);
</script>
<?php endif; ?>
</body>
</html>
